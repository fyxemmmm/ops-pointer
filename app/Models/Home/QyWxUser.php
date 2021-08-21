<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/10/10
 * Time: 18:00
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QyWxUser extends Model
{
    use SoftDeletes;

    protected $table = "qywx_user";

    protected $fillable = [
        "user_id",//users表中id
        "userid",//成员UserID
        "name",//成员姓名
        "email",//成员邮箱
        "mobile",//成员手机号
        "avatar",//用户头像
        "gender",//性别。0表示未定义，1表示男性，2表示女性
        "qr_code",//员工个人二维码
    ];



}