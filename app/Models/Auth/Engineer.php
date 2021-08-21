<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class Engineer extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name','desc'
    ];

    public function user()
    {
        return $this->belongsToMany("App\Models\Auth\User","users_engineers","engineer_id","user_id")
            ->whereIn("identity_id",[User::USER_MANAGER,User::USER_ENGINEER]);
    }

}
