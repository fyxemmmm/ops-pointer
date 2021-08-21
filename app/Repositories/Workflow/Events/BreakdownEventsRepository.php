<?php
/**
 * @description 删除报废事件
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/3/30
 * Time: 17:48
 */

namespace App\Repositories\Workflow\Events;

use App\Models\Workflow\Category;
use App\Exceptions\ApiException;
use App\Models\Code;

class BreakdownEventsRepository extends BaseEventsRepository
{

    const EVT_CATEGORY = Category::BREAKDOWN;

    public function add($assetId) {

    }

    public function prepareSaveDraft($data) {
        return null;
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
                $update[$key] = $data[$key];
            }
        }

        //检查该类别的内容
        $categoryRequire = $this->categoryRepository->getCategoryRequire($device->sub_category_id, self::EVT_CATEGORY);
        foreach($categoryRequire as $sname => $v) {
            if($v['require'] === 1 && $update[$sname] === "") {
                throw new ApiException(Code::ERR_EVENT_FIELD_REQUIRE, [$v['cname']]);
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
        $deviceDraft = $this->eventDeviceModel->getByEventId($eventId);
        $data = [];
        $this->getDeviceName($device, $data, false);

        //$item = $this->getItem($device->sub_category_id, $device, self::EVT_CATEGORY);

        $data['lastUpdateTime'] = $event->updated_at->format('Y-m-d H:i:s');
        $data['info'] = null;

        return $data;
    }

}