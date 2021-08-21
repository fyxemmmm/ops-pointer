<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class UsersEngineers extends Model
{

    public $timestamps = false;

    public $table = "users_engineers";

    protected $fillable = [
        "user_id",
        "engineer_id"
    ];


    public function user() {
        return $this->hasOne("App\Models\Auth\User", "id", "user_id")->withDefault();
    }

    public function engineer() {
        return $this->hasOne("App\Models\Auth\Engineer", "id", "engineer_id")->withDefault();
    }

}