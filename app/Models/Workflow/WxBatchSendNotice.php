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


class WxBatchSendNotice extends Model
{
//    use SoftDeletes;

    protected $table = "wx_batch_send_notice";

    protected $fillable = [
        "openids",
        "event_id",
        "etype",
        "title",
        "url",
        "description",
        "state",
        "is_send",
    ];


}