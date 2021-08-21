<?php

namespace App\Http\Controllers\Inventory;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Inventory\PlanDeviceRepository;
use App\Http\Requests\Inventory\DeviceRequest;


class DeviceController extends Controller
{

    protected $deviceRepository;

    function __construct(PlanDeviceRepository $deviceRepository)
    {
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * 获取盘点计划与资产列表
     * @return mixed
     */
    public function getList(){
        $data = $this->deviceRepository->getList();
        return $this->response->send($data);
    }

    /**
     * 发送盘点结果
     * @param DeviceRequest $deviceRequest
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function postReport(DeviceRequest $deviceRequest){
        $this->deviceRepository->postReport($deviceRequest);
        return $this->response->send();
    }

}
