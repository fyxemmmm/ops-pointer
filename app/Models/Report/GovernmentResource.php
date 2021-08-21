<?php

namespace App\Models\Report;

use Illuminate\Database\Eloquent\Model;

class GovernmentResource extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public $table = "report_government_resource";

}
