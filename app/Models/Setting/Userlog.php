<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;

class Userlog extends Model
{
    protected $fillable = [
        "user_id",
        "desc",
    ];

    public function user() {
        return $this->hasOne("App\Models\Auth\User", "id", "user_id")->withTrashed()->withDefault();
    }

}