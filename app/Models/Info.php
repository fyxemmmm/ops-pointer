<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Info extends Model
{
    protected $fillable = [
        "name",
        "value",
        "remark"
    ];

    public function getByName($name) {
        return $this->where(["name" => $name])->first()->value;
    }

}