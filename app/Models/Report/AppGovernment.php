<?php

namespace App\Models\Report;

use Illuminate\Database\Eloquent\Model;

class AppGovernment extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public $table = "report_government_situation";

}
