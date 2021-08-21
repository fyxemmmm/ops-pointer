<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use DB;

class DevicePorts extends Model
{

    protected $table = "workflow_device_ports";

    protected $fillable = [
        "event_id",
        "asset_id",
        "type",
        "port",
        "remote_port_id",
        "remark",
    ];

    const EPORT = 0; //电口
    const CPORT = 1; //光口

    public function getList($eventId, $assetId, $type = null) {
        if(!is_null($type)) {
            $where = ["A.event_id"=> $eventId, "A.asset_id" => $assetId, "A.type" => $type];
        }
        else {
            $where = ["A.event_id"=> $eventId,"A.asset_id" => $assetId];
        }

        return DB::table("workflow_device_ports as A")->
        join("workflow_device_ports as B","A.remote_port_id","=","B.id")
            ->join("assets_device as C","B.asset_id", "=", "C.id")
            ->where($where)
            ->select("A.id","A.asset_id","A.type","A.port","A.ip","B.asset_id as remote_asset_id", "B.type as remote_type","B.port as remote_port","A.remark","C.number","C.name")->get();
    }
}
