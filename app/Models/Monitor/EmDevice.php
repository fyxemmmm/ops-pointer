<?php

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class EmDevice extends Model
{
    use SoftDeletes;

    protected $table = "em_device";

    protected $fillable = [
        "device_id",
        "name",
        "var_name",
        "asset_id",
        "device_type",
        "model",
        "notes"
    ];



}
