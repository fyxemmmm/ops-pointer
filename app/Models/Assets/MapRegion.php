<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

class MapRegion extends Model
{

    protected $table = "map_region";

    protected $fillable = [
        "id",'name',
    ];

}
