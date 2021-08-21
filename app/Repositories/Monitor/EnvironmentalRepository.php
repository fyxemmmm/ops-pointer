<?php
/**
 * Created by PhpStorm.
 * 环境监控
 * User: wangwei
 * Date: 2018/3/28
 * Time: 16:05
 */

namespace App\Repositories\Monitor;

use App\Repositories\BaseRepository;
use App\Models\Monitor\EmMonitorpoint;
use App\Models\Monitor\EmRealtimeData;
use App\Models\Monitor\EmDevice;
use App\Models\Monitor\EmInspectionTemplate;
use App\Models\Monitor\EmAlarm;
use App\Repositories\Monitor\EmWanlianRepository;
use App\Repositories\Monitor\CommonRepository;
use App\Models\Code;
use App\Models\Monitor\EmInspection;
use App\Exceptions\ApiException;
use DB;
use App\Models\Auth\DataSource;
use App\Repositories\Workflow\EventsRepository;
use Log;
use App\Models\Workflow\Event;
use App\Repositories\Weixin\CommonRepository as WxCommonRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Weixin\QyWxUserRepository;
use App\Models\Assets\Device;
use App\Repositories\Auth\UserRepository;

class EnvironmentalRepository extends BaseRepository
{
    protected $emMonitorpointModel;
    protected $emrealtimedataModel;
    protected $emdeviceModel;
    protected $emwanlian;
    protected $emiTemplateModel;
    protected $eminspectionModel;
    protected $common;
    protected $emAlarmModel;
    protected $eventsRepository;
    protected $weixinuser;
    protected $wxcommon;
    protected $qywxuser;
    protected $hostName;
    protected $userRepository;

    public function __construct(EmMonitorpoint $emmonitorpointModel,
                                EmRealtimeData $emrealtimedataModel,
                                EmDevice $emdeviceModel,
                                EmWanlianRepository $emwanlian,
                                EmInspectionTemplate $emiTemplateModel,
                                EmInspection $eminspectionModel,
                                CommonRepository $common,
                                DataSource $dataSourceModel,
                                EmAlarm $emAlarmModel,
                                EventsRepository $eventsRepository,
                                WeixinUserRepository $weixinuser,
                                QyWxUserRepository $qywxuser,
                                WxCommonRepository $wxcommon,
                                userRepository $userRepository
    )
    {
        $this->emMonitorpointModel = $emmonitorpointModel;
        $this->emrealtimedataModel = $emrealtimedataModel;
        $this->emdeviceModel = $emdeviceModel;
        $this->emwanlian = $emwanlian;
        $this->emiTemplateModel = $emiTemplateModel;
        $this->eminspectionModel = $eminspectionModel;
        $this->common = $common;
        $this->dataSourceModel = $dataSourceModel;
        $this->emAlarmModel = $emAlarmModel;
        $this->eventsRepository = $eventsRepository;
        $this->weixinuser = $weixinuser;
        $this->wxcommon = $wxcommon;

        $this->hostName = config("app.wx_url");
        $this->qywxuser = $qywxuser;
        $this->userRepository = $userRepository;
    }


    /**
     * 数据来源：1:默顿尔,2:万联,3:中联通
     * 添加环控设备
     * @return mixed
     */
    public function addDevices(){
        $result = array();
        $deviceRes = array();
        switch(\ConstInc::EM_DATA_SOURCE){
            //1:默顿尔
            case '1':
                $sqlsrv = DB::connection('sqlsrvdata');
                $devicesObj = $sqlsrv->table('device')->get();
                $deviceRes = json_decode($devicesObj,true);
                break;
            //2:万联
            case '2':
                $wldevice = $this->emwanlian->WLGetDevice();
//                var_dump($wldevice);exit;
                if($wldevice) {
                    foreach ($wldevice as $k=>$v) {
                        $deviceType = isset($v['SUBTYPE']) ? $v['SUBTYPE'] : '';
                        $ip = isset($v['IP']) ? $v['IP'] : '';
                        if(!in_array($deviceType,array(1,101)) && $ip) {
                            $deviceRes[$k] = array(
                                'deviceid' => $ip,
                                'devicename' => isset($v['Name']) ? $v['Name'] : '',
                                'devicetype' => $deviceType,
                                'varname' => isset($v['id']) ? $v['id'] : '',
                            );
                        }
                    }
                }
                break;
            //3:中联通
            case '3':
                $sqlsrv = DB::connection('sqlsrv');
                $devicesObj = $sqlsrv->table('Alldevices')->get();
                $deviceRes = $devicesObj ? $devicesObj->toArray() : '';

                break;
        }

//        var_dump($deviceRes);exit;

        $create = array();
        $update = array();
        if($deviceRes) {
            foreach ($deviceRes as $val) {
//                var_dump($val);exit;
                $devicetype = '';
                $deviceModel = '';
                $devicenotes = '';
                $varname = '';
                if(3 == \ConstInc::EM_DATA_SOURCE) {
                    $val = (array)$val;
//                    var_dump($val);exit;
                    $deviceId = $val['deviceId'] ? $val['deviceId'] : '';
                    $devicename = $val['deviceName'] ? $val['deviceName'] : '';
                    $deviceModel = $val['deviceModel'] ? $val['deviceModel'] : '';
                    $devicenotes = $val['notes'] ? $val['notes'] : '';
                }else {
                    $deviceId = isset($val['deviceid']) ? $val['deviceid'] : '';
                    $devicename = isset($val['devicename']) ? $val['devicename'] : '';
                    $devicetype = isset($val['devicetype']) ? $val['devicetype'] : '';
                    $varname = isset($val['varname']) ? $val['varname'] : '';
                }
                $getData = $this->emdeviceModel->where(["device_id" => $deviceId])->first();
                $id = isset($getData['id'])?$getData['id']:0;
                $param = array(
                    'device_id' => $deviceId,
                    'name' => $devicename,
                    'var_name' => $varname,
                    'device_type' => $devicetype,
                    'model' => $deviceModel,
                    'notes' => $devicenotes,

                );
//                var_dump($param);exit;

                if($deviceId) {
                    if (!$id) {
                        $res = $this->emdeviceModel->create($param);
                        if ($res) {
                            $create[] = $res['id'] ? $res['id'] : 0;
                        }
                    } else {
                        $up = $this->emdeviceModel->where(['id' => $id])->update($param);
                        if ($up) {
                            $update[] = $id;
                        }
                    }
                }
            }
            $result = array('create'=>count($create),'update'=>count($update));
        }
        return $result;

    }





    /**
     * 获取环境监控监控点列表并添加到数据库
     * @param $input
     */
    public function monitorPoint($input=array(),$auto=false) {
        $data = array();

        $assetId = isset($input['assetId']) ? $input['assetId'] : 0;
        $deviceId = isset($input['device_id']) ? $input['device_id'] : 0;
        if($assetId) {
            if($assetId) {
                $where[] = ['asset_id', '=', $assetId];
            }
            $res = $this->getDeviceOne($where);
            $res = $res ? $res->toArray() : array();
            $deviceId = isset($res['device_id']) ? $res['device_id'] : '';
        }

        switch(\ConstInc::EM_DATA_SOURCE){
            case '1':
                $data = $this->getDevicesVariate();
                break;
            case '2':
                $data = $this->getWLMonitorPoint($deviceId,$auto);
                break;

        }
//        return $data;

        $createArr = array();
        $updateArr = array();
        if ($data) {
            $pointArr = [];
            $pointArrSource = [];
            if($deviceId) {
                $where = array('device_id'=>$deviceId);
                $empoint = $this->getEmPointList($where);
                $empointArr = $empoint?$empoint->toArray():[];
                $pointArr = array_column($empointArr,'var_id');
            }
            foreach ($data as $val) {
//                var_dump($val);exit;
                $var_id = isset($val['var_id'])?$val['var_id']:'';
                if($var_id) {
                    $pointArrSource[] = $var_id;
                }
                $res = $this->addMonitorPointOne($val);
                $create = isset($res['create']) ? $res['create'] : '';
                $update = isset($res['update']) ? $res['update'] : '';
                if($create){
                    $createArr[] = $create;
                }elseif($update){
                    $updateArr[] = $update;
                }
            }
            //删除监控点
            $diff = array_diff($pointArr,$pointArrSource);
            if($diff&&$deviceId){
                $whereDel = array('device_id'=>$deviceId);
                $this->emMonitorpointModel->where($whereDel)
                    ->whereIn("var_id", $diff)
                    ->delete();
            }
//            var_dump($diff);exit;
        }
        $result = array('create'=>count($createArr),'update'=>(count($updateArr)),'delete'=>count($diff));//json_decode($resJson,true);


        return $result;

    }


    /**
     * 获取环境监控实时数据并添加到数据库
     * @param $input
     */
    public function realtimedata($input=array(),$type='',$vars=array()){
        $result = '';
        $data = array();

        switch(\ConstInc::EM_DATA_SOURCE){
            case '1':
                if(!$input){
                    $varsStr = $this->getRealtimedataParam($vars);
                    if($varsStr){
                        $input = $varsStr;
                    }
                }

                $sqlsrv = DB::connection('sqlsrvdata');
//                var_dump($input);exit;
                if($input) {
                    $variateObj = $sqlsrv->table('devpropertyname')->whereIn('devpropid',$input)->get();
                    $data = json_decode($variateObj, true);
                }
                break;
            case '2':
                break;

        }

//        var_dump($data);exit;
        $ids = array();
        $insert = array();
        if ($data) {
            foreach ($data as $k=>$val) {
//                var_dump($val);exit;
                $date = date("Y-m-d H:i:s");
                $insert[] = array(
                    'var_name' => isset($val['devpropid']) ? $val['devpropid'] : '',
                    'value' => isset($val['curvalue']) ? $val['curvalue'] : '',
                    'high_value' => isset($val['highValue']) ? $val['highValue'] : '0',
                    'min_value' => isset($val['minValue']) ? $val['minValue'] : '0',
                    'max_value' => isset($val['maxValue']) ? $val['maxValue'] : '0',
                    'low_value' => isset($val['lowValue']) ? $val['lowValue'] : '0',
                    'unit' => isset($val['unit']) ? $val['unit'] : '',
                    'created_at' => $date,
                    'updated_at' => $date,
                );
                $ids[] = $k;
//                    $ids[] = $this->addRealtimedataOne($param);
            }
//                var_dump($insert);exit;
            if('add' == $type) {
                $res = $this->emrealtimedataModel->insert($insert);
                $result = $res ? count($ids) : 0;
            }else{
                foreach($vars as $k=>$v){
                    $varName = isset($v['var_name'])?$v['var_name']:'';
                    $device_id = isset($v['device_id'])?$v['device_id']:'';
                    $device_name = isset($v['device_name'])?$v['device_name']:'';
                    $res[$k]['device_id'] = $device_id;
                    $res[$k]['device_name'] = $device_name;
                    $res[$k]['remark'] = isset($v['remark'])?$v['remark']:'';
                    $res[$k]['var_name'] = '';
                    $res[$k]['value'] = '';
                    $res[$k]['high_value'] = '';
                    $res[$k]['min_value'] = '';
                    $res[$k]['max_value'] = '';
                    $res[$k]['low_value'] = '';
                    if($insert) {
                        foreach ($insert as $vv) {
                            $varNamer = isset($vv['var_name']) ? $vv['var_name'] : '';
                            if ($varName == $varNamer) {
                                $res[$k]['var_name'] = isset($vv['var_name'])?$vv['var_name']:'';
                                $res[$k]['value'] = isset($vv['value']) ? $vv['value'] : '';
                                $res[$k]['high_value'] = isset($vv['highValue']) ? $vv['highValue'] : '';
                                $res[$k]['min_value'] = isset($vv['minValue']) ? $vv['minValue'] : '';
                                $res[$k]['max_value'] = isset($vv['maxValue']) ? $vv['maxValue'] : '';
                                $res[$k]['low_value'] = isset($vv['lowValue']) ? $vv['lowValue'] : '';
                            }
                        }
                    }
                }
                $result = $res;
            }



        }


        return $result;
    }


    /**
     * 添加监控点（一条数据）
     * @param $input
     * @return int
     * @throws ApiException
     */
    public function addMonitorPointOne($input) {
        $result = array('create'=>0,'update'=>0);
//        var_dump($input);exit;

        $device_id = isset($input['device_id'])?$input['device_id']:'';
        $varId = isset($input['var_id'])?$input['var_id']:'';
        $varName = isset($input['var_name'])?$input['var_name']:'';
        $where[] = ['device_id','=',$device_id];
        $where[] = ['var_id','=',$varId];

        $getData = $this->emMonitorpointModel->where($where)->withTrashed()->first();
        $id = isset($getData['id'])?$getData['id']:0;
//        var_dump($id,$input,$getData);exit;

//        $data = $input;
//        $data['asset_id'] = $input['asset_id'];
//        var_dump($getData->toArray());exit;
        $param = array(
            'device_id' => $device_id,
            'device_name' => isset($input['device_name']) ? $input['device_name'] : '',
            'var_id' => $varId,
            'var_name' => $varName,
            'unit' => isset($input['unit']) ? $input['unit'] : '',
            'remark' => isset($input['remark']) ? $input['remark'] : '',

        );
        $create = 0;
        $update = 0;
        if($varId) {
            if (!$getData) {
                $res = $this->emMonitorpointModel->create($param);
                if ($res) {
                    $create = $res['id'] ? $res['id'] : 0;
                }
            } else {
                unset($param['device_id']);
                unset($param['var_id']);
                $param['deleted_at'] = null;
                try {
                    $up = $this->emMonitorpointModel->where(['id' => $id])->withTrashed()->update($param);
                    if($up){
                        $update = $id;
                    }
                } catch (Exception $e) {
                    throw new ApiException(Code::ERR_QUERY, $e);
                }

//            $result = $this->update($id,$input);
            }
            $result = array('create'=>$create,'update'=>$update);
        }
        return $result;

    }






    /**
     * 添加监控点数据（一条数据）
     * @param $input
     * @return int
     */
    public function addRealtimedataOne($input){
        $result = 0;
//        var_dump($input);exit;

        $varName = isset($input['var_name'])?$input['var_name']:'';

//        var_dump($data);exit;
        if($varName) {
            $res = $this->emrealtimedataModel->create($input);
            if ($res) {
                $result = $res['id'] ? $res['id'] : 0;
            }
        }
        return $result;
    }


    /**
     * 数组格式化为 key=value(多个用逗号)
     * @param array $array
     * @return string
     */
    public function formatToParam($array=array()){
        $result = '';
        if($array && is_array($array)){
            foreach($array as $k=>$v){
                $v = trim($v,',');
                $result .= $k ."=".$v;
            }
            $result = trim($result,',');
        }
        return $result;
    }


    /**
     * 获取varName,格式：val,val2,val3...
     * @param array $data
     * @return string
     */
    public function getRealtimedataParam($data=array()){
        if(!$data) {
            $where[] = ['status', '=', 1];
            $res = $this->emMonitorpointModel->where($where)->get();
            $resArr = $res ? $res->toArray() : array();
        }else{
            $resArr = $data;
        }
        $varNames = array();
        if($resArr){
            foreach($resArr as $v){
                $var_name = isset($v['var_name']) ? $v['var_name']:'';
                if($var_name) {
                    $varNames[] = $var_name;
                }
            }
        }
        $varNames = array_filter(array_unique($varNames));
//        var_dump(count($varNames));exit;
        $result = $varNames;//implode(',',$varNames);
        return $result;
    }


    /**
     * 获取环控服务器的设备关联的监控点
     * @return array
     */
    public function getDevicesVariate(){
        $sqlsrv = DB::connection('sqlsrvdata');
        $devicesObj = $sqlsrv->table('device')->get();
//        print_r($devicesObj);exit;
        $devices = $devicesObj->toArray();
        $variateObj = $sqlsrv->table('devpropertyname')->get();
        $variate = $variateObj->toArray();
//        print_r($variate);exit;
        $result = array();
        if($variate){
            foreach($variate as $k=>$vv) {
                $vdid = !is_null($vv->deviceid) ? $vv->deviceid : '';
                $result[$k]['device_id'] = $vdid;
//                var_dump($vv->toArray());exit;
//                $result[$k] = $vv;
                foreach ($devices as $v) {
                    $dID = !is_null($v->deviceid) ? $v->deviceid : '';
                    $dName = !is_null($v->devicename) ? $v->devicename : '';
                    if($dID == $vdid && $vdid){
                        $result[$k]['device_name'] = $dName;
                    }
                }
                $result[$k]['var_id'] = !is_null($vv->devpropid) ? $vv->devpropid : '';;
                $result[$k]['var_name'] = !is_null($vv->propname) ? $vv->propname : '';
            }
        }
        return $result;
    }



    public function getWLMonitorPoint($deviceId='',$auto=false){

        $result = array();
        $input = array('ip' => $deviceId);
        $mpArr = array();
        if($deviceId){
            $mp = $this->emwanlian->WLGetDataForIP($input);
            if($mp){
                foreach($mp as $k=>$vv){
                    $mpArr[$k] = array(
                        'device_id' => isset($vv['IP']) ? $vv['IP'] : '',
                        'var_id' => isset($vv['SUBID']) ? $vv['SUBID'] : '',
                        'var_name' => isset($vv['subName']) ? $vv['subName'] : '',
                        'unit' => isset($vv['dw']) ? $vv['dw'] : '',
                    );
                }
            }

        }
        $result = array_merge($result,$mpArr);
        return $result;

    }


    /**
     * 获取环控设备列表
     * @return mixed
     */
    public function getDeviceList($where=array()){
        $model = $this->emdeviceModel;
        if ($where) {
            $model = $model->where($where);
        }
        $res = $model->get();
        return $res;
    }


    /**
     * 更新环控设备
     * @param array $where
     * @param array $param
     * @return bool
     */
    public function updateDevice($where=array(),$param=array()){
        $up = false;
        if($where && $param){
            $up = $this->emdeviceModel->where($where)->update($param);
        }
        return $up;

    }


    /**
     * 绑定(解绑)资产和环控设备
     * @param int $deviceId
     * @param int $assetId
     * @return bool
     */
    public function bindDeviceAsset($deviceId=0,$assetId=0,$unbind=false){
        if(!\ConstInc::$emOpen) {
            Code::setCode(Code::ERR_EM_MONITOR_CLOSEED);
            return false;
        }
        if($unbind) {

            if(!$assetId) {
                throw new ApiException(Code::ERR_PARAMS, ["资产不能为空"]);
            }
            $where = array('asset_id' => $assetId);
            $deviceRes = $this->getDeviceOne($where);

            if (!$deviceRes) {
                throw new ApiException(Code::ERR_EM_NOT_DEVICE);
            }
            $deviceId = isset($deviceRes['device_id']) ? $deviceRes['device_id'] : 0;

            $assetId =  0;

            // 环控绑定页面点击解绑使得“绑定项筛选”恢复默认值
            $where = array('device_id'=> $deviceId);
            $param = array('status' => 1);
            if($deviceId) {
                $this->emMonitorpointModel->where($where)->update($param);
            }


        }else{
            if(!$assetId || !$deviceId) {
                throw new ApiException(Code::ERR_PARAMS, ["资产或环控设备不能为空"]);
            }
            $where = array('device_id' => $deviceId);
            $deviceRes = $this->getDeviceOne($where);
            $assetidRes = isset($deviceRes['asset_id']) ? $deviceRes['asset_id'] : 0;
            if($assetidRes){
                throw new ApiException(Code::ERR_EM_BIND_DEVICE);
            }
        }
        $upWhere = ['device_id' => $deviceId];
        $param = array('asset_id'=>$assetId);
        $res = $this->updateDevice($upWhere,$param);

//        var_dump($res,$assetId,$deviceId,$unbind);exit;
        if($assetId && $deviceId && $res && !$unbind) {
            //当前设备的监控点入库
            $input = array("assetId" => $assetId);
            $this->monitorPoint($input);

        }
        return $res;
    }


    /**
     * 根据设备获取监控点
     * @param int $deviceId
     * @return array
     */
    public function getMPByDevice($input=array()){
        $res = array();
        $where = array();
        $deviceId = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        $unit = isset($input['unit']) ? trim($input['unit']) : '';
        if(!$deviceId){
            Code::setCode(Code::ERR_PARAMS, '参数不能为空');
            return false;
        }

        if(!$input['status']){
            $where[] = ['status', '=', 1];
        }

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
     * 启用禁用监控点
     * @param array $where
     * @param array $param
     * @return bool
     */
    public function updateMonitorpoint($input=array()){
        $up = false;
        $pointArr = isset($input['point']) ? $input['point'] : array();

        if($pointArr && is_array($pointArr)) {
            foreach($pointArr as $v)
            {
                $id = isset($v['id']) ? $v['id'] : 0;
                $status = isset($v['value']) ? $v['value'] : 0;
                $where = array('id'=> $id);
                $param = array('status' => $status);
                if($id) {
                    $this->emMonitorpointModel->where($where)->update($param);
                }
            }
            $up = true;
        }
        return $up;
    }


    /**
     * 获取监控点历史数据
     * @param Request $request
     * @return mixed
     */
    public function getRealtimedata($input=array()){
        $res = array();
        $stime = isset($input['stime']) ? $input['stime'] : '';
        $etime = isset($input['etime']) ? $input['etime'] : '';
        $sStrtime = date('Y-m-d H:i:s',$stime);
        $estrtime = date('Y-m-d H:i:s',$etime);
        $varName = $input['varName'];
        $where[] = ['created_at','>=',$sStrtime];
        $where[] = ['created_at','<=',$estrtime];
        $where[] = ['var_name','=',$varName];

        if($varName && $stime && $etime && $etime >= $stime) {
            $res = $this->emrealtimedataModel->where($where)->get();
        }
        return $res;
    }

    public function getDeviceAsset($assetId) {
        return $this->emdeviceModel->where(["asset_id" => $assetId])->first();
    }

    /**
     * 获取一条设备数据
     * @param array $where
     * @return array
     */
    public function getDeviceOne($where=array()){
        if(!\ConstInc::$emOpen) {
            Code::setCode(Code::ERR_EM_MONITOR_CLOSEED);
            return false;
        }
        $res = array();
        if($where) {
            $res = $this->emdeviceModel->where($where)->first();
        }
        return $res;
    }


    /**
     * 巡检报告模板新增或编辑
     * @param array $param
     * @param int $id 编辑必填
     * @return bool|int
     */
    public function addEditEmiTemplate($param=array(),$type='',$id=0){
        if(!\ConstInc::$emOpen && !\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_EM_M_MONITOR_CLOSEED);
            return false;
        }
        if(!$param){
            Code::setCode(Code::ERR_PARAMS,'参数不能为空');
            return false;
        }
        if(!$type){
            if(!$this->checkTemplateNum()){
                Code::setCode(Code::ERR_EM_TEMPLATE_NUM, '',[\ConstInc::EM_TEMPLATE_NUM]);
                return false;
            }
        }
        $name = isset($param['it_name']) ? $param['it_name'] : '';
        $content = isset($param['content']) ? $param['content'] : '';
        $mcontent = isset($param['mcontent']) ? $param['mcontent'] : '';
        $report_dates = isset($param['report_dates']) ? $param['report_dates'] : '';

        if(!$name){
            Code::setCode(Code::ERR_EM_TEMPLATE_NAME_EMPTY);
            return false;
        }
        //每天报告时间，字符转化数组,去重,去空,排序
        if($report_dates){
            $rdatesArr = array_filter(array_unique(explode(',',$report_dates)),'arrayFilterHourCallbak');
            sort($rdatesArr);
            if($this->checkReportDates(count($rdatesArr))){
                Code::setCode(Code::ERR_REPORT_DATES_NUM, '',[\ConstInc::REPORT_DATES_NUM]);
                return false;
            }
            $param['report_dates'] = implode(',',$rdatesArr);
        }
        if(!$content && !$mcontent){
            Code::setCode(Code::ERR_EM_TEMPLATE_CONTENT_EMPTY);
            return false;
        }else{
            $param['content'] = json_encode($content);
            $param['mcontent'] = json_encode($mcontent);
        }
        if('edit' == strtolower($type)){
            if(!$id) {
                Code::setCode(Code::ERR_PARAMS, '模板ID不能为空');
                return false;
            }
            $where = array('id' => $id);
            $fields = array('id','it_name');
            $dataOne = $this->getEmiTemplateOne($where,$fields);

            if(!$dataOne) {
                Code::setCode(Code::ERR_MODEL);
                return false;
            }
            $up = $this->emiTemplateModel->where($where)->update($param);
            if(!$up){
                Code::setCode(Code::ERR_UPDATE);
                return false;
            }
            $result = $id;
        }else{
            $rs = EmInspectionTemplate::create($param);
            $result = isset($rs['id'])?$rs['id']:0;
        }
        return $result;
    }


    /**
     * 获取巡检报告模板一条数据
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
     * 获取巡检报告模板列表
     * @return mixed
     */
    public function getTemplateList($type='',$where=array()){
        if(!\ConstInc::$emOpen) {
            Code::setCode(Code::ERR_EM_MONITOR_CLOSEED);
            return false;
        }
        $model = $this->emiTemplateModel;
        if($where){
            $model = $model->where($where);
        }
        if('all' == $type) {
            $data = $model->get();
        }else{
            $data = $this->usePage($model);
        }
        if($data){
            foreach($data as $k=>$v){
                if(isset($v['content'])) {
                    $v['content'] = isset($v['content']) ? json_decode($v['content'],true) : array();
                }
                if(isset($v['mcontent'])) {
                    $v['mcontent'] = isset($v['mcontent']) ? json_decode($v['mcontent'],true) : array();
                }
            }
        }
        return $data;
    }


    /**
     * 模板总数
     * @return mixed
     */
    public function getTemplateCount(){
        $count = $this->emiTemplateModel->count();
        return $count;
    }


    /**
     * 验证是否可添加模板
     * @return bool
     */
    public function checkTemplateNum(){
        return $this->getTemplateCount() < \ConstInc::EM_TEMPLATE_NUM ? true :false;
    }


    /**
     * 删除模板
     * @param string $ids
     * @return bool
     */
    public function delTemplate($ids=''){
        if(!\ConstInc::$emOpen && !\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_EM_M_MONITOR_CLOSEED);
            return false;
        }
        $idArr = array_filter(array_unique(explode(',',$ids)));
        if(!$idArr){
            Code::setCode(Code::ERR_PARAMS, '模板ID不能为空');
            return false;
        }
        $del = $this->emiTemplateModel->whereIn("id", $idArr)->delete();
        if(!$del){
            Code::setCode(Code::ERR_EM_TEMPLATE_DELETED);
            return false;
        }
        return $del;
    }

    /**
     * 设置模板
     * @param $id
     * @param $is_default
     * @return bool
     * @throws \Exception
     */
    public function setTemplate($id,$is_default){

        if(empty($id)){
            Code::setCode(Code::ERR_PARAMS, '模板ID不能为空');
            return false;
        }
        $template = $this->emiTemplateModel->find($id);
        if(empty($template)){
            Code::setCode(Code::ERR_MODEL, '模板ID不存在');
            return false;
        }
        if($template->is_default == $is_default){
            return $template->id;
        }

        DB::beginTransaction();
        if($is_default != EmInspectionTemplate::IS_DEFAULT){
            $this->emiTemplateModel->where('is_default','=',$is_default)->update(['is_default'=>EmInspectionTemplate::IS_DEFAULT]);
        }
        $template->is_default = $is_default;
        $template->update();
        DB::commit();

        return $template->id;
    }


    public function getRealTimeEmReport($input=array())
    {
        //验证监控功能
        if(!\ConstInc::$emOpen) {
            Code::setCode(Code::ERR_EM_MONITOR_CLOSEED);
            return false;
        }

        $tId = isset($input['tid']) ? $input['tid'] : '';
        $default = isset($input['default']) ? $input['default'] : 2;
        $report_date = date('YmdH');

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
        $template = $this->emiTemplateModel->where('id',$tId)->first();
        if(empty($template)){
            Code::setCode(Code::ERR_MODEL);
        }
        if(isset($template['content'])) {
            $template['content'] = \ConstInc::$emOpen ? json_decode($template['content'],true) : array();
        }
        if(isset($template['mcontent'])) {
            $template['mcontent'] = \ConstInc::$mOpen ? json_decode($template['mcontent'],true) : array();
        }

        // 万联-获取当前报警
        $alarm = $this->emwanlian->WLGetAlarm();
        // 获取环控告警数据并格式化
        $wlAlarmMsg = $this->wlGetAlarmMsg($alarm, false);
        $wlAlarmMsgIpPid = $this->wlGetAlarmMsg($alarm);

        $result = array();

        $tid = isset($template['id']) ? $template['id'] : 0;
        $tContent = isset($template['content']) ? $template['content'] : array();
        if ($tContent && is_array($tContent)) {
            //设备分类
            foreach ($tContent as $k => $v) {
                $cId = isset($v['id']) ? $v['id'] : 0;
                $deviceArr = isset($v['child']) ? (array)$v['child'] : array();
                if ($deviceArr && is_array($deviceArr)) {
                    //设备
                    foreach ($deviceArr as $kk => $vv) {
                        $pointArr = isset($vv['child']) ? (array)$vv['child'] : array();
                        // $deviceId  eg: => 192.1.8.214
                        $deviceId = isset($vv['device_id']) ? $vv['device_id'] : '';
//                        dd($deviceId);

                        $batterysArr = isset($vv['batterys']) ? (array)$vv['batterys'] : array();
                        // $alarm_msg eg: => 主机房采集环境:设备断线
                        $alarm_msg = isset($wlAlarmMsg[$deviceId]) ? $wlAlarmMsg[$deviceId] : '';
                        $tContent[$k]['child'][$kk]['alarm_msg'] = $alarm_msg ? implode(',', $alarm_msg) : '';
                        //监控点数据开始
                        $pidArr = array();//监控点id
                        foreach ($pointArr as $pk => $pv) {
                            $pidArr[] = isset($pv['var_id']) ? $pv['var_id'] : 0;
                        }
                        if ($pidArr) {
                            $lscds = implode(',', array_filter(array_unique($pidArr)));
                        }
                        //获取第三方监控数据
//                                    $pointRes = array();
                        if ($deviceId) {
                            $pParam = array('ip' => $deviceId);
                            if ($lscds) {
                                $pParam['lscids'] = $lscds;
                                $pointRes = $this->emwanlian->getDataForIPSub($pParam);
                            } else {
                                $pointRes = $this->emwanlian->WLGetDataForIP($pParam);
                            }
                            $pointRes = $this->formatEmDataForIPSub($pointRes);
                        }
//                                var_dump($deviceId,$lscds,$pointRes);//exit;
                        foreach ($pointArr as $pk => $pv) {
                            $pid = isset($pv['id']) ? $pv['id'] : 0;
                            $var_id = isset($pv['var_id']) ? $pv['var_id'] : 0;
                            $dpKey = $deviceId . '_' . $var_id;
                            $point = isset($pointRes[$var_id]) ? $pointRes[$var_id] : array();
                            $tContent[$k]['child'][$kk]['child'][$pk]['value'] = isset($point['value']) ? $point['value'] : '';
                            $tContent[$k]['child'][$kk]['child'][$pk]['dw'] = isset($point['dw']) ? $point['dw'] : '';
                            $tContent[$k]['child'][$kk]['child'][$pk]['status'] = isset($wlAlarmMsgIpPid[$dpKey]) ? 1 : 0;
                        }
                        //监控点数据结束

                        //电池组开始
                        if ($batterysArr && is_array($batterysArr)) {
                            $bpidArr = array();//监控点id
                            foreach ($batterysArr as $bk => $bv) {
                                $bpidArr[] = isset($bv['var_id']) ? $bv['var_id'] : 0;
                            }
                            $blscds = $bpidArr ? implode(',', array_filter(array_unique($bpidArr))) : "";

                            //获取第三方监控数据
                            $bPointRes = array();
                            if ($deviceId) {
                                $bParam = array('ip' => $deviceId);
                                if ($blscds) {
                                    $bParam['lscids'] = $blscds;
                                    $bPointRes = $this->emwanlian->getDataForIPSub($bParam);
                                } else {
                                    $bPointRes = $this->emwanlian->WLGetDataForIP($bParam);
                                }
                                $bPointRes = $this->formatEmDataForIPSub($bPointRes);
                            }
                            foreach ($batterysArr as $bk => $bv) {
                                $var_id = isset($bv['var_id']) ? $bv['var_id'] : 0;
                                $bPoint = isset($bPointRes[$var_id]) ? $bPointRes[$var_id] : array();
                                $tContent[$k]['child'][$kk]['batterys'][$bk]['value'] = isset($bPoint['value']) ? $bPoint['value'] : '';
                                $tContent[$k]['child'][$kk]['batterys'][$bk]['dw'] = isset($bPoint['dw']) ? $bPoint['dw'] : '';
                            }
                        }
                        //电池组结束
                    }
                }
            }

            $content = $tContent;
            $result = array(
                'report_date' => $report_date,
                'template_id' => $tid,
                'content' => $content,
            );

        }

        return $result;
    }

    /**
     * 添加环控巡检报告数据
     * @param array $input  => array("reportDate"=>"2018112715");
     * @param string $setReportDate
     * @return int
     */
    public function addEmReport($input=array(),$setReportDate='')
    {

        //验证监控功能是否开启
        if (!\ConsTinc::$emOpen) {
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
        $add = array();
        // 获取巡检报告模板列表 （em_inspection_template）
        $data = $this->common->getTemplateList('all',$where);
        $data = $data ? $data->toArray() : array();
//        var_dump($data);exit;
        if ($data) {
            $wlAlarmMsg = [];
            $wlAlarmMsgIpPid = [];
            //取出每个模板中设置需要跑数据的时间
            $rdAll = $this->reportDatesFlag($data);
            //取出时间和当前时间是否相同
            $rdAllFlag = dateArrStrCompare($rdAll);
            if($rdAllFlag) {
                // 万联-获取当前报警
                $alarm = $this->emwanlian->WLGetAlarm();
                // 获取环控告警数据并格式化
                $wlAlarmMsg = $this->wlGetAlarmMsg($alarm, false);
                $wlAlarmMsgIpPid = $this->wlGetAlarmMsg($alarm);
            }
//                var_dump(json_encode($wlAlarmMsg['192.1.8.214']));exit;
            foreach ($data as $val) {
                $tid = isset($val['id']) ? $val['id'] : 0;
                $tContent = isset($val['content']) ? $val['content'] : array();
                $report_dates = isset($val['report_dates']) ? $val['report_dates'] : '';
                $rdArr = $report_dates ? array_filter(array_unique(explode(',',$report_dates))) : [];
                //验证当前时间是否需要跑数据
                $rdFlag = dateArrStrCompare($rdArr);
                if ($tContent && is_array($tContent) && $rdFlag) {
                    //设备分类
                    foreach ($tContent as $k => $v) {
                        $cId = isset($v['id']) ? $v['id'] : 0;
                        $deviceArr = isset($v['child']) ? (array)$v['child'] : array();
                        if ($deviceArr && is_array($deviceArr)) {
                            //设备
                            foreach ($deviceArr as $kk => $vv) {
                                $pointArr = isset($vv['child']) ? (array)$vv['child'] : array();
                                // $deviceId  eg: => 192.1.8.214
                                $deviceId = isset($vv['device_id']) ? $vv['device_id'] : '';

                                $batterysArr = isset($vv['batterys']) ? (array)$vv['batterys'] : array();
                                // $alarm_msg eg: => 主机房采集环境:设备断线
                                $alarm_msg = isset($wlAlarmMsg[$deviceId]) ? $wlAlarmMsg[$deviceId] : '';
                                $tContent[$k]['child'][$kk]['alarm_msg'] = $alarm_msg ? implode(',',$alarm_msg) : '';
                                //监控点数据开始
                                $pidArr = array();//监控点id
                                foreach ($pointArr as $pk => $pv) {
                                    $pidArr[] = isset($pv['var_id']) ? $pv['var_id'] : 0;
                                }
                                if ($pidArr) {
                                    $lscds = implode(',', array_filter(array_unique($pidArr)));
                                }
                                //获取第三方监控数据
//                                    $pointRes = array();
                                if ($deviceId) {
                                    $pParam = array('ip' => $deviceId);
                                    if($lscds){
                                        $pParam['lscids'] = $lscds;
                                        $pointRes = $this->emwanlian->getDataForIPSub($pParam);
                                    }else{
                                        $pointRes = $this->emwanlian->WLGetDataForIP($pParam);
                                    }
                                    $pointRes = $this->formatEmDataForIPSub($pointRes);
                                }
//                                var_dump($deviceId,$lscds,$pointRes);//exit;
                                foreach ($pointArr as $pk => $pv) {
                                    $pid = isset($pv['id']) ? $pv['id'] : 0;
                                    $var_id = isset($pv['var_id']) ? $pv['var_id'] : 0;
                                    $dpKey = $deviceId.'_'.$var_id;
                                    $point = isset($pointRes[$var_id]) ? $pointRes[$var_id] : array();
                                    $tContent[$k]['child'][$kk]['child'][$pk]['value'] = isset($point['value']) ? $point['value'] : '';
                                    $tContent[$k]['child'][$kk]['child'][$pk]['dw'] = isset($point['dw']) ? $point['dw'] : '';
                                    $tContent[$k]['child'][$kk]['child'][$pk]['status'] = isset($wlAlarmMsgIpPid[$dpKey]) ? 1 : 0;
                                }
                                //监控点数据结束

                                //电池组开始
                                if($batterysArr && is_array($batterysArr)){
                                    $bpidArr = array();//监控点id
                                    foreach($batterysArr as $bk=>$bv){
                                        $bpidArr[] = isset($bv['var_id']) ? $bv['var_id'] : 0;
                                    }
                                    $blscds = $bpidArr ? implode(',', array_filter(array_unique($bpidArr))) : "";

                                    //获取第三方监控数据
                                    $bPointRes = array();
                                    if ($deviceId) {
                                        $bParam = array('ip' => $deviceId);
                                        if($blscds){
                                            $bParam['lscids'] = $blscds;
                                            $bPointRes = $this->emwanlian->getDataForIPSub($bParam);
                                        }else{
                                            $bPointRes = $this->emwanlian->WLGetDataForIP($bParam);
                                        }
                                        $bPointRes = $this->formatEmDataForIPSub($bPointRes);
                                    }
                                    foreach ($batterysArr as $bk => $bv) {
                                        $var_id = isset($bv['var_id']) ? $bv['var_id'] : 0;
                                        $bPoint = isset($bPointRes[$var_id]) ? $bPointRes[$var_id] : array();
                                        $tContent[$k]['child'][$kk]['batterys'][$bk]['value'] = isset($bPoint['value']) ? $bPoint['value'] : '';
                                        $tContent[$k]['child'][$kk]['batterys'][$bk]['dw'] = isset($bPoint['dw']) ? $bPoint['dw'] : '';
                                    }
                                }
                                //电池组结束
                            }
                        }


                    }

                    // 保存数据
                    $dataOne = true;
                    if ($tid) {
                        if ($report_date) {
                            $where = array('template_id' => $tid, 'report_date' => $report_date);
                            $dataOne = $this->eminspectionModel->where($where)->first();
                        }
                    }
                    $content = $tContent;
//                        var_dump($tContent);exit;
                    if (!$dataOne) {
                        $insert = array(
                            'report_date' => $report_date,
                            'template_id' => $tid,
                            'content' => json_encode($content),
                        );
//                            var_dump($insert);exit;
                        $create = $this->eminspectionModel->create($insert);
                        if (isset($create['id']) && $create['id']) {
                            $add[] = $tid;
                        }
                    }
                }
            }
        }
        return $add;
    }



    /**
     * 获取环控报告
     * @param $input
     * @return array|bool
     */
    public function getEmReport($input){
        if(!\ConstInc::$emOpen) {
            Code::setCode(Code::ERR_EM_MONITOR_CLOSEED);
            return false;
        }
        $res = array();
        $tId = isset($input['tid']) ? $input['tid'] : '';
        $default = isset($input['default']) ? $input['default'] : '';
        $reportDate = isset($input['reportDate']) ? $input['reportDate'] : '';

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
        if($tId && $reportDate){
            $where = array('template_id'=>$tId,'report_date'=>$reportDate);
            $res = $this->eminspectionModel->where($where)->first();
            $res = $res ? $res->toArray() : array();
            if($res) {
                $res['content'] = json_decode($res['content'], true);
            }
        }
        return $res;
    }


    /**
     * 格式化万联监控点数据
     * @param array $data
     * @return array
     */
    public function formatEmDataForIPSub($data=array()){
        $result = array();
        if($data){
            foreach($data as $v){
                $k = isset($v['SUBID']) ? $v['SUBID']:'';
                if($k) {
                    $result[$k] = $v;
                }
            }
        }
        return $result;
    }


    /**
     * 获取环控告警数据并格式化
     * @param bool $typek false:只用
     * @return array
     */
    public function wlGetAlarmMsg($alarm = array(),$typek=true){
        $result = array();
        if(!$alarm) {
            $alarm = $this->emwanlian->WLGetAlarm();
        }
        if($alarm){
            foreach($alarm as $v){
                $lscid = isset($v['lscid'])?$v['lscid']:'';
                $ip = isset($v['IP'])?$v['IP']:'';
                $name = isset($v['name'])?$v['name']:'';
                $value = isset($v['value'])?$v['value']:'';
                //唯一key
                $k = $ip.'_'.$lscid;
                if(!$typek) {
                    //获取ip
                    $trans = array(":" . $lscid => "");
                    $k = strtr($ip, $trans);
                    if(!isset($result[$k])) {
                        $result[$k][] = $name.':'.$value;
                    }else{
                        $result[$k][] = $name.':'.$value;
                    }
                }else{
                    if(!isset($result[$k])) {
                        $result[$k] = $lscid;
                    }else{
                        $result[$k] = $lscid;
                    }
                }
            }
        }
        return $result;
    }


    /**
     * 根据资产编号，监控点id获取监控点数据
     * @param array $input
     * @return array|mixed
     */
    public function getEmonitorPoint($input=array()){
        $mpoint = getKey($input,'mpoint');
        $result = array();
        $deviceId = getKey($input,'deviceId');
        $number = getKey($input,'number');
        $dataSourceId = getKey($input,'id');

        if($number){
            //根据资产编号获取监控设备ip
            $where = array('A.number'=>$number);
            $fieldArrKeys = array(
                'em_device.device_id'
            );
            $emDevice = $this->emdeviceModel->select($fieldArrKeys)
                ->join('assets_device as A','A.id','=','em_device.asset_id')
                ->where($where)->first();
            $deviceId = isset($emDevice['device_id'])?$emDevice['device_id']:'';
        }

        $pidArr = array();
        $param = array('ip' => $deviceId);
        //转成数组
        $mpointArr = array_filter(array_unique(explode(',',$mpoint)));

        if ($mpointArr && $deviceId) {
            $pidArr = $this->emMonitorpointModel->whereIn('id',$mpointArr)->get()->pluck('var_id','id')->toArray();
        }
        $dsRes = array();
        if($dataSourceId) {
            $dsWhere = array('id' => $dataSourceId);
            $dsRes = $this->dataSourceModel->where($dsWhere)->first();
        }
        $dsOption = isset($dsRes['option']) ? $dsRes['option'] : '';
        $dsOption = $dsOption ? json_decode($dsOption,true) : array();
        $mpointArr = array();
        $pointNameArr = array();
        if($dsOption){
            foreach($dsOption as $v){
                if($v['name']=='mpoint'){
                    $mpointArr = $v['in'];
                }
            }
        }
        if($mpointArr) {
            foreach ($mpointArr as $v) {
                $id = isset($v['id']) ? $v['id'] : '';
                $key = isset($pidArr[$id]) ? $pidArr[$id] : '';
                if ($key) {
                    $pointNameArr[$key] = isset($v['name']) ? $v['name'] : '';
                }
            }
        }

        //数组转成字符,例：1,2,3
        $lscds = implode(',', array_filter(array_unique($pidArr)));
//        var_dump($dsArr,$pointNameArr,$pidArr);exit;
        //根据IP和监控点获取监控点数据   
        if($lscds && $deviceId) {
            $param['lscids'] = $lscds;
            $res = $this->emwanlian->getDataForIPSub($param);
            //test data
            /*$res = array(
                array('SubName'=>'电压','SUBID'=>'26','value'=>'23','dw'=>'A'),
                array('SubName'=>'电流','SUBID'=>'27','value'=>'33','dw'=>'V'),
                array('SubName'=>'频率','SUBID'=>'33','value'=>'44','dw'=>'Av'),
                array('SubName'=>'电阻','SUBID'=>'34','value'=>'55','dw'=>'C')
            );*/
            if($res){
                foreach($res as $k=>$v){
                    $subName = isset($v['SubName']) ? $v['SubName'] : '';
                    $subid = isset($v['SUBID']) ? $v['SUBID'] : '';
                    $value = isset($v['value']) ? $v['value'] : '';
                    $dw = isset($v['dw']) ? $v['dw'] : '';
                    $result[$k] = array(
                        'name' => isset($pointNameArr[$subid]) ? $pointNameArr[$subid] : $subName,
                        'value' => $dw ? $value.' '.$dw : $value
                    );
                }
            }
        }
        return $result;
    }


    /**
     * 添加或更新告警数据
     * @return bool
     */
    public function addEmAlarm($test=array()){
        //万联数据源
        $alarm = $this->emwanlian->WLGetAlarm();
//        var_dump($alarm);exit;
//        $alarm = $test;//test data
//        var_dump($alarm);exit;
        $wheres = array('state'=>1);
        $list = $this->emAlarmModel->where($wheres)->get();
//        var_dump($list->toArray());exit;
        $listAlarmids = array();
        if($list) {
            foreach ($list as $v) {
                $id = isset($v['id']) ? $v['id'] : '';
                $alarmid = isset($v['alarm_id']) ? $v['alarm_id'] : '';
                $listAlarmids[$id] = $alarmid;
            }
        }

        $param = array();
        $alarmidArr = array();
        DB::beginTransaction(); //开启事务
        try {
            if ($alarm) {
                $aList = [];
                $alarmids = array_column($alarm,'alarmid');
                if($alarmids) {
                    $aList = $this->emAlarmModel->whereIn('alarm_id', $alarmids)->get();
                    $aList = $aList ? $aList->toArray() : [];
                }
                $alarmidAll = $aList ? array_column($aList,'alarm_id') : [];
//                var_dump($alarmidAll);exit;
                foreach ($alarm as $v) {
                    $date = date("Y-m-d H:i:s");
                    $alarmid = isset($v['alarmid']) ? $v['alarmid'] : '';
                    $alarmidArr[] = $alarmid;
                    if ($alarmid && !in_array($alarmid, $listAlarmids) && !in_array($alarmid,$alarmidAll)) {
                        $where = array('alarm_id' => $alarmid);
                        $aOne = $this->emAlarmModel->where($where)->first();
                        if (!$aOne) {
                            $param[] = array(
                                'alarm_id' => $alarmid,
                                'point_id' => isset($v['lscid']) ? $v['lscid'] : '',
                                'levels' => isset($v['Itemlevels']) ? $v['Itemlevels'] : '',
                                'device_id' => isset($v['IP']) ? $v['IP'] : '',
                                'device_name' => isset($v['siteName']) ? $v['siteName'] : '',
                                'times' => isset($v['TS']) ? $v['TS'] : '',
                                'aname' => isset($v['name']) ? $v['name'] : '',
                                'avalue' => isset($v['value']) ? $v['value'] : '',
                                'dw' => isset($v['dw']) ? $v['dw'] : '',
                                'created_at' => $date,
                                'updated_at' => $date,
                            );
                        }
                    }
                }
//            var_dump($param);exit;
                //添加告警数据
                if ($param) {
                    $add = $this->emAlarmModel->insert($param);
                    if($add) {
                        //推送告警消息
                        $this->triggerEvent($param);
                    }
                }
            }
            if ($list) {
                //获取需要更新成恢复的数据
                $upids = array();
                foreach ($list as $v) {
                    $id = isset($v['id']) ? $v['id'] : '';
                    $alarmid = isset($v['alarm_id']) ? $v['alarm_id'] : '';
                    if (!in_array($alarmid, $alarmidArr)) {
                        $upids[$alarmid] = $id;
                    }
                }

//            var_dump($alarmidArr,$upids);exit;
                //批量更新为恢复
                $param = array('state' => 0);
                if ($upids) {
                    $up = $this->emAlarmModel->whereIn('id', $upids)->update($param);
                    if($up) {
                        //推送恢复消息
                        $this->triggerEventClose($upids);
                    }
                }
            }
            //提交事务
            DB::commit();
        }catch(Exception $e){
            //记录日志，回滚事务
            Log::info('add or update em alarm fail');
            DB::rollback();
        }
        return true;

    }


    /**
     * 触发告警事件
     * @param array $aParam
     */
    public function triggerEvent($aParam=array()){
        $events = [];
        $assetList = [];
        if($aParam) {
            $deviceIDs = array_column($aParam,'device_id');
//            var_dump(111,$deviceIDs);exit;
            $fieldArrKeys = array(
                'em_device.asset_id',
                'em_device.device_id',
                'A.state',
                'A.id'
            );
            $assetRes = $this->emdeviceModel->select($fieldArrKeys)
                ->join('assets_device as A','A.id','=','em_device.asset_id')
                ->whereIn('em_device.device_id',$deviceIDs)->get();
            if($assetRes){
                foreach($assetRes as $v){
                    $device_id = isset($v['device_id']) ? $v['device_id']: '';
                    $assetList[$device_id] = $v;
                }
            }
//            var_dump($events,$assetList);exit;
            foreach ($aParam as $v) {
                $device_id = isset($v['device_id']) ? $v['device_id']: '';

                $asset = isset($assetList[$device_id]) ? $assetList[$device_id] : array();
                $assetState = isset($asset['state']) ? $asset['state'] : '';
                $assetID = isset($asset['id']) ? $asset['id'] : '';
                $aname = isset($v['aname']) ? $v['aname'] : '';
                $avalue = isset($v['avalue']) ? $v['avalue'] : '';
                $content = $aname ? $aname.':'.$avalue : '';
                $alarm_id = isset($v['alarm_id']) ? $v['alarm_id'] : '';
                if ($assetState == Device::STATE_USE) {
                    Log::info("add monitor " . $assetID . " " . $content);
                    $eventId = $this->eventsRepository->addByMonitor($assetID, $content, $alarm_id, 'em_alert_event_id');
                    if (false !== $eventId) {
                        $events[] = $eventId;
                    }
                } else {
                    Log::info("device state not in use. not add emonitor " . $assetID . " " . $content);
                }
            }
        }

        //推送消息
        if($events && \ConstInc::WX_PUBLIC) {
            foreach ($events as $event) {
                $this->sendErrNotice($event);
            }
        }
    }

    /**
     * 触发关闭告警事件
     * @param array $aParam
     */
    public function triggerEventClose($aParam=array()){
        $closeEvents = []; //恢复正常的
        $content = '报警已解决，关闭事件';
        if($aParam) {
            foreach ($aParam as $alarmid => $v) {
                //已解决的报警，关闭事件
                Log::info("trigger em event close" . $v);
                $closeEvents = array_merge($closeEvents, $this->eventsRepository->closeByMonitor($alarmid, $content, 'em_alert_event_id'));

            }
        }

        //推送消息
        if($closeEvents && \ConstInc::WX_PUBLIC) {
            foreach ($closeEvents as $event) {
                $this->sendCloseNotice($event);
            }
        }
    }


    /**
     * 推送关闭消息
     * @param $event
     */
    protected function sendCloseNotice($event) {
        $eventId = $event['id'];
        $engineers = $this->userRepository->getEngineers();
        $uids = array();
        if ($engineers) {
            foreach ($engineers as $v) {
                $uids[] = isset($v['id']) ? $v['id'] : 0;
            }
        }

        $uids = array_filter(array_unique($uids));
        $uids = $this->eventsRepository->checkAssetOperationAccess($event,$uids);
        Log::info($eventId.'_asset_operation_access:'.json_encode($uids));

        if(2 == \ConstInc::WX_PUBLIC){
            //企业微信通知消息
            $wxUsers = $this->qywxuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $useridArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $userid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    $useridArr[] = $userid;
                }
                $wxNotice = array(
                    'touser' => $useridArr,
                    'title' => '设备已恢复正常，点击查看详情',
                    'url' => $this->hostName . '/eventsdetail/' . $eventId,
                    'desc' => isset($event['remark']) ? $event['remark'] : '',
                    'eventID' => $eventId,
                    'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : '',
                    'dtype' => 1
                );
                $this->wxcommon->qySendTextcard($wxNotice);
                Log::info($eventId . '_send_qywx_notice_batch:' . json_encode($useridArr));
            }
        } else {
            //微信公众号通知消息
            $wxUsers = $this->weixinuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $openidArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $openid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    $openidArr[] = $openid;
                    $wxNotice = array(
                        'openid' => $openid,
                        'title' => '设备已恢复正常，点击查看详情',
                        'url' => $this->hostName . '/eventsdetail/' . $eventId,
                        'desc' => isset($event['remark']) ? $event['remark'] : '',
                        'eventID' => $eventId,
                        'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : ''
                    );
                    $this->wxcommon->sendWXNotice($wxNotice);
                }
                Log::info($eventId . '_send_wx_notice_batch:' . json_encode($openidArr));
            }
        }
    }


    /**
     * 推送告警消息
     * @param $event
     */
    protected function sendErrNotice($event) {
        $eventId = $event['id'];
        $engineers = $this->userRepository->getEngineers();
        $uids = array();
        if ($engineers) {
            foreach ($engineers as $v) {
                $uids[] = isset($v['id']) ? $v['id'] : 0;
            }
        }

        $uids = array_filter(array_unique($uids));
        $uids = $this->eventsRepository->checkAssetOperationAccess($event,$uids);
        if(2 == \ConstInc::WX_PUBLIC){
            //企业微信通知消息
            $wxUsers = $this->qywxuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $useridArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $userid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    $useridArr[] = $userid;
                }
                $wxNotice = array(
                    'touser' => $useridArr,
                    'title' => '设备出现异常，点击查看详情',
                    'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                    'desc' => isset($event['description']) ? $event['description'] : '',
                    'eventID' => $eventId,
                    'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : '',
                    'dtype' => 1
                );
                $this->wxcommon->qySendTextcard($wxNotice);
                Log::info($eventId . '_send_qywx_notice_batch:' . json_encode($useridArr));
            }
        } else {
            //微信公众号通知消息
            Log::info($eventId . '_asset_operation_access:' . json_encode($uids));
            $wxUsers = $this->weixinuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $openidArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $openid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    $openidArr[] = $openid;
                    $wxNotice = array(
                        'openid' => $openid,
                        'title' => '设备出现异常，点击查看详情',
                        'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                        'desc' => isset($event['description']) ? $event['description'] : '',
                        'eventID' => $eventId,
                        'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : ''
                    );
                    $this->wxcommon->sendWXNotice($wxNotice);
                }
                Log::info($eventId . '_send_wx_notice_batch:' . json_encode($openidArr));
            }

        }
    }

    /**
     * 验证每天报告时间最大数
     * @return bool true超过最大数
     */
    public function checkReportDates($ct=0){
        return $ct > \ConstInc::REPORT_DATES_NUM ? true :false;
    }


    /**
     * 获取模板的report_dates是否有值标示
     * @param $data
     * @return bool
     */
    private function reportDatesFlag($data){
        //取出每个模板中设置需要跑数据的时间
        $report_datesArr = array_filter(array_column($data,'report_dates'));
        $rds = [];
        foreach($report_datesArr as $v){
            $rds = array_merge($rds,explode(',',$v));
        }
        return array_filter(array_unique($rds));
    }


    /**
     * 获取所有监控点
     * @param array $where
     * @return array
     */
    public function getEmPointList($where=array()){
        $model = $this->emMonitorpointModel;
        if($where){
            $model = $model->where($where);
        }
        $result = $model->withTrashed()->get();
        return $result;
    }


}