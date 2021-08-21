<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class Device extends Model implements FieldsInterface
{
    //
    use SoftDeletes;

    protected $cache = [];

    protected $table = "assets_device";

    const STATE_FREE = 0; //闲置
    const STATE_USE = 1; //使用中
    const STATE_MAINTAIN = 2; //维护
    const STATE_DOWN = 3; //报废

    const RACK_CATEID = 37; //机柜分类ID，写死
    const FRAME_CATEID = 39; //配线架分类ID，写死

    public static $stateMsg = [
        self::STATE_FREE => "闲置",
        self::STATE_USE => "使用中",
        self::STATE_MAINTAIN => "维护中",  # 原来叫维修
        self::STATE_DOWN => "报废"
    ];

    protected $guarded = [
        "id",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    public $fieldTrans = [
        "rack" => "rack_no",  //机柜对应的可读名称使用字段
        "frame_device" => "frame_num" //配线架对应的可读名称使用字段
    ];


    public function category() {
        return $this->hasOne("App\Models\Assets\Category", "id", "category_id")->withDefault();
    }

    public function sub_category() {
        return $this->hasOne("App\Models\Assets\Category", "id", "sub_category_id")->withDefault();
    }

    public function monitor() {
        return $this->hasOne("App\Models\Monitor\AssetsMonitor", "asset_id", "id")->withDefault();
    }

    public function events() {
        return $this->hasMany("App\Models\Workflow\Event", "asset_id", "id");
    }

    public function emdevice() {
        return $this->hasOne("App\Models\Monitor\EmDevice", "asset_id", "id")->withDefault();
    }

    public function engineroom(){
        return $this->hasOne("App\Models\Assets\Engineroom", "id", "area")->withDefault();
    }

    public function zone(){
        return $this->hasOne("App\Models\Assets\Zone", "id", "location")->withDefault();
    }

    public function department(){
        return $this->hasOne("App\Models\Assets\Department", "id", "department")->withDefault();
    }

    public function office_building(){
        return $this->hasOne("App\Models\Assets\Building", "id", "officeBuilding")->withDefault();
    }

    public function dict(){
        return $this->hasOne("App\Models\Assets\Dict", "id", "brand")->withDefault();
    }

    /**
     * 根据机柜号取id
     * @param $name
     * @return mixed
     */
    public function getByName($value, $where = null, $insert = false) {
        $field = "rack_no";
        $cacheKey = "name$value";
        if(!empty($where)) {
            ksort($where);
            foreach($where as $k => $v) {
                $cacheKey .= $k.$v."_";
            }
        }
        $cacheKey = md5($cacheKey);

        if(!isset($this->cache[$cacheKey])) {
            $where[$field] = $value;
            $ret = $this->where($where)->first();
            if(!empty($ret)) {
                $this->cache[$cacheKey] = $ret->id;
            }
            else {
                $this->cache[$cacheKey] = null;
            }
        }
        return $this->cache[$cacheKey];
    }


    public function getRelatedFields() { //仅用于机柜
        return [
            "area" => "area"
        ];
    }

    public function getFieldById($id, $field) {
        if(!isset($this->cache[$id])) {
            $ret = $this->find($id);
            if(!empty($ret)) {
                $this->cache[$id] = $ret;
            }
            else {
                $this->cache[$id] = null;
                return null;
            }
        }
        return $this->cache[$id]->$field;
    }

    /**
     * 根据资产编号获取
     * @param $number
     * @return mixed
     */
    public function getByNumber($number, $where = null) {
        if (is_array($number)) {
            $obj = $this->whereIn("number" , $number);
        }
        else {
            $obj = $this->where(["number" => $number]);
        }
        if(!empty($where)) {
            $obj = $obj->where($where);
        }
        return $obj->get();
    }

    public function getRackList($assetId = null) {
        $where = ["sub_category_id" => self::RACK_CATEID, "state" => self::STATE_USE];
        if(!empty($assetId)) {
            $where['id'] = $assetId;
            return $this->where($where)->select(["id","category_id","sub_category_id","number","brand", "rack_size", "rack_no"])->first();
        }
        else {
            return $this->where($where)->select(["id","category_id","sub_category_id","number","brand", "rack_size", "rack_no"])->get();
        }
    }

    /**
     * 是否为上架状态
     * @param null $assetId
     * @return bool
     */
    public function isRack($assetId = null) {
        $ret = $this->where(["sub_category_id" => self::RACK_CATEID, "id" => $assetId])->first();
        if(!empty($ret)) {
            return true;
        }
        else {
            return false;
        }
    }

    public function getDict($field) {
        $method = "get" . ucfirst(camel_case($field));
        if(method_exists($this, $method)) {
            return call_user_func_array([$this, "$method"],[]);
        }
        else {
            return false;
        }
    }

    /**
     * 获取机柜列表
     * @return array
     */
    public function getRack() {
        $where = ["sub_category_id" => self::RACK_CATEID, "state" => self::STATE_USE];
        $data = $this->where($where)->select(["id","category_id","sub_category_id","number","brand", "rack_size", "rack_no", "area"])->get()->groupBy("area");

        $ret = [];
        foreach($data as $area => $v) {
            $rackList = [];
            foreach($v as $vv) {
                $rackList[] = [
                    "text" => $vv->rack_no,
                    "value" => $vv->id,
                ];
            }
            $ret[] = [
                "area" => $area,
                "rackList" => $rackList
            ];
        }
        return $ret;
    }

    /**
     * 获取配线架列表
     * @return array
     */
    public function getFrameDevice() {
        $where = ["sub_category_id" => self::FRAME_CATEID, "state" => self::STATE_USE];
        $data = $this->where($where)->select(["id","category_id","sub_category_id","number","brand", "frame_num", "area"])->get()->groupBy("area");

        $ret = [];
        foreach($data as $area => $v) {
            $frameList = [];
            foreach($v as $vv) {
                $frameList[] = [
                    "text" => $vv->frame_num,
                    "value" => $vv->id,
                ];
            }
            $ret[] = [
                "area" => $area,
                "rackList" => $frameList
            ];
        }
        return $ret;
    }

    public function getCntByState($state) {
        return $this->where(["state" => $state])->count();
    }

}
