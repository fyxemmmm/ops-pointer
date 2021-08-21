<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Workflow;

use App\Repositories\BaseRepository;
use App\Models\Workflow\DevicePorts;
use App\Models\Assets\DevicePorts as AssetsDevicePorts;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Assets\DevicePortsRepository as AssetsDevicePortsRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Models\Code;
use App\Models\Assets\Device;
use DB;

class __DevicePortsRepository extends BaseRepository
{

    protected $deviceRepository;

    public function __construct(DevicePorts $devicePorts,
                                AssetsDevicePorts $assetDevicePorts,
                                DeviceRepository $deviceRepository,
                                EventsRepository $eventsRepository,
                                AssetsDevicePortsRepository $assetsDevicePortsRepository
    )
    {
        $this->model = $devicePorts;
        $this->deviceRepository = $deviceRepository;
        $this->assetDevicePorts = $assetDevicePorts;
        $this->eventsRepository = $eventsRepository;
        $this->assetsDevicePortsRepository = $assetsDevicePortsRepository;
    }


    public function checkDevicePort($assetId, $port, $isLocal = false) {
        return $this->assetsDevicePortsRepository->checkDevicePort($assetId, $port, $isLocal);
    }


    /**
     * 网口连接，包括新增和变更
     * @param $input
     * @return bool
     * @throws \Exception
     */
    public function connect($input) {
        if($input['assetId'] == $input['remoteAssetId']) {
            Code::setCode(Code::ERR_ASSETS_PORT_SAME);
            return false;
        }

        $device = $this->checkDevicePort($input['assetId'], $input['port'], true);
        if (false === $device) {
            return false;
        }

        $remoteDevice = $this->checkDevicePort($input['remoteAssetId'], $input['remotePort']);
        if (false === $remoteDevice) {
            return false;
        }

        DB::beginTransaction();

        //检查本地端口占用
        $local = $this->model->where(["event_id" => $input["eventId"], "asset_id" => $input['assetId'], "type" => $input['type'], "port" => $input['port']])->first();
        if(empty($local)) {
            $insert = [
                "asset_id" => $input['assetId'],
                "event_id" => $input['eventId'],
                "type" => $input['type'],
                "port" => $input['port'],
                "ip" => isset($input['ip'])?$input['ip']:null,
                "remark" => $input['remark'],
                "remote_port_id" => 0,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ];
            $insertId = $this->model->insertGetId($insert);
        }
        else {
            //更新
            $update = [
                "asset_id" => $input['assetId'],
                "type" => $input['type'],
                "port" => $input['port'],
                "ip" => isset($input['ip'])?$input['ip']:null,
                "remark" => $input['remark'],
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ];
            $this->update($local->id, $update);
            $insertId = $local->id;

            //释放端口
            $org = $local->remote_port_id;
            $this->del($org);
        }

        //检查远端端口占用
        $remote = $this->model->where(["event_id" => $input["eventId"],"asset_id" => $input['remoteAssetId'], "type" => $input['type'], "port" => $input['remotePort']])->count();
        if(0 !== $remote) {
            Code::setCode(Code::ERR_ASSETS_PORT_USE);
            return false;
        }

        $insert = [
            "asset_id" => $input['remoteAssetId'],
            "event_id" => $input['eventId'],
            "type" => $input['type'],
            "port" => $input['remotePort'],
            "remote_port_id" => $insertId,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        ];
        $insertId2 = $this->model->insertGetId($insert);

        $this->model->findOrFail($insertId)->update(["remote_port_id" => $insertId2]);
        DB::commit();
    }

    public function disconnect($input) {
        $device = $this->checkDevicePort($input['assetId'], $input['port']);
        if (false === $device) {
            return false;
        }

        $local = $this->model->where(["event_id" => $input["eventId"], "asset_id" => $input['assetId'], "type" => $input['type'], "port" => $input['port']])->first();
        DB::beginTransaction();
        $this->del($local->id);
        $remoteId = $local->remote_port_id;
        $this->del($remoteId);
        DB::commit();
    }

    public function disconnectAll($eventId, $assetId) {
        $this->deviceRepository->getById($assetId);

        $result = $this->model->where(["event_id" => $eventId,"asset_id" => $assetId])->get();
        DB::beginTransaction();
        foreach($result as $local) {
            $this->del($local->id);
            $remoteId = $local->remote_port_id;
            $this->del($remoteId);
        }
        DB::commit();
    }


    public function get($eventId, $assetId, $type = null) {
        $device = $this->deviceRepository->getById($assetId);

        $event = $this->eventsRepository->getById($eventId);
        if($assetId != $event->asset_id) {
            //不显示闲置与报废的设备
            if(in_array($device->state, [Device::STATE_FREE, Device::STATE_DOWN])) {
                return [];
            }
        }

        $portList = $this->model->getList($eventId, $assetId, $type)->groupBy("type");
        $realPortList = $this->assetDevicePorts->getList($assetId, $type)->groupBy("type");

        $eportList = [];
        $cportList = [];
        $realEportList = [];
        $realCportList = [];
        foreach($portList as $k => $value) {
            if($k === DevicePorts::EPORT) {
                $eportList = $value->keyBy("port")->toArray();
            }
            if($k === DevicePorts::CPORT) {
                $cportList = $value->keyBy("port")->toArray();
            }
        }

        foreach($realPortList as $k => $value) {
            if($k === DevicePorts::EPORT) {
                $realEportList = $value->keyBy("port")->toArray();
            }
            if($k === DevicePorts::CPORT) {
                $realCportList = $value->keyBy("port")->toArray();
            }
        }

        $eportList = $eportList + $realEportList;
        $cportList = $cportList + $realCportList;

        $eport = $device->eport;
        $cport = $device->cport;

        $edata = [];
        for($i = 1; $i <= $eport; $i++) {
            $current = [
                "text" => "电口".$i,
                "value" => $i,
                "use" => isset($eportList[$i])?$eportList[$i]:null
            ];
            $edata[] = $current;
        }

        $cdata = [];
        for($i = 1; $i <= $cport; $i++) {
            $current = [
                "text" => "光口".$i,
                "value" => $i,
                "use" => isset($cportList[$i])?$cportList[$i]:null
            ];
            $cdata[] = $current;
        }


        if(is_null($type)) {
            $data = array_merge($edata, $cdata);
        }
        else {
            if($type == DevicePorts::EPORT) {
                $data = $edata;
            }
            else if ($type == DevicePorts::CPORT){
                $data = $cdata;
            }
        }


        return $data;
    }


}