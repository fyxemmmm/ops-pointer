<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

class Wrong extends Model
{

    protected $table = "assets_wrong";

    protected $fillable = [
        "name",
        "remark",
    ];

}
