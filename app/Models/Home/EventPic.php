<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/3
 * Time: 18:24
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventPic extends Model
{
    use SoftDeletes;

    protected $table = "events_pic";

    protected $fillable = [
        "event_id",
        "src",
        "etype",//0:事件，1：OA事件
    ];


}