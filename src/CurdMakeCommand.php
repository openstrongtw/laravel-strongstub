<?php

namespace OpenStrong\StrongAdmin;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class CurdMakeCommand extends GeneratorCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'strongstub:curd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '建立一個 CURD（增刪改查）controller class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Controller';
    private $columns;
    private $url_path_index;
    private $url_path_show;
    private $url_path_create;
    private $url_path_update;
    private $url_path_destroy;

    public function handle()
    {
        if (parent::handle() === false && !$this->option('force'))
        {
            return;
        }
        $input_name = $this->getNameInput();
        $name = $this->qualifyClass($input_name);
        if ($this->option('path'))
        {
            $url_path = trim($this->option('path'), '/');
        } else
        {
            $url_path = CommonClass::getRoutePathName($name);
        }
        $controller_name = str_replace('/', '\\', $input_name);

        $this->url_path_index = "{$url_path}/index";
        $this->url_path_show = "{$url_path}/show";
        $this->url_path_create = "{$url_path}/create";
        $this->url_path_update = "{$url_path}/update";
        $this->url_path_destroy = "{$url_path}/destroy";

        $index = "Route::any('{$this->url_path_index}', '{$controller_name}@index');";
        $show = "Route::any('{$this->url_path_show}', '{$controller_name}@show');";
        $create = "Route::any('{$this->url_path_create}', '{$controller_name}@create');";
        $update = "Route::any('{$this->url_path_update}', '{$controller_name}@update');";
        $destroy = "Route::any('{$this->url_path_destroy}', '{$controller_name}@destroy');";

//        $route_admin = base_path('routes/admin.php');
//        if (file_exists($route_admin))
//        {
//            $route_str = "\n{$index}\n{$show}\n{$create}\n{$update}\n{$destroy}\n";
//            file_put_contents($route_admin, $route_str, FILE_APPEND);
//        }

        $this->info('');
        $this->info($index);
        $this->info($show);
        $this->info($create);
        $this->info($update);
        $this->info($destroy);
        $this->info("\n");

        //postman 參數
        $str = '';
        $str_json = [];
        foreach ($this->columns as $key => $column)
        {
            $str .= "{$column->COLUMN_NAME}:\n";
            $str_json[$column->COLUMN_NAME] = "";
        }

        $this->info($str);
        $this->info(json_encode($str_json));

        if ($this->option('view'))
        {
            $modelClass = $this->parseModel($this->option('model'));
            $this->info("\n");
            $params = ['name' => $url_path, '--table' => (new $modelClass())->getTable()];
            if ($this->option('force'))
            {
                $params = array_merge($params, ['--force' => '1']);
            }
            $this->call('strongstub:view', $params);
        }
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('model'))
        {
            if ($this->option('view'))
            {
                return __DIR__ . '/stubs/controller.model.blade.stub';
            } else
            {
                return __DIR__ . '/stubs/controller.model.stub';
            }
        }

        return __DIR__ . '/stubs/controller.plain.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Http\Controllers';
    }

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
        $controllerNamespace = $this->getNamespace($name);
        $class = str_replace($controllerNamespace . '\\', '', $name);

        $replace = [];

        if ($this->option('model'))
        {
            $replace = $this->buildModelReplacements($replace);
            $replace = $this->buildCurdReplacements($replace);
            $replace['DummyViewBladeFolder'] = lcfirst(str_replace('Controller', '', $class));
        }

        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
                array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Build the model replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildModelReplacements(array $replace)
    {
        $modelClass = $this->parseModel($this->option('model'));

        if (!class_exists($modelClass))
        {
            if ($this->confirm("A {$modelClass} model does not exist. Do you want to generate it?", true))
            {
                $params = ['name' => $modelClass, '--cut' => true];
                if ($this->option('force'))
                {
                    $params = array_merge($params, ['--force' => '1']);
                }
                $this->call('strongstub:model', $params);
                $path = CommonClass::getModelPath($modelClass);
                require_once base_path() . '/app/' . $path . '.php';
            } else
            {
                exit(0);
            }
        }

        $extends_controller = trim($this->option('extends'));
        if ($this->option('view'))
        {
            $extends_controller = 'OpenStrong\StrongAdmin\Http\Controllers\BaseController as Controller';
        } else
        {
            if (!$extends_controller)
            {
                $extends_controller = 'App\Http\Controllers\Controller';
            } else
            {
                $extends_controller .= ' as Controller';
            }
        }

        return array_merge($replace, [
            'DummyFullModelClass' => $modelClass,
            'DummyModelClass' => class_basename($modelClass),
            'DummyModelVariable' => lcfirst(class_basename($modelClass)),
            'DummyExtendsController' => $extends_controller,
        ]);
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     * @return string
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model))
        {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        $model = trim(str_replace('/', '\\', $model), '\\');

        if (!Str::startsWith($model, $rootNamespace = $this->laravel->getNamespace()))
        {
            $model = $rootNamespace . $model;
        }

        return $model;
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Generate a resource controller for the given model.'],
            ['extends', 'e', InputOption::VALUE_OPTIONAL, '要繼承的 controller'],
            ['path', 'p', InputOption::VALUE_OPTIONAL, '路由 URL 路徑字首。'],
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists.'],
            ['view', null, InputOption::VALUE_NONE, 'Create controller for laravel-strongadmin view.'],
        ];
    }

    protected function buildCurdReplacements(array $replace)
    {
        $modelClass = $this->parseModel($this->option('model'));

        $obj = new $modelClass();
        $table = $obj->getTable();
        $table_db = $obj->getConnectionName() ? "{$obj->getConnectionName()}.{$table}" : $table;
        $primaryKeyName = $obj->getKeyName();
        $this->columns = $columns = CommonClass::getColumns($table, $obj->getConnectionName());

        // Search Condition
        $createDefaultValue = $uniqueRuleUpdate = $uniqueRule = $searchCondition = '';
        foreach ($columns as $key => $column)
        {
            $DATA_TYPE = CommonClass::getDataType($column->DATA_TYPE);
            if ($column->COLUMN_NAME == 'created_at')
            {
                $searchCondition .= '
        if ($request->created_at_begin && $request->created_at_end) {
            $model->whereBetween(\'' . $column->COLUMN_NAME . '\', [$request->created_at_begin, Carbon::parse($request->created_at_end)->endOfDay()]);
        }';
            } elseif ($column->COLUMN_NAME == 'updated_at')
            {
                
            } elseif ($column->COLUMN_NAME == 'deleted_at')
            {
                
            } else
            {
                if ($DATA_TYPE == 'string')
                {
                    $searchCondition .= '
        if ($request->' . $column->COLUMN_NAME . ') {
            $model->where(\'' . $column->COLUMN_NAME . '\', \'like\', "%{$request->' . $column->COLUMN_NAME . '}%");
        }';
                } else
                {
                    $searchCondition .= '
        if ($request->' . $column->COLUMN_NAME . ') {
            $model->where(\'' . $column->COLUMN_NAME . '\', $request->' . $column->COLUMN_NAME . ');
        }';
                }
            }

            //$createDefaultValue
            if ($column->COLUMN_DEFAULT !== null && $column->COLUMN_DEFAULT !== '')
            {
                $createDefaultValue .= '
        //' . $column->COLUMN_COMMENT . '
        if($request->' . $column->COLUMN_NAME . ' === \'\'){
            $request->merge([\'' . $column->COLUMN_NAME . '\'=>\'' . $column->COLUMN_DEFAULT . '\']);
        }';
            }

            //單欄位唯一索引
            if ($column->COLUMN_KEY === 'UNI')
            {
                $uniqueRule .= "
            '{$column->COLUMN_NAME}' => ['unique:{$table_db}'],";

                $uniqueRuleUpdate .= "
            '" . $column->COLUMN_NAME . "' => [Rule::unique('" . $table_db . "')->ignore(" . '$request->id' . ")],";
            }
            //多欄位唯一索引
            if ($column->COLUMN_KEY === 'MUL')
            {
                $constraint_name = CommonClass::getColumnsIndex($table, $column->COLUMN_NAME, $obj->getConnectionName());
                $uniques = CommonClass::getIndexColumns($table, $constraint_name, $obj->getConnectionName());
                $fields = collect($uniques)->pluck('COLUMN_NAME');

                foreach ($fields as $field)
                {

                    $uniqueRule .= "
            '" . $field . "' => [Rule::unique('" . $table_db . "')";

                    $fields_except = $fields->reject(function ($value, $key) use($field) {
                        return $value === $field;
                    });
                    $where_str = '';
                    foreach ($fields_except as $fe)
                    {
                        $where_str .= '->where("' . $fe . '", $request->' . $fe . '?:" ")';
                    }

                    $uniqueRule .= $where_str . '],';

                    //===========================

                    $uniqueRuleUpdate .= "
            '" . $field . "' => [Rule::unique('" . $table_db . "')";

                    $fields_except = $fields->reject(function ($value, $key) use($field) {
                        return $value === $field;
                    });
                    $where_str = '';
                    foreach ($fields_except as $fe)
                    {
                        $where_str .= '->where("' . $fe . '", $request->' . $fe . '?:" ")';
                    }

                    $uniqueRuleUpdate .= $where_str . "->ignore(" . '$request->id' . ")],";
                }
            }
        }

        return array_merge($replace, [
            'DummyTableName' => "$table_db",
            'DummySearchCondition' => ltrim($searchCondition),
            'DummyPrimaryKeyName' => $primaryKeyName,
            'DummyUniqueRule' => $uniqueRule,
            'DummyUniqueUpdateRule' => $uniqueRuleUpdate,
            'DummyCreateDefaultValue' => $createDefaultValue,
        ]);
    }

    protected function getRowData()
    {
        $modelClass = $this->parseModel($this->option('model'));
        $row = $modelClass::first();
        return $row->toArray();
    }

    protected function getRowsData()
    {
        $modelClass = $this->parseModel($this->option('model'));
        $row = $modelClass::limit(2)->get();
        return $row->toArray();
    }

}
