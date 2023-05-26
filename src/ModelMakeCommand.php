<?php

namespace OpenStrong\StrongAdmin;

use Illuminate\Support\Str;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class ModelMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'strongstub:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '建立一個包含驗證規則的 Eloquent Model 模型';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Model';

    /**
     * Build the class with the given name.
     *
     * Remove the base controller import if we are already in base namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        if ($this->option('table')) {
            $table = $this->option('table');
        }else{
            $table = CommonClass::getTable($name);
        }
        
        do{
            $exists = CommonClass::existsTable($table, $this->option('connection'));
            if (!$exists) {
                $tableAsk = $this->ask("The table `{$table}` does not exist. Enter table name to regenerateit.(Do not fill in the table prefix)", $table);                
                if($tableAsk === $table){
                    //break;
                }else{
                    $table = $tableAsk;
                }
            }else{
                break;
            }
        }while(isset($tableAsk) && $tableAsk !== false);
        
        $replace['DummyTableName'] = $table;
        $replace['DummyTableComments'] = CommonClass::getTableInfo($table, $this->option('connection'));
        $replace = $this->buildRulesReplacements($replace, $table);
        $replace = $this->buildAttributesReplacements($replace, $table);
        
        return str_replace(
                array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }
    
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return;
        }

        if ($this->option('all')) {
            $this->input->setOption('factory', true);
            $this->input->setOption('migration', true);
            $this->input->setOption('controller', true);
            $this->input->setOption('resource', true);
        }

        if ($this->option('factory')) {
            $this->createFactory();
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }
        
        if ($this->option('controller') || $this->option('resource')) {
            $this->createController();
        }
    }

    /**
     * Create a model factory for the model.
     *
     * @return void
     */
    protected function createFactory()
    {
        $this->call('make:factory', [
            'name' => $this->argument('name').'Factory',
            '--model' => $this->argument('name'),
        ]);
    }

    /**
     * Create a migration file for the model.
     *
     * @return void
     */
    protected function createMigration()
    {
        $table = Str::plural(Str::snake(class_basename($this->argument('name'))));

        $this->call('make:migration', [
            'name' => "create_{$table}_table",
            '--create' => $table,
        ]);
    }

    /**
     * Create a controller for the model.
     *
     * @return void
     */
    protected function createController()
    {
        $controller = Str::studly(class_basename($this->argument('name')));

        $modelName = $this->qualifyClass($this->getNameInput());

        $this->call('make:controller', [
            'name' => "{$controller}Controller",
            '--model' => $this->option('resource') ? $modelName : null,
        ]);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('pivot')) {
            return __DIR__.'/stubs/pivot.model.stub';
        }

        return __DIR__.'/stubs/model.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate a migration, factory, and resource controller for the model'],

            ['controller', 'co', InputOption::VALUE_NONE, 'Create a new controller for the model'],

            ['factory', 'f', InputOption::VALUE_NONE, 'Create a new factory for the model'],

            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists.'],

            ['migration', 'm', InputOption::VALUE_NONE, 'Create a new migration file for the model.'],

            ['pivot', 'p', InputOption::VALUE_NONE, 'Indicates if the generated model should be a custom intermediate table model.'],

            ['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller.'],
            
            ['table', 't', InputOption::VALUE_OPTIONAL, 'Generate the model with table name.'],
            
            ['connection', 'c', InputOption::VALUE_OPTIONAL, '數據庫連線名稱.'],
            
            ['cut', null, InputOption::VALUE_NONE, '縮減`欄位註釋`(自動刪除空格/冒號後面的字元).'],
        ];
    }
    
    protected function buildRulesReplacements(array $replace, $table)
    {
        $columns = CommonClass::getColumns($table, $this->option('connection'));
        $primaryKeyName = CommonClass::getKeyName($table, $this->option('connection'));
        
        // rules -------------------------------------
        $str = '';
        foreach ($columns as $column) {
            if($primaryKeyName === $column->COLUMN_NAME){
                continue;
            }
            $str .= "
            '{$column->COLUMN_NAME}' => ";
            $str .= "[";
            if ($column->IS_NULLABLE === 'NO') {
                $str .= "'required', ";
            }
            
            $str .= "'{$this->getDataType($column->DATA_TYPE)}'";
            
            if ($column->CHARACTER_MAXIMUM_LENGTH) {
                $str .= ", 'max:{$column->CHARACTER_MAXIMUM_LENGTH}'";
            }
            
            $str .= "],";
        }
        // ------------------------------
        if ($this->option('connection'))
        {
            $connection = $this->option('connection');
            $str_connection = '
    protected $connection = \''.$connection.'\';';
        } else
        {
            $str_connection = '';
        }
        return array_merge($replace, [
            'DummyRules' => $str,
            'DummyConnection' => $str_connection,
        ]);
    }
    
    protected function buildAttributesReplacements(array $replace, $table)
    {
        $columns = CommonClass::getColumns($table, $this->option('connection'));
        
        // attributes ----------------------------------
        $str = "";
        foreach($columns as $column){
            $COLUMN_COMMENT = $column->COLUMN_COMMENT ?: strtoupper($column->COLUMN_NAME);
            if ($this->option('cut')) {
                $COLUMN_COMMENT = CommonClass::strBefore($COLUMN_COMMENT);
            }
            $str .= "
            '{$column->COLUMN_NAME}' => '{$COLUMN_COMMENT}',";
        }
        // ------------------------------------------
        
        return array_merge($replace, [
            'DummyAttributes' => $str,
        ]);
    }

    protected function getDataType(string $type)
    {
        return CommonClass::getDataType($type);
    }
}
