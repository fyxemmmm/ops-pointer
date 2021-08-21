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
use App\Models\Code;
use App\Exceptions\ApiException;
use DB;

class EmWanlianRepository extends BaseRepository
{
    protected $emMonitorpointModel;
    protected $emrealtimedataModel;
    protected $emdeviceModel;

    public function __construct(EmMonitorpoint $emmonitorpointModel,EmRealtimeData $emrealtimedataModel,EmDevice $emdeviceModel)
    {
        $this->emMonitorpointModel = $emmonitorpointModel;
        $this->emrealtimedataModel = $emrealtimedataModel;
        $this->emdeviceModel = $emdeviceModel;
    }


    /**
     * 万联-获取设备
     * @return mixed|string
     */
    public function WLGetDevice(){
        $result = array();
        $xml = apiCurl(\ConstInc::EM_API_URL_WS . '/GetSite');
        if ($xml) {
            $result = xmlobjectToArray($xml);
        }
        return $result;
    }


    /**
     * 万联-获取当前报警
     * @return mixed|string
     */
    public function WLGetAlarm(){
        $result = array();
        $xml = apiCurl(\ConstInc::EM_API_URL_WS . '/GetAlarm');
        if ($xml) {
            $result = xmlobjectToArray($xml);
        }

        return $result;
    }


    /**
     * 万联-获取当前所有实时数据
     * @return mixed|string
     */
    public function WLGetData(){
        $result = array();
        $xml = apiCurl(\ConstInc::EM_API_URL_WS . '/GetData');
        if ($xml) {
            $result = xmlobjectToArray($xml);
        }
        return $result;
    }


    /**
     * 万联-根据设备IP获取当前实时数据
     * @return mixed|string
     */
    public function WLGetDataForIP($input=array()){
        $ip = isset($input['ip']) ? $input['ip'] : '';
        $result = array();
        if(!$ip){
            throw new ApiException(Code::ERR_PARAMS, ["ip"]);
        }

        $xml = apiCurl(\ConstInc::EM_API_URL_WS . '/GetDataForIP?ip=' . $ip);
        if ($xml) {
            $result = xmlobjectToArray($xml);
        }
        return $result;
    }


    public function getDataForIPSub($input=array()){
        $ip = isset($input['ip']) ? $input['ip'] : '';
        $lscds = isset($input['lscids']) ? $input['lscids'] : '';
        $result = array();
        if(!$ip){
            throw new ApiException(Code::ERR_PARAMS, ["ip"]);
        }
        if(!$lscds){
            Code::setCode(Code::ERR_PARAMS, '',['监控点']);
        }
        $xml = apiCurl(\ConstInc::EM_API_URL_WS . '/GetDataForIPAndSub?ip=' . $ip.'&lscid='.$lscds);
        if ($xml) {
            $result = xmlobjectToArray($xml);
        }
        return $result;
    }




}