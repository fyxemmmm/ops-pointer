<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/3/2
 * Time: 10:01
 */

namespace App\Repositories\Monitor;

use Illuminate\Http\Request;
use App\Repositories\BaseRepository;
use App\Models\Monitor\AssetsMonitor;
use App\Models\Monitor\MonitorAlert;
use App\Models\Monitor\MonitorCurrentAlert;
use App\Models\Assets\Category;
use App\Models\Assets\Device;
use App\Models\Monitor\Inspection;
use App\Models\Monitor\EmMonitorpoint;
use App\Models\Monitor\EmInspectionTemplate;
use App\Models\Workflow\Event;

use App\Models\Monitor\EmInspection;
use Auth;

use App\Models\Code;
use App\Exceptions\ApiException;
use Log;

class CommonRepository extends BaseRepository
{
    const DEF_RETURN = array('result' => '');
    protected $request;
    protected $assetsmonitorModel;
    protected $hengwei;
    protected $monitoralert;
    protected $monitoralertModel;
    protected $monitorcurrentalert;
    protected $monitorcurrentalertModel;
    protected $categoryModel;
    protected $deviceModel;
    protected $inspectionModel;
    protected $emMonitorpointModel;
    protected $emiTemplateModel;
    protected $emInspectionModel;
    protected $eventModel;

    public function __construct(Request $request,
                                AssetsMonitor $assetsmonitorModel,
                                HengweiRepository $hengwei,
                                MonitorAlertRepository $monitoralert,
                                MonitorAlert $monitoralertModel,
                                MonitorCurrentAlertRepository $monitorcurrentalert,
                                MonitorCurrentAlert $monitorcurrentalertModel,
                                Category $categoryModel,
                                Device $deviceModel,
                                Inspection $inspectionModel,
                                EmMonitorpoint $emMonitorpointModel,
                                EmInspectionTemplate $emiTemplateModel,
                                EmInspection $emInspectionModel,
                                Event $eventModel
    ){
        $this->request = $request;
        $this->assetsmonitorModel = $assetsmonitorModel;
        $this->hengwei = $hengwei;
        $this->monitoralert = $monitoralert;
        $this->monitoralertModel = $monitoralertModel;
        $this->monitorcurrentalert = $monitorcurrentalert;
        $this->monitorcurrentalertModel = $monitorcurrentalertModel;
        $this->categoryModel = $categoryModel;
        $this->deviceModel = $deviceModel;
        $this->inspectionModel = $inspectionModel;
        $this->emMonitorpointModel = $emMonitorpointModel;
        $this->emiTemplateModel = $emiTemplateModel;
        $this->emInspectionModel = $emInspectionModel;
        $this->eventModel = $eventModel;
    }


    public function formatJson($json='')
    {
        $result = array();
        if($json){
            $data = json_decode($json,true);
            if(isset($data['error'])){
                dd($data);
                Log::error("zabbix call error ",["error" => $data]);
                throw new ApiException(Code::ERR_ZABBIX_RET);
//                $result['error'] = isset($data['error'])?$data['error']:array();
            }else{
                $result['result'] = isset($data['result'])?$data['result']:array();
            }
        }
        return $result;
    }


    public function formatJsonHistory($json='',$itemData=array())
    {
        $result = array();
        if($json){
            $data = json_decode($json,true);
            if(isset($data['error'])){
                $result['error'] = isset($data['error'])?$data['error']:array();
            }else{
                $tmp = array();
                $rs = isset($data['result'])?$data['result']:array();
//                var_dump($rs);exit;
                if($rs){
//                    var_dump($itemData);exit;
                    foreach($rs as $val){
                        $itemid = isset($val['itemid'])?$val['itemid']:'';
//                        echo $itemid;exit;
                        $name = isset($itemData[$itemid])?$itemData[$itemid]:'';
                        if(!isset($tmp[$itemid])){
                            $tmp[$itemid]['itemid'] = $itemid;
                            $tmp[$itemid]['name'] = $name;
                            $tmp[$itemid]['data'][] = array(
                                'clock' => $val['clock'],
                                'value' => $val['value'],
                                'ns' => $val['ns']
                            );
                        }else{
                            $tmp[$itemid]['itemid'] = $itemid;
                            $tmp[$itemid]['name'] = $name;
                            $tmp[$itemid]['data'][] = array(
                                'clock' => $val['clock'],
                                'value' => $val['value'],
                                'ns' => $val['ns']
                            );
                        }
                    }
                }


                $result['result'] = $tmp;
            }
        }
        return $result;
    }


    public function __getZbxHosts($input = array()){
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'host.get',
            'params' => $input,
            /*array(
            "fillte"=>array("host"=>array("Zabbix server","Linux server"))
        ),*/
            'auth' => \ConstInc::ZABBIX_API_AUTH,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;
        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        return $res;
    }


    public function __addZbxHosts($input){
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'host.create',
            'params' => $input,
            'auth' => \ConstInc::ZABBIX_API_AUTH,
            'id' => 0
        );
//        $jsonParam = json_encode($input);

        /*

        {
            "host": "test server",
            "interfaces": [
                {
                    "type": 1,
                    "main": 1,
                    "useip": 1,
                    "ip": "172.17.0.4",
                    "dns": "",
                    "port": "10050"
                }
            ],
            "groups": [
                {
                    "groupid": "2"
                }
            ],
            "templates": [
                {
                    "templateid": "10001"
                }
            ],
            "inventory_mode": 0,
            "inventory": {
                "macaddress_a": "01234",
                "macaddress_b": "56768"
            }
        }
         */

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;
        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
//        echo $res;exit;
        return $res;
    }


    public function login($input=array()){
        $result = array();
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => 'user.login',
            'params' => $input,
            'id' => 0
        );
//        var_dump($param);exit;
        $jsonParam = json_encode($param);
        //????????????params
        /*
         {
            "user": "admin",
            "password": "sysdev@yl123"
        }
        */
        if($input) {
            $resJson = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
            $result = $this->formatJson($resJson);
        }
        return $result;
    }


    /**
     * ?????????????????????cpu/memory/disk??????
     * @return array
     */
    public function getHWDevicePerformance(){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }

        $data = [];
        $where = array('core' => AssetsMonitor::CORE_SERVER); //????????????????????????
        $result = $this->assetsmonitorModel->where($where)->select(["asset_id","device_id"])->get();

        if(empty($result)) {
            Code::setCode(Code::ERR_BIND_ASSETS_NOT);
            return false;
        }
        $deviceId = $result->pluck("device_id","asset_id")->toArray();

        foreach($deviceId as $k => $v) {
            $deviceData = $this->hengwei->getDevicePerformanceById(["deviceId" => $v]);
            if(false === $deviceData) {
                return false;
            }
            $deviceData['asset_id'] = $k;
            $data[$k]['device'] = $deviceData;
        }
        $res['result'] = array_values($data);
        return $res;
    }

    /**
     * ????????????deviceId?????????????????????
     * @param array $input
     * @return array|bool
     */
    public function getHWDevicePerformanceById($input=array()) {
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_HW_MONITOR_CLOSEED);
            return false;
        }

        $deviceData = $this->hengwei->getDevicePerformanceById($input);
        return $deviceData;
    }


    /**
     * ????????????ID????????????
     * @param $input
     * @return bool|string
     */
    public function getDeviceById($input){
        $data = array();
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $device_id = isset($input['deviceId']) ? trim($input['deviceId']) : '';

        if(!$device_id){
            if(!$assetId){
                Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
                return false;
            }
            if($assetId) {
                $where = array('asset_id' => $assetId);
                // ???????????? id??????????????????????????? ID
                $data = $this->assetsmonitorModel->where($where)->first();
            }
            if(empty($data)) {
                Code::setCode(Code::ERR_BIND_ASSETS_NOT);
                return false;
            }

            $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : 0;
        }

        // ???????????? ID ?????????????????????????????????????????????
        $result = $this->hengwei->getDeviceById($input);
        return $result;
    }



    /**
     * ????????????ID????????????
     * @param $input
     * @return bool|string
     */
    public function getDeviceCpu($input){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $data = array();
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $device_id = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        $input['type'] = 'cpu2';

        if(!$assetId){
            Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
            return false;
        }
        if($assetId) {
            $where = array('asset_id' => $assetId);
            $data = $this->assetsmonitorModel->where($where)->first();
        }
        if(empty($data)) {
            Code::setCode(Code::ERR_BIND_ASSETS_NOT);
            return false;
        }
        if(!$device_id){
            $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : 0;
        }
        $result['result'] = $this->hengwei->getOneByIdType($input);
        return $result;
    }


    /**
     * ????????????ID??????????????????
     * @param $input
     * @return bool|string
     */
    public function getDeviceStatus($input=array()){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $data = array();
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $device_id = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        $input['type'] = 'icmp_test';
        if(!$device_id){
            if(!$assetId){
                Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
                return false;
            }
            if($assetId) {
                $where = array('asset_id' => $assetId);
                $data = $this->assetsmonitorModel->where($where)->first();
            }
            if(empty($data)) {
                Code::setCode(Code::ERR_BIND_ASSETS_NOT);
                return false;
            }

            $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : 0;
        }

        // ????????????ID????????????????????????
        $icmp_test = $this->hengwei->getOneByIdType($input);
//        var_dump($icmp_test);exit;
//        Log::info("get icmp_test:".json_encode($icmp_test));
        $result = isset($icmp_test['result']) && $icmp_test['result'] ? true : false;
        return $result;
    }



    /**
     * ????????????ID?????????????????????????????????(??????)
     * @param $input
     * @return bool|string
     */
    public function getDataCommon($input){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $data = array();
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $device_id = isset($input['deviceId']) ? trim($input['deviceId']) : '';

        if(!$assetId){
            Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
            return false;
        }
        if($assetId) {
            $where = array('asset_id' => $assetId);
            $data = $this->assetsmonitorModel->where($where)->first();
        }
        if(empty($data)) {
            Code::setCode(Code::ERR_BIND_ASSETS_NOT);
            return false;
        }
        if(!$device_id){
            $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : 0;
        }
//        var_dump($input);
        $res = $this->hengwei->getDataByIdTypeDate($input);
        $result['result'] = $res ? $res : '';
        return $result;
    }


    /**
     * ????????????????????????
     * @param $input
     * @return string
     * @deprecated
     */
    public function __getAlertHistory($input){
        $input['limit'] = isset($input['per_page']) ? intval($input['per_page']) : 10;
        $res = $this->hengwei->getAlertHistory($input);
        return $res;
    }


    /**
     * ????????????????????????
     * @param $input
     * @return mixed
     */
    public function getAlertList($input){
        $input['limit'] = isset($input['per_page']) ? intval($input['per_page']) : 10;
        $res = $this->hengwei->getAlertList($input);
        return $res;
    }

    /**
     * ?????????????????????????????????
     * @param $input
     * @return mixed
     */
    public function getAlertListExtra($input){
        $input['limit'] = isset($input['per_page']) ? intval($input['per_page']) : 10;
        $res = $this->hengwei->getAlertList($input);
        $result = [];
        if($res['total'] > 0) {
            foreach($res['result'] as $v) {
                $eventId[] = $v['event_id'];
            }
            $ret = $this->eventModel->leftJoin("users","user_id","=","users.id")->where("source","=",Event::SRC_MONITOR)->whereIn("alert_event_id",$eventId)->get()->pluck("username","alert_event_id");
            $result = [];
            foreach($res['result'] as $v) {
                $result[] = [
                    "triggered_at" => $v['triggered_at'],
                    "level_msg" => MonitorAlert::$levelMsg[$v['level']],
                    "content" => $v['content'],
                    "status_msg" => $v['status'] == 0 ? "?????????" : "??????",
                    "username" => $ret[$v['event_id']]
                ];
            }
        }
        $data = ['meta' => [], 'result' => $result];
        $data['meta'] = [];
        $data['meta']['fields'] = [
            ["sname" => "triggered_at" , "cname" => "??????"],
            ["sname" => "level_msg" , "cname" => "??????"],
            ["sname" => "content" , "cname" => "??????"],
            ["sname" => "status_msg" , "cname" => "??????"],
            ["sname" => "username" , "cname" => "?????????"],
        ];

        $page = [];
        $page['total'] = $res['total'];
        $page['limit'] = isset($input['limit']) && $input['limit'] ? intval($input['limit']) : 10;
        $page['page'] = isset($input['page']) ? intval($input['page']) : '';
        $page['offset'] = $offset = isset($input['offset']) ? $input['offset'] : '';
        $page['pageall'] = isset($input['pageall']) ? trim($input['pageall']) : '';
        $data['meta']['pagination'] = $page;

        return $data;
    }


    /**
     * ????????????24????????????????????????
     * @param array $input
     * @return array
     */
    public function getAlertDayLevel($input=array()){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $date = date('Y-m-d H:i:s');
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : date('Y-m-d H:i:s');
        $begin = !$begin && !$edate  ? date('Y-m-d H:i:s',time()-86400) : $begin;

        if(!$begin){
            if(!$edate) {
                $begin = date('Y-m-d H:i:s', time() - 86400);
            }else{
                $begin = date('Y-m-d H:i:s', strtotime($edate) - 86400);
            }
        }

        $input['begin'] = $begin;
        $input['end'] = $edate ? $edate : $date;
        $resData = $this->hengwei->getAlertList($input);
        $res = isset($resData['result']) ? $resData['result'] : array();

        $result = array();
        $levelArr = array();
        if($res){
            foreach($res as $v){
                $id = isset($v['id']) ? $v['id'] : '';
                $level= isset($v['level']) ? $v['level'] : '';
                if(isset($levelArr[$level])){
                    $levelArr[$level][] = $id;
                }else{
                    $levelArr[$level][] = $id;
                }
            }
        }
        if($levelArr){
            $levelNames = $this->hengwei->alertLevel;
            $i = 0;
            foreach($levelNames as $lid=>$lname){
                $result[$i] = array(
                    'level' => $lid,
                    'name' => $lname,
                    'count' => 0
                );
                if(isset($levelArr[$lid])) {
                    $result[$i]['count'] = count($levelArr[$lid]);
                }
                $i++;
            }
        }
//        var_dump($result);exit;
        return $result;
    }



    /**
     * ????????????ID???????????????????????????
     * @param int $deviceId 1:???????????????,3:???????????????
     * @return bool|string
     */
    public function getNetworkFlow($deviceId = null){
        //??????????????????????????????
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }

        $input = [];
        if(!empty($deviceId)) {
            $input['deviceId'] = $deviceId;
        }
        else {
            $where = array('core' => AssetsMonitor::CORE_ROUTER); //?????????????????????
            $data = $this->assetsmonitorModel->where($where)->first();
            if(empty($data)) {
                Code::setCode(Code::ERR_BIND_ASSETS_NOT);
                return false;
            }
            $input['deviceId'] = $data['port'];
        }

        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $input['type'] = 'interface_flux';
        $resData = $this->hengwei->getDataByIdTypeDate($input);
        $res = [];
        if($resData){
            foreach($resData as $k=>$v){
                $time = isset($v['time']) ? strtotime($v['time']) : '';
                $res[$k] = array(
                    'ifInOctets' => isset($v['ifInOctets']) ? $v['ifInOctets'] : '',
                    'ifOutOctets' => isset($v['ifOutOctets']) ? $v['ifOutOctets'] : '',
                    'time' => $time,
                    'datetime' => date("Y-m-d H:i:s",$time)
                );
            }
        }
        $result['result'] = $res;
        return $result;
    }


    /**
     * ????????????ID???????????????ID????????????????????????????????????
     * @param $input
     * @return bool
     */
    public function getSwitchboard($input){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $device_id = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        if(!$assetId){
            Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
            return false;
        }

        $where = array('asset_id' => $assetId);
        $data = $this->assetsmonitorModel->where($where)->first();

        if(!$device_id){
            $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : 0;
        }
        $res['result'] = $this->hengwei->getSwitchboard($input);
        return $res;
    }

    /**
     * ???????????????????????????
     * @return bool
     */
    public function getCoreSwitch(){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }

        $result = $this->assetsmonitorModel->where(["core" => AssetsMonitor::CORE_SWITCH])->select(["asset_id","device_id","port"])->get();

        $deviceId = $result->pluck("device_id","asset_id")->toArray();

        $data = [];
        foreach($deviceId as $k => $v) {
            $deviceData = $this->getHWDevicePerformanceById(["deviceId" => $v]);
            if(false === $deviceData) {
                return false;
            }
            $deviceData['asset_id'] = $k;
            $data[$k]['device'] = $deviceData;
        }

        $portId = $result->pluck("port","asset_id")->toArray();
        foreach($portId as $k => $v) {
            $netflow = $this->getNetworkFlow($v);
            if(false === $netflow) {
                return false;
            }
            $data[$k]['netflow'] = $netflow['result'];
        }

        $res['result'] = array_values($data);
        return $res;
    }


    /**
     * ????????????????????????ID???????????????????????????
     * @param $input
     * @return bool
     */
    public function getPortFlowData($input){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $deviceId = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        if(!$assetId){
            Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
            return false;
        }
        if($assetId) {
            $where = array('asset_id' => $assetId);
            $data = $this->assetsmonitorModel->where($where)->first();
        }
        if(!$deviceId){
            $input['deviceId'] = isset($data['port']) ? $data['port'] : 0;
        }
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $input['type'] = 'interface_flux';

        $resData = $this->hengwei->getDataByIdTypeDate($input);
        if($resData){
            foreach($resData as $k=>$v){
                $time = isset($v['time']) ? strtotime($v['time']) : '';
                $res[$k] = array(
                    'ifInOctets' => isset($v['ifInOctets']) ? $v['ifInOctets'] : '',
                    'ifOutOctets' => isset($v['ifOutOctets']) ? $v['ifOutOctets'] : '',
                    'time' => $time,
                    'datetime' => date("Y-m-d H:i:s",$time)
                );
            }
        }
        $result['result'] = isset($res) ? $res : '';
        return $result;
    }


    /**
     * ??????????????????????????????
     * @param $input
     * @return array
     */
    public function getAlertListSave($data=array(), $type = 1){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $result = array('add'=>array(),'update'=>array());
        $res = isset($data['result']) ? $data['result'] : array();;
        $insert = array();
        $updateIds = array();
        $ids = array();
        if($res){
            foreach($res as $v){
                $id = isset($v['id'])?$v['id']:0;
                $triggered_at = isset($v['triggered_at'])?strtotime($v['triggered_at']):'';
                $triggered_at = $triggered_at ? date("Y-m-d H:i:s",$triggered_at) : '';
                $where = array('alert_id'=>$id);
                $getOne = $this->monitoralert->getOne($where);
                $maID = isset($getOne['id']) ? $getOne['id'] : 0;
                if($id) {
                    $param = array(
                        'current_value'=>isset($v['current_value'])?$v['current_value']:'',
                        'content'=>isset($v['content'])?$v['content']:'',
                        'previous_status'=>isset($v['previous_status'])?$v['previous_status']:'',
                        'level'=>isset($v['level'])?$v['level']:'',
                        'event_id'=>isset($v['event_id'])?$v['event_id']:'',
                        'action_id'=>isset($v['action_id'])?$v['action_id']:'',
                        'triggered_at'=> $triggered_at,
                        'managed_id'=>isset($v['managed_id'])?$v['managed_id']:'',
                        'sequence_id'=>isset($v['sequence_id'])?$v['sequence_id']:'',
                        'status'=>isset($v['status'])?$v['status']:'',
                        'type' => $type
                    );
                    if(!$maID){
                        $param['alert_id'] = $id;
                        $insert[] = $param;
                        $ids[] = $id;
                    }else{
                        $up = $this->monitoralertModel->where($where)->update($param);
                        if($up) {
                            $updateIds[] = $id;
                        }
                    }

                }
            }
        }
        $add = $this->monitoralert->addBatch($insert);
        if($add){
            $result['add'] = $ids;
        }
        $result['update'] = $updateIds;
        return $result;

    }


    /**
     * ??????????????????????????????
     * @param $input
     * @return array
     */
    public function getCurrentAlertListSave($input=array(), $type = 1){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $result = array('add'=>array(),'update'=>array());
        $date = date("Y-m-d H:i:s");
        $mAlertTime = \ConstInc::$mAlertTime ? \ConstInc::$mAlertTime : 30;
        $begin = isset($input['begin']) ? date('Y-m-d H:i:s',strtotime($input['begin'])) : date("Y-m-d H:i:s",strtotime($date) - $mAlertTime);
        $end = isset($input['end']) ? $input['end'] : $date;
        $input = array(
            'begin' => $begin,
            'end' => $end,
            'pageall' => true,
        );
        $resData = $this->hengwei->getAlertList($input, $type);
        $res = isset($resData['result']) ? $resData['result'] : array();

        $insert = array();
        $updateIds = array();
        $ids = array();
        if($res){
            foreach($res as $v){
                $id = isset($v['id'])?$v['id']:0;
                $triggered_at = isset($v['triggered_at'])?strtotime($v['triggered_at']):'';
                $triggered_at = $triggered_at ? date("Y-m-d H:i:s",$triggered_at) : '';
                $where = array('alert_id'=>$id);
                $getOne = $this->monitorcurrentalert->getOne($where);
                $maID = isset($getOne['id']) ? $getOne['id'] : 0;
                if($id) {
                    $param = array(
                        'current_value'=>isset($v['current_value'])?$v['current_value']:'',
                        'content'=>isset($v['content'])?$v['content']:'',
                        'previous_status'=>isset($v['previous_status'])?$v['previous_status']:'',
                        'level'=>isset($v['level'])?$v['level']:'',
                        'event_id'=>isset($v['event_id'])?$v['event_id']:'',
                        'action_id'=>isset($v['action_id'])?$v['action_id']:'',
                        'triggered_at'=> $triggered_at,
                        'managed_id'=>isset($v['managed_id'])?$v['managed_id']:'',
                        'sequence_id'=>isset($v['sequence_id'])?$v['sequence_id']:'',
                        'status'=>isset($v['status'])?$v['status']:'',
                        'type' => $type
                    );
                    if(!$maID){
                        $param['alert_id'] = $id;
                        $insert[] = $param;
                        $ids[] = $id;
                    }else{
                        $up = $this->monitorcurrentalertModel->where($where)->update($param);
                        if($up) {
                            $updateIds[] = $id;
                        }
                    }

                }
            }
        }
        $add = $this->monitorcurrentalert->addBatch($insert);
        if($add){
            $result['add'] = $ids;
        }
        $result['update'] = $updateIds;
        return $result;

    }


    /**
     * ??????24??????????????????
     * @param array $input
     * @return array
     */
    public function getAlertCountListDay($input=array()){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
//            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
            $date = date('Y-m-d H:i:s');
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : date('Y-m-d H:i:s');
        $begin = !$begin && !$edate  ? date('Y-m-d H:i:s',time()-86400) : $begin;

        if(!$begin){
            if(!$edate) {
                $begin = date('Y-m-d H:i:s', time() - 86400);
            }else{
                $begin = date('Y-m-d H:i:s', strtotime($edate) - 86400);
            }
        }

        $end = $edate ? $edate : $date;
        $input['begin'] = $begin;
        $input['end'] = $end;
        $input['pageall'] = true;


        $sTime = strtotime($begin);
        $eTime = strtotime($end);
        $timeArr = array();
        for($i=$sTime;$i<=$eTime;$i+=3600){
            $ymdh = date('Y-m-d H:00',$i);
            $timeArr[] = array('time' => $ymdh);
        }
        $resData = $this->hengwei->getAlertHistory($input);
        $res = isset($resData['result']) ? $resData['result'] : array();

        if($timeArr) {
            foreach ($timeArr as $k => $t) {
                $time = isset($t['time']) ? $t['time'] : '';
                $count = 0;
                $count1 = 0;//?????????
                $count2 = 0;//??????
                $count3 = 0;//??????
                $count4 = 0;//??????
                $count5 = 0;//?????????
                $ids = array();
                $timeArr[$k]['level'] = array();
                if($res) {
                    foreach ($res as $v) {
                        $id = isset($v['id']) ? $v['id'] : '';
                        $triggered_at = isset($v['triggered_at']) ? strtotime($v['triggered_at']) : '';
                        $level = isset($v['level']) ? $v['level'] : '';
                        $triggered_at = $triggered_at ? date('Y-m-d H:00', $triggered_at) : '';
                        $sequence_id = isset($v['sequence_id']) ? $v['sequence_id'] : '';
                        if ($triggered_at == $time && 1 == $sequence_id) {
//                            $count++;
                            if(1 == $level){
                                $count1++;
                            }elseif(2 == $level){
                                $count2++;
                            }elseif(3 == $level){
                                $count3++;
                            }elseif(4 == $level){
                                $count4++;
                            }elseif(5 == $level){
                                $count5++;
                            }
                        }

                    }
                }
                $levelArr = array($count1,$count2,$count3,$count4,$count5);
                $timeArr[$k]['level'] = $levelArr;
            }
        }
        return $timeArr;
    }


    /**
     * ????????????????????????????????????
     * @param array $input
     * @return array
     */
    public function alertHistoryBatchSave($input=array(), $type = 1){
        //??????????????????????????????
        if(!\ConstInc::$mOpen){
            return false;
        }
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : date('Y-m-d H:i:s');
        $begin = $begin  ? $begin : '';

        $end = $edate ? $edate : '';
        $input['begin'] = $begin;
        $input['end'] = $end;
        if(!$begin){
            Code::setCode(Code::ERR_PARAMS,'????????????????????????');
            return false;
        }

        $hCount = $this->hengwei->getAlertHistoryCount($input, '', '', $type);
        $alertIDs = array();
        if($hCount) {
            $limit = 100;
            $pageCount = ceil($hCount/$limit);
            for($i=1;$i<=$pageCount;$i++) {
                $input['page'] = $i;
                $input['limit'] = $limit;
                $resData = $this->hengwei->getAlertHistory($input,false, $type);
                if($resData){
                    $testData[] = $resData;
                    $alertIDs[$i] = $this->getAlertListSave($resData, $type);
                }
            }
        }
        return $alertIDs;
    }


    /**
     * ??????????????????ID??????IP
     * @param array $input assetId???deviceId
     * @return string
     */
    public function getDeviceIP($input=array()){
        $data = $this->getDeviceById($input);
        $ip = isset($data['fields']['address']) ? $data['fields']['address'] : '';
        return $ip;
    }



    /**
     * ???????????????key=>val(id=>name)
     * @return mixed
     */
    public function getCategoryIdName(){
        $res = $this->categoryModel->get()->pluck('name','id')->all();
        return $res;
    }



    /**
     * ????????????
     * @return mixed
     */
    public function getEmDevices(){
        $fieldArrKeys = array(
            'assets_device.id',
            'assets_device.category_id',
            'assets_device.sub_category_id',
            'assets_device.number',
            'assets_device.name',
            'A.asset_id',
            'A.device_id',
            'A.var_name',
        );

        $where[] = ['A.asset_id','>',0];
        $data = $this->deviceModel
            ->select($fieldArrKeys)
            ->leftJoin('em_device as A','A.asset_id','=','assets_device.id')
            ->whereIn('assets_device.category_id',\ConstInc::$em_category_id)
            ->where($where)
            ->get();
        return $data;
    }


    /**
     * ??????????????????
     * @return array
     */
    public function getEmDeviceCategory($select=array()){

        $emDevice = $this->getEmDevices();
        // return $emDevice->toArray();
        $catgoryArr = $this->getCategoryIdName();
//        var_dump($emDevice->toArray(),$catgoryArr);exit;
//        var_dump($emDevice->toArray());exit;
        $result = array();
        if($emDevice){
            $category = array();
            $opArr = \ConstInc::$em_category_op;
            foreach($emDevice as $val){
                $subCategoryId = isset($val['sub_category_id']) ? $val['sub_category_id'] : '';
                $name = isset($catgoryArr[$subCategoryId]) ? $catgoryArr[$subCategoryId] : '';

                $type = $opArr && isset($opArr[$subCategoryId]) ? $opArr[$subCategoryId]: '';
                if(!isset($result[$subCategoryId])){
                    $category[$subCategoryId] = array(
                        "id" => $subCategoryId,
                        "name" => $name,
                        "type" => $type,
                        "child" => array()
                    );
                }else{
                    $category[$subCategoryId] = array(
                        "id" => $subCategoryId,
                        "name" => $name,
                        "type" => $type,
                        "child" => array()
                    );
                }
            }

            foreach($emDevice as $val){
                $id = isset($val['id']) ? $val['id'] : '';
                $name = isset($val['name']) ? $val['name'] : '';
                $number = isset($val['number']) ? $val['number'] : '';
                $device_id = isset($val['device_id']) ? $val['device_id'] : '';
                $var_name = isset($val['var_name']) ? $val['var_name'] : '';
                $categoryId = isset($val['category_id']) ? $val['category_id'] : '';
                $subCategoryId = isset($val['sub_category_id']) ? $val['sub_category_id'] : '';
                if($device_id) {
                    $is_show = 0;
                    $showBattery = isset($val['show_battery']) ? $val['show_battery'] : false;

                    $batterys = isset($val['batterys']) ? $val['batterys'] : array();
//                    foreach($select as $sv){
//                        $devices = isset($sv['child']) ? $sv['child'] : array();
//                        foreach($devices as $sdv){
//                            $sdid = isset($val['id']) ? $val['id'] : '';
//                            $is_show = $sdid == $id ? 1 : 0;
                            if (isset($category[$subCategoryId])) {
                                $category[$subCategoryId]['child'][] = array(
                                    "id" => $id,
                                    "category_id" => $categoryId,
                                    "sub_category_id" => $subCategoryId,
                                    "device_id" => $device_id,
                                    "name" => $name,
                                    "number" => $number,
                                    "var_name" => $var_name,
                                    "is_show" => $is_show,
                                    "child" => array(),
                                    "show_battery" => $showBattery,
                                    "batterys" => $batterys,
                                );
                            } else {
                                $category[$subCategoryId]['child'][] = array(
                                    "id" => $id,
                                    "category_id" => $categoryId,
                                    "sub_category_id" => $subCategoryId,
                                    "device_id" => $device_id,
                                    "name" => $name,
                                    "number" => $number,
                                    "var_name" => $var_name,
                                    "is_show" => $is_show,
                                    "child" => array(),
                                    "show_battery" => $showBattery,
                                    "batterys" => $batterys,
                                );
                            }
//                        }
//                    }
                }
            }

            /*foreach($category as $k=>$v){
                $device = isset($v['child']) ? $v['child'] : '';
                if(!$device){
                    unset($category[$k]);
                }
            }*/
            $result = $category;//array_values($category);
        }
        return $result;
    }


    /**
     * ??????????????????
     * @return array
     */
    public function getEmCategoryAll($select=array()){
        $result = array();
        $data = $this->categoryModel->where(["assets_category.pid" => \ConstInc::$em_category_id])
                                    ->join('menus','assets_category.id','=','menus.category_id')
                                    ->select('assets_category.id','menus.name')
                                    ->get();
        $data = $data ? $data->toArray() : array();
        if($data){
            // ??????????????????
            $emdCategory = $this->getEmDeviceCategory($select);
            foreach($data as $v){
                $id = getKey($v,'id','');
                $name = getKey($v,'name','');

                $category = isset($emdCategory[$id]) ? $emdCategory[$id] :array();
                $device = getKey($category,'child',array());
//                var_dump($select,$device);exit;
                //??????????????????????????????????????????is_show=1
                $isShow = false;
                if($select && is_array($select)) {
                    // dd($select);
                    foreach ($select as $mv) {
                        $mvid = getKey($mv,'id',0);
                        if($id != $mvid) {
                            continue;
                        }

                        $childs = isset($mv['child']) ? array_column($mv['child'],'id') : array();
                        // return $childs;
                        $ChildArr = getKey($mv,'child', array());
                        $isShow = getKey($mv,'is_show',false);

                        foreach ($device as $dk => $dv) {
                           // $device[$dk] = $dv;
                            $did = getKey($dv,'id',0);

                            if (in_array($did, $childs)) {
                                $device[$dk]['is_show'] = 1;
                            }
                            foreach($ChildArr as $kk=>$vv){
                                // dd($vv);
                                $vvid = getKey($vv,'id',0);
                                if($did == $vvid) {
                                    $device[$dk]['child'] = getKey($vv,'child',array());
                                    $device[$dk]['batterys'] = getKey($vv,'batterys',array());
                                    // ??????????????????????????? show_battery ???
                                    if(isset($vv['show_battery'])){
                                        $device[$dk]['show_battery'] = $vv['show_battery'];
                                    }
                                }
                            }
                        }
                    }
                }


                $result[] = array(
                    'id'=>$id,
                    'name'=>$name,
                    'type' => getKey($category,'type',''),
                    'is_show' => $isShow,
                    'child'=> $device
                );
            }
        }
        return $result;
    }



    /**
     * ???????????????????????????
     * @param int $deviceId
     * @return array
     */
    public function getEMPByDevice($input=array()){
        $res = array();
        $deviceId = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        $unit = isset($input['unit']) ? trim($input['unit']) : '';
        if(!$deviceId){
            Code::setCode(Code::ERR_PARAMS, '??????????????????');
            return false;
        }
        $where[] = ['status', '=', 1];
        $where[] = ['device_id','=',$deviceId];
        if($unit){
            $where[] = ['unit','=',$unit];
        }
        if ($where) {
            $res = $this->emMonitorpointModel->where($where)->get();
        }
        return $res;
    }





    /**
     * ????????????????????????
     * @param array $assetIds ??????id
     * @return mixed
     */
    public function getMDevices($assetIds=array()){
        $fieldArrKeys = array(
            'assets_device.id',
            'assets_device.category_id',
            'assets_device.sub_category_id',
            'assets_device.number',
            'assets_device.name',
            'A.asset_id',
            'A.device_id',
        );
        $where[] = ['A.asset_id','>',0];
        $model = $this->deviceModel
            ->select($fieldArrKeys)
            ->Join('assets_monitor as A','A.asset_id','=','assets_device.id')
            ->whereNull('A.deleted_at')
            ->where($where);
        if($assetIds){
            $model = $model->whereIn('A.asset_id',$assetIds);
        }
        $data = $model->get();
        return $data;
    }


    /**
     * ?????????????????????
     * @return array
     */
    public function getMDeviceCategory()
    {
        $mDevice = $this->getmDevices();
        $catgoryArr = $this->getCategoryIdName();
        $result = array();
        $category = array();
        if($mDevice) {
//            $opArr = \ConstInc::$em_category_op;
            foreach ($mDevice as $val) {
                $categoryId = isset($val['category_id']) ? $val['category_id'] : '';
                $name = isset($catgoryArr[$categoryId]) ? $catgoryArr[$categoryId] : '';

//                $type = $opArr && isset($opArr[$subCategoryId]) ? $opArr[$subCategoryId] : '';
                if (!isset($result[$categoryId])) {
                    $category[$categoryId] = array(
                        "id" => $categoryId,
                        "name" => $name,
                        "child" => array()
                    );
                } else {
                    $category[$categoryId] = array(
                        "id" => $categoryId,
                        "name" => $name,
                        "child" => array()
                    );
                }
            }

            foreach($mDevice as $val){
                $id = isset($val['id']) ? $val['id'] : '';
                $name = isset($val['name']) ? $val['name'] : '';
                $number = isset($val['number']) ? $val['number'] : '';
                $device_id = isset($val['device_id']) ? $val['device_id'] : '';
                $categoryId = isset($val['category_id']) ? $val['category_id'] : '';
                if($device_id) {
                    $is_show = 0;
                    if (isset($category[$categoryId])) {
                        $category[$categoryId]['child'][] = array(
                            "id" => $id,
                            "device_id" => $device_id,
                            "name" => $name,
                            "number" => $number,
                            "is_show" => $is_show,
                        );
                    } else {
                        $category[$categoryId]['child'][] = array(
                            "id" => $id,
                            "device_id" => $device_id,
                            "name" => $name,
                            "number" => $number,
                            "is_show" => $is_show,
                        );
                    }
                }
            }
        }
        return $category;
    }





    /**
     * ??????????????????
     * @return array
     */
    public function getMCategoryAll($mcontent=array(),$select=false){
        $result = array();
        $mdCategory = $this->getMDeviceCategory();
        $data = inspection::$categoryMsg;
//        var_dump($mdCategory,$data);exit;
        foreach($data as $k=>$v){
            $categoryDevice = isset($mdCategory[$k]['child']) ? $mdCategory[$k]['child'] : array();
            $device = $categoryDevice ? $categoryDevice : array();
            if($mcontent && is_array($mcontent)) {
                foreach ($mcontent as $mv) {
                    $id = isset($mv['id']) ? $mv['id'] : 0;
                    $childs = isset($mv['child']) ? $mv['child'] : array();
                    foreach ($categoryDevice as $dk => $dv) {
                        $did = isset($dv['id']) ? $dv['id'] : 0;
                        if ($id == $k) {
                            if (in_array($did, $childs)) {
                                $device[$dk]['is_show'] = 1;
                            }
                        }

                    }
                }
            }

            //??????????????????????????????
            $result[] = array(
                'id' => $k,
                'name' => $v,
                'child' => $device
            );

        }
        return $result;
    }


    /**
     * ?????????????????????????????????
     * @return array
     */
    public function getMCategoryAllSelect($mcontent=array()){
        $result = array();
        $mdCategory = $this->getMDeviceCategory();
        $data = inspection::$categoryMsg;
//        var_dump($mdCategory,$data);exit;
        foreach($data as $k=>$v) {
            $categoryDevice = isset($mdCategory[$k]['child']) ? $mdCategory[$k]['child'] : array();
            $device = $mcontent ? $categoryDevice : array();
            $flag = false;
            if ($mcontent && is_array($mcontent)) {
                foreach ($mcontent as $mv) {
                    $id = isset($mv['id']) ? $mv['id'] : 0;
                    $childs = isset($mv['child']) ? $mv['child'] : array();
                    foreach ($categoryDevice as $dk => $dv) {
                        $did = isset($dv['id']) ? $dv['id'] : 0;
                        if ($id == $k) {
                            if (in_array($did, $childs)) {
                                $flag = true;
                                $device[$dk]['is_show'] = 1;
                            }else{
                                unset($device[$dk]);
                            }
                        }
                    }

                }
            }
            //????????????????????????????????????
            if ($device && $flag) {
                $result[] = array(
                    'id' => $k,
                    'name' => $v,
                    'child' => array_values($device)
                );
            }

        }
        return $result;
    }


    /**
     * ??????????????????????????????
     * @return mixed
     */
    public function getTemplateList($type='',$where=array(),$fields=array()){
        //???????????????????????????
        if(!\ConstInc::$emOpen && !\ConstInc::$mOpen) {
            throw new ApiException(Code::ERR_EM_M_MONITOR_CLOSEED);
        }

        $model = $this->emiTemplateModel;
        if($where){
            $model = $model->where($where);
        }
        if($fields){
            $model = $model->select($fields);
        }
        if('all' == $type) {
            $data = $model->get();
        }else{
            $data = $this->usePage($model);
        }
        if($data){
            foreach($data as $k=>$v){
                if(isset($v['content'])) {
                    $v['content'] = \ConstInc::$emOpen ? json_decode($v['content'],true) : array();
                }
                if(isset($v['mcontent'])) {
                    $v['mcontent'] = \ConstInc::$mOpen ? json_decode($v['mcontent'],true) : array();
                }
            }
        }
        return $data;
    }


    /**
     * ????????????????????????????????????
     * @param array $where
     * @return array
     */
    public function getEmiTemplateOne($where=array(),$fields=''){
        $res = array();
        if($where) {
            $model = $this->emiTemplateModel;
            if($fields){
                $model = $model->select($fields);
            }
            $res = $model->where($where)->first();
            $res['content'] = isset($res['content']) ? json_decode($res['content'], true) : array();
            $res['mcontent'] = isset($res['mcontent']) ? json_decode($res['mcontent'], true) : array();
        }
        return $res;
    }


    /**
     * ??????????????????
     * @return array|mixed
     */
    public function getTemplate($input=array()){
        $default = isset($input['default']) ? $input['default'] : '';
        $tData = [];
        if($default) {
            $where = array('is_default' => $default);
            $tData = $this->getTemplateList('all', $where, array('id', 'it_name'));
            $tData = $tData ? $tData->toArray() : array();
        }
        if(!$tData){
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }
//        $tids = array();
//        if($tData){
//            $tids = array_column($tData,'id');
//        }
        foreach($tData as $k=>$v){
            $id = isset($v['id']) ? $v['id'] : 0;

            $inspectionModel = $this->inspectionModel;
            $emInspectionModel = $this->emInspectionModel;

            //??????
            $inspectionModelRes = \ConstInc::$mOpen ? $this->getReportDateByTid($id,\ConstInc::$mTemplateReportMax,$inspectionModel) : array();

            //??????
            $emInspectionModelRes = \ConstInc::$emOpen ? $this->getReportDateByTid($id,\ConstInc::$mTemplateReportMax,$emInspectionModel) : array();

            // $inspectionModelRes ????????? $emInspectionModelRes ??????????????????
            if(array_key_exists($id,$inspectionModelRes) && array_key_exists($id,$emInspectionModelRes)){
                // ???????????????????????????
                $newarray = array_unique(array_merge($inspectionModelRes[$id],$emInspectionModelRes[$id]));

                // ??????????????????????????????
                rsort($newarray);
                // ??????????????????????????????????????????20???
                $array = array_slice($newarray,0,\ConstInc::$mTemplateReportMax);
            }else{
                // $inspectionModelRes???$emInspectionModelRes???????????????
                if(empty($inspectionModelRes) && empty($emInspectionModelRes)){
                    $array = array();
                }else{
                    // $inspectionModelRes ???????????? $emInspectionModelRes ??????????????????????????????
                    if(empty($inspectionModelRes)){
                        $array = $emInspectionModelRes[$id];
                    }else{
                        $array = $inspectionModelRes[$id];
                    }
                }
            }
            $reportDates = array();
            if($array) {
                foreach ($array as $val) {
//                    var_dump(time($val));
                    $dh = explode(':',$val);
                    $d = isset($dh[0]) ? $dh[0] : '';
                    $h = isset($dh[1]) ? $dh[1] : '';
                    $report_date_name = $val ? date('Y???m???d??? H???', strtotime($d.$h.'0000')) : '';
//                    $report_date_name = strtotime($val);
                    $reportDates[] = array('id' => $val, 'name' => $report_date_name);
                }
            }

            $tData[$k]['child'] = isset($reportDates) ? $reportDates : array();
        }
        return $tData;
    }


    /**
     * ????????????id?????????????????????
     * @param int $tid
     * @param int $limit
     * @return bool
     */
    public function getReportDateByTid($tid=0,$limit=0,$model){
        $res = array();
        //$model = $this->inspectionModel;
        if(!$tid){
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }
        $where = array('template_id'=>$tid);
        $model = $model->where($where);
        if($limit){
            $model = $model->limit($limit);
        }

        $data = $model->selectRaw("distinct report_date,template_id")->orderBy('report_date','desc')->get();
        if($data){
            foreach($data as $v){
                $template_id = isset($v['template_id']) ? $v['template_id'] : '';
                $report_date = isset($v['report_date']) ? $v['report_date'] : '';
                if($report_date){
                    if(!isset($res[$template_id])){
                        $res[$template_id][] = $report_date;
                    }else{
                        $res[$template_id][] = $report_date;
                    }
                }
            }
        }
        return $res;

    }



    /**
     * ????????????ID????????????????????????
     * @param $input
     * @return bool|string
     */
    public function getDeviceWorkDays($input=array()){
        //??????????????????????????????
        if(!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $data = array();
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $device_id = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        $input['type'] = 'uptime';
        if(!$device_id){
            if(!$assetId){
                Code::setCode(Code::ERR_PARAMS,'??????ID????????????');
                return false;
            }
            if($assetId) {
                $where = array('asset_id' => $assetId);
                $data = $this->assetsmonitorModel->where($where)->first();
            }
            if(empty($data)) {
                Code::setCode(Code::ERR_BIND_ASSETS_NOT);
                return false;
            }

            $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : 0;
        }
        $res = $this->hengwei->getOneByIdType($input);
//        var_dump($icmp_test);exit;
//        Log::info("get icmp_test:".json_encode($icmp_test));
        $result['result'] = isset($res['value']) && $res['value'] ? $res['value'] : '';
        return $result;
    }


    /**
     * ???????????????????????????
     * @return array
     */
    public function getUnbindDeviceList(){
        $result = array();
        $assetm = $this->assetsmonitorModel->get();
        $assetArr = array();
        if($assetm) {
            $assetm = $assetm->toArray();
            foreach($assetm as $v){
                $aid = getKey($v,'id');
                $assetArr[$aid] = getKey($v,'device_id');
            }

        }
        //????????????
        $nDeviceCount = $this->hengwei->getNetworkDeviceCount();
        $limit = \ConstInc::$mPages;
//        $nDevice = array();
        $ids = array();
        $diff = array();

        $pageMax = ceil($nDeviceCount/$limit);
//        var_dump($nDeviceCount,$pageMax);exit;
        if($pageMax) {
            //???????????????
            for ($i = 1; $i <= $pageMax; $i++) {
                $offset = ($i - 1) * $limit;
                $nDevice = $this->getNetworkDeviceFormat($offset, $limit);
                if ($nDevice) {
                    foreach ($nDevice as $v) {
                        $id = getKey($v, 'id');
                        $ids[$id] = $id;
                        if (!in_array($id, $assetArr)) {
                            $result[$id] = $v;
                        }
                    }
                }
            }
            $diff = array_diff($assetArr,$ids);
        }
        if($diff) {
            //?????????????????????????????????????????????????????????
            $keyId = array_keys($diff);
            $this->assetsmonitorModel->whereIn('id',$keyId)->delete();
        }

//        return $nDevice;
        return array_merge($result,array());

    }


    /**
     * ???????????????????????????
     * @return array
     */
    public function getNetworkDeviceFormat(){
        $result = array();
        $nDevice = $this->hengwei->getNetworkDevice($offset=0,$limit=0);
//        return $nDevice;
        if($nDevice){
            foreach($nDevice as $k=>$v){
                $fileds = getKey($v,'fields');
                $result[$k] = array(
                    'id' => getKey($v,'id'),
                    'custom_name' => getKey($v,'custom_name'),
                    'display_name' => getKey($fileds,'display_name'),
                    'name' => getKey($fileds,'name'),
                    'ip' => getKey($fileds,'address'),
                    'level' => getKey($fileds,'level'),
                );
            }
        }

        return $result;

    }











}