<?php
/**
 * 当前监控告警
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/7/18
 * Time: 16:30
 */
namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class MonitorCurrentAlert extends Model
{
    use SoftDeletes;

    protected $table = "monitor_current_alert";

    protected $fillable = [
        "alert_id",
        "current_value",
        "content",
        "previous_status",
        "level",
        "event_id",
        "action_id",
        "triggered_at",
        "managed_id",
        "sequence_id",
        "status"
    ];


    public function asset() {
        return $this->belongsToMany("App\Models\Assets\Device","assets_monitor","device_id","asset_id", "managed_id");
    }

    public function link() {
        return $this->hasOne("App\Models\Monitor\Links", "link_id", "managed_id")->withDefault();
    }

}
