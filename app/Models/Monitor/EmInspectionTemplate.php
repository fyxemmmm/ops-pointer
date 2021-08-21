<?php
/**
 * 巡检报告模板
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/9/5
 * Time: 16:30
 */

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class EmInspectionTemplate extends Model
{
    use SoftDeletes;

    protected $table = "em_inspection_template";

    protected $fillable = [
        "it_name",
        "it_desc",
        "it_type",
        "content",
        "mcontent",
        "report_dates",
        "is_default"
    ];

    //模板属性
    const IS_DEFAULT = 0;
    const IS_MONITOR = 1;
    const IS_ENVIRONMENT = 2;



}
