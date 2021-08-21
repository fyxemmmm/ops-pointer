<?php
/**
 * 环控巡检报告
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/9/13
 * Time: 10:45
 */

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class EmInspection extends Model
{
    use SoftDeletes;

    protected $table = "em_inspection";

    protected $fillable = [
        "template_id",
        "report_date",
        "content",
    ];



}
