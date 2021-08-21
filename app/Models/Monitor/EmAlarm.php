<?php

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class EmAlarm extends Model
{

    protected $table = "em_alarm";

    protected $fillable = [
        "alarm_id",
        "point_id",
        "levels",
        "device_id",
        "device_name",
        "times",
        "aname",
        "avalue",
        "dw",
        "state",
    ];



}
