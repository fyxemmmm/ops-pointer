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

class Event extends Model
{
    use SoftDeletes;

    protected $table = "workflow_events";

    protected $fillable = [
        "category_id",
        "asset_id",
        "user_id",
        "assigner_id",
        "state",
        "source",
        "remark",
        "report_id",
        "report_name",
        "description",
        "mobile",
        "is_comment",
        "close_uid",
        "alert_event_id",
        "changes",
        "suspend_status",
        "process_time",
        "response_time",
        "accept_at",
        "reached_at",
        "finished_at",
        "report_at",
        "distance_time",
        "em_alert_event_id"
    ];

    const STATE_WAIT = 0;
    const STATE_ACCESS = 1;
    const STATE_ING = 2;
    const STATE_END = 3;
    const STATE_CLOSE = 4;

    const SRC_SELF = 0;
    const SRC_ASSIGN = 1;
    const SRC_MONITOR = 2;
    const SRC_TERMINAL = 3;

    const TIME_1 = 1;
    const TIME_2 = 2;
    const TIME_3 = 3;
    const TIME_4 = 4;
    const TIME_5 = 5;

    public static $stateMsg = [
        self::STATE_WAIT =>     '待处理',
        self::STATE_ACCESS =>   '已接单',
        self::STATE_ING =>      '处理中',
        self::STATE_END =>      '已完成',
        self::STATE_CLOSE =>    '已关闭',
    ];

    //事件来源
    public static $sourceMsg = [
        self::SRC_SELF => '我的事件',
        self::SRC_ASSIGN => '主管分派',
        self::SRC_MONITOR => '监控报警',
        self::SRC_TERMINAL => '终端上报',
    ];

    public static $timeArr = [
        self::TIME_1 => array(0,1800),//半小时内
        self::TIME_2 => array(0,3600),//1小时内
        self::TIME_3 => array(0,43200),//半天以内
        self::TIME_4 => array(0,86400),//一天以内
        self::TIME_5 => 86400,//一天以外
    ];

    public static $timeArrMsg = [
        self::TIME_1 => "半小时内",
        self::TIME_2 => "1小时内",
        self::TIME_3 => "半天以内",
        self::TIME_4 => "一天以内",
        self::TIME_5 => "一天以外"
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

    public function asset() {
        return $this->hasOne("App\Models\Assets\Device", "id", "asset_id")->withDefault();
    }

    public function suspend(){
        return $this->hasMany("App\Models\Workflow\EventsSuspend","event_id","id")->withTrashed()->where('events_suspend.etype',EventsSuspend::ETYPE);
    }

}