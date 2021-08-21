<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Assets\Device;

class Category extends Model
{
    //
    use SoftDeletes;

    protected $table = "workflow_category";

    const INSTORAGE = 1; //入库
    const UP = 2; //上架
    const MODIFY = 3; //变更
    const MAINTAIN = 4; //维护
    const DOWN = 5; //下架
    const BREAKDOWN = 6; //报废
    const MULTI_INSTORAGE = 7; //批量入库
    const MULTI_UP = 8; //批量上架
    const MULTI_MODIFY = 9; //批量变更

    protected $fillable = [
        "name",
        "address",
        "desc",
        "admin",
        "phone",
        "user_id",
    ];

    public static $stateMsg = [
        Device::STATE_FREE => "闲置",
        Device::STATE_USE => "使用中",
        Device::STATE_MAINTAIN => "维护中",    # 原来叫维修
        Device::STATE_DOWN => "报废"
    ];

    public static $msg = [
        self::INSTORAGE => "入库",
        self::UP => "上架",
        self::MODIFY => "变更",
        self::MAINTAIN => "维护",
        self::DOWN => "下架",
        self::BREAKDOWN => "报废",
        self::MULTI_INSTORAGE => "批量入库",
        self::MULTI_UP => "批量上架",
        self::MULTI_MODIFY => "批量变更",
    ];

    public static function getMsg($categoryId) {
        return isset(self::$msg[$categoryId])?self::$msg[$categoryId]:"";
    }

    /**
     * 根据状态返回可操作的事件
     * @param $state
     * @return array
     */
    public function getCategoriesByState($state, $isReporter = false) {
        $categories = $this->where("batch","=",0)->where("id","<",100)->get(['id', 'name','icon'])->toArray();

        $validCate = [];
        if($isReporter) { //终端上报只允许变更和维修
            if($state != Device::STATE_USE) {
                return false;
            }
            $validCate = [self::MODIFY, self::MAINTAIN];
        }
        else {
            if(is_null($state)) {
                $validCate = [self::INSTORAGE];
            }
            else {
                switch ($state) {
                    case Device::STATE_FREE:  //闲置 0
                        $validCate = [self::UP, self::BREAKDOWN];
                        break;
                    case Device::STATE_USE:  //使用中 1
                        $validCate = [self::MODIFY, self::MAINTAIN, self::DOWN];
                        break;
                    case Device::STATE_MAINTAIN: //维护 2
                        $validCate = [self::MODIFY, self::MAINTAIN, self::DOWN];
                        break;
                    case Device::STATE_DOWN: //报废 3
                        $validCate = [];
                        break;
                }
            }
        }


        foreach($categories as &$v) {
            if(in_array($v['id'] , $validCate)) {
                $v['enable'] = true;
            }
            else {
                $v['enable'] = false;
            }
        }
        return $categories;
    }

    /**
     * 根据事件分类返回资产状态
     * @param $category
     * @return int
     */
    public function getStateByCategory($category) {
        switch ($category) {
            case self::DOWN:
            case self::INSTORAGE:
                $state = Device::STATE_FREE;
                break;
            case self::UP:
                $state = Device::STATE_USE;
                break;
            case self::MAINTAIN:
            case self::MODIFY:
                $state = Device::STATE_MAINTAIN;
                break;
            case self::BREAKDOWN:
                $state = Device::STATE_DOWN;
                break;
        }
        return $state;
    }

}
