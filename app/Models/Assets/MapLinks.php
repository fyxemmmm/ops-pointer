<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class MapLinks extends AbstractDict implements FieldsInterface
{
    protected $cache = [];

    protected $table = "map_links";

    protected $fillable = [
        "id",'source_mrc_id','source_asset_id','mlinks_id',
    ];

    public function asset(){
        return $this->hasOne('App\Models\Assets\Device', 'id','source_asset_id')
                    ->select('id', 'number', 'name')
                    ->withDefault();
    }

    public function monitorLink(){
        return $this->hasOne('App\Models\Monitor\Links','id','mlinks_id')
                    ->select('id', 'status')
                    ->withDefault();
    }

    public function sourceMapRegionConfig() {
        return $this->hasOne('App\Models\Assets\MapRegionConfig', 'id','source_mrc_id')
            ->withDefault();
    }

    public function destMapRegionConfig() {
        return $this->hasOne('App\Models\Assets\MapRegionConfig', 'id','dest_mrc_id')
            ->withDefault();
    }


}
