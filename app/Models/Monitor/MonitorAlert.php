<?php
/**
 * 监控告警
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/27
 * Time: 17:15
 */
namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
//use DB;

class MonitorAlert extends Model
{
    use SoftDeletes;

    protected $table = "monitor_alert";

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

    public static $levelMsg = [
        "1" => "提示级",
        "2" => "低级",
        "3" => "中级",
        "4" => "高级",
        "5" => "紧急级",
    ];

    public static $statusMsg = [
        '0'=>'已恢复',
        '1'=>'异常',
    ];

    public function asset() {
        return $this->belongsToMany("App\Models\Assets\Device","assets_monitor","device_id","asset_id", "managed_id");
    }

    public function link() {
        return $this->hasOne("App\Models\Monitor\Links", "link_id", "managed_id")->withDefault();
    }
}
