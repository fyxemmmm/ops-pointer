<?php
/**
 * 巡检报告
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/13
 * Time: 16:20
 */

namespace App\Repositories\Report;

use App\Repositories\BaseRepository;
use App\Models\Monitor\EmMonitorpoint;
use App\Models\Monitor\Inspection;
use App\Repositories\Monitor\AssetsMonitorRepository;
use App\Repositories\Monitor\CommonRepository;
use App\Repositories\Monitor\HengweiRepository;
use App\Models\Assets\Device;
use App\Models\Code;
use DB;
use App\Exceptions\ApiException;
use Log;
use App\Models\Monitor\EmInspectionTemplate;

class InspectionRepository extends BaseRepository
{

    protected $model;
    protected $emMonitorpointModel;
    protected $assetsmonitor;
    protected $common;
    protected $hengwei;
    protected $deviceModel;
    protected $emiTemplateModel;

    public $terminalCategory = [5,6];

    public function __construct(Inspection $InspectionModel,
                                EmMonitorpoint $emmonitorpointModel,
                                AssetsMonitorRepository $assetsmonitor,
                                CommonRepository $common,
                                HengweiRepository $hengwei,
                                Device $deviceModel,
                                EmInspectionTemplate $emiTemplateModel
    ){
        $this->model = $InspectionModel;
        $this->emMonitorpointModel = $emmonitorpointModel;
        $this->assetsmonitor = $assetsmonitor;
        $this->common = $common;
        $this->hengwei = $hengwei;
        $this->deviceModel = $deviceModel;
        $this->emiTemplateModel = $emiTemplateModel;
    }


    /**
     * 获取监控点
     * @param array $where
     * @return array
     */
    public function getMonitorpoint($where=array()){
        $res = array();
        if($where){
            $res = $this->emMonitorpointModel->where($where)->get()->toArray();
        }
        return $res;
    }


    /**
     * 获取报告时间
     * @param $request
     * @return mixed
     */
    public function getReportDate($input=array()){
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : '';
        $tid = isset($input['tid']) ? $input['tid'] : '';
        $where = array();
        if($deviceId) {
            $where[] = ['device_id','=',$deviceId];
        }
        if($tid){
            $where[] = ['template_id', '=', $tid];
        }
        if(!$tid){
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }
        $res = array();
        /*$where[] = array('id','>',0);*/
        $model = $this->model;
        if($where) {
            $model = $model->where($where);
        }
        $data = $model->selectRaw("distinct report_date")->orderBy('report_date','desc')->get();
        if($data){
            foreach($data as $v){
                $report_date = isset($v['report_date']) ? $v['report_date'] : '';
                if($report_date){
                    $res[] = $report_date;
                }
            }
        }
//        var_dump($res);exit;
        return $res;

    }


    /**
     * 根据资产类型获取资产监控设备
     * @param array $input
     * @return array
     */
    public function getAssetByCategory($input=array()){
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $result = array();
        $categoryId = isset($input['categoryId']) ? $input['categoryId'] : '';
        $reportDate = isset($input['reportDate']) ? trim($input['reportDate']) : '';
        if(!$categoryId) {
            Code::setCode(Code::ERR_ASSET_CATEGORY_NOT);
            return false;
        }
        if(!$reportDate){
            Code::setCode(Code::ERR_NOT_REPORTDATE);
            return false;
        }

        if($categoryId) {
            $model = $this->model->join("assets_device as AD", "inspection.asset_id", "=", "AD.id")
//                ->where("AD.category_id", "=", $categoryId)
                ->where("inspection.device_type","=",$categoryId)
                ->where("report_date","=",$reportDate)
                ->select("AD.id","AD.category_id","AD.name","AD.number");
            $ret = $model->get();
            $result = $ret->toArray();
//            var_dump($ret);exit;
        }

        return $result;
    }


    /**
     * 获取环控、监控报告
     * @return array
     */
    public function getReport($input){
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }

//        var_dump($input);exit;
        $res = array();
        $assetId = isset($input['assetId']) ? $input['assetId'] : '';
        $tId = isset($input['tid']) ? $input['tid'] : '';
        $reportDate = isset($input['reportDate']) ? $input['reportDate'] : '';
        $default = isset($input['default']) ? $input['default'] : '1';
        //根据默认模板获取模板id
        if($default) {
            $where = array('is_default' => $default);
            $tData = $this->emiTemplateModel->select('id')->where($where)->first();
            $tId = isset($tData['id']) ? $tData['id'] : '';
        }
        if(!$tId) {
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }
        if(!$reportDate){
            Code::setCode(Code::ERR_NOT_REPORTDATE);
            return false;
        }
        if(!$assetId) {
            Code::setCode(Code::ERR_EMPTYASSETS);
            return false;
        }
        if($tId && $reportDate && $assetId){
            $where = array('template_id'=>$tId,'report_date'=>$reportDate,'asset_id'=>$assetId);
            $res = $this->model->where($where)->get();
            $res = $res ? $res->toArray() : array();
            if(!empty($res)){
                foreach($res as &$item){
                    if(!empty($item['content'])){
                        $item['content'] = json_decode($item['content'],true);
                        $key_arr = ['cpu_percent','mem_percent','disk_percent'];
                        foreach($key_arr as $key){
                            if(!isset($item['content'][$key])){
                                continue;
                            }
                            if($item['content'][$key] >= 80 ){
                                $item['content'][$key.'_status'] = 3; //严重告警
                            }else if(($item['content'][$key] < 80) && ($item['content'][$key] >= 70)){
                                $item['content'][$key.'_status'] = 2; //告警
                            }else{
                                $item['content'][$key.'_status'] = 1; //正常
                            }
                        }
                        $item['content'] = json_encode($item['content']);
                    }
                }
            }
        }
        return $res;

    }


    /**
     * 添加巡检报告数据
     * @param array $input    => array('mtype'=>1); [mtype=1：监控，2：环控]
     * @param string $setReportDate   => 2018112617
     * @return int
     */
    public function addReport($input=array(),$setReportDate=''){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){ //监控没开启则返回
            return false;
        }
        $setReportDate = $setReportDate ? $setReportDate : '';
        $templateId = isset($input['tid']) ? trim($input['tid']) : '';
        $reportDate = isset($input['reportDate']) ? trim($input['reportDate']) : '';
        $where = array();//array('status'=>1);
        if($templateId){
            $where['id'] = $templateId;
        }
        $report_date = date('YmdH');
        // 获取巡检报告模板列表 （em_inspection_template）
        $tData = $this->common->getTemplateList('all',$where);
        $tData = $tData ? $tData->toArray() : array();
        if($tData) {
            foreach ($tData as $val) {
                $tid = isset($val['id']) ? $val['id'] : '';
                $mData = isset($val['mcontent']) ? $val['mcontent'] : array();
                $report_dates = isset($val['report_dates']) ? $val['report_dates'] : '';
                $rdArr = $report_dates ? array_filter(array_unique(explode(',',$report_dates))) : [];
                //验证当前时间是否需要跑数据
                $rdFlag = dateArrStrCompare($rdArr);
                if($mData && $rdFlag){
                    //保存当前模板
                    if($tid){
                        $insert = array(
                            'template_id' => $tid,  // 模板 id
                            'report_date' => $report_date, // 当前时间点
                            'content' => json_encode($mData),
                            'is_template' => 1,
                        );
                        $where = array('template_id' => $tid, 'report_date' => $report_date);
                        $dataOne = $this->model->where($where)->first();
                        if(!$dataOne) {
                            $this->model->create($insert);
                        }
                    }
                    //保存监控数据
                    foreach($mData as $mv) {
                        $assetIds = isset($mv['child']) ? $mv['child'] : array();
                        // 通过资产 id 获取资产监控列表 （assets_monitor）
                        $amData = $this->assetsmonitor->getListByInAssetId(array(), $assetIds);
                        $add = array();
                        if ($amData) {
                            foreach ($amData as $v) {
                                // 资产id
                                $assetId = isset($v['asset_id']) ? trim($v['asset_id']) : '';
                                // 监控设备id
                                $deviceId = isset($v['device_id']) ? trim($v['device_id']) : '';
                                // 端口
                                $amPort = isset($v['port']) ? trim($v['port']) : '';
                                $input['deviceId'] = $deviceId;
                                $input['assetId'] = $assetId;

                                $ip = $cpu_percent = $mem_percent = $disk_percent = $status = $cpuData = $memData = $diskData = $cpuCount = '';


                                $dataOne = true;
                                $device = array();
                                $device_type = 0;
                                if ($assetId) {
                                    // 获取相应资产 assets_device 表中一条记录
                                    $device = $this->deviceModel->find($assetId);
                                    $device = $device ? $device->toArray() : array();
                                    // 取出该设备所对应的分类 id
                                    $device_type = getKey($device,'category_id', 0);
                                    if ($report_date) {
                                        $where = array(
                                            'asset_id' => $assetId,
                                            'report_date' => $report_date,
                                            'template_id' => $tid,
                                            'device_type'=>$device_type
                                        );
                                        // inspection 巡检报告中
                                        $dataOne = $this->model->where($where)->first();
                                    }
                                }

                                try {
                                    if (!$dataOne && $rdFlag) {
                                        // $input = array("mtype"=>1,"deviceId"=>"489","assetId"=>"1791");
                                        // $deviceAvail = array("cpu"=>1,"mem"=>1,"loadavg"=>1,"disk"=>1,"port"=>1);
                                        $deviceAvail = $this->assetsmonitor->getDeviceAvail($input);
                                        $cpu = isset($deviceAvail['cpu']) ? $deviceAvail['cpu'] : 0;
                                        $mem = isset($deviceAvail['mem']) ? $deviceAvail['mem'] : 0;
                                        $loadavg = isset($deviceAvail['loadavg']) ? $deviceAvail['loadavg'] : 0;
                                        $disk = isset($deviceAvail['disk']) ? $deviceAvail['disk'] : 0;
                                        $port = isset($deviceAvail['port']) ? $deviceAvail['port'] : 0;

                                        // 根据监控设备ID获取IP
                                        // $input = array("mtype"=>1,"deviceId"=>"489","assetId"=>"1791");
                                        $ip = $this->common->getDeviceIP($input);
                                        try {
                                            // 根据监控设备 ID 获取相应设备的 cpu memory disk 使用率
                                            // $input = array("deviceId"=>"489");
                                            $info = $this->hengwei->getDevicePerformanceById($input);
                                        }catch(\Exception $e){
                                            Log::error($e);
                                        }
                                        //网络设备，安全设备线路，丢包率
                                        if(in_array($device_type,array(1,2))) {
                                            $linkInput = $input;
                                            $linkInput['deviceId'] = $amPort; // 端口号
                                            try {
                                                // $linkInput = array("mtype"=>1,"deviceId"=>"489","assetId"=>"1791");
                                                // 获取线路流量
                                                $linkInfo = $this->hengwei->getDeviceLinkById($linkInput);
                                            }catch(\Exception $e){
                                                Log::error($e);
                                            }
                                        }
                                        $packet_loss_percent = isset($linkInfo['ifDiscards']) ? $linkInfo['ifDiscards'] : 0;

                                        $cpu_percent = isset($info['cpu']) ? $info['cpu'] : 0;
                                        $mem_percent = isset($info['memory']['mem_percent']) ? $info['memory']['mem_percent'] : 0;
                                        $disk_percent = isset($info['disk']['used_percent']) ? $info['disk']['used_percent'] : 0;
                                        $disk_total = isset($info['disk']['total']) ? $info['disk']['total'] : 0;
                                        //        $input['type'] = 'icmp_test2';
                                        // 根据设备ID获取设备状态
                                        $status = $this->common->getDeviceStatus($input);
                                        //        $status = $this->hengwei->getOneByIdType($input);

                                        $cpuData = $cpu ? $this->assetsmonitor->getDeviceCpu($input) : '';
                                        $memData = $mem ? $this->assetsmonitor->getDeviceMem($input) : '';
                                        $diskData = $disk ? $this->assetsmonitor->getDeviceDisk($input) : '';

                                        $input['type'] = 'cpu2';

                                        // 根据设备ID和类型名获取数据
                                        $cpuRes = $this->hengwei->getOneByIdType($input);
                                        $cpuCount = isset($cpuRes) ? count($cpuRes) : 0;//$cpuRes;//
                                        $portData = array();
                                        if ($amPort) {
                                            $portParam = array('assetId' => $assetId, 'deviceId' => $amPort);
                                            // 根据监控设备端口ID获取入流量和出流量
                                            $portData = $port ? $this->common->getPortFlowData($portParam) : '';
                                        }


                                        $content = array(
                                            'cpu_percent' => $cpu_percent,
                                            'mem_percent' => $mem_percent,
                                            'disk_percent' => $disk_percent,
                                            'disk_total' => $disk_total,//磁盘总大小
                                            'packet_loss_percent' => $packet_loss_percent,//丢包率
                                            'ip' => $ip,
                                            'status' => $status,
                                            'cpuCount' => $cpuCount
                                        );

                                        $insert = array(
                                            'template_id' => $tid,  // 模板 id
                                            'asset_id' => $assetId,  // 资产 id
                                            'device_id' => $deviceId, // 监控设备 id
                                            'device_type' => $device_type, // 资产设备所对应的分类 id
                                            'report_date' => $report_date, // 当前时间点
                                            'content' => json_encode($content),
                                            'cpu_data' => json_encode($cpuData),
                                            'memory_data' => json_encode($memData),
                                            'disk_data' => json_encode($diskData),
                                            'port_data' => json_encode($portData),
                                        );
//                                                var_dump($insert);exit;
                                        $create = $this->model->create($insert);
                                        if (isset($create['id']) && $create['id']) {
                                            $add[] = $assetId;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error($e);
                                    continue;
                                }
                            }

                        }
                        $count = count($add);
                        if (!$count) {
                            Log::info('Template[id:' . $tid . ']_inspection_add_data_null:' . $report_date);
                        } else {
                            Log::info('Template[id:' . $tid . ']_inspection_add_data:' . $report_date . ',count:' . $count . 'asset_ids:' . json_encode($add));
                        }
                    }
                }
            }
        }

        return true;
    }


    /**
     * 根据模板获取报告分类
     * @param $tid 模板id
     * @param string $type 默认：空，m:监控
     * @return array
     */
    public function getReportCategory($input=array()){
        $result = array();
        $tid = isset($input['tid']) ? $input['tid'] : 0;
        $type = isset($input['type']) ? $input['type'] : '';
        $type = $type ? $type : '';
        $default = isset($input['default']) ? $input['default'] : '';
        //根据默认模板获取模板id
        if($default) {
            $where = array('is_default' => $default);
            $tData = $this->emiTemplateModel->select('id')->where($where)->first();
            $tid = isset($tData['id']) ? $tData['id'] : '';
        }
        $where = array('id' => $tid);
        if(!$tid) {
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }
        if ($tid) {
            $data = $this->common->getEmiTemplateOne($where);
            $content = isset($data['content']) ? $data['content'] : array();
            $mcontent = isset($data['mcontent']) ? $data['mcontent'] : array();

                if('1' == $type){
                    $result['content'] = $content;
                }else{
                    $mData = $this->common->getMCategoryAllSelect($mcontent);
                    if('2' == $type){
                        $result['mcontent'] = $mData;
                    }else{
                        $result = array(
                            'content' => $content,
                            'mcontent' => $mData
                        );
                    }
                }
        }



        return $result;
    }


    /**
     * 获取模板配置
     * @param int tid 模板id
     * @param string reportDate 时间
     * @param int default 1:监控,2:环控
     * @return array
     */
    public function getReportTemplate($input=array()){
        $result = array();
        $tid = isset($input['tid']) ? $input['tid'] : 0;
        $reportDate = isset($input['reportDate']) ? trim($input['reportDate']) : '';
        $default = isset($input['default']) ? $input['default'] : 1;
        //根据默认模板获取模板id
        if ($default) {
            $where = array('is_default' => $default);
            $tData = $this->emiTemplateModel->select('id')->where($where)->first();
            $tid = isset($tData['id']) ? $tData['id'] : '';
        }
        if (!$tid) {
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }
        if (!$reportDate) {
            Code::setCode(Code::ERR_NOT_REPORTDATE);
            return false;
        }
        if ($tid) {
            $where = array(
                'template_id' => $tid,
                'report_date' => $reportDate,
                'is_template'=>1
            );
            $res = $this->model->where($where)->first();
            $res = $res ? $res->toArray() : array();
            $mcontent = isset($res['content']) ? json_decode($res['content'],true) : [];
            $mData = $this->common->getMCategoryAllSelect($mcontent);
            $result = array('mcontent' => $mData);
        }
        return $result;
    }















}
