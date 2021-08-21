<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Zone;
use App\Models\Code;

class ZoneRepository extends BaseRepository
{

    public function __construct(Zone $model)
    {
        $this->model = $model;
    }

    public function delete($zoneId) {
        //删除地区之前确认是否有被使用
        $result = $this->getById($zoneId);
        if($result->buildings->count() > 0) {
            Code::setCode(Code::ERR_BUILDING_NOT_EMPTY);
            return false;
        }

        if($result->enginerooms->count() > 0) {
            Code::setCode(Code::ERR_ROOM_NOT_EMPTY);
            return false;
        }

        if($result->departments->count() > 0) {
            Code::setCode(Code::ERR_DPT_NOT_EMPTY);
            return false;
        }

        $this->del($zoneId);
        return true;
    }

    public function getAllId(){
        $ids = $this->model->get()->pluck('id');
        return $ids;
    }


}