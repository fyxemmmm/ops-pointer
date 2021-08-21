<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/19
 * Time: 15:00
 */


namespace App\Models\Home;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use SoftDeletes;

    protected $table = "feedback";

    protected $fillable = [
        "content",
        "user_id",
        "state",
    ];


}