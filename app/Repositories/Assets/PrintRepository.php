<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Exceptions\ApiException;
use App\Repositories\BaseRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Models\Assets\Device;
use App\Models\Code;
use Log;

class PrintRepository extends BaseRepository
{

    protected $device;
    protected $fieldsRepo;
    protected $categoryRepo;

    const ZIPFILE = "导出资产.zip";

    public function __construct(Device $deviceModel,DeviceRepository $deviceRepository)
    {
        $this->device = $deviceModel; //资产模型
        $this->deviceRepository = $deviceRepository; //资产字段
    }

    public function genByCateId(array $categoryId, $overwrite = 0) {
        $ids = join(",", $categoryId);
        $result = $this->device->whereIn("sub_category_id", $categoryId)
            ->orderByRaw("find_in_set(sub_category_id,'$ids') ")
            ->orderBy("created_at","desc")
            ->get();
        return $this->processGen($result, $overwrite);
    }

    public function genById(array $id, $overwrite = 0) {
        $ids = join(",", $id);
        $result = $this->device->whereIn("id", $id)->orderByRaw("find_in_set(id,'$ids') ")->get();
        return $this->processGen($result, $overwrite);
    }

    protected function processGen($result, $overwrite) {
        if($result->isEmpty()) {
            Code::setCode(Code::ERR_EMPTYASSETS);
            return false;
        }
        $url = [];
        foreach($result as $value) {    // 原来是 genQrcode
            $url[] = $this->deviceRepository->printQrcode($value->id, $overwrite);
        }
        return $url;
    }

}