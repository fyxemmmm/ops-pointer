<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Models\Assets\CategoryFields;
use App\Repositories\BaseRepository;
use App\Exceptions\ApiException;
use App\Models\Code;
use App\Models\Assets\Fields;
use Log;
use DB;
use Cache;

class FieldsRepository extends BaseRepository
{

    protected $validFieldsCache = []; //有效字段缓存
    protected $categoryCache = [] ;
//    protected $deviceTables = ["assets_device", "workflow_device", "workflow_multidevice"];
    protected $deviceTables = ["assets_device", "workflow_device"];

    public function __construct(Fields $fields, CategoryFields $categoryFields)
    {
        $this->model = $fields;
        $this->categoryFields = $categoryFields;
    }

    /**
     * 将单个字段值转为关联数据
     * @param $value
     * @param Fields $field
     * @return bool
     */
    public function transform($value, Fields $field) {
        if(!empty($field->field_dict) && !is_null($value)) {
            $fieldDict = json_decode($field->field_dict, true);
            if(isset($fieldDict[$value])) {
                return $fieldDict[$value];
            }
            else {
                Log::error("field transform error", ['value' => $value ]);
                return false;
            }
        }

        if(!empty($field->dict_table) && !is_null($value)) {
            if(strpos($field->dict_table, "|") > 0){
                list($dictTable, $p_sname) = explode("|", $field->dict_table);
                $cls = "\App\Models\\".$dictTable;
            }
            else {
                $cls = "\App\Models\\".$field->dict_table;
            }
            $model = new $cls();

            $result = $model->where("id","=",$value)->first();
            if(empty($result)) return null;
            return $result->name;
        }

        return $value;
    }


    protected function getType($type, $length) {
        switch($type) {
            case Fields::TYPE_INT:
            case Fields::TYPE_DICT:
                $type = "INT(10)";
                /*
                $default = intval($default);
                if($default === 0) {
                    Code::setCode(Code::ERR_PARAMS, ["默认值格式错误"]);
                    return false;
                }
                */
                break;
            case Fields::TYPE_STR:
                if($length < 0) {
                    Code::setCode(Code::ERR_PARAMS, ["长度错误"]);
                    return false;
                }
                $length = intval($length);
                if($length === 0) {
                    $length = 128;
                }
                $type = "VARCHAR($length)";
                break;
            case Fields::TYPE_FLOAT:
                $type = "DOUBLE";
                break;
            case Fields::TYPE_DATE:
                $type = "DATE";
                break;
            case Fields::TYPE_DATETIME:
                $type = "DATETIME";
                break;
            default:
                $type = "VARCHAR(128)";
        }
        return $type;
    }

    public function add($request) {
//        $length = null;
        $default = $request->input("default");
        if(empty($default)) {
            $default = null;
        }

        $type = $this->getType($request->input("type"), $request->input("length"));

        $ret = $this->model->orderBy("id","desc")->first();
        $lastField = $ret->field_sname;

        $data = [
            "field_sname" => $request->input("sname"),
            "field_cname" => $request->input("cname"),
            "field_type" => $request->input("type"),
            "field_length" => $request->input('length'),
            "field_desc" => $request->input("desc"),
            "field_default" => $default,
            "field_type_id" => $request->input("typeId"),
            "field_dict" => $request->input("dict"),
            "dict_table" => $request->input("model"),
            "url" => $request->input("url"),
            "system" => $request->input("system", 0),
        ];

        $alterSql = [];
        if(is_null($default)) {
            foreach($this->deviceTables as $table) {
                $alterSql[] = sprintf("ALTER TABLE `%s` ADD `%s` %s NULL DEFAULT NULL COMMENT '%s' AFTER `%s`", $table, $request->input("sname"),$type,$request->input("desc"), $lastField);
            }
        }
        else {
            foreach($this->deviceTables as $table) {
                $alterSql[] = sprintf("ALTER TABLE `%s` ADD `%s` %s NULL DEFAULT '%s' COMMENT '%s' AFTER `%s`", $table, $request->input("sname"),$type, $default,$request->input("desc"), $lastField);
            }
        }

        DB::transaction(function () use($data, $alterSql) {
            $this->store($data); //todo 检查事物是否生效

            foreach($alterSql as $sql) {
                DB::statement($sql);
            }
        });
        userlog("添加了资产字段：".$request->input("sname"));
        Cache::forget("fields");
    }

    public function view($request) {
        return $this->getById($request->input("id"));
    }


    public function edit($request) {
        $id = $request->input("id");
        $field = $this->getById($id);
        if($request->input("type") != $field->field_type ||
            $request->input("length") != $field->field_length ||
            $request->input("default") != $field->field_default ||
            (!empty($request->input("desc")) && $request->input("desc") != $field->field_desc)
        ) {
            //修改表结构
            $type = $this->getType($request->input("type"), $request->input("length"));
            $default = $request->input("default");
            $desc = $request->input("desc");
            $sname = $field->field_sname;

            $alterSql = [];
            if(is_null($default)) {
                foreach($this->deviceTables as $table) {
                    $alterSql[] = sprintf("ALTER TABLE `%s` CHANGE `%s` `%s` %s NULL DEFAULT NULL COMMENT '%s';",$table, $sname,$sname,$type, $desc);
                }
            }
            else {
                foreach($this->deviceTables as $table) {
                    $alterSql[] = sprintf("ALTER TABLE `%s` CHANGE `%s` `%s` %s NULL DEFAULT '%s' COMMENT '%s'", $table, $sname,$sname,$type, $default, $desc);
                }
            }

            DB::transaction(function () use($alterSql) {
                foreach($alterSql as $sql) {
                    DB::statement($sql);
                }
            });
        }
        $data = [
            "field_cname" => $request->input("cname"),
            "field_type" => $request->input("type"),
            "field_length" => $request->input("length"),
            "field_desc" => $request->input("desc"),
            "field_default" => $request->input("default"),
            "field_type_id" => $request->input("typeId"),
            "field_dict" => $request->input("dict"),
            "dict_table" => $request->input("model"),
            "url" => $request->input("url"),
            "system" => $request->input("system"),
        ];
        $this->update($id, $data);
        userlog("修改了资产字段，字段id：".$id);
        Cache::forget("fields");
    }

    public function delete($request) {
        //确保category没有使用
        $ret = $this->categoryFields->where("field_id","=",$request->input("id"))->first();
        if($ret) {
            Code::setCode(Code::ERR_FIELD_INUSE);
            return false;
        }

        $ret = $this->getById($request->input("id"));

        $this->del($request->input("id"));

        $alterSql = [];
        foreach($this->deviceTables as $table) {
            $alterSql[] = sprintf("ALTER TABLE `%s` DROP `%s`", $table, $ret->field_sname);
        }

        DB::transaction(function () use($alterSql) {
            foreach($alterSql as $sql) {
                DB::statement($sql);
            }
        });

        userlog("删除了资产字段：".$ret->field_sname);
        Cache::forget("fields");
    }

    public function getList($request) {
        $search = $request->input("s");

        $model = $this->model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("field_sname", "like", "%" . $search . "%");
                $query->orWhere("field_cname", "like", "%" . $search . "%");
            }
        });

        $hiddenFields = $this->categoryFields->hiddenFields;
        foreach($hiddenFields as $v) {
            $model->where("field_sname","!=",$v);
        }

        $model->orderBy("id","asc");

        $data = $this->usePage($model);
        return $data;
    }


}