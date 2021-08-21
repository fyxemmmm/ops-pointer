<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/12
 * Time: 11:35
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventTrack extends Model
{
    use SoftDeletes;

    protected $table = "events_track";

    protected $fillable = [
        "event_id",
        "description",
        "asset_id",//资产id
        "step",//步骤：1，2，3，4....
        "state",//1：已接单,2：处理中,3：已完成,4：已关闭
        "etype",//0:事件，1：OA事件
    ];


}