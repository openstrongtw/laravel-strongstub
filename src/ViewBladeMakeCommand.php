<?php

namespace OpenStrong\StrongAdmin;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Str;

class ViewBladeMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'strongstub:view';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '建立 laravel-strongadmin 檢視檔案';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Blade View';
    
    protected $table;
    protected $ignore_fields = ['created_at', 'updated_at', 'deleted_at'];

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $name = $this->getNameInput();
        $name = trim($name, '/');
        $name = Str::camel($name);
//        $path = base_path() . '/.view/blade/' .  $name;
        $path = config('view.paths')[0];
        
        $form = $path . "/{$name}"  . '/form.blade.php';
        $list = $path . "/{$name}"  . '/index.blade.php';
        $show = $path . "/{$name}"  . '/show.blade.php';
        
        // First we will check to see if the class already exists. If it does, we don't want
        // to create the class and overwrite the user's code. So, we will bail out so the
        // code is untouched. Otherwise, we will continue generating this class' files.
        if ((! $this->hasOption('force') || ! $this->option('force'))) {
            if($this->files->exists($form)){
                $this->error($this->type."`{$form}` already exists!");
                $error =1;
            }
            if($this->files->exists($list)){
                $this->error($this->type."`{$list}` already exists!");
                $error =1;                
            }
            if($this->files->exists($show)){
                $this->error($this->type."`{$show}` already exists!");
                $error =1;                
            }
            if(isset($error)){
                return false;
            }
        }

        if ($this->option('table')) {
            $table = $this->option('table');
        }
        do{
            $exists = CommonClass::existsTable($table);
            if (!$exists) {
                $tableAsk = $this->ask("The table `{$table}` does not exist. Enter table name to regenerate or exit.",'Quit');
                if($tableAsk === 'Quit'){
                    exit(0);
                }else{
                    $table = $tableAsk;
                }
            }else{
                break;
            }
        }while(isset($tableAsk) && $tableAsk !== false);
        
        $this->table = $table;
        
        $this->makeDirectory($form);
        $this->makeDirectory($list);
        $this->makeDirectory($show);
        
        $this->files->put($form, $this->buildClass('form'));
        $this->files->put($list, $this->buildClass('index'));
        $this->files->put($show, $this->buildClass('show'));

        $this->info($this->type."`{$form}` created successfully.");
        $this->info($this->type."`{$list}` created successfully.");
        $this->info($this->type."`{$show}` created successfully.");
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
        $inputName = $this->getNameInput();
        $table = $this->table;
        $pathName = trim($inputName, '/');
        $replace['DummyInputPath'] = $inputName;
        $replace['DummyPathNameTitleCase'] = $pathName;
        $replace['DummyPathNameLcfirstTitleCase'] = lcfirst($pathName);//首字母小寫
        $replace['DummyTableName'] = $table;
        $replace = $this->buildRulesReplacements($replace, $table);
        $replace = $this->buildAttributesReplacements($replace, $table);
        
        $view = $this->files->get($this->getView($name));
        
        return str_replace(
                array_keys($replace), array_values($replace), $view
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists.'],
            ['table', 't', InputOption::VALUE_OPTIONAL, 'Generate the model with table name.'],
            ['radio', null, InputOption::VALUE_OPTIONAL, 'Generate radio input.'],
            ['checkbox', null, InputOption::VALUE_OPTIONAL, 'Generate checkbox input.'],
            ['select', null, InputOption::VALUE_OPTIONAL, 'Generate select input.'],
            ['cut', null, InputOption::VALUE_NONE, '縮減`欄位註釋`(自動刪除 空格/冒號 後面的字元).'],
        ];
    }
    
    protected function buildRulesReplacements(array $replace, $table)
    {
        $columns = CommonClass::getColumns($table);
        $primaryKeyName = $this->getKeyName($table);
        if ($this->option('radio')) {
            $filedsRadio = explode(',',$this->option('radio'));
        }
        if ($this->option('checkbox')) {
            $filedsCheckbox = explode(',',$this->option('checkbox'));
        }
        if ($this->option('select')) {
            $filedsSelect = explode(',',$this->option('select'));
        }
        // rules -------------------------------------
        $str_show = $str_input = $str = '';
        foreach ($columns as $key=>$column) {
            if($primaryKeyName === $column->COLUMN_NAME){
                //continue;
            }
            if(($key)%2 == 0)
            {
            $str_show .= '
        <tr>';
            }
            $str_show .='
            <td><strong>{{$model->getAttributeLabel(\''.$column->COLUMN_NAME.'\')}}</strong></td>
            <td>{{$model->'.$column->COLUMN_NAME.'}}</td>';
            if(($key)%2 > 0)
            {
        $str_show .='
        </tr>';
            }
                    
            if(in_array($column->COLUMN_NAME, $this->ignore_fields)){
                continue;
            }
            $COLUMN_COMMENT = $column->COLUMN_COMMENT ?: strtoupper($column->COLUMN_NAME);
            if ($this->option('cut')) {
                $COLUMN_COMMENT = CommonClass::strBefore($COLUMN_COMMENT);
            }
            $COLUMN_TYPE = CommonClass::getDataType($column->DATA_TYPE);
            $COLUMN_DEFAULT = in_array($COLUMN_TYPE, ['integer','numberic']) ? $column->COLUMN_DEFAULT : "'{$column->COLUMN_DEFAULT}'";
            $modifier = '';//修飾符
            if(in_array($COLUMN_TYPE, ['integer','numberic'])){
                $modifier = '.number';
            }
            //是否允許為空
            $not_NULLABLE='';
            if($column->IS_NULLABLE == 'NO'){
                $not_NULLABLE = ' st-form-input-required';
            }
            
            /*
             * Form Input default value
             */
            $str_input .= '
            <div class="layui-form-item">
                <label class="layui-form-label'.$not_NULLABLE.'"><i class="layui-icon layui-icon-help st-form-tip-help"></i>{{$model->getAttributeLabel(\''.$column->COLUMN_NAME.'\')}}</label>
                <div class="layui-input-block">
                    <input type="text" name="'.$column->COLUMN_NAME.'" value="{{$model->'.$column->COLUMN_NAME.'}}" autocomplete="off" placeholder="" class="layui-input">
                    <div class="layui-word-aux st-form-tip"></div>
                </div>
            </div>';
            
            // radio
            if(isset($filedsRadio) && in_array($column->COLUMN_NAME, $filedsRadio)){
                $str .= "
        <el-form-item label=\"{$COLUMN_COMMENT}\">
          <el-radio-group v-model=\"inputForm.{$column->COLUMN_NAME}\">
          <el-radio :label=\"1\">備選項1</el-radio>
          <el-radio :label=\"2\">備選項2</el-radio>
          </el-radio-group>
        </el-form-item>";
                continue;
            }
            // checkbox
            if(isset($filedsCheckbox) && in_array($column->COLUMN_NAME, $filedsCheckbox)){
                $str .= "
        <el-form-item label=\"{$COLUMN_COMMENT}\">
          <el-checkbox-group v-model=\"inputForm.{$column->COLUMN_NAME}\">
          <el-checkbox :label=\"1\">覈取方塊 1</el-checkbox>
          <el-checkbox :label=\"2\">覈取方塊 2</el-checkbox>
          </el-checkbox-group>
        </el-form-item>";
                continue;
            }
            // select
            if(isset($filedsSelect) && in_array($column->COLUMN_NAME, $filedsSelect)){
                $str .= "
        <el-select v-model{$modifier}=\"inputForm.{$column->COLUMN_NAME}\" clearable placeholder=\"請選擇\">
            <el-option
              v-for=\"item in options\"
              :key=\"item.value\"
              :label=\"item.label\"
              :value=\"item.value\">
            </el-option>
        </el-select>";
                continue;
            }
            $str .= "
        <el-form-item label=\"{$COLUMN_COMMENT}\">
          <el-input type=\"text\" v-model{$modifier}=\"inputForm.{$column->COLUMN_NAME}\"></el-input>
        </el-form-item>";
        }
        
        if(count($columns)%2 > 0){
                $str_show .='
        </tr>';
                }
                
        // ------------------------------
        
        return array_merge($replace, [
            'DummyRules' => $str,
            'DummyInputParams' => rtrim($str_input,','),
            'DummyShowParams' => rtrim($str_show,','),
        ]);
    }
    
    protected function buildAttributesReplacements(array $replace, $table)
    {
        $columns = CommonClass::getColumns($table);
        $primaryKeyName = $this->getKeyName($table);
        if ($this->option('radio')) {
            $filedsRadio = explode(',',$this->option('radio'));
        }
        // attributes ----------------------------------
        $str_search = $str_list = $str = "";
        $n = 1;
        foreach($columns as $column){
            if($primaryKeyName === $column->COLUMN_NAME){
                continue;
            }
            $COLUMN_COMMENT = $column->COLUMN_COMMENT ?: strtoupper($column->COLUMN_NAME);
            if ($this->option('cut')) {
                $COLUMN_COMMENT = CommonClass::strBefore($COLUMN_COMMENT, [' ',':','：']);
            }
            
            $html_comment_begin = $html_comment_end = '';
            if($n > 2){
                $html_comment_begin = '
        <!-- ';
                $html_comment_end = ' -->';
            }
            
            /*
             * search Form Input
             */
            $str .= $html_comment_begin;
            $str .= '<div class="layui-inline">
            <label class="layui-form-label">{{$model->getAttributeLabel(\''.$column->COLUMN_NAME.'\')}}</label>
            <div class="layui-input-inline">
                <input type="text" name="'.$column->COLUMN_NAME.'" autocomplete="off" placeholder="" class="layui-input">
            </div>
        </div>';
            $str .= $html_comment_end;
            
            /*
             *  table list
             */
            $str_list .= '
                , {field: \''.$column->COLUMN_NAME.'\', title: \'{{$model->getAttributeLabel("'.$column->COLUMN_NAME.'")}}\', width: 150, sort: true}';
            
            /*
             * Search Params
             */
            $str_search .= "
        {$column->COLUMN_NAME}: null,";
            
            ++$n;
        }
        // ------------------------------------------
        return array_merge($replace, [
            'DummySearchInput' => $str,
            'DummyList' => $str_list,
//            'DummySearchParams' => rtrim($str_search,','),
        ]);
    }

    public function getKeyName(string $table)
    {
        return CommonClass::getKeyName($table);
    }
    
    public function existsTable(string $table)
    {
        return CommonClass::existsTable($table);
    }
    
    protected function getStub(){}
    
    protected function getView($name)
    {
        return __DIR__."/view-blade/{$name}.blade.php";
    }
}
