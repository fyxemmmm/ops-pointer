<?php

namespace App\Http\Controllers\Assets;

use App\Repositories\Assets\DeviceConfigRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\DeviceConfigRequest;

class DeviceConfigController extends Controller
{

    protected $config;

    function __construct(DeviceConfigRepository $config) {
        $this->config = $config;
    }

    public function postAdd(DeviceConfigRequest $request)
    {
        $input = $request->input();
        $this->config->add($input);
        userlog("添加了资产配置，资产id：".$input['assetId']);
        return $this->response->send();
    }

    public function getView(DeviceConfigRequest $request) {
        $data = $this->config->get($request->input());
        return $this->response->send($data);
    }

    public function getList(DeviceConfigRequest $request) {
        $data = $this->config->page(["asset_id" => $request->input("assetId")]);
        $fields = [
            'id',
            'asset_id' ,
            'asset_number',
            'asset_name' ,
            'title' ,
            'diff' ,
            'created_at' ,
            'updated_at'];
        return $this->response->send($data, $fields);
    }
}
