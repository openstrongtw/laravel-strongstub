<?php

namespace OpenStrong\StrongAdmin;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Facades\DB;

class WikiMakeCommand extends GeneratorCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'strongstub:wiki';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '建立 api 介面 markdown 文件';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'wiki';
    
    private $columns;
    private $url_path_index;
    private $url_path_show;
    private $url_path_create;
    private $url_path_update;
    private $url_path_destroy;
    
    private $expect_files_create = ['id', 'created_at', 'updated_at', 'deleted_at'];
    private $expect_files_update = ['created_at', 'updated_at', 'deleted_at'];

    public function handle()
    {
        if (! $this->option('force')) {
            $this->warn('請攜帶參數 --force');
            return;
        }
        $input_name = $this->getNameInput();
        $name = $this->qualifyClass($input_name);
        $url_path = CommonClass::getRoutePathName($name);
        $controller_name = str_replace('/', '\\', $input_name);
        
        $this->url_path_index = "{$url_path}/index";
        $this->url_path_show = "{$url_path}/show";
        $this->url_path_create = "{$url_path}/create";
        $this->url_path_update = "{$url_path}/update";
        $this->url_path_destroy = "{$url_path}/destroy";
        
        $this->createWiki();
        
        $this->info('Api Wiki created successfully.');
    }
    
    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('parent')) {
            return __DIR__ . '/stubs/controller.nested.stub';
        } elseif ($this->option('model')) {
            return __DIR__ . '/stubs/controller.model.stub';
        } elseif ($this->option('resource')) {
            return __DIR__ . '/stubs/controller.stub';
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

        $replace = [];

        if ($this->option('parent')) {
            $replace = $this->buildParentReplacements();
        }

        if ($this->option('model')) {
            $replace = $this->buildModelReplacements($replace);
            $replace = $this->buildCurdReplacements($replace);
        }

        $replace["use {$controllerNamespace}\Controller;\n"] = '';

        return str_replace(
                array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }
    
    protected function createWiki()
    {
        $input_name = $this->getNameInput();
        $name = $this->qualifyClass($input_name);
        $url_path = CommonClass::getRoutePathName($name);
        
        $modelClass = $this->parseModel($this->option('model'));
        if (!class_exists($modelClass)) {
            $this->error(" Model [{$modelClass}] does not exist. ");
            exit(0);
        }
        $obj = new $modelClass();
        $table = $obj->getTable();
        $primaryKeyName = $obj->getKeyName();
        $this->columns = $columns = CommonClass::getColumns($table);
        
        $path = base_path() . "/.wiki/{$url_path}";
        
        $this->makeDirectory($path.'/a.wiki');
        
        $md_create = $this->buildCreateClass('create');
        $md_update = $this->buildUpdateClass('update');
        $md_destroy = $this->buildDestroyClass('destroy');
        $md_index = $this->buildIndexClass('index');
        $md_show = $this->buildShowClass('show');
        
        $this->files->put($path . "/create.md", $md_create);
        $this->info($path . "/create.md");
        
        $this->files->put($path . '/update.md', $md_update);
        $this->info($path . "/update.md");
        
        $this->files->put($path . '/destroy.md', $md_destroy);
        $this->info($path . "/destroy.md");
        
        $this->files->put($path . '/index.md', $md_index);
        $this->info($path . "/index.md");
        
        $this->files->put($path . '/show.md', $md_show);
        $this->info($path . "/show.md");
        
        if(!$this->option('doc')){
            return true;
        }
        if(!$this->option('book')){
            $book_id = 2;
        }else{
            $book_id = (int) $this->option('book');
        }
        $document_name = $this->option('doc');
        $base = [
            'book_id' => $book_id,
            'parent_id' => 0,
            'release' => '',
            'content' => '',
            'create_time' => date('Y-m-d H:i:s'),
            'member_id' => 1,
            'modify_time' => date('Y-m-d H:i:s'),
            'version' => time(),
        ];
        $data = array_merge($base, [
            'identify' => $this->getIdentify(),
            'document_name' => $document_name,
        ]);
        $parent_id  = DB::connection('mysqlMindoc')->table('documents')->insertGetId($data);
        $base = array_merge($base, [
            'parent_id' => $parent_id,
        ]);
        $datas[] = array_merge($base, [
            'identify' => $this->getIdentify(),
            'document_name' => '新增',
            'markdown' => $md_create,
        ]);
        $datas[] = array_merge($base, [
            'identify' => $this->getIdentify(),
            'document_name' => '更新',
            'markdown' => $md_update,
        ]);
        $datas[] = array_merge($base, [
            'identify' => $this->getIdentify(),
            'document_name' => '刪除',
            'markdown' => $md_destroy,
        ]);
        $datas[] = array_merge($base, [
            'identify' => $this->getIdentify(),
            'document_name' => '列表',
            'markdown' => $md_index,
        ]);
        $datas[] = array_merge($base, [
            'identify' => $this->getIdentify(),
            'document_name' => '詳情',
            'markdown' => $md_show,
        ]);
        DB::connection('mysqlMindoc')->table('documents')->insert($datas);
    }
    
    protected function buildCreateClass($name)
    {
        $replace['DummyCreateWikiDate'] = date('Y-m-d');
        $replace['DummyHostPath'] = $this->url_path_create;
        
        $str = '';
        foreach($this->columns as $column){
            if(collect($this->expect_files_create)->search($column->COLUMN_NAME) !== false){
                continue;
            }
            $COLUMN_COMMENT = $column->COLUMN_COMMENT ?: strtoupper($column->COLUMN_NAME);
            $IS_NULLABLE = $this->getIS_NULLABLE($column->COLUMN_NAME) ? '是' : '否';
            $DATA_TYPE = CommonClass::getDataType($column->DATA_TYPE);
            $str .= "\n|{$column->COLUMN_NAME} |{$IS_NULLABLE}  |{$DATA_TYPE} |{$COLUMN_COMMENT}   |";
        }
        $comments = collect($this->columns)->keyBy('COLUMN_NAME')->all();
        $row = $this->getRowData();
        $str2 = '';
        $n = 0;
        foreach ($row as $key=>$value){
            $n++;
            if(is_array($value)){
                $value = json_encode($value);
            }
            if(is_numeric($value)){
                $value = floatval($value);
            }else{
                $value = "\"{$value}\"";
            }
            if($n < count($row)){
                $value .= ',';
            }
            $str2 .= "
        \"{$key}\": {$value} //{$comments[$key]->COLUMN_COMMENT}";
        }
        $replace['DummyFormData'] = $str;
        $replace['DummyRowDetail'] = $str2;
        
        $view = $this->files->get($this->getWikiView($name));
        
        return str_replace(
                array_keys($replace), array_values($replace), $view
        );
    }
    protected function buildUpdateClass($name)
    {
        $replace['DummyCreateWikiDate'] = date('Y-m-d');
        $replace['DummyHostPath'] = $this->url_path_update;
        
        $str = '';
        foreach($this->columns as $column){
            if(collect($this->expect_files_update)->search($column->COLUMN_NAME) !== false){
                continue;
            }
            $COLUMN_COMMENT = $column->COLUMN_COMMENT ?: strtoupper($column->COLUMN_NAME);
            $IS_NULLABLE = $this->getIS_NULLABLE($column->COLUMN_NAME, $column->IS_NULLABLE) ? '是' : '否';
            $DATA_TYPE = CommonClass::getDataType($column->DATA_TYPE);
            $str .= "\n|{$column->COLUMN_NAME} |{$IS_NULLABLE}  |{$DATA_TYPE} |{$COLUMN_COMMENT}   |";
        }
        $comments = collect($this->columns)->keyBy('COLUMN_NAME')->all();
        $row = $this->getRowData();
        $str2 = '';
        $n = 0;
        foreach ($row as $key=>$value){
            $n++;
            if(is_array($value)){
                $value = json_encode($value);
            }
            if(is_numeric($value)){
                $value = floatval($value);
            }else{
                $value = "\"{$value}\"";
            }
            if($n < count($row)){
                $value .= ',';
            }
            $str2 .= "
        \"{$key}\": {$value} //{$comments[$key]->COLUMN_COMMENT}";
        }
        $replace['DummyFormData'] = $str;
        $replace['DummyRowDetail'] = $str2;
        
        $view = $this->files->get($this->getWikiView($name));
        
        return str_replace(
                array_keys($replace), array_values($replace), $view
        );
    }
    protected function buildShowClass($name)
    {
        $replace['DummyCreateWikiDate'] = date('Y-m-d');
        $replace['DummyHostPath'] = $this->url_path_show;
        
        $comments = collect($this->columns)->keyBy('COLUMN_NAME')->all();
        $replace['DummyFormData'] = $comments['id']->COLUMN_COMMENT;
        $row = $this->getRowData();
        $str2 = '';
        $n = 0;
        foreach ($row as $key=>$value){
            $n++;
            if(is_array($value)){
                $value = json_encode($value);
            }
            if(is_numeric($value)){
                $value = floatval($value);
            }else{
                $value = "\"{$value}\"";
            }
            if($n < count($row)){
                $value .= ',';
            }
            $str2 .= "
        \"{$key}\": {$value} //{$comments[$key]->COLUMN_COMMENT}";
        }
        $replace['DummyRowDetail'] = $str2;
        
        $view = $this->files->get($this->getWikiView($name));
        
        return str_replace(
                array_keys($replace), array_values($replace), $view
        );
    }
    protected function buildIndexClass($name)
    {
        $replace['DummyCreateWikiDate'] = date('Y-m-d');
        $replace['DummyHostPath'] = $this->url_path_index;
        
        $str = '';
        foreach($this->columns as $column){
            $COLUMN_COMMENT = $column->COLUMN_COMMENT ?: strtoupper($column->COLUMN_NAME);
            $IS_NULLABLE = '否';
            $DATA_TYPE = CommonClass::getDataType($column->DATA_TYPE);
            if($column->COLUMN_NAME == 'id'){
                continue;
            }
            if($column->COLUMN_NAME == 'updated_at'){
                continue;
            }
            if($column->COLUMN_NAME == 'created_at'){
                $str .= "\n|{$column->COLUMN_NAME}_begin |{$IS_NULLABLE}  |{$DATA_TYPE} |{$COLUMN_COMMENT} 開始日期   |";
                $str .= "\n|{$column->COLUMN_NAME}_end |{$IS_NULLABLE}  |{$DATA_TYPE} |{$COLUMN_COMMENT} 結束日期   |";
                continue;
            }
            $str .= "\n|{$column->COLUMN_NAME} |{$IS_NULLABLE}  |{$DATA_TYPE} |{$COLUMN_COMMENT}   |";
        }
        $replace['DummyFormData'] = $str;
        
        $comments = collect($this->columns)->keyBy('COLUMN_NAME')->all();
        $rows = $this->getRowsData();
        $str2 = '';
        $m = 0;
        foreach ($rows as $rk=>$row){
            $m++;
            $n = 0;
            $str2 .= '
            {';
            foreach($row as $key=>$value){
                $n++;
                if(is_array($value)){
                    $value = json_encode($value);
                }
                if(is_numeric($value)){
                    $value = floatval($value);
                }else{
                    $value = "\"{$value}\"";
                }
                if($n < count($row)){
                    $value .= ',';
                }
            $str2 .= "
                \"{$key}\": {$value}";
                if($rk ==0){
                    $str2 .= " //{$comments[$key]->COLUMN_COMMENT}";
                }
            }
            
            $str2 .= '
            }';
            if($m < count($rows)){
                $str2 .= ',';
            }
        }

        $replace['DummyRowDetail'] = $str2;
        
        $view = $this->files->get($this->getWikiView($name));
        
        return str_replace(
                array_keys($replace), array_values($replace), $view
        );
    }
    protected function buildDestroyClass($name)
    {
        $replace['DummyCreateWikiDate'] = date('Y-m-d');
        $replace['DummyHostPath'] = $this->url_path_destroy;
        
        $comments = collect($this->columns)->keyBy('COLUMN_NAME')->all();
        $replace['DummyFormData'] = $comments['id']->COLUMN_COMMENT;
        
        $view = $this->files->get($this->getWikiView($name));
        
        return str_replace(
                array_keys($replace), array_values($replace), $view
        );
    }

    /**
     * Build the replacements for a parent controller.
     *
     * @return array
     */
    protected function buildParentReplacements()
    {
        $parentModelClass = $this->parseModel($this->option('parent'));

        if (!class_exists($parentModelClass)) {
            if ($this->confirm("A {$parentModelClass} model does not exist. Do you want to generate it?", true)) {
                $this->call('make:model', ['name' => $parentModelClass]);
            }
        }

        return [
            'ParentDummyFullModelClass' => $parentModelClass,
            'ParentDummyModelClass' => class_basename($parentModelClass),
            'ParentDummyModelVariable' => lcfirst(class_basename($parentModelClass)),
        ];
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
        if (!class_exists($modelClass)) {
            $this->error('model does not exist.');
            exit(0);
        }
        return array_merge($replace, [
            'DummyFullModelClass' => $modelClass,
            'DummyModelClass' => class_basename($modelClass),
            'DummyModelVariable' => lcfirst(class_basename($modelClass)),
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
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        $model = trim(str_replace('/', '\\', $model), '\\');

        if (!Str::startsWith($model, $rootNamespace = $this->laravel->getNamespace())) {
//            $model = $rootNamespace . $model;
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
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Generate wiki for the given model.'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller class.'],
            ['parent', 'p', InputOption::VALUE_OPTIONAL, 'Generate a nested resource controller class.'],
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists.'],
            ['doc', 'd', InputOption::VALUE_OPTIONAL, 'Generate mindoc wiki.'],
            ['book', 'b', InputOption::VALUE_OPTIONAL, 'Select a book of mindoc wiki.'],
        ];
    }

    protected function buildCurdReplacements(array $replace)
    {
        $modelClass = $this->parseModel($this->option('model'));
        
        $obj = new $modelClass();
        $table = $obj->getTable();
        $primaryKeyName = $obj->getKeyName();
        $this->columns = $columns = CommonClass::getColumns($table);
        
        // Search Condition
        $uniqueRuleUpdate = $uniqueRule = $searchCondition = '';
        foreach ($columns as $key=>$column) {
            $searchCondition .= '
        if ($request->'.$column->COLUMN_NAME.') {
            $model->where(\''.$column->COLUMN_NAME.'\', $request->'.$column->COLUMN_NAME.');
        }';
            //單欄位唯一索引
            if($column->COLUMN_KEY === 'UNI'){
                $uniqueRule .= "
                '{$column->COLUMN_NAME}' => ['unique:{$table}'],";
            
                $uniqueRuleUpdate .= "
                '".$column->COLUMN_NAME."' => [Rule::unique('".$table."')->ignore(".'$request->id'.")],";
            }
            //多欄位唯一索引
            if($column->COLUMN_KEY === 'MUL'){
                $constraint_name = CommonClass::getColumnsIndex($table, $column->COLUMN_NAME);
                $uniques = CommonClass::getIndexColumns($table, $constraint_name);
                $fields = collect($uniques)->pluck('COLUMN_NAME');
                
                foreach ($fields as $field){
                    
                    $uniqueRule .= "
                '".$field."' => [Rule::unique('".$table."')";
                    
                    $fields_except = $fields->reject(function ($value, $key) use($field) {
                        return $value === $field;
                    });
                    $where_str = '';
                    foreach($fields_except as $fe){
                        $where_str .= '->where("'.$fe.'", $request->'.$fe.'?:" ")';
                    }
                    
                    $uniqueRule .= $where_str.'],';
                    
                    //===========================
                    
                    $uniqueRuleUpdate .= "
                '".$field."' => [Rule::unique('".$table."')";
                    
                    $fields_except = $fields->reject(function ($value, $key) use($field) {
                        return $value === $field;
                    });
                    $where_str = '';
                    foreach($fields_except as $fe){
                        $where_str .= '->where("'.$fe.'", $request->'.$fe.'?:" ")';
                    }
                    
                    $uniqueRuleUpdate .= $where_str . "->ignore(".'$request->id'.")],";
                }
            }
            
        }
        
        return array_merge($replace, [
            'DummyTableName' => $table,
            'DummySearchCondition' => ltrim($searchCondition),
            'DummyPrimaryKeyName' => $primaryKeyName,
            'DummyUniqueRule' => $uniqueRule,
            'DummyUniqueUpdateRule' => $uniqueRuleUpdate,
        ]);
    }
    
    protected function getWikiView($name)
    {
        return __DIR__."/wiki/{$name}.wiki";
    }
    
    protected function getRowData()
    {
        $modelClass = $this->parseModel($this->option('model'));
        $row = $modelClass::first();
        if(!$row){
            $obj = new $modelClass();
            $table = $obj->getTable();
            $this->error("[{$table}]表中至少保留一條數據");
            exit;
        }
        return $row->toArray();
    }
    protected function getRowsData()
    {
        $modelClass = $this->parseModel($this->option('model'));
        $row = $modelClass::limit(2)->get();
        return $row->toArray();
    }
    
    protected function getIdentify()
    {
        return 'mindoc-' . date('ymdH') . Str::random(16);
    }
    
    protected function getIS_NULLABLE($field, $IS_NULLABLE = 100)
    {
        $modelClass = $this->parseModel($this->option('model'));
        $obj = new $modelClass();
        $rules = $obj->rules()[$field] ?? [];
        if(empty($rules) && $IS_NULLABLE !== 100){
            return $IS_NULLABLE ? true : false;
        }
        if(collect($rules)->search('required') !== false){
            return true;
        }
        return false;
    }
}
