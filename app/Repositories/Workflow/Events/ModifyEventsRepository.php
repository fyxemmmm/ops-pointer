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
use Auth;
use App\Repositories\Assets\DeviceRepository;
use Log;

class ModifyEventsRepository extends BaseEventsRepository
{

    const EVT_CATEGORY = Category::MODIFY;

    public function add($assetId) {
        $update['state'] = $this->getStateByCategory(Category::MAINTAIN);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $assetId])->update($update);
    }

    # 关闭事件
    public function close($event) {
        $device = $this->deviceModel->findOrFail($event->asset_id);
        $update['state'] = $this->getStateByCategory(Category::UP);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $device->id])->update($update);
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

        $fieldsName = [];
        $update = [];

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            if(array_key_exists($key,$data)) {
                $update[$key] = encryptStr($key,$data[$key]);
            }
            $fieldsName[$key] = $field->field_cname;
        }


        //检查该类别的内容
        $categoryRequire = $this->categoryRepository->getCategoryRequire($device->sub_category_id, self::EVT_CATEGORY);

        foreach($categoryRequire as $sname => $v) {
            if(1 === $v['require'] && !isset($update[$sname])){
                throw new ApiException(Code::ERR_MUST_FILL_FILED);
            }
//            if(array_key_exists($sname,$update)) {
//                if ($v['require'] === 1 && !$update[$sname]) {
//                    throw new ApiException(Code::ERR_EVENT_FIELD_REQUIRE, [$v['cname']]);
//                }
//            }
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
                $this->assetsDevicePortsRepository->connect($portInput,false);
            }
        }

        if(isset($data['rack']) && isset($data['rack_pos'])) {
            //机柜上架
            $rackData = [
                "rackId" => $data['rack'],
                "position" => $data['rack_pos'],
                "assetId" => $event->asset_id,
                "unit" => isset($data['unit']) ? $data['unit'] : 0
            ];
            $this->deviceRepository->rackUp($rackData);
        }

        $change = [];
        foreach($update as $k => $v){
            if($device->$k != $v) {
                $change[$k] = $v;
            }
        }

        $msg = [];
        if(!empty($change)) {
            $user = Auth::user();

            $msg[] = sprintf("%s\n\n[%s]进行了以下变更，详情如下：", date("Y-m-d H:i:s"), $user->username);
            foreach($change as $k => $v) {
                $from = DeviceRepository::transform($k, $device->$k, $tkey1);
                $to = DeviceRepository::transform($k, $v, $tkey2);
                if(empty($from)) {
                    $from = "无";
                }
                if(empty($to)) {
                    $to = "无";
                }
                $msg[] = sprintf("[%s]从[%s]变更为[%s]", $fieldsName[$k], $from, $to);
            }
        }

        $update['state'] = $this->getStateByCategory(Category::UP);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $device->id])->update($update);

        $event->changes = join("\n", $msg);
        $event->save();
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
        $data['changes'] = $event->changes; //设备U数

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