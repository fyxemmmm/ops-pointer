<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class MapRegionConfig extends AbstractDict implements FieldsInterface
{
    protected $cache = [];

    protected $table = "map_region_config";

    protected $fillable = [
        "mr_id",'er_id','px','py',
    ];

    public function mapLinks() {

        return $this->hasOne("App\Models\Assets\MapLinks","mrc_id", "id")->withDefault();
    }

    public function mapRegion(){
        return $this->hasOne('App\Models\Assets\MapRegion', 'id','mr_id')->withDefault();
    }

    public function enginerooms() {
        return $this->hasOne("App\Models\Assets\Engineroom","id","er_id")->withDefault();
    }

}
