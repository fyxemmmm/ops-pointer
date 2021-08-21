<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/3/30
 * Time: 17:48
 */

namespace App\Repositories\Workflow\Events;

use App\Models\Workflow\Category;
use App\Exceptions\ApiException;
use App\Models\Code;

class UpEventsRepository extends BaseEventsRepository
{

    const EVT_CATEGORY = Category::UP;

    public function add($assetId) {

    }

    public function prepareSaveDraft($data) {
        $event = $this->getEvent($data['eventId']);
        $assetId = $event->asset_id;
        $device = $this->deviceModel->findOrFail($assetId);

        //取该类别字段
        $fields = $this->getFields($device->sub_category_id);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            if(isset($data[$key])) {
                $insert[$key] = encryptStr($key,$data[$key]);
            }
        }

        $insert['sub_category_id'] = $device->sub_category_id;
        $insert['category_id'] = $device->category_id;
        $insert["updated_at"] = date("Y-m-d H:i:s");
        return $insert;
    }

    public function save($data) {
        $eventId = $data['eventId'];
        $event = $this->getEvent($eventId);
        $device = $this->deviceModel->findOrFail($event->asset_id);

        //取该类别字段
        $fields = $this->getFields($device->sub_category_id);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            if(isset($data[$key])) {
                $update[$key] = encryptStr($key,$data[$key]);
            }
        }
        //检查该类别的内容
        $categoryRequire = $this->categoryRepository->getCategoryRequire($device->sub_category_id, self::EVT_CATEGORY);
        foreach($categoryRequire as $sname => $v) {
            if ($v['require'] === 1 && !isset($update[$sname])) {
                throw new ApiException(Code::ERR_EVENT_FIELD_REQUIRE, [$v['cname']]);
            }

        }

        //网络连接
        $input = [
            "eventId" => $eventId,
            "assetId" => $event->asset_id
        ];
        $ports = $this->assetsDevicePortsRepository->get($input);
        foreach($ports as $port) {
            if(!empty($port['use'])) {
                $portInput = [
                    "assetId" => $port['use']->asset_id,
                    "type" => $port['use']->type,
                    "port" => $port['use']->port,
                    "ip" => $port['use']->ip,
                    "remoteAssetId" => $port['use']->remote_asset_id,
                    "remotePort" => $port['use']->remote_port,
                    "remark" => $port['use']->remark
                ];
                $this->assetsDevicePortsRepository->connect($portInput, false);
            }
        }

        if(!$this->deviceRepository->isRack($event->asset_id)) {
            //机柜上架
            if(isset($data['rack']) && isset($data['rack_pos'])) { //有机柜和机柜位置，则上架
                $rackData = [
                    "rackId" => $data['rack'],
                    "position" => $data['rack_pos'],
                    "assetId" => $event->asset_id,
                    "unit" => isset($data['unit']) ? $data['unit'] : 0 //允许在上架时才写设备U数
                ];
                $this->deviceRepository->rackUp($rackData);
            }
        }

        $update['state'] = $this->getStateByCategory(self::EVT_CATEGORY);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $device->id])->update($update);
    }

    public function process($event) {
        $eventId = $event->id;
        $assetId = $event->asset_id;
        $device = $this->deviceModel->findOrFail($assetId);
        if(empty($event->event_id)) {
            //批量事件
            $deviceDraft = $this->eventDeviceModel->getByEventId($eventId);
        }
        else {
            $deviceDraft = $this->multiDeviceModel->getByEventId($eventId);
        }

        $this->getDeviceName($device, $data);
        $data['unit'] = $device->unit; //设备U数

        $item = $this->getItem($device->sub_category_id, $device, self::EVT_CATEGORY);
        $data['lastUpdateTime'] = null;

        if(!empty($deviceDraft)) { //无记录，先提供资产分类数据
            $itemDraft = $this->getItem($deviceDraft->sub_category_id, $deviceDraft, self::EVT_CATEGORY);
            foreach($item as $k => &$v) {
                foreach($v['children'] as $kk => &$vv) {
                    if(!is_null($itemDraft[$k]['children'][$kk]['value'])) {
                        $vv['value'] = $itemDraft[$k]['children'][$kk]['value'];
                    }
                }
            }

            $data['lastUpdateTime'] = $deviceDraft->updated_at->format('Y-m-d H:i:s');
        }
        $data['info'] = $item;

        return $data;
    }

}