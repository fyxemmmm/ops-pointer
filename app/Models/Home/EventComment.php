<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/10
 * Time: 10:20
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventComment extends Model
{
    use SoftDeletes;

    protected $table = "events_comment";

    protected $fillable = [
        "event_id",
        "content",//评价内容
        "feedback",//意见反馈
        "star_level",//星级别1,2,3,4,5
        "user_id",
        "etype",//0:事件，1：OA事件
    ];


}