<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{

    use SoftDeletes;

    protected $table = "inventory";

    protected $fillable = [
        "number",
        "name",
        "user",
        "desc",
        "status",
        "plan_time",
        "result",
        "location_flag"
    ];

    const STATE_START = 0;
    const STATE_DOING = 1;
    const STATE_END = 2;
    const STATE_SCRAP = 3;

    public static $stateMsg = [
        self::STATE_START => "未开始",
        self::STATE_DOING => "盘点中",
        self::STATE_END => "已盘点",
        self::STATE_SCRAP => "已废弃",
    ];


    public function inventoryAsset(){
        return $this->hasMany("App\Models\Inventory\InventoryAsset","inventory_id", "id");
    }

}
