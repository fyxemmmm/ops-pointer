<?php

namespace App\Http\Controllers\Assets;

use App\Models\Assets\DevicePorts;
use App\Repositories\Assets\CategoryRepository;
use App\Repositories\Assets\DevicePortsRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Assets\DevicePortRequest;
use App\Http\Controllers\Controller;
use Validator;

class DevicePortsController extends Controller
{

    protected $devicePorts;
    protected $category;

    function __construct(DevicePortsRepository $devicePorts, CategoryRepository $category) {
        $this->devicePorts = $devicePorts;
        $this->category = $category;
    }

    /**
     * 获取点位信息
     * @param DevicePortRequest $request
     * @return mixed
     */
    public function getList(DevicePortRequest $request)
    {
        $result = $this->devicePorts->get($request->input(), false);
        return $this->response->send(["result" => $result]);
    }

    /**
     * @param DevicePortRequest $request
     * @return mixed
     * @throws \Exception
     */
    public function postConnect(DevicePortRequest $request) {
        $ip = trim($request->input("ip"));
        if(!empty($ip)) {
            $this->validate($request, [
                'ip' => 'ip',
            ],
            [
                "ip" => "IP地址不合法",
            ]);
        }

        $this->devicePorts->connect($request->input(), false);
        return $this->response->send();
    }

    /**
     * @param DevicePortRequest $request
     * @return mixed
     */
    public function postDisconnect(DevicePortRequest $request) {
        $this->devicePorts->disconnect($request->input(), false);
        return $this->response->send();
    }


    /**
     * 取点位信息
     * @param DevicePortRequest $request
     * @return mixed
     */
    public function getPortlist(DevicePortRequest $request) {
        $result = $this->devicePorts->get($request->input(), false);
        return $this->response->send(["result" => $result]);
    }


}
