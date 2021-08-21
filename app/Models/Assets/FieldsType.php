<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

class FieldsType extends Model
{
    protected $table = "assets_fields_type";

    public function category()
    {
        return $this->belongsToMany("App\Models\Assets\Category","assets_category_fields","category_id","field_id");

    }


}
