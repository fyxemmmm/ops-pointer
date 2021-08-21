<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class Engineroom extends AbstractDict implements FieldsInterface
{
    //
    use SoftDeletes;

    protected $cache = [];

    protected $table = "assets_enginerooms";

    protected $fillable = [
        "name",
        "zone_id",
        "building_id",
        "type",
        "address",
    ];

    const TYPE_FIRST = 1;
    const TYPE_SECOND = 2;
    const TYPE_THIRD = 3;

    const ENGINEROOM_TYPES = [
        self::TYPE_FIRST => "一级机房",
        self::TYPE_SECOND => "二级机房",
        self::TYPE_THIRD => "三级机房",
    ];

    public function zone() {
        return $this->hasOne("App\Models\Assets\Zone", "id", "zone_id")->withDefault();
    }

    public function building() {
        return $this->hasOne("App\Models\Assets\Building", "id", "building_id")->withDefault();
    }


    public function getDict($field = "") {
        $data = $this->get();
        $options = [];
        foreach($data as $v) {
            $options[] = [
                "text" => $v->name,
                "value" => $v->id,
                "zoneId" => $v->zone_id,
                "buildingId" => $v->building_id
            ];
        }
        return $options;
    }

    public function getRelatedFields() {
        return [
            "zone_id" => \ConstInc::ZONE,
            "building_id" => \ConstInc::BUILDING,
        ];
    }

}
