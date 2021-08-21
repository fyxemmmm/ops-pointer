<?php

namespace App\Http\Controllers\Assets;

use App\Repositories\Assets\CategoryRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Monitor\EnvironmentalRepository;
use App\Repositories\Monitor\AssetsMonitorRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Assets\DeviceRequest;
use App\Http\Controllers\Controller;
use App\Models\Code;
use App\Transformers\Assets\DeviceRawTransformer;
use App\Exceptions\ApiException;
use App\Transformers\Assets\AnalyTransformer;

class DeviceController extends Controller
{

    protected $device;
    protected $category;
    protected $assetsmonitor;

    function __construct(DeviceRepository $device, CategoryRepository $category,
                         EnvironmentalRepository $environmentalRepository,
                         AssetsMonitorRepository $assetsmonitor) {
        $this->device = $device;
        $this->category = $category;
        $this->environmentalRepository = $environmentalRepository;
        $this->assetsmonitor = $assetsmonitor;

    }

    /**
     * 删除资产
     * @param DeviceRequest $request
     * @return mixed
     */
    public function postDel(DeviceRequest $request) {
        $assetId = trim($request->input("assetId"),",");
        $assetIds = explode(",", $assetId);
        $this->device->del($assetIds);
        return $this->response->send();
    }

    /**
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getList(DeviceRequest $request) {
        $category = $request->input("category");
        $fields =  $request->input("fields");
        $search =  $request->input("search");
        $fieldSearch = $request->all();
        $reset = $request->input("reset");

        if(empty($category)) {
            $data = $this->device->getListAll(null, $search, $fieldSearch);
            $fieldsList = $this->category->getDefaultFieldsList($this->device->defaultFields);

        }
        else {
            $categoryInfo = $this->device->getCategory($category);
            $categoryId = $categoryInfo->id;
            $pid = $categoryInfo->pid;
            if(!$pid) {
                $data = $this->device->getListAll($categoryId, $search, $fieldSearch);
                $fieldsList = $this->category->getDefaultFieldsList($this->device->defaultFields);
            }
            else {
                $data = $this->device->getList($categoryId, $fields, $search, $fieldSearch, $reset);
                $prefFields = $this->device->getPreferences($categoryId);
                $fieldsList = $this->category->getFieldsList($categoryId, $prefFields);
            }
        }
        $this->response->addMeta(["fields" => $fieldsList]);
        return $this->response->send($data);
    }


    public function postSearch(DeviceRequest $request) {
        $search =  $request->input("s");
        $data = $this->device->search($search);
        return $this->response->send($data);
    }

    /**
     * 资产详情
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getView(DeviceRequest $request) {
        $assetId = $request->input("assetId");
        $data = $this->device->getItem($assetId);
        return $this->response->send($data);
    }


    /**
     * 通过资产监控ID获取详情
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getViewByDeviceId(DeviceRequest $request) {
        $deviceId = $request->input("deviceId");
        $result = $this->assetsmonitor->getByDevice($deviceId);
        if(false === $result) {
            return $this->response->send();
        }
        $assetId = $result[$deviceId];
        $data = $this->device->getItem($assetId);
        return $this->response->send($data);
    }

    /**
     * 回收站列表
     * @param DeviceRequest $request
     * @return mixed
     */
    public function getTrash(DeviceRequest $request) {
        $search = $request->input("search");
        $data = $this->device->getTrashList($search);
        $fieldsList = $this->category->getDefaultFieldsList($this->device->defaultFields);
        $this->response->addMeta(["fields" => $fieldsList]);
        return $this->response->send($data);
    }

    public function postTrashDel(DeviceRequest $request) {
        $id = $request->input("assetId");
        $type = $request->input("type", 0);
        $data = $this->device->trashDel($id, $type);

        userlog("从回收站彻底删除了资产: $id");
        return $this->response->send($data);
    }

    public function postTrashRestore(DeviceRequest $request) {
        $id = $request->input("assetId");
        $data = $this->device->trashRestore($id);

        userlog("从回收站还原了资产: $id");
        return $this->response->send($data);
    }


    /**
     * 取机柜列表
     * @param DeviceRequest $request
     */
    public function getRackList(DeviceRequest $request) {
        $data = $this->device->getRackList();
        return $this->response->send($data);
    }

    /**
     * 取机柜详情
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getRackInfo(DeviceRequest $request) {
        $data = $this->device->getRackInfo($request->input("assetId"));
        return $this->response->send($data);
    }

    /**
     * 机柜上架
     */
    public function postRackUp(DeviceRequest $request) {
        $this->device->rackUp($request->input());
        return $this->response->send();
    }

    public function postCheckMonitor(DeviceRequest $request) {
        $assetId = $request->input("assetId");
        $host = $this->device->getById($assetId);
        $isEm = false;
        $bind = false;
        if(in_array($host->category_id, \ConstInc::$em_category_id)) {
            $isEm = true;
            //环控
            $info = $this->environmentalRepository->getDeviceAsset($assetId);
            if(!empty($info)) {
                $bind = true;
            }
        }
        else {
            //验证监控功能是否开启
            if(!\ConsTinc::$mOpen){
                Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            }
            $info = $this->assetsmonitor->getByAsset($request->input("assetId"));
            if($info) {
                $bind = true;
            }
        }


        $data = [
            "isEm" => $isEm,
            "bind" => $bind
        ];
        return $this->response->send($data);
    }

    /**
     * 添加监控主机与绑定
     * @param DeviceRequest $request
     * @deprecated
     */
    public function __postAddMonitor(DeviceRequest $request){
        $data = $this->assetsmonitor->addMonitor($request);
        return $this->response->send($data);
    }


    /**
     * 监控主机和资产解绑
     * @param Request $request
     * @deprecated
     * @return mixe
     */
    public function __postAssetsMonitorUntie(Request $request){
        $input = $request->input() ? $request->input() : array();
//        var_dump($input);exit;
        $res['result'] = $this->assetsmonitor->assetsHostsUntie($input);
        return $this->response->send($res);
    }


    /**
     * 获取监控主机
     * @param Request $request
     * @return mixed
     */
    public function getMonitorHost(Request $request){
        $data['result'] = $this->assetsmonitor->getOne($request);
//        var_dump($data);exit;
        return $this->response->send($data);
    }


    public function postEditMonitor(Request $request){
        $data['result'] = $this->assetsmonitor->updateMonitor($request);
/*        {
            "assetId":"1365",
            "ip":"127.0.0.8",
            "port":"52220"
        }*/
//        var_dump($data);exit;
        return $this->response->send($data);
    }

    /**
     * 绑定环控主机
     */
    public function postAddEmMonitor(DeviceRequest $request) {
        $this->environmentalRepository->bindDeviceAsset($request->input("emDeviceId"), $request->input("assetId"));
        userlog("绑定环控主机 设备id：".$request->input("assetId"). " 环控设备id：".$request->input("emDeviceId"));
        return $this->response->send();
    }

    /**
     * 解绑环控主机
     */
    public function postDelEmMonitor(DeviceRequest $request) {
        $this->environmentalRepository->bindDeviceAsset(0, $request->input("assetId"),true);
        userlog("解绑环控主机 设备id：".$request->input("assetId"). " 环控设备id：".$request->input("emDeviceId"));
        return $this->response->send();
    }

    /**
     * 首页机房配置下机房所对应的设备列表
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getListByArea(DeviceRequest $request){
        $data['result'] = $this->device->getListByArea($request);
        return $this->response->send($data);
    }


    /**
     * 根据监控设备id（多个）获取资产列表
     * @param Request $request
     * @return mixed
     */
    public function getGetAssetsByMDIds(Request $request){
        $input = $request->input();
        $data = $this->assetsmonitor->getAssetsByMDeviceIds($input);
        return $this->response->send($data);
    }


    /**
     * 绑定已经有监控设备时搜索资产
     * @param Request $request
     * @return mixed
     */
    public function getSearchMonitor(Request $request){
        $input = $request->input();
        //获取监控绑定设备
        $monitor = $this->assetsmonitor->getList();
        $result = $this->device->searchMonitor($input,$monitor);
        return $this->response->send($result);
    }

    /**
     * 获取设备使用地分类
     */
    public function getAssetsZoneCategory(Request $request)
    {
        $input = $request->input();
        $result = $this->device->getAssetsZoneCategory($input);
        return $this->response->send($result);
    }

    public function getAssetsTotalByCategory(Request $request)
    {
        $input = $request->input();
        $result = $this->device->getAssetsTotalByCategory($input);
        return $this->response->send($result);
    }


    /**
     * 资产账号管理
     * @param Request $request
     * @return mixed
     */
    public function getAssetsAccount(Request $request){
        $input = $request->input();
        $result = $this->device->getAssetsAccount($input);
        $tfObj = new DeviceRawTransformer(true);
        $this->response->setTransformer($tfObj);
        return $this->response->send($result);
    }


    /**
     * 根据机柜id获取详情
     * @param Request $request
     * @return mixed
     */
    public function getRackInfoById(Request $request){
        $result = $this->device->rackInfoById($request->input("assetId"));
        return $this->response->send($result);
    }


    /**
     * 批量报废资产
     * @param DeviceRequest $request
     * @return mixed
     */
    public function postBreakdown(DeviceRequest $request) {
        $assetId = trim($request->input("assetId"),",");
        $assetIds = explode(",", $assetId);
        $this->device->breakdown($assetIds);
        return $this->response->send();
    }


    /**
     * 入库显示详情
     * @param Request $request
     * @return mixed
     */
    public function getInstorageInfo(Request $request){
        $result = $this->device->instorageInfo($request->input());
        return $this->response->send($result);
    }


    /**
     * 入库保存
     * @param Request $request
     * @return mixed
     */
    public function postInstorageSave(Request $request){
        $this->device->instorageSave($request->input());
        return $this->response->send();
    }



    public function getAstAnaly(Request $request){
        $data = $this->device->getAstAnaly($request->input());
        $tsf = new AnalyTransformer($request->input()['endTime']);
        $this->response->setTransformer($tsf);
        return $this->response->send($data);
    }

    /**
     * 资产分类
     * @param Request $request
     * @return mixed
     */
    public function getAstCategory(){
        $data = $this->device->getAstCategory();
        $this->response->addMeta([1,3]);//返回默认分类网络设备、服务器
        return $this->response->send($data);
    }


    /**
     * 资产单个、多个分类
     * @param Request $request
     * @return mixed
     */
    public function getAstInfo(Request $request){
        $data = $this->device->getAstInfo($request->input());
        return $this->response->send($data);
    }


    public function getAstDetail(Request $request){
        $data = $this->device->getAstDetail($request->input());
        return $this->response->send($data);
    }

    public function getAstExcel(Request $request){
        $this->device->getAssetExcel($request->input());
        return $this->response->send();
    }





}
