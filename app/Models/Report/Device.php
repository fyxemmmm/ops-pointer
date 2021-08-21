<?php

namespace App\Models\Report;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title','desc', 'config', "is_default"
    ];

    public $table = "report_device";

}
