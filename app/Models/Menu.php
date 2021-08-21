<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/5
 * Time: 12:02
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Menu extends Model
{
    protected $fillable = [
        "pid",
        "name",
        "path",
        "icon",
        "category_id",
        "status"
    ];

    public $timestamps = false;

    public function getList($pids = []) {
        if(empty($pids)) {
            return $this->where("pid","=",0)->where("status",1)->get();
        }
        else {
            return $this->whereIn("pid",$pids)->where("status",1)->get();
        }
    }

    public function category() {
        return $this->hasOne("App\Models\Assets\Category", "id", "category_id")->withDefault();
    }

    public function getParentName($pid){
        // 父级 id 有可能为 0 ，若为 0 查询语句会报错
        if(empty($pid)) return;
        $data = $this->where("id","=",$pid)->first();
        return $data['name'];
    }



}