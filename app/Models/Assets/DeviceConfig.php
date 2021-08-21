<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceConfig extends Model
{
    //
    use SoftDeletes;

    protected $table = "assets_device_config";

    protected $fillable = [
        "asset_id",
        "title",
        "diff",
        "conf",
        "remark",
    ];

    /**
     * 根据资产编号获取
     * @param $number
     * @return mixed
     */
    public function getByNumber($number, $where = null) {
        if (is_array($number)) {
            $obj = $this->whereIn("number" , $number);
        }
        else {
            $obj = $this->where(["number" => $number]);
        }
        if(!empty($where)) {
            $obj = $obj->where($where);
        }
        return $obj->get();
    }

    public function device() {
        return $this->hasOne("App\Models\Assets\Device","id", "asset_id");
    }

}
