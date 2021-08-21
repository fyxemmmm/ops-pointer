<?php
/**
 * 监控
 */

namespace App\Http\Controllers\Monitor;

use App\Http\Requests\Monitor\HomeRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Monitor\AssetsMonitorRepository;
use App\Repositories\Monitor\CommonRepository;
use App\Repositories\Monitor\HengweiRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use App\Repositories\Monitor\MonitorAlertRepository;
use App\Repositories\Monitor\MonitorCurrentAlertRepository;
use App\Repositories\Assets\DeviceRepository;

class HomeController extends Controller
{

    protected $common;
    protected $hosts;
    protected $assetsmonitor;
    protected $hengwei;
    protected $alertRepository;
    protected $device;

    function __construct(AssetsMonitorRepository $assetsmonitor,
                         CommonRepository $common,
                         HengweiRepository $hengwei,
                         MonitorAlertRepository $alertRepository,
                         MonitorCurrentAlertRepository $currentAlertRepository,
                         DeviceRepository $device
    ){
        $this->assetsmonitor = $assetsmonitor;
        $this->common = $common;
        $this->hengwei = $hengwei;
        $this->alertRepository = $alertRepository;
        $this->currentAlertRepository = $currentAlertRepository;
        $this->device = $device;
    }


    /**
     * 获取监控设备总数
     * @return mixed
     */
    public function getMonitorDeviceCount(){
        $result['result'] = $this->assetsmonitor->getMonitorDeviceCount();
        return $this->response->send($result);

    }


    public function __getToken(){
        $result = $this->common->hwApiLogin();
        return $this->response->send($result);
    }


    /**
     * 获取所有网络设备
     * @return mixed
     */
    public function getDevices(){
        $result = $this->hengwei->getDevices();
        return $this->response->send($result);
    }


    /**
     * 根据设备id获取核心服务器性能监控 (cpu/memory/disk)占用
     * @param Request $request
     * @return mixed
     */
    public function getDevicePerformance(HomeRequest $request){
        $result = $this->common->getHWDevicePerformance();
        return $this->response->send($result);
    }


    /**
     * 根据设备id获取告警总数
     * @param Request $request
     * @return mixed
     */
    public function getAlertCountById(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result['result'] = $this->hengwei->getAlertCountById($input);
        return $this->response->send($result);
    }


    /**
     * 获取当前告警总数
     * @param Request $request
     * @return mixed
     */
    public function getAlertCount(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result['result'] = $this->hengwei->getAlertCount($input);
        return $this->response->send($result);
    }

    /**
     * 获取24小时告警总数
     * @param Request $request
     * @return mixed
     */
    public function getAlertCountHour(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        //$result['result'] = $this->hengwei->getAlertCountDay($input);
        $result['result'] = $this->alertRepository->getAlertCountDay($input);
        return $this->response->send($result);
    }

    /**
     * 获取报警工单数
     * @param HomeRequest $request
     * @return mixed
     */
    public function getEventAlertCount(HomeRequest $request){
        $result['result'] = $this->alertRepository->getEventCnt($request);
        return $this->response->send($result);
    }


    /**
     * 获取当前告警列表
     * @param Request $request
     * @return mixed
     */
    public function getAlertList(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->common->getAlertList($input);
        return $this->response->send($result);
    }


    /**
     * 添加绑定资产和监控设备
     * @param Request $request
     * @return mixed
     */
    public function postAddDevice(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $this->assetsmonitor->addMonitor($input);
        return $this->response->send();
    }


    /**
     * 更新绑定资产和监控设备
     * @param Request $request
     * @return mixed
     */
    public function postUpdateDevice(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $this->assetsmonitor->updateMonitor($input);
        return $this->response->send();
    }

    public function getUpdateDevice(HomeRequest $request){
        $result = $this->assetsmonitor->getMonitor($request->input("assetId"));
        return $this->response->send($result);
    }


    /**
     * 删除绑定资产和监控设备
     * @param Request $request
     * @return mixed
     */
    public function postDelDevice(HomeRequest $request){
        $this->assetsmonitor->delMonitor($request->input("assetId"));
        return $this->response->send();
    }


    /**
     * 根据设备ID获取设备
     * @param Request $request
     * @return mixed
     */
    public function getDeviceById(HomeRequest $request){
        //验证监控功能是否开启
        if(!\ConsTinc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $input = $request->input() ? $request->input() : array();
        $result['result'] = $this->common->getDeviceById($input);
        return $this->response->send($result);
    }



    /**
     * 获取设备cpu信息
     * @param Request $request
     * @return mixed
     */
    public function getCpu(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->assetsmonitor->getDeviceCpu($input);
        return $this->response->send();
    }


    /**
     * 获取设备状态
     * @param HomeRequest $request
     * @return mixed
     */
    public function getDeviceStatus(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result['result'] = $this->common->getDeviceStatus($input);
        return $this->response->send($result);
    }


    
    /**
     * 获取历史告警列表
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertHistory(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->alertRepository->getAlertHistory($input,'alert_id');
        return $this->response->send($result);
    }

    /**
     * 获取报警级别列表
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertLevel(HomeRequest $request){
        $data = $this->alertRepository->getLevelOrStatusList('level');
        return $this->response->send($data);
    }

    /**
     * 获取报警状态列表
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertStatus(HomeRequest $request){
        $data = $this->alertRepository->getLevelOrStatusList('status');
        return $this->response->send($data);
    }



    /**
     * 根据设备ID和类型名、时间获取数据(通用)
     * @param HomeRequest $request
     * @return mixed
     */
    public function getDataCommon(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->common->getDataCommon($input);
        return $this->response->send($result);
    }
    /**
     * 获取最近24小时报警等级分布
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertDayLevel(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->common->getAlertDayLevel($input);
        return $this->response->send($result);
    }


    /**
     * 网络流量统计
     * @param HomeRequest $request
     * @return mixed
     */
    public function getNetworkFlow(HomeRequest $request){
        $result = $this->common->getNetworkFlow();
        return $this->response->send($result);
    }

    public function getDeviceCpu(HomeRequest $request) {
        $input = $request->input() ? $request->input() : array();
        $result = $this->assetsmonitor->getDeviceCpu($input);
        return $this->response->send($result);
    }

    public function getDeviceCpuDetail(HomeRequest $request) {
        $input = $request->input() ? $request->input() : array();
        $result = $this->assetsmonitor->getDeviceCpuDetail($input);
        return $this->response->send($result);
    }

    public function getDeviceMem(HomeRequest $request) {
        $input = $request->input() ? $request->input() : array();
        $result = $this->assetsmonitor->getDeviceMem($input);
        return $this->response->send($result);
    }

    public function getDeviceDisk(HomeRequest $request) {
        $input = $request->input() ? $request->input() : array();
        $result = $this->assetsmonitor->getDeviceDisk($input);
        return $this->response->send($result);
    }

    public function getDeviceLoadavg(HomeRequest $request) {
        $input = $request->input() ? $request->input() : array();
        $result = $this->assetsmonitor->getDeviceLoadavg($input);
        return $this->response->send($result);
    }


    /**
     * 根据资产ID或监控设备ID获取网卡信息
     * @param HomeRequest $request
     * @return mixed
     */
    public function getSwitchboard(HomeRequest $request){
        $result = $this->common->getSwitchboard($request->input());
        return $this->response->send($result);
    }

    public function getCoreSwitch(HomeRequest $request){
        $result = $this->common->getCoreSwitch();
        return $this->response->send($result);
    }


    /**
     * 根据监控设备端口ID获取入流量和出流量
     * @param HomeRequest $request
     * @return mixed
     */
    public function getPortFlowData(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->common->getPortFlowData($input);
        return $this->response->send($result);
    }


    /**
     * 获取告警数据入库
     * @return mixed
     */
    public function getAlertListSave(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        for($type = 1;$type <= 2;$type++){
            $res = $this->hengwei->getAlertList($input, $type);
            $this->common->getAlertListSave($res, $type);
        }
        return $this->response->send();
    }


    /**
     * 获取历史告警总数
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertHistoryCount(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result['result'] = $this->hengwei->getAlertHistoryCount($input);
        return $this->response->send($result);
    }


    /**
     * 最近24小时报警数量
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertCountListDay(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->common->getAlertCountListDay($input);
        return $this->response->send($result);
    }

    public function getTestAlert(HomeRequest $request) {
        $this->alertRepository->triggerEvent([206]);
        return $this->response->send();
    }


    /**
     * 批量添加历史告警数据入库
     * @param HomeRequest $request
     * @return mixed
     */
    public function getAlertListBatchSave(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->common->alertHistoryBatchSave($input, 0);
        return $this->response->send($result);
    }

    /**
     * 取给定设备的监控是否可用
     * @param HomeRequest $request
     * @return mixed
     * @throws ApiException
     */
    public function getAvailable(HomeRequest $request) {
        $result = $this->assetsmonitor->getDeviceAvail($request);
        return $this->response->send($result);
    }


    /**
     * 获取资产监控列表
     * @param HomeRequest $request
     * @return mixed
     */
    public function getDeviceList(HomeRequest $request){
        $category = $request->input("category");
        $categoryId = '';
        $pid = '';
        if($category){
            $categoryInfo = $this->device->getCategory($category);
            $categoryId = $categoryInfo->id;
            $pid = $categoryInfo->pid;
        }
        $result['result'] = $this->assetsmonitor->getDeviceList($request,$categoryId,$pid);
        return $this->response->send($result);
    }

    /**
     * @deprecated
     * @param HomeRequest $request
     * @return mixed
     */
    public function __getTransDeviceId(HomeRequest $request) {
        $result = $this->assetsmonitor->getByDevice($request->input("deviceId"));
        return $this->response->send($result);
    }

    public function getLogin(HomeRequest $request) {
        $hostDomain = config('app.web_url');
        $hw = $this->hengwei->getHWToken(true);
        if($hw){
            $signature = $this->hengwei->signature($hw);
            $cookieName = getKey($signature,'name');
            $cookieValue = getKey($signature,'value');
            $cookiePath = getKey($signature,'path');

            //设置cookie
            setrawcookie($cookieName, $cookieValue, 0,$cookiePath,'',false,true);
        }
        $result = ["token" => $hw, "host" => $hostDomain];
        return $this->response->send($result);
    }


    /**
     * 获取未绑定的监控设备
     * @return mixed
     */
    public function getUnbindDeviceList(){
        $result = $this->common->getUnbindDeviceList();
        return $this->response->send($result);
    }

    public function postSaveDevices(HomeRequest $request){
        $params = $request->input();
//        dd($params);
        $result = $this->assetsmonitor->addBatchMonitor($params);
//        var_dump($result);exit;
        return $this->response->send($result);
    }

    /**
     * 获取当前告警列表,提供给首页
     * @param Request $request
     * @return mixed
     */
    public function getAlertListExtra(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $data = $this->common->getAlertListExtra($input);
        return $this->response->send($data);
    }


    /**
     * 获取监控工作天数
     * @param HomeRequest $request
     * @return mixed
     */
    public function getDeviceWorkDays(HomeRequest $request){
        $input = $request->input() ? $request->input() : array();
        $data = $this->common->getDeviceWorkDays($input);
        return $this->response->send($data);
    }




}
