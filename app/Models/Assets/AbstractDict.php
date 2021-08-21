<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractDict extends Model
{

    protected $sname = null;

    /**
     * 取字典数据，针对fields表中的dict_table
     */
    public function getDict($field = "") {
        $data = $this->get()->pluck("name", "id")->toArray();
        $options = [];
        foreach($data as $id => $name) {
            $options[] = [
                "text" => $name,
                "value" => $id
            ];
        }
        return $options;
    }

    public function getRelatedFields() {
        return [];
    }

    public function setRelatedFields($sname = null) {
        $this->sname = $sname;
    }


    public function getByName($value, $where = [], $insert = false) {
        $cacheKey = "name$value";
        if(!empty($where)) {
            ksort($where);
            foreach($where as $k => $v) {
                $cacheKey .= $k.$v."_";
            }
        }
        $cacheKey = md5($cacheKey); //todo 缓存需要全局生效

        if(!isset($this->cache[$cacheKey])) {
            $where['name'] = $value;
            $ret = $this->where($where)->first();
            if(!empty($ret)) {
                $this->cache[$cacheKey] = $ret->id;
            }
            else {
                if($insert) {
                    $input = $where;
                    $this->fill($input)->save();
                    $this->cache[$cacheKey] = $this->where($where)->first()->id;
                }
                else {
                    $this->cache[$cacheKey] = null;
                }
            }
        }
        return $this->cache[$cacheKey];
    }

    public function getFieldById($id, $field = "name") {
        if(!isset($this->cache[$id])) {
            $ret = $this->find($id);
            if(!empty($ret)) {
                $this->cache[$id] = $ret->$field;
            }
            else {
                $this->cache[$id] = null;
            }
        }
        return $this->cache[$id];
    }

}
