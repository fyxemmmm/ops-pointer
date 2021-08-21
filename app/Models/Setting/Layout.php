<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models\Setting;

use Illuminate\Database\Eloquent\Model;

class Layout extends Model
{

    protected $table = "layout";

    protected $fillable = [
        "name",
        "content",
        "is_default",
    ];

}