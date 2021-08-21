<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class Zone extends AbstractDict implements FieldsInterface
{
    use SoftDeletes;

    protected $cache = [];

    protected $table = "assets_zone";

    protected $fillable = [
        "name",
    ];

    public function buildings() {
        return $this->hasMany("App\Models\Assets\Building","zone_id");
    }

    public function enginerooms() {
        return $this->hasMany("App\Models\Assets\Engineroom","zone_id");
    }

    public function departments() {
        return $this->hasMany("App\Models\Assets\Department","zone_id");
    }


}
