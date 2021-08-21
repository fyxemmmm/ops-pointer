<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class Building extends AbstractDict implements FieldsInterface
{
    use SoftDeletes;

    protected $cache = [];

    protected $table = "assets_building";

    protected $fillable = [
        "name",
        "zone_id",
    ];

    public function zone() {
        return $this->hasOne("App\Models\Assets\Zone", "id", "zone_id")->withDefault();
    }

    public function enginerooms() {
        return $this->hasMany("App\Models\Assets\Engineroom","building_id");
    }

    public function departments() {
        return $this->hasMany("App\Models\Assets\Department","building_id");
    }


    public function getDict($field = "") {
        $data = $this->get();
        $options = [];
        foreach($data as $v) {
            $options[] = [
                "text" => $v->name,
                "value" => $v->id,
                "zoneId" => $v->zone_id
            ];
        }
        return $options;
    }

    public function getRelatedFields() {
        //数据库依赖字段表哪个字段
        return ["zone_id" => \ConstInc::ZONE];
    }


}
