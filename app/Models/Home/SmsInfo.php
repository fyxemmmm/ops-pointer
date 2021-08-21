<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/4
 * Time: 19:00
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsInfo extends Model
{
    use SoftDeletes;

    protected $table = "sms_info";

    protected $fillable = [
        "phone",
        "code",
        "send_id",
        "error"
    ];


}