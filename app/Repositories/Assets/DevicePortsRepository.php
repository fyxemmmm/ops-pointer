<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\DevicePorts;
use App\Models\Workflow\DevicePorts as WorkDevicePorts;
use App\Models\Assets\Device;
use App\Repositories\Workflow\EventsRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use DB;

class DevicePortsRepository extends BaseRepository
{

    protected $deviceRepository;

    public function __construct(DevicePorts $devicePorts,
                                DeviceRepository $deviceRepository,
                                WorkDevicePorts $workDevicePorts,
                                EventsRepository $eventsRepository
    )
    {
        $this->model = $devicePorts;
        $this->workDevicePorts = $workDevicePorts;
        $this->deviceRepository = $deviceRepository;
        $this->eventsRepository = $eventsRepository;
    }

    public function checkDevicePort($assetId, $port, $isLocal = false) {
        $device = $this->deviceRepository->getById($assetId);

        if($isLocal) {
            //检查设备状态，本地的允许是闲置，因为可能是上架操作
            if($device->state === Device::STATE_DOWN) {
                throw new ApiException(Code::ERR_ASSETS_BREAK);
            }
        }
        else {
            if($device->state === Device::STATE_DOWN || $device->state === Device::STATE_FREE) {
                throw new ApiException(Code::ERR_ASSETS_BREAK);
            }
        }

        //检查端口存在
        if($device->type == DevicePorts::EPORT && (empty($device->eport) || $device->eport < $port)) {
            throw new ApiException(Code::ERR_ASSETS_TPORT_EMPTY);
        }

        //检查端口存在
        if($device->type == DevicePorts::CPORT && (empty($device->cport) || $device->cport < $port)) {
            throw new ApiException(Code::ERR_ASSETS_CPORT_EMPTY);
        }

        return $device;
    }


    /**
     * 网口连接，包括新增和变更
     * @param $input
     * @return bool
     * @throws \Exception
     */
    public function connect($input, $isDraft = true) {
        if($input['assetId'] == $input['remoteAssetId']) {
            throw new ApiException(Code::ERR_ASSETS_PORT_SAME);
        }

        $this->checkDevicePort($input['assetId'], $input['port'],true);
        $this->checkDevicePort($input['remoteAssetId'], $input['remotePort']);


        if($isDraft) {
            $this->model = $this->workDevicePorts;
        }

        DB::beginTransaction();

        $where = ["asset_id" => $input['assetId'], "type" => $input['type'], "port" => $input['port']];
        if($isDraft) {
            $where['event_id'] = $input["eventId"];
        }

        //检查本地端口占用
        $local = $this->model->where($where)->first();
        if(empty($local)) {
            $insert = [
                "asset_id" => $input['assetId'],
                "type" => $input['type'],
                "port" => $input['port'],
                "ip" => isset($input['ip'])?$input['ip']:null,
                "remark"=> isset($input['remark'])?$input['remark']:"",
                "remote_port_id" => 0,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ];
            if($isDraft) {
                $insert['event_id'] = $input["eventId"];
            }
            $insertId = $this->model->insertGetId($insert);
        }
        else {
            //更新
            $update = [
                "asset_id" => $input['assetId'],
                "type" => $input['type'],
                "port" => $input['port'],
                "remark"=> $input['remark'],
                "ip" => isset($input['ip'])?$input['ip']:null,
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
        $where = ["asset_id" => $input['remoteAssetId'], "type" => $input['type'], "port" => $input['remotePort']];
        if($isDraft) {
            $where['event_id'] = $input["eventId"];
        }
        $remote = $this->model->where($where)->count();
        if(0 !== $remote) {
            throw new ApiException(Code::ERR_ASSETS_PORT_USE);
        }

        $insert = [
            "asset_id" => $input['remoteAssetId'],
            "type" => $input['type'],
            "port" => $input['remotePort'],
            "remark"=> isset($input['remark'])?$input['remark']:"",
            "remote_port_id" => $insertId,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        ];
        if($isDraft) {
            $insert['event_id'] = $input["eventId"];
        }
        $insertId2 = $this->model->insertGetId($insert);

        $this->model->findOrFail($insertId)->update(["remote_port_id" => $insertId2]);
        DB::commit();
        if(!$isDraft) {
            userlog("进行网络点位变更，连接新端口。本地资产id：".$input['assetId']. "本地端口号：".$input['port']." 远端资产id：".$input['remoteAssetId']." 远端端口号：".$input['remotePort']);
        }
    }

    public function disconnect($input, $isDraft = true) {
        $this->checkDevicePort($input['assetId'], $input['port'], true);

        $where = ["asset_id" => $input['assetId'], "type" => $input['type'], "port" => $input['port']];
        if($isDraft) {
            $this->model = $this->workDevicePorts;
            $where['event_id'] = $input['eventId'];
        }

        $local = $this->model->where($where)->first();
        DB::beginTransaction();
        $this->del($local->id);
        $remoteId = $local->remote_port_id;
        $this->del($remoteId);
        DB::commit();

        if(!$isDraft) {
            userlog("进行网络点位变更，断开端口。本地资产id：".$input['assetId']. "本地端口号：".$input['port']);
        }
    }

    public function disconnectAll($assetId) {
        $this->deviceRepository->getById($assetId);
        $where = ["asset_id" => $assetId];

        $result = $this->model->where($where)->get();
        DB::beginTransaction();
        foreach($result as $local) {
            $this->del($local->id);
            $remoteId = $local->remote_port_id;
            $this->del($remoteId);
        }
        DB::commit();
    }

    public function get($input, $isDraft = true) {
        $assetId = $input["assetId"];
        $type = isset($input["type"])?$input["type"]:null;
        $eventId = isset($input["eventId"])?$input["eventId"]:null;
        //不显示闲置与报废的设备
        $device = $this->deviceRepository->getById($assetId);

        if($isDraft) {
            $event = $this->eventsRepository->getById($eventId);
            if($assetId != $event->asset_id) {
                //不显示闲置与报废的设备
                if(in_array($device->state, [Device::STATE_FREE, Device::STATE_DOWN])) {
                    return [];
                }
            }
        }
        else {
            if(in_array($device->state, [Device::STATE_FREE, Device::STATE_DOWN])) {
                return [];
            }
        }

        $portList = [];
        if($isDraft) {
            $portList = $this->workDevicePorts->getList($eventId, $assetId, $type)->groupBy("type");
        }
        $realPortList = $this->model->getList($assetId, $type)->groupBy("type");

        $eportList = [];
        $cportList = [];
        $realEportList = [];
        $realCportList = [];
        if(!empty($portList)) {
            foreach($portList as $k => $value) {
                if($k === DevicePorts::EPORT) {
                    $eportList = $value->keyBy("port")->toArray();
                }
                if($k === DevicePorts::CPORT) {
                    $cportList = $value->keyBy("port")->toArray();
                }
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