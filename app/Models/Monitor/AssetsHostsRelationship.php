<?php

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class AssetsHostsRelationship extends Model
{
    use SoftDeletes;

    protected $table = "assets_hosts_relationship";

    protected $fillable = [
        "asset_id",
        "zbx_hostid",
        "zbx_status",
        "zbx_available",
    ];

    /*public function fields() {
        return $this->belongsToMany("App\Models\Assets\Fields","assets_category_fields","category_id","field_id");
    }*/


    /**
     * 取id和pid
     * @param $category
     * @return Model|null|static
     */
    /*public function getIdPidByName($category) {
        return DB::table("assets_category as A")->
        join("assets_category as B","A.pid","=","B.id")
            ->where(["A.name" => $category])->select("A.id","A.pid")->first();
    }

    public function getList() {
        return DB::table("assets_category as A")->
        join("assets_category as B","A.pid","=","B.id")
            ->select("A.id","A.name","B.id as pid", "B.name as pname")->get();
    }*/


}
