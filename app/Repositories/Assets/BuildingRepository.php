<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Building;
use App\Models\Code;

class BuildingRepository extends BaseRepository
{

    public function __construct(Building $model)
    {
        $this->model = $model;
    }

    public function checkBuildingZone($buildingId, $zoneId) {
        if(!empty($buildingId)) {
            $result = $this->getById($buildingId);
            return $result->zone_id == $zoneId;
        }
        return true;
    }

    public function delete($buildingId) {
        //删除楼之前确认是否有被使用
        $result = $this->getById($buildingId);
        if($result->enginerooms->count() > 0) {
            Code::setCode(Code::ERR_ROOM_NOT_EMPTY);
            return false;
        }

        if($result->departments->count() > 0) {
            Code::setCode(Code::ERR_DPT_NOT_EMPTY);
            return false;
        }

        $this->del($buildingId);
        return true;
    }




}