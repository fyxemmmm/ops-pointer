<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class UserIdentity extends Model
{

    public $table = "users_identities";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'identity','remark'
    ];
}
