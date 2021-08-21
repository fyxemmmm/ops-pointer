<?php
/**
 * 巡检报告
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/13
 * Time: 16:20
 */

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;

class Links extends Model
{
    protected $table = "monitor_links";

    protected $fillable = [
        "link_id",
        "number",
        "name",
        "remark",
        "level",
        "source_asset_id",
        "source_port_id",
        "source_port_desc",
        "dest_asset_id",
        "dest_port_id",
        "dest_port_desc",
        "forward",
        "from_based",
        "custom_speed_up",
        "custom_speed_down",
        "custom_speed_up_limit",
        "custom_speed_down_limit",
        "status"
    ];

    public function sourceAssetsMonitor() {
        return $this->hasOne("App\Models\Monitor\AssetsMonitor", "asset_id", "source_asset_id")->withDefault();
    }

    public function destAssetsMonitor() {
        return $this->hasOne("App\Models\Monitor\AssetsMonitor", "asset_id", "dest_asset_id")->withDefault();
    }
}
