<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class UsersPreferences extends Model
{

    public $timestamps = false;

    protected $fillable = [
        "uid",
        "assets_fields"
    ];

}