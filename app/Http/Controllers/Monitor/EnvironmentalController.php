<?php
/**
 * 环境监控
 * User: wangwei
 * Date: 2018/3/28
 * Time: 16:05
 */

namespace App\Http\Controllers\Monitor;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Monitor\EnvironmentalRepository;
use App\Repositories\Monitor\EmMonitorpointRepository;
use App\Repositories\Monitor\EmWanlianRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Monitor\AssetsMonitorRepository;
use App\Repositories\Monitor\CommonRepository as mCommon;

use App\Models\Code;
use App\Exceptions\ApiException;
use DB;

class EnvironmentalController extends Controller
{

    protected $environmental;
    protected $emMonitorpoint;
    protected $emwanlian;
    protected $device;
    protected $assetsmonitor;
    protected $mcommon;

    function __construct(EnvironmentalRepository $environmental,
                         EmMonitorpointRepository $emMonitorpoint,
                         EmWanlianRepository $emwanlian,
                         DeviceRepository $device,
                         AssetsMonitorRepository $assetsmonitor,
                         mCommon $mcommon)
    {
        $this->environmental = $environmental;
        $this->emMonitorpoint = $emMonitorpoint;
        $this->emwanlian = $emwanlian;
        $this->device = $device;
        $this->assetsmonitor = $assetsmonitor;
        $this->mcommon = $mcommon;
    }


    /**
     * 添加环控设备入库（初始）
     * @return mixed
     */
    public function postAddDevice(){
        $res['result'] = $this->environmental->addDevices();
        return $this->response->send($res);
    }


    /**
     * 获取环境监控监控点列表并入库（初始）
     * @param $input
     */
    public function postMonitorPoint(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res = $this->environmental->monitorPoint($input);
        return $this->response->send($res);

    }


    /**
     * 获取环境监控实时数据并入库（初始）
     * @param $input
     */
    public function postRealtimedata(Request $request){
        $input = $request->post() ? $request->post() : array();
        $res['result'] = $this->environmental->realtimedata($input,'add');
        return $this->response->send($res);

    }


    public function getTest(){

        $devicesVar = $this->environmental->getDevicesVariate();
        print_r($devicesVar);
    }


    /**
     * 获取环控设备列表
     * @return mixed
     */
    public function getDeviceList(){
        $where = array('asset_id' => 0);
        $res['result'] = $this->environmental->getDeviceList($where);
        return $this->response->send($res);
    }


    /**
     * 获取监控点历史数据
     * @param Request $request
     * @return mixed
     */
    public function postRealtimedataList(Request $request){
        $input = $request->post() ? $request->post() : array();
        $res['result'] = $this->environmental->getRealtimedata();
        return $this->response->send($res);
    }


    /**
     * 根据设备获取监控点(无分页)
     * @param Request $request
     * @return mixed
     */
    public function getMonitorPointByDevice(Request $request){
        $input = $request->input() ? $request->input() : array();
        $assetId = isset($input['assetId']) ? $input['assetId'] : 0;
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : 0;

        // 当为 TRUE 时数据库中做 status 查询条件
        $input['status'] = boolval($assetId) ? TRUE : FALSE;

        if(!$assetId && !$deviceId) {
            throw new ApiException(Code::ERR_PARAMS, ["资产或环控设备不能为空"]);
        }
        if($assetId) {
            $where = array("asset_id" => $assetId);
            $deviceOne = $this->environmental->getDeviceOne($where);
            $deviceId = isset($deviceOne['device_id']) ? $deviceOne['device_id'] : '';
            $input['deviceId'] = $deviceId;
        }
        if(!$deviceId){
            throw new ApiException(Code::ERR_EM_NOT_DEVICE);
        }
        $whereMPD = array('device_id'=>$deviceId);
        $res['result'] = $this->environmental->getMPByDevice($input);
        return $this->response->send($res);
    }


    /**
     * 根据设备获取监控点数据(无分页)
     * @param Request $request
     * @return mixed
     */
    public function postMonitorPointDataByDevice(Request $request){
        $input = $request->post() ? $request->post() : array();
        $assetId = isset($input['assetId']) ? $input['assetId'] : 0;
        $where = array("asset_id" => $assetId);
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : 0;
        if($assetId) {
            $deviceOne = $this->environmental->getDeviceOne($where);
            $deviceId = isset($deviceOne['device_id']) ? $deviceOne['device_id'] : '';
            $input['deviceId'] = $deviceId;
        }
        $whereMPD = array('status'=>1,'device_id'=>$deviceId);
        $mpRes = $this->environmental->getMPByDevice($input);
        $mpRes = $mpRes ? $mpRes->toArray() : array();
        //$mpData = isset($mpRes['data']) ?$mpRes['data'] :array();
//        var_dump($mpRes);exit;
        $result['result'] = array();
        $res = array();
        if($mpRes){
            $res = $this->environmental->realtimedata(array(),'',$mpRes);
//            var_dump($mpRes);exit;

            $result['result'] = $res;
        }
        return $this->response->send($result);
    }


    /**
     * 启用禁用监控点
     * @param Request $request
     * @return mixed
     */
    public function postUpdateMonitorpointED(Request $request){
        $input = $request->post() ? $request->post() : array();
        $res['result'] = $this->environmental->updateMonitorpoint($input);
        return $this->response->send($res);
    }


    /**
     * 获取监控点列表(有分页)
     * @param Request $request
     * @return mixed
     */
    public function getMonitorpointList(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res['result'] = $this->emMonitorpoint->monitorpointList($input);
        return $this->response->send($res);
    }


    /**
     * 获取监控点数据列表(有分页)
     * @param Request $request
     * @return mixed
     */
    public function getMonitorpointData(Request $request){
        $input = $request->input() ? $request->input() : array();
        $mpRes = $this->emMonitorpoint->monitorpointList($input);
        $mpRes = $mpRes->toArray();
        $mpData = isset($mpRes['data']) ?$mpRes['data'] :array();
//        var_dump($mp);exit;
        $result['result'] = array();
        if($mpData){
            $mp = $this->environmental->realtimedata(array(),'',$mpData);
            foreach($mpData as $k=>$v){
                $varName = isset($v['var_name'])?$v['var_name']:'';
                $mpData[$k]['value'] = '';
                $mpData[$k]['high_value'] = '';
                $mpData[$k]['min_value'] = '';
                $mpData[$k]['max_value'] = '';
                $mpData[$k]['low_value'] = '';
                if($mp) {
                    foreach ($mp as $vv) {
                        $varNamer = isset($vv['varName']) ? $vv['varName'] : '';
                        if ($varName == $varNamer) {
                            $mpData[$k]['value'] = isset($vv['value']) ? $vv['value'] : '';
                            $mpData[$k]['high_value'] = isset($vv['highValue']) ? $vv['highValue'] : '';
                            $mpData[$k]['min_value'] = isset($vv['minValue']) ? $vv['minValue'] : '';
                            $mpData[$k]['max_value'] = isset($vv['maxValue']) ? $vv['maxValue'] : '';
                            $mpData[$k]['low_value'] = isset($vv['lowValue']) ? $vv['lowValue'] : '';
                        }
                    }
                }
            }
            $mpRes['data'] = $mpData;
            $result['result'] = $mpRes;
        }
        return $this->response->send($result);

    }



    /**
     * 根据设备ID获取资产(环控设备)
     * @param Request $request
     * @return mixed
     */
    public function getDevice(Request $request){
        $input = $request->input() ? $request->input() : array();
        $assetId = isset($input['assetId']) ? $input['assetId'] : 0;

        if(!\ConstInc::$emOpen) {
            throw new ApiException(Code::ERR_EM_MONITOR_CLOSEED);
        }

        if(!$assetId) {
            throw new ApiException(Code::ERR_PARAMS, ["资产或环控设备不能为空"]);
        }
        $res = array();
        if($assetId) {
            $res = $this->device->getDeviceById($input);
            if($res) {
                $where = array("asset_id" => $assetId);
                $deviceOne = $this->environmental->getDeviceOne($where);
                $res['em_device'] = isset($deviceOne['name']) ? $deviceOne['name'] : '';
                $res['em_device_id'] = isset($deviceOne['device_id']) ? $deviceOne['device_id'] : '';
            }
        }
//        $res['result'] = $deviceRes;
//        var_dump($res);exit;
        return $this->response->send($res->toArray());
    }


    /**
     * 显示巡检报告模板新增或编辑
     * @param Request $request
     * @return mixed
     */
    public function getShowTemplate(Request $request){
        //验证环控，监控开关
        if(!\ConstInc::$emOpen && !\ConstInc::$mOpen) {
            throw new ApiException(Code::ERR_EM_M_MONITOR_CLOSEED);
        }
        $input = $request->input();
        $id = isset($input['id']) ? intval($input['id']) : 0;

        $res = array();

        $content = '';
        $mcontent = '';
        $data = array();
        if(!$id){
           // var_dump($this->environmental->checkTemplateNum());exit;
            if(!$this->environmental->checkTemplateNum()){
                // 不可以添加模板时
                Code::setCode(Code::ERR_EM_TEMPLATE_NUM, '',[\ConstInc::EM_TEMPLATE_NUM]);
                return $this->response->send();
            }
        }else {
            $where = array('id' => $id);
            if ($id) {
                $data = $this->environmental->getEmiTemplateOne($where);
                $content = isset($data['content']) ? $data['content'] : array();
                $mcontent = isset($data['mcontent']) ? $data['mcontent'] : array();
            }
        }
        //环控
        $emCategory = $this->mcommon->getEmCategoryAll($content);
//        $res['all'] = $emCategory;
        $data['report_max'] = \ConstInc::REPORT_DATES_NUM;
        if($emCategory){
            $data['content'] = $emCategory;
        }

        //监控
        $mDevice = $this->mcommon->getMCategoryAll($mcontent);
        if($mDevice) {
            $data['mcontent'] = $mDevice;
        }
        $res['result'] = $data;
        return $this->response->send($res);
    }


    /**
     * 保存巡检报告模板新增
     * @param Request $request
     * @return mixed
     */
    public function postAddTemplate(Request $request){
        $input = $request->input();
        $param = array(
            'it_name' => isset($input['name']) ? $input['name'] : '',
            'it_desc' => isset($input['desc']) ? $input['desc'] : '',
            'content' => isset($input['content']) ? $input['content'] : '',
            'mcontent' => isset($input['mcontent']) ? $input['mcontent'] : '',
            'report_dates' => isset($input['rdates']) ? $input['rdates'] : '',
        );
//        var_dump($param);exit;
        $res['result'] = $this->environmental->addEditEmiTemplate($param);
        return $this->response->send($res);
    }


    /**
     * 保存巡检报告模板编辑
     * @param Request $request
     * @return mixed
     */
    public function postEditTemplate(Request $request){
        $input = $request->input();
        $id = isset($input['id']) ? intval($input['id']) : 0;
        $param = array(
            'it_name' => isset($input['name']) ? $input['name'] : '',
            'it_desc' => isset($input['desc']) ? $input['desc'] : '',
            'content' => isset($input['content']) ? $input['content'] : '',
            'mcontent' => isset($input['mcontent']) ? $input['mcontent'] : '',
            'report_dates' => isset($input['rdates']) ? $input['rdates'] : '',
        );
        $res['result']  = $this->environmental->addEditEmiTemplate($param,'edit',$id);
        return $this->response->send($res);
    }


    /**
     * 获取巡检报告模板列表
     * @return mixed
     */
    public function getTemplateList(){
        $res['result'] = $this->mcommon->getTemplateList();
        return $this->response->send($res);
    }

    /**
     * 设置模板属性
     */
    public function postSetTemplate(Request $request){
        $id = $request->input('id');
        $is_default = $request->input('is_default');
        $res['result'] = $this->environmental->setTemplate($id,$is_default);
        return $this->response->send($res);
    }

    /**
     * 删除模板
     * @param Request $request
     * @return mixed
     */
    public function postDelTemplate(Request $request){
        $ids = $request->input('id');
        $res['result'] = $this->environmental->delTemplate($ids);
        return $this->response->send($res);
    }



    public function getGetSite(){
        $result = $this->emwanlian->WLGetDevice();
        return $this->response->send($result);
    }


    public function getGetAlarm(){
        $result = $this->emwanlian->WLGetAlarm();
        return $this->response->send($result);
    }


    public function getGetData(){
        $result = $this->emwanlian->WLGetData();
        return $this->response->send($result);
    }


    public function getGetDataForIP(Request $request){

        $input = $request->input() ? $request->input() : array();
        $result = $this->emwanlian->WLGetDataForIP($input);

        return $this->response->send($result);
    }


    public function getGetDataForIPSub(Request $request){

        $input = $request->input() ? $request->input() : array();
        $result = $this->emwanlian->getDataForIPSub($input);

        return $this->response->send($result);
    }


    /**
     * 获取所有绑定监控的设备
     * @return mixed
     */
    public function getMonitors(){
        $where = array();
        $result = $this->mcommon->getMDevices($where);
        return $this->response->send($result);
    }


    public function getAlarm(Request $request){
        $input = $request->input() ? $request->input() : array();
        $test = isset($input['test']) ? $input['test'] : array();
        $this->environmental->addEmAlarm($test);
        return $this->response->send();

    }



}
