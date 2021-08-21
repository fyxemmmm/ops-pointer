<?php

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class EmMonitorpoint extends Model
{
    use SoftDeletes;

    protected $table = "em_monitor_point";

    protected $fillable = [
        "device_id",
        "device_name",
        "var_id",
        "var_name",
        "var_type",
        "unit",
        "status",
        "remark",
    ];






}
