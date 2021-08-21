<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class Layout extends AbstractDict implements FieldsInterface
{
    use SoftDeletes;

    protected $cache = [];

    protected $table = "map_layout";

    protected $fillable = [
        "name",'content',
    ];


}
