<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Workflow\EventsSuspend;


class Oa extends Model
{
    use SoftDeletes;

    protected $table = "workflow_oa";

    protected $fillable = [
        "category_id",
        "company",
        "object",
        "accept_at",
        "reached_at",
        "finished_at",
        "user_id",
        "assigner_id",
        "state",
        "source",
        "remark",
        "report_id",
        "report_name",
        "description",
        "mobile",
        "close_uid",
        "response_time",
        "device_name",
        "location",
        "problem",
        "is_comment",
        "process_time",
        "suspend_status",
        "report_at",
        "distance_time"
    ];

    const STATE_WAIT = 0;
    const STATE_ACCEPT = 1;
    const STATE_ING = 2;
    const STATE_END = 3;
    const STATE_CLOSE = 4;

    const SRC_SELF = 0;
    const SRC_ASSIGN = 1;
    const SRC_TERMINAL = 2;

    const OBJ_NETWORK = 1;
    const OBJ_SECURE = 2;
    const OBJ_SERVER = 3;
    const OBJ_STORE = 4;
    const OBJ_PC = 5;
    const OBJ_OFFICE = 6;
    const OBJ_ROOM = 7;
    const OBJ_BASE = 8;
    const OBJ_OTHER = 9;

    const TIME_1 = 1;
    const TIME_2 = 2;
    const TIME_3 = 3;
    const TIME_4 = 4;
    const TIME_5 = 5;

    public static $stateMsg = [
        self::STATE_WAIT =>     '待处理',
        self::STATE_ACCEPT =>   '已接单',
        self::STATE_ING =>      '处理中',
        self::STATE_END =>      '已完成',
        self::STATE_CLOSE =>    '已关闭',
    ];

    public static $objectMsg = [
        self::OBJ_NETWORK =>     '网络设备',
        self::OBJ_SECURE =>     '安全设备',
        self::OBJ_SERVER =>     '服务器',
        self::OBJ_STORE =>     '存储',
        self::OBJ_PC =>     '电脑',
        self::OBJ_OFFICE =>     '办公外设',
        self::OBJ_ROOM =>     '机房设备',
        self::OBJ_BASE =>     '机房基础设备',
        self::OBJ_OTHER =>     '其他'
    ];

    //事件来源
    public static $sourceMsg = [
        self::SRC_SELF => '我的事件',
        self::SRC_ASSIGN => '主管分派',
        self::SRC_TERMINAL => '终端上报',
    ];

    public function getState() {
        $result = [];
        foreach(self::$stateMsg as $k => $v) {
            $result[] = [
                "value" => $k,
                "msg"   => $v
            ];
        }
        return $result;
    }

    public function getObject() {
        $result = [];
        foreach(self::$objectMsg as $k => $v) {
            $result[] = [
                "value" => $k,
                "msg"   => $v
            ];
        }
        return $result;
    }

    public function getSource(){
        foreach(self::$sourceMsg as $k=>$v){
            $result[] = array(
                'value' => $k,
                'msg' => $v,
            );
        }
        return $result;
    }


    public static $timeArr = [
        self::TIME_1 => array(0,1800),//半小时内
        self::TIME_2 => array(0,3600),//1小时内
        self::TIME_3 => array(0,43200),//半天以内
        self::TIME_4 => array(0,86400),//一天以内
        self::TIME_5 => 86400,//一天以外
    ];

    public function category() {
        return $this->hasOne("App\Models\Workflow\Category", "id", "category_id")->withDefault();
    }

    public function user() {
        return $this->hasOne("App\Models\Auth\User", "id", "user_id")->withTrashed()->withDefault();
    }

    public function assigner() {
        return $this->hasOne("App\Models\Auth\User", "id", "assigner_id")->withTrashed()->withDefault();
    }

    public function reporter() {
        return $this->hasOne("App\Models\Auth\User", "id", "report_id")->withTrashed()->withDefault();
    }

    public function suspend(){
        return $this->hasMany("App\Models\Workflow\EventsSuspend","event_id","id")->withTrashed()->where('events_suspend.etype',EventsSuspend::OATYPE);
    }


}