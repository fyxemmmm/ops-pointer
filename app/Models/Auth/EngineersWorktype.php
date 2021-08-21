<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class EngineersWorktype extends Model
{

    public $timestamps = false;

    public $table = "engineers_worktype";

    protected $fillable = [
        "engineer_id",
        "category_id"
    ];


    public function category() {
        return $this->hasOne("App\Models\Assets\Category", "id", "category_id")->withDefault();
    }

    public function engineer() {
        return $this->hasOne("App\Models\Auth\Engineer", "id", "engineer_id")->withDefault();
    }

}