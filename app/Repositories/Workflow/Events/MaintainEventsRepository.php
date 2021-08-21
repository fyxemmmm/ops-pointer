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

class MaintainEventsRepository extends BaseEventsRepository
{

    const EVT_CATEGORY = Category::MAINTAIN;

    public function add($assetId) {
        $update['state'] = $this->getStateByCategory(self::EVT_CATEGORY);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $assetId])->update($update);
    }

    public function prepareSaveDraft($data) {
        $maintain = $this->maintainModel->getByEventId($data['eventId']);
        $wrongId = isset($data['wrongId'])?$data['wrongId']:null;
        $solutionId = isset($data['solutionId'])?$data['solutionId']:null;
        $wrongDesc = isset($data['wrongDesc'])?$data['wrongDesc']:null;
        $solutionDesc = isset($data['solutionDesc'])?$data['solutionDesc']:null;

        if(is_null($wrongId) && is_null($solutionId) && is_null($wrongDesc) && is_null($solutionDesc)) {
            return null;
        }

        if(empty($maintain)) {
            $insert = [
                "event_id" => $data['eventId'],
                "wrong_id" => $wrongId,
                "solution_id" => $solutionId,
                "wrong_desc" => $wrongDesc,
                "solution_desc" => $solutionDesc,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ];
            $this->maintainModel->insert($insert);
        }
        else {
            $update = [
                "wrong_id" => $wrongId,
                "solution_id" => $solutionId,
                "wrong_desc" => $wrongDesc,
                "solution_desc" => $solutionDesc,
                "updated_at" => date("Y-m-d H:i:s")
            ];
            $maintain->fill($update)->save();
        }

        return null;
    }

    public function save($data) {
        $eventId = $data['eventId'];
        $event = $this->getEvent($eventId);
        $device = $this->deviceModel->findOrFail($event->asset_id);
        $wrongId = isset($data['wrongId'])?$data['wrongId']:null;
        $solutionId = isset($data['solutionId'])?$data['solutionId']:null;
        $wrongDesc = isset($data['wrongDesc'])?$data['wrongDesc']:null;
        $solutionDesc = isset($data['solutionDesc'])?$data['solutionDesc']:null;

        if(is_null($wrongId) || is_null($solutionId)) {
            throw new ApiException(Code::ERR_PARAMS, ["请选择异常原因和解决办法"]);
        }

        $maintain = $this->maintainModel->getByEventId($eventId);
        if(empty($maintain)) {
            $insert = [
                "event_id" => $data['eventId'],
                "wrong_id" => $wrongId,
                "solution_id" => $solutionId,
                "wrong_desc" => $wrongDesc,
                "solution_desc" => $solutionDesc,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ];
            $this->maintainModel->insert($insert);
        }
        else {
            $update = [
                "wrong_id" => $wrongId,
                "solution_id" => $solutionId,
                "wrong_desc" => $wrongDesc,
                "solution_desc" => $solutionDesc,
                "updated_at" => date("Y-m-d H:i:s")
            ];
            $maintain->fill($update)->save();
            $update = [];
        }
        $update['state'] = $this->getStateByCategory(Category::UP);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $device->id])->update($update);
    }

    public function close($event) {
        $device = $this->deviceModel->findOrFail($event->asset_id);
        $update['state'] = $this->getStateByCategory(Category::UP);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $device->id])->update($update);
    }

    public function process($event) {
        $eventId = $event->id;
        $assetId = $event->asset_id;
        $device = $this->deviceModel->find($assetId);
        $maintain = $this->maintainModel->getByEventId($eventId);
        if(!empty($maintain)) {
            $data['info'] = $maintain->toArray();
        }
        else {
            $data['info'] = null;
        }
        $wrongs = $this->maintainModel->getWrongs();
        $solutions = $this->maintainModel->getSolutions();

        $data["wrongs"] = $wrongs;
        $data["solutions"] = $solutions;
        $this->getDeviceName($device, $data, false);

        //$item = $this->getItem($device->sub_category_id, $device, self::EVT_CATEGORY);

        $data['lastUpdateTime'] = $event->updated_at->format('Y-m-d H:i:s');

        return $data;
    }

}