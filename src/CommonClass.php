<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OpenStrong\StrongAdmin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Description of CommonClass
 *
 * @author Administrator
 */
class CommonClass
{

    public static function getColumns(string $table, $connection = null, $prefix = null)
    {
        if (!$connection)
        {
            $connection = DB::getDefaultConnection();
        }
        if (!$prefix)
        {
            $prefix = DB::connection($connection)->getConfig('prefix');
        }
        $prefix = config("database.connections.{$connection}.prefix");
        $db = config("database.connections.{$connection}.database");
        $columns = DB::select("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = '{$prefix}{$table}'");
        return $columns;
    }

    public static function getTableInfo(string $table, $connection = null, $prefix = null)
    {
        if (!$connection)
        {
            $connection = DB::getDefaultConnection();
        }
        if (!$prefix)
        {
            $prefix = DB::connection($connection)->getConfig('prefix');
        }
        $prefix = config("database.connections.{$connection}.prefix");
        $db = config("database.connections.{$connection}.database");
        $columns = DB::select("select table_schema, table_name, table_comment from information_schema.tables where table_schema = '{$db}' and table_name = '{$prefix}{$table}';");
        return $columns[0]->table_comment ?: $columns[0]->table_name;
    }

    /**
     * 獲取model相對路徑
     * @param type $modelClass
     * @return type
     */
    public static function getModelPath($modelClass)
    {
        return str_replace('App\\', '', $modelClass);
    }

    /**
     * 獲取表字段的索引名稱
     * @param string $table
     * @param string $column_name
     * @return type
     */
    public static function getColumnsIndex(string $table, string $column_name, $connection = null, $prefix = null)
    {
        if (!$connection)
        {
            $connection = DB::getDefaultConnection();
        }
        if (!$prefix)
        {
            $prefix = DB::connection($connection)->getConfig('prefix');
        }
        $prefix = config("database.connections.{$connection}.prefix");
        $db = config("database.connections.{$connection}.database");
        $columns = DB::select("SELECT * FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = '{$db}' AND `TABLE_NAME` = '{$prefix}{$table}' AND `COLUMN_NAME` = '$column_name';");
        return $columns[0]->CONSTRAINT_NAME ?? '';
    }

    /**
     * 根據索引名稱獲取`相同索引名稱`欄位
     * @param string $table
     * @param string $constraint_name
     * @return type
     */
    public static function getIndexColumns(string $table, string $constraint_name, $connection = null, $prefix = null)
    {
        if (!$connection)
        {
            $connection = DB::getDefaultConnection();
        }
        if (!$prefix)
        {
            $prefix = DB::connection($connection)->getConfig('prefix');
        }
        $prefix = config("database.connections.{$connection}.prefix");
        $db = config("database.connections.{$connection}.database");
        $columns = DB::select("SELECT * FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = '{$db}' AND `TABLE_NAME` = '{$prefix}{$table}' AND `CONSTRAINT_NAME` = '$constraint_name';");
        return $columns;
    }

    /**
     * api介面的 url path
     * @param string $name
     * @return type
     */
    public static function getRoutePathName(string $name)
    {
        $name = str_replace('App\\Http\\Controllers\\', '', $name);
        $name = str_replace('Controller', '', $name);
        $arr = explode('\\', $name);
        foreach ($arr as $k => $r)
        {
            $arr[$k] = Str::camel($r);
        }
        $path_name = join('/', $arr);
        return $path_name;
    }

    /**
     * Vue的駝峰命名
     * @param string $name
     * @return type
     */
    public static function getVueStudlyCase(string $name)
    {
        $arr = explode('/', $name);
        foreach ($arr as $k => $val)
        {
            $arr[$k] = snake_case($val);
        }
        $underline_name = join('_', $arr);
        $pathName = Str::studly($underline_name);
        return $pathName;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public static function getTable($name)
    {
        $name_space = trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
        $class = str_replace($name_space . '\\', '', $name);
//        return str_replace(
//            '\\', '', Str::snake(Str::plural($class))
//        );
        return str_replace(
                '\\', '', Str::snake(($class))
        );
    }

    /**
     * 獲取表主鍵名
     * @param string $table
     * @return type
     */
    public static function getKeyName(string $table, $connection = null, $prefix = null)
    {
        if (!$connection)
        {
            $connection = DB::getDefaultConnection();
        }
        if (!$prefix)
        {
            $prefix = DB::connection($connection)->getConfig('prefix');
        }
        $prefix = config("database.connections.{$connection}.prefix");
        $db = config("database.connections.{$connection}.database");
        $row = DB::select("SELECT column_name FROM INFORMATION_SCHEMA.`KEY_COLUMN_USAGE` WHERE TABLE_SCHEMA = '{$db}' AND table_name='{$prefix}{$table}' AND constraint_name='PRIMARY'");

        return $row[0]->column_name ?? null;
    }

    public static function existsTable(string $table, $connection = null, $prefix = null)
    {
        if (!$connection)
        {
            $connection = DB::getDefaultConnection();
        }
        if (!$prefix)
        {
            $prefix = DB::connection($connection)->getConfig('prefix');
        }
        $prefix = config("database.connections.{$connection}.prefix");
        $db = config("database.connections.{$connection}.database");
        $row = DB::select("SELECT table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = '{$db}' AND table_name='{$prefix}{$table}'");
        return $row[0]->table_name ?? null;
    }

    /**
     * 獲取 laravel model 驗證規則
     * @param string $type 表字段型別
     * @return string
     */
    public static function getDataType(string $type)
    {
        $data_type = strtoupper($type);
        $data = [
            'integer' => ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INTEGER', 'INT', 'BIGINT'],
            'numeric' => ['FLOAT', 'DOUBLE', 'DECIMAL'],
            'date' => ['DATE', 'DATETIME', 'TIMESTAMP'],
            'time' => ['TIME'],
            'year' => ['YEAR'],
            'string' => ['CHAR', 'VARCHAR', 'TINYTEXT', 'TEXT', 'LONGTEXT'],
        ];
        if (in_array($data_type, $data['integer']))
        {
            return 'integer';
        } elseif (in_array($data_type, $data['numeric']))
        {
            return 'numeric';
        } elseif (in_array($data_type, $data['date']))
        {
            return 'date';
        } elseif (in_array($data_type, $data['time']))
        {
            return 'date_format:H:i:s';
        } elseif (in_array($data_type, $data['year']))
        {
            return 'date_format:Y';
        } else
        {
            return 'string';
        }
    }

    /**
     * 返回字串中給定值之前的所有內容
     * @param type $subject
     * @param type $search
     * @return type
     */
    public static function strBefore($subject, $search = null)
    {
        $pun = [' ', ':', '：', '，', ','];
        if ($search === null)
        {
            $datas = $pun;
        } else
        {
            if (is_string($search))
            {
                $datas[] = $search;
            } else
            {
                $datas = $search;
            }
            $datas = array_merge_recursive($datas, $pun);
        }
        foreach ($datas as $data)
        {
            if (!function_exists('str_before'))
            {
                $subject = Str::before($subject, $data);
            } else
            {
                $subject = str_before($subject, $data);
            }
        }
        return $subject;
    }

}
