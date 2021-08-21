<?php
/**
 * 资产字段通用字典表业务处理
 * User: yanxiang
 * Date: 2019/4/1
 * Time: 22:30
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Dict;
use App\Models\Code;
use App;

class DictRepository extends BaseRepository
{

    protected $fieldsRepository;

    const DICT_MODEL = "Assets\\Dict";

    public function __construct(Dict $model, FieldsRepository $fieldsRepository)
    {
        $this->model = $model;
        $this->fieldsRepository = $fieldsRepository;
    }

    /**
     * 获取验证字段
     * @param $fieldId
     * @return bool|mixed
     */
    protected function checkAndGetField($fieldId) {
        $field = $this->fieldsRepository->getById($fieldId, false);

        if(empty($field) || strpos($field->dict_table, self::DICT_MODEL) === false) {
            Code::setCode(Code::ERR_PARAMS, ["给定字段没有数据字典"]);
            return false;
        }
        return $field;
    }

    /**
     * 获取验证父字段
     * @param $field
     * @return bool
     */
    public function checkAndGetParentField($field) {
        if(strpos($field->dict_table, "|") > 0){
            list($dictTable, $p_sname) = explode("|", $field->dict_table);
            $rs = $this->fieldsRepository->all(["field_sname" => $p_sname]);
            if(count($rs) > 0) {
                return $rs[0];
            }
        }
        else {
            return false;
        }
    }

    /**
     * @param $dictTable
     */
    public function getParentField($dictTable) {
        if(strpos($dictTable, "|") > 0){
            list($dictTable, $p_sname) = explode("|", $dictTable);
            $rs = $this->fieldsRepository->all(["field_sname" => $p_sname]);
            if(count($rs) > 0) {
                return [
                    "id"   => $rs[0]->id,
                    "sname" => $rs[0]->field_sname,
                    "cname" => $rs[0]->field_cname,
                ];
            }
        }
        else {
            return false;
        }
    }


    /**
     * 返回模型名称
     * @param $dictTable
     * @param null $p_sname
     * @return bool|string
     */
    public function getCls($dictTable, &$p_sname = null) {
        if(strpos($dictTable, "|") > 0){
            list($dictTable, $p_sname) = explode("|", $dictTable);
            $cls = "\App\Models\\".$dictTable;
        }
        else {
            $cls = "\App\Models\\".$dictTable;
        }
        if(!class_exists($cls)) {
            return false;
        }
        else {
            return $cls;
        }
    }

    public function getOptions($dictTable, $sname) {
        $className = $this->getCls($dictTable, $p_sname);
        $cls = App::make($className);
        return call_user_func_array([$cls, "getDict"],[$sname]);
    }


    /**
     * 获取dict table相关的数据
     * @return mixed
     */
    public function getType() {
        $where[] = ["dict_table", "like", addslashes(self::DICT_MODEL)."%"];
        $data =  $this->fieldsRepository->all($where);
        $result = [];
        foreach($data as $v) {
            $result[] = [
                "fieldId" => $v->id,
                "fieldSname" => $v->field_sname,
                "fieldCname" => $v->field_cname,
            ];
        }
        return $result;
    }

    /**
     * 添加字典数据
     */
    public function add($request) {
        $fieldId = $request->input("fieldId");
        $pid = $request->input("pid");
        //检查field
        $field = $this->checkAndGetField($fieldId);
        if(false === $field) {
            return false;
        }

        $pdata = null;
        if($pid) {
            $pdata = $this->getById($pid, false);
            if(empty($pdata)) {
                Code::setCode(Code::ERR_PARAMS, ["找不到给定父节点数据"]);
                return false;
            }
        }

        //判断当前字段是否有父字段
        $parentField = $this->checkAndGetParentField($field);
        if(!empty($parentField)) {
            if($pid == 0) {
                Code::setCode(Code::ERR_PARAMS, ["找不到给定父节点数据"]);
                return false;
            }
            if(empty($pdata)) {
                Code::setCode(Code::ERR_PARAMS, ["找不到给定父节点数据"]);
                return false;
            }

            if($parentField->id != $pdata->field_id) {
                Code::setCode(Code::ERR_PARAMS, ["父节点数据不匹配"]);
                return false;
            }
        }


        $data = [
            "name" => $request->input("name"),
            "remark" => $request->input("remark"),
            "field_id" => $fieldId,
            "pid"  => $pid,
        ];

        $this->store($data);
        $log = sprintf("添加了%s数据：%s", $field->field_cname, $request->input("name"));
        userlog($log);
        return true;
    }

    public function delete($id) {
        //删除字典之前确认是否有子数据使用
        $data = $this->all(["pid" => $id]);
        if(count($data) > 0) {
            Code::setCode(Code::ERR_DICT_NOT_EMPTY);
            return false;
        }

        $this->del($id);
        return true;
    }


    /**
     * 获取字段信息
     * @param $fieldId
     */
    public function getFieldInfo($fieldId) {
        $field = $this->checkAndGetField($fieldId);
        if(false === $field) {
            return [];
        }
        $parentField = $this->checkAndGetParentField($field);

        $field = [
            "id"   => $field->id,
            "sname" => $field->field_sname,
            "cname" => $field->field_cname,
        ];
        $parent = [];

        if(!empty($parentField)) {
            $where = [
                "field_id" => $parentField->id
            ];
            $parentFieldOptions = $this->all($where);
            $options = [];
            foreach($parentFieldOptions as $v) {
                $options[] = [
                    "text" => $v['name'],
                    "value" => $v['id']
                ];
            }
            $parent = [
                "id"   => $parentField->id,
                "sname" => $parentField->field_sname,
                "cname" => $parentField->field_cname,
                "options" => $options
            ];
        }
        return [
            "field" => $field,
            "parentField" => $parent
        ];
    }

    public function getChildren($pid) {
        //取当前字段ID
        $row = $this->getById($pid);
        $where = [ "id" => $row->field_id ];
        $result = $this->fieldsRepository->all($where);

        $where = [ "dict_table" => "Assets\\Dict|".$result[0]->field_sname ];
        $childResult = $this->fieldsRepository->all($where);
        if($childResult->count() > 0) {
            $childFieldId = $childResult[0]->id;
        }
        else {
            $childFieldId = 0;
        }

        $where = [];
        $where[] = ['pid', '=', $pid];
        $children =  $this->all($where);
        $data = ["result" => [], "fieldId" => $childFieldId];
        if($children->count() > 0) {
            $options = [];
            foreach($children as $v) {
                $options[] = [
                    "text" => $v['name'],
                    "value" => $v['id']
                ];
            }
            $data["result"] = $options;
        }
        return $data;
    }

    public function getList($request) {
        $fieldId = $request->input("fieldId");
        $search = $request->input("search");

        $where = [];
        $where[] = ['field_id', '=', $fieldId];

        if(!empty($search)) {
            $where[] = ['name', 'like', "%". $search . "%"];
        }

        return $this->page($where);
    }

    public function edit($request) {
        $id = $request->input("id");
        $pid = $request->input("pid", 0);
        $name = $request->input("name");
        $remark = $request->input("remark");
        $data = $this->getById($id);

        //判断是否有父节点
        $field = $this->checkAndGetField($data->field_id);
        if(false === $field) {
            return [];
        }

        $parent = $this->checkAndGetParentField($field);
        if($parent) {
            //必须选取父节点数据
            if($pid == 0) {
                Code::setCode(Code::ERR_PARAMS, ["缺少父节点数据"]);
                return false;
            }

            //检查父节点数据是否正确
            $pdata = $this->getById($pid, false);
            if(empty($pdata)) {
                Code::setCode(Code::ERR_PARAMS, ["找不到给定父节点数据"]);
                return false;
            }
            if($parent->id != $pdata->field_id) {
                Code::setCode(Code::ERR_PARAMS, ["父节点数据不匹配"]);
                return false;
            }
        }
        else {
            $pid = 0;
        }

        $update = [
            "pid" => $pid,
            "name" => $name,
            "remark" => $remark,
        ];

        $this->update($id, $update);
    }




}