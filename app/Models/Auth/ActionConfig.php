<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class ActionConfig extends Model
{

    protected $table = "action_config";

    protected $fillable = [
        "status",
        "msg",
        "name",
        "desc",
        "type",
        "key",
    ];

    public $timestamps = false;



}
