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

class Multievent extends Model
{
    use SoftDeletes;

    protected $table = "workflow_multievents";

    protected $fillable = [
        "event_id",
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
        "is_comment"
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

    public static $stateMsg = [
        self::STATE_WAIT =>     '待处理',
        self::STATE_ACCESS =>   '已接单',
        self::STATE_ING =>      '处理中',
        self::STATE_END =>      '已完成',
        self::STATE_CLOSE =>    '已关闭',
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

}