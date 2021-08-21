<?php
/**
 * 巡检报告
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/13
 * Time: 16:20
 */

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class Inspection extends Model
{
    use SoftDeletes;

    protected $table = "inspection";

    protected $fillable = [
        "asset_id",
        "device_id",
        "device_type",
        "mtype",
        "report_date",
        "content",
        "cpu_data",
        "memory_data",
        "disk_data",
        "port_data",
        "template_id",
        "is_template",
    ];


    const CNE = 1;  //网络设备
    const CSE = 2;  //安全设备
    const CSED = 3; //服务器
    const CSD = 4;  //存储

    //大分类
    public static $categoryMsg = [
        self::CNE => "网络设备",
        self::CSE => "安全设备",
        self::CSED => "服务器",
        self::CSD => "存储"
    ];



}
