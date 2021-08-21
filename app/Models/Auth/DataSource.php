<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;


class DataSource extends Model
{
    public $table = "data_source";

    public function getCondition(){
        return $this->hasMany('App\Models\Auth\DataSourceCondition');
    }

}
