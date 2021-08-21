<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;


class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name','username', 'email', 'password', 'phone', 'identity_id','wxid','telephone','db'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    const USER_ADMIN = 1;
    const USER_MANAGER = 2;
    const USER_ENGINEER = 3;
    const USER_NORMAL = 4;
    const USER_LEADER = 5;
    const USER_SYSADMIN = 6;

    const ADMIN_ID = 1;
    const SYSADMIN_ID = 10000;

    public function identity() {
        return $this->hasOne("App\Models\Auth\UserIdentity", "id", "identity_id")->withDefault();
    }

}
