<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/8
 * Time: 21:52
 */

namespace App\Http\Controllers\Summary;

use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Workflow\EventsRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Transformers\Assets\ChartListTransformer;
use App\Repositories\Monitor\EnvironmentalRepository;
use App\Transformers\Assets\ChartTransformer;
use App\Transformers\Workflow\EventCommonTransformer;

class HomeController extends Controller {

    protected $deviceRepository;
    protected $eventsRepository;

    function __construct(DeviceRepository $deviceRepository,
                         EventsRepository $eventsRepository,
                         EnvironmentalRepository $environmental){
        $this->deviceRepository = $deviceRepository;
        $this->eventsRepository = $eventsRepository;
        $this->environmental = $environmental;
    }

    /**
     * 健康度
     * @return mixed
     */
    public function getHealthy() {
        $device = $this->deviceRepository->getHealthy();
        $network = 99.999;
        $engineroom = "良";
        $data = [
            "device" => $device,
            "network" => $network,
            "engineroom" => $engineroom
        ];
        return $this->response->send($data);
    }

    /**
     * 事件信息
     * @return mixed
     */
    public function getEvents() {
        $data = $this->eventsRepository->getTodayEvents();
        return $this->response->send($data);
    }

    /**
     * 资产数
     */
    public function getAssets() {
        $data = $this->deviceRepository->getAssetsNum();
        return $this->response->send($data);
    }

    /**
     * 设备年数
     * @return mixed
     */
    public function getAssetsYears() {
        $data = $this->deviceRepository->getAssetsYears();
        return $this->response->send($data);
    }


    /**
     * 质保
     * @return mixed
     */
    public function getWarranty() {
        $data = $this->deviceRepository->getWarranty();
        return $this->response->send($data);
    }


    /**
     * 资产维修次数(频率)
     */
    public function getAssetRepairFrequency(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getAssetRepairFrequency($input);
        $tfObj = new ChartListTransformer;
        $this->response->setTransformer($tfObj);
        if($list) {
            $header = array(
                array('sname' => 'location_area', 'cname' => '使用地｜部门'),
                array('sname' => 'area_department_msg', 'cname' => '机房／科室'),
                array('sname' => 'sub_category', 'cname' => '资产类别'),
                array('sname' => 'number', 'cname' => '资产编号'),
                array('sname' => 'name', 'cname' => '资产名称'),
                array('sname' => 'event_cnt', 'cname' => '维修次数'),
                array('sname' => 'warranty_begin', 'cname' => '质保开始日期'),
                array('sname' => 'warranty_end', 'cname' => '质保结束日期'),
                array('sname' => 'warranty_state_msg', 'cname' => '在保情况'),
            );

            $this->response->addMeta(["fields" => $header]);
        }
        return $this->response->send($data);
    }


    /**
     * 处理中的事件图表和列表（待处理,已结单,处理中）
     * @param Request $request
     * @return mixed
     */
    public function getEventsProcess(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
//        var_dump($data);exit;
        if($list) {
            $data = $this->eventsRepository->getEventsProcessList($input);
            $header = array(
                array('sname' => 'location_area', 'cname' => '使用地｜部门'),
                array('sname' => 'area_department_msg', 'cname' => '机房／科室'),
                array('sname' => 'sub_category', 'cname' => '资产类别'),
                array('sname' => 'number', 'cname' => '资产编号'),
                array('sname' => 'name', 'cname' => '资产名称'),
                array('sname' => 'event_state_msg', 'cname' => '处理状态'),
                array('sname' => 'username', 'cname' => '处理人'),
                array('sname' => 'description', 'cname' => '事件描述'),
            );
            $events_state = $this->eventsRepository->getEventsProcessState();

            $tfObj = new ChartListTransformer;
            $this->response->setTransformer($tfObj);

            $this->response->addMeta(["fields" => $header]);
            $this->response->addMeta(["state" => $events_state]);

        }else{
            $data = $this->eventsRepository->getEventsProcess($input);
        }
        return $this->response->send($data);
    }


    /**
     * 各位置资产总数
     * @param Request $request
     * @return mixed
     */
    public function getAssetsLocationNum(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getAssetsLocationNum($input);

        return $this->response->send($data);
    }


    /**
     * 各位置资产使用率
     * @param Request $request
     * @return mixed
     */
    public function getAssetsLocationUserate(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getAssetsLocationRate($input);
        if($list) {
            $header = array(
                array('sname' => 'location_msg', 'cname' => '使用地'),
                array('sname' => 'used', 'cname' => '使用台数'),
                array('sname' => 'state_free_cnt', 'cname' => '闲置台数'),
                array('sname' => 'state_down_cnt', 'cname' => '报废台数'),
                array('sname' => 'asset_cnt', 'cname' => '总台数'),
                array('sname' => 'use_rate', 'cname' => '使用率'),
            );
            $this->response->addMeta(["fields" => $header]);
        }
        $tfObj = new ChartListTransformer;
        $this->response->setTransformer($tfObj);

        return $this->response->send($data);
    }

    public function getAssetsLocationFreeRate(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getAssetsLocationRate($input,'free');
        if($list) {
            $header = array(
                array('sname' => 'location_msg', 'cname' => '使用地'),
                array('sname' => 'state_free_cnt', 'cname' => '闲置台数'),
                array('sname' => 'used', 'cname' => '使用台数'),
                array('sname' => 'state_down_cnt', 'cname' => '报废台数'),
                array('sname' => 'asset_cnt', 'cname' => '总台数'),
                array('sname' => 'use_rate', 'cname' => '闲置率'),
            );
            $this->response->addMeta(["fields" => $header]);
        }
        $tfObj = new ChartListTransformer;
        $this->response->setTransformer($tfObj);

        return $this->response->send($data);
    }


    /**
     * 核心监控资产使用情况
     * @param Request $request
     * @return mixed
     */
    public function getMonitorCore(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getMonitorCore($input);
        if($list) {
            $header = array(
                array('sname' => 'location_msg', 'cname' => '使用地'),
                array('sname' => 'sub_category', 'cname' => '资产类别'),
                array('sname' => 'number', 'cname' => '资产编号'),
                array('sname' => 'name', 'cname' => '资产名称'),
                array('sname' => 'cpu_percent', 'cname' => '处理器使用率'),
                array('sname' => 'mem_percent', 'cname' => '内存使用率'),
                array('sname' => 'disk_percent', 'cname' => '磁盘使用率'),
            );
            $this->response->addMeta(["fields" => $header]);
        }
        $tfObj = new ChartListTransformer;
        $this->response->setTransformer($tfObj);

        return $this->response->send($data);
    }

    public function getMonitorCoreTitle(){
        $data = [
            '1'=>'网络设备',
            '2'=>'安全设备',
            '3'=>'服务器'
        ];
        return $this->response->send($data);
    }

    public function getWeekEvents() {
        //本周事件总数
        $data = $this->eventsRepository->getWeekEvents();
        return $this->response->send(["result" => $data]);

    }

    // 获得每个事件的总数
    public function getEveryEvent(){
        $data = $this->eventsRepository->getEveryEvent();
        return $this->response->send($data);
    }


    public function getTerminalTrend()
    {
        $data = $this->eventsRepository->getTerminalTrend();
        return $this->response->send(["result" => $data]);
    }

    /**
     * 根据资产编号，监控点id获取监控点数据（环控）
     * @param Request $request
     * @return mixed
     */
    public function getEmonitorPoint(Request $request){
        $input = $request->input();
        $data = $this->environmental->getEmonitorPoint($input);
        return $this->response->send($data);
    }


    /**
     * 资产过保情况
     * @param Request $request
     * @return mixed
     */
    public function getAssetsWarranty(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getAssetsWarranty($input);

        if($list) {
            $header = array(
                array('sname' => 'location_area', 'cname' => '使用地/部门'),
                array('sname' => 'area_department_msg', 'cname' => '机房|科室'),
                array('sname' => 'sub_category', 'cname' => '资产类别'),
                array('sname' => 'number', 'cname' => '资产编号'),
                array('sname' => 'name', 'cname' => '资产名称'),
                array('sname' => 'warranty_state_msg', 'cname' => '过保状态'),
                array('sname' => 'warranty_begin', 'cname' => '质保开始日期'),
                array('sname' => 'warranty_end', 'cname' => '质保结束日期'),
                array('sname' => 'master', 'cname' => '所属人'),
            );
            $tfObj = new ChartListTransformer;
            $this->response->setTransformer($tfObj);
            $this->response->addMeta(["fields" => $header]);
        }
        return $this->response->send($data);
    }


    /**
     * 设备故障类型
     * @param Request $request
     * @return mixed
     */
    public function getEventSolutionType(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->eventsRepository->getSolutionType($input,$request);

        $tfObj = new EventCommonTransformer;
        $this->response->setTransformer($tfObj);
        if($list) {
            $header = array(
                array('sname' => 'solution_name', 'cname' => '类别'),
                array('sname' => 'reporter', 'cname' => '上报人'),
                array('sname' => 'user', 'cname' => '处理人'),
                array('sname' => 'distance_time_msg', 'cname' => '路程时间'),
                array('sname' => 'response_time_msg', 'cname' => '响应时间'),
                array('sname' => 'process_time_msg', 'cname' => '处理时间'),
            );
            $this->response->addMeta(["fields" => $header]);
        }
        return $this->response->send($data);
    }


    /**
     * 核心监控资产状态
     * @param Request $request
     * @return mixed
     */
    public function getAssetsCoreStatus(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getAssetsCoreStatus($input);
        if($list) {
            $header = array(
                array('sname' => 'location_msg', 'cname' => '使用地'),
                array('sname' => 'sub_category', 'cname' => '资产类别'),
                array('sname' => 'number', 'cname' => '资产编号'),
                array('sname' => 'name', 'cname' => '资产名称'),
                array('sname' => 'device_status_msg', 'cname' => '状态'),
            );
        }else{
            $header = array(
                array('sname' => 'location_msg', 'cname' => '使用地'),
                array('sname' => 'number', 'cname' => '资产编号'),
                array('sname' => 'device_status_msg', 'cname' => '状态'),
            );
        }
        $this->response->addMeta(["fields" => $header]);
        $tfObj = new ChartListTransformer;
        $this->response->setTransformer($tfObj);

        return $this->response->send($data);
    }


    /**
     * 各站点报修情况
     * @param Request $request
     * @return mixed
     */
    public function getLocationUserEventNum(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        $data = $this->deviceRepository->getLocationUserEventNum($request,$input);
        if($list) {
            $header = array(
                array('sname' => 'location_msg', 'cname' => '使用地'),
                array('sname' => 'cnt', 'cname' => '报修次数'),
                array('sname' => 'state0', 'cname' => '待处理'),
                array('sname' => 'state1', 'cname' => '已接单'),
                array('sname' => 'state2', 'cname' => '处理中'),
                array('sname' => 'state3', 'cname' => '已完成'),
                array('sname' => 'state4', 'cname' => '已关闭'),
            );
            $this->response->addMeta(["fields" => $header]);
            $tfObj = new ChartListTransformer;
            $this->response->setTransformer($tfObj);
        }else{
            $tfObj = new ChartTransformer;
            $this->response->setTransformer($tfObj);
        }

        return $this->response->send($data);
    }




    /**
     * 使用年限列表&设备使用年限统计
     * @param Request $request
     * @return mixed
     */
    public function getAssetsTime(Request $request){
        $input = $request->input();
        $list = getKey($input,'list');
        if($list) {
            $data = $this->deviceRepository->getAssetsTimeList($input);
            $header = array(
                array('sname' => 'name', 'cname' => '类别名称'),
                array('sname' => 'year', 'cname' => '<1年'),
                array('sname' => 'year1_3', 'cname' => '1-3年（含1年）'),
                array('sname' => 'year3_5', 'cname' => '3-5年（含3年）'),
                array('sname' => 'year5', 'cname' => '>5年（含5年）'),
            );
            $this->response->addMeta(["fields" => $header]);
            $tfObj = new ChartListTransformer;
            $this->response->setTransformer($tfObj);
        }else{
            $data = $this->deviceRepository->getRangeTime();
        }
        return $this->response->send($data);
    }


    # 得到类型的各总数
    public function getWrongTypeNum(){
        $data = $this->eventsRepository->getWrongTypeNum();
        return $this->response->send($data);
    }



}