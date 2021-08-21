<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/7/27
 * Time: 17:30
 */


namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventsSuspend extends Model
{
    use SoftDeletes;

    protected $table = "events_suspend";

    protected $fillable = [
        "event_id",
        "etype",
        "estate",
        "content",
        "start_at",
        "end_at",
        "usetime",
    ];

    const ETYPE = 0;
    const OATYPE = 1;

    public static $stateMsg = [
        self::ETYPE     => '事件',
        self::OATYPE    => 'OA事件',
    ];

}