<?php

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class EmRealtimeData extends Model
{
    use SoftDeletes;

    protected $table = "em_realtime_data";

    protected $fillable = [
        "var_name",
        "value",
        "high_value",
        "min_value",
        "max_value",
        "low_value",
    ];



}
