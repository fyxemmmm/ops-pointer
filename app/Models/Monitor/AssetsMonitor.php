<?php

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class AssetsMonitor extends Model
{
    use SoftDeletes;

    protected $table = "assets_monitor";

    protected $fillable = [
        "asset_id",
        "device_id",
        "status",
        "core",
        "port",
        "ip",
        "cpu_percent",
        "mem_percent",
        "loadavg",
        "disk_percent",
        "device_status",
        "work_days",
        'level',
    ];

    const CORE_SWITCH = 1;
    const CORE_SERVER = 2;
    const CORE_ROUTER = 3;

    const CORE_ARR = [
        self::CORE_SWITCH => '核心交换机',
        self::CORE_SERVER => '核心服务器',
        self::CORE_ROUTER => '出口路由器'
    ];

    public function asset() {
        return $this->hasOne("App\Models\Assets\Device", "id", "asset_id")->withDefault();
    }


}
