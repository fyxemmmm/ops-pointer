<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/3/21
 * Time: 10:45
 */

namespace App\Repositories\Monitor;

use App\Models\Monitor\Links;
use Illuminate\Http\Request;
use App\Repositories\BaseRepository;
use App\Models\Monitor\AssetsMonitor;
use App\Repositories\Monitor\CommonRepository;
use App\Repositories\Monitor\HengweiRepository;
use App\Repositories\Auth\UserRepository;
use App\Models\Assets\Device;
use App\Models\Code;
use App\Exceptions\ApiException;
use Log;
use Auth;
use Mockery\Exception;
use DB;

class AssetsMonitorRepository extends BaseRepository
{

    protected $common;
    protected $hengwei;
    const POINTS = 50;
    protected $user;
    protected $deviceModel;
    protected $linksModel;

    public function __construct(AssetsMonitor $assetsmonitorModel,
                                Request $request,
                                CommonRepository $common,
                                HengweiRepository $hengwei,
                                UserRepository $user,
                                Links $linksModel,
                                Device $deviceModel)
    {
        $this->model = $assetsmonitorModel;
        $this->hengwei = $hengwei;
        $this->common = $common;
        $this->user = $user;
        $this->deviceModel = $deviceModel;
        $this->linksModel = $linksModel;
    }


    /**
     * 根据资产获取绑定监控设备
     * @param $assetId
     * @return bool
     */
    public function getByAsset($assetId) {
        $data = $this->model->where("asset_id","=",$assetId)->first();
        if(empty($data)) {
            //Code::setCode(Code::ERR_HOST_BIND);
            return false;
        }
        return $data;
    }

    public function getByDevice($deviceId) {
        $data = $this->model->where("device_id",$deviceId)->get()->pluck("asset_id", "device_id")->toArray();
        if(empty($data)) {
            Code::setCode(Code::ERR_EM_NOT_DEVICE);
            return false;
        }
        return $data;
    }

    public function getMonitor($assetId){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        if(!$assetId){
            Code::setCode(Code::ERR_PARAMS,'资产ID不能为空');
            return false;
        }

        $where = array('asset_id' => $assetId);
        $data = $this->model->where($where)->first();
        $deviceId = isset($data['device_id']) ? $data['device_id'] : '';
        //资产级别
        $level = isset($data['level']) ? $data['level'] : 1 ;

        if(empty($data) || empty($deviceId)) {
            Code::setCode(Code::ERR_BIND_ASSETS_NOT);
            return false;
        }

        $input['deviceId'] = $deviceId;
        $info = $this->hengwei->getDeviceById($input);
        if($info === false) {
            return false;
        }
        $read_community = isset($info["access_params"]['snmp']['attributes']['read_community']) ? $info["access_params"]['snmp']['attributes']['read_community'] : '';
        $write_community = isset($info["access_params"]['snmp']['attributes']['write_community']) ? $info["access_params"]['snmp']['attributes']['write_community'] : '';

        $sec_level = isset($info["access_params"]['snmp']['attributes']['sec_level']) ? $info["access_params"]['snmp']['attributes']['sec_level'] : '';
        $sec_name = isset($info["access_params"]['snmp']['attributes']['sec_name']) ? $info["access_params"]['snmp']['attributes']['sec_name'] : '';
        $auth_proto = isset($info["access_params"]['snmp']['attributes']['auth_proto']) ? $info["access_params"]['snmp']['attributes']['auth_proto'] : '';
        $auth_pass = isset($info["access_params"]['snmp']['attributes']['auth_pass']) ? $info["access_params"]['snmp']['attributes']['auth_pass'] : '';
        $priv_proto = isset($info["access_params"]['snmp']['attributes']['priv_proto']) ? $info["access_params"]['snmp']['attributes']['priv_proto'] : '';
        $priv_pass = isset($info["access_params"]['snmp']['attributes']['priv_pass']) ? $info["access_params"]['snmp']['attributes']['priv_pass'] : '';

        $port = isset($info["access_params"]['snmp']['attributes']['port']) ? $info["access_params"]['snmp']['attributes']['port'] : '';
        $version = isset($info["access_params"]['snmp']['attributes']['version']) ? $info["access_params"]['snmp']['attributes']['version'] : '';
        $ret = [
            "custom_name" => isset($info['custom_name']) ? $info['custom_name'] : '',
            //"read_community" => $read_community,
            "read_community" => "******",
            //"write_community" => $write_community,
            "write_community" => "******",
            "port" => $port,
            "version" => $version,
            "ip"    => isset($info['fields']['address']) ? $info['fields']['address'] : '',
            "core" => $data['core'],
            "network_port" => $data['port'],
            'level' => $level,
            'sec_level' => $sec_level,
            'sec_name' => $sec_name,
            'auth_proto' => $auth_proto,
//            'auth_pass' => $auth_pass,
            'auth_pass' => "******",
            'priv_proto' => $priv_proto,
//            'priv_pass' => $priv_pass,
            'priv_pass' => "******",
        ];
        return $ret;
    }

    /**
     * 新增资产和监控设备绑定
     * @param $request
     * @return bool|string
     */
    public function addMonitor($input) {
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $result = '';
        $assetId = isset($input["assetId"]) ? $input["assetId"] : '';
        $ip = isset($input["ip"]) ? $input["ip"] : '';
        $core = isset($input["core"]) ? $input["core"] : 0;
        $level = isset($input["level"]) ? $input["level"] : 1;
        $data = $this->model->where("asset_id","=",$assetId)->first();
        if(!empty($data)) {
            Code::setCode(Code::ERR_MONITOR_EXISTS);
            return false;
        }
        if(!$ip){
            Code::setCode(Code::ERR_PARAMS,'ip不能为空');
            return false;
        }

        // 2.19 不同设备绑定相同监控的问题
        $sql_ip = $this->model->select('ip')->get()->pluck('ip')->toArray();
        if(in_array($ip, $sql_ip)){
            throw new ApiException(Code::COMMON_ERROR, ["请勿将设备绑定到相同监控"]);
        }
        // end

        DB::beginTransaction();
        try {
            if ($core) {
                if(in_array($core, [AssetsMonitor::CORE_SWITCH, AssetsMonitor::CORE_SERVER])) { //如果是核交或者服务器，先判断是否已有2台
                    $cnt = $this->model->where(["core" => $core])->count();
                    if($cnt >= 2) {
                        Code::setCode(Code::ERR_MONITOR_DEVICE_MAX);
                        return false;
                    }
                }
                else {
                    $this->model->where(["core" => $core])->limit(1)->update(['core' => 0]); //不是核心交换机或者服务器的，需要将已配置过的那台重置
                }
            }

            //调用监控系统获取设备id
            $deviceId = $this->hengwei->addDevice($input);
            if (!$deviceId) {
                Code::setCode(Code::ERR_ADD_MONITOR_FAIL);
                return false;
            }

            $insert = [
                "asset_id" => $assetId,
                "device_id" => $deviceId,
                "core" => $core,
                "ip" => $ip,
                "level" => $level
            ];

            $add = $this->store($insert);
            $id = isset($add['id'])?$add['id']:0;
            $input = array('assetId'=>$assetId);
            $info = $this->getHWDeviceInfo($input,true);
            /*$update = isset($info[$id])?$info[$id]:array();
            if(!empty($update)) {
                $this->update($id, $update);
            }*/
            DB::commit();
        }catch(Exception $e){
            DB::rollback();
        }

        Log::info("add monitor success. asset_id: $assetId device_id: $deviceId");

        $msg = sprintf("将资产加入了监控。资产id：%s 监控主机：%s IP：%s", $assetId, $deviceId, $ip);
        userlog($msg);
        return $result;
    }


    /**
     * 获取一条监控数据
     * @param array $input
     * @return bool|string
     */
    public function getOne($input=array())
    {
        $result = '';
        $assetId = isset($input['assetId']) ? $input['assetId'] : '';
        if ($assetId) {
            $where = array('asset_id' => $assetId);
            $data = $this->model->where($where)->first();
            $device_id = isset($data['device_id']) ? $data['device_id'] : 0;
            if (empty($data)) {
                Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
                return false;
            }
            //to do
            //$device_id调用监控系统需要用到
        }

        return $result;
    }


    public function getDeviceCpuDetail($input=array()){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
//        $assetId = $request->input("assetId");
        $input['type'] = 'cpu2';
        $input['deviceId'] = $this->getDeviceID($input);

//        $input['begin'] = $request->input("begin", date("Y-m-d H:i:s", strtotime("-1 hour")));
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $result = $this->hengwei->getDataByIdTypeDate($input);
        $data = [];
        $time = [];
        foreach($result as $v) {
            if(empty($v['key'])) {
                $key = "default";
            }
            else {
                $key = $v['key'];
            }
            if(!isset($data[$key])) {
                $data[$key] = [];
            }
            $data[$key][] = $v['cpu_percent'];
            $time[] = date("Y-m-d H:i:s", strtotime($v['time']));
        }

        $time = array_values(array_unique($time));
        $this->reduce($data, $time);
        return [
            "result" => $data,
            "time" => $time
        ];
    }


    public function getDeviceCpu($input=array()){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
//        $assetId = $request->input("assetId");
        $input['type'] = 'cpu';
        $input['deviceId'] = $this->getDeviceID($input);

//        $input['begin'] = $request->input("begin", date("Y-m-d H:i:s", strtotime("-1 hour")));
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $result = $this->hengwei->getDataByIdTypeDate($input);

        $data = [];
        $time = [];
        if($result) {
            foreach ($result as $v) {
                $data["cpu"][] = $v['cpu'];
                $time[] = date("Y-m-d H:i:s", strtotime($v['time']));
            }
        }

        $time = array_values(array_unique($time));
        $this->reduce($data, $time);
        return [
            "result" => $data,
            "time" => $time
        ];
    }


    public function getDeviceMem($input=array()){//验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
//        $assetId = $request->input("assetId");
        $input['type'] = 'mem2';

        $input['deviceId'] = $this->getDeviceID($input);

//        $input['begin'] = $request->input("begin", date("Y-m-d H:i:s", strtotime("-1 hour")));
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $result = $this->hengwei->getDataByIdTypeDate($input);
        if(false === $result) {
            return false;
        }
        $data = [];
        $time = [];
        foreach($result as $v) {
            if(empty($v['key'])) {
                $key = "内存使用率";
            }
            else {
                $key = $v['key'];
            }

            if(!isset($data[$key])) {
                $data[$key] = [];
            }
            $data[$key][] = [
                "free" => isset($v['mem_free'])?$v['mem_free']:'',
                "percent" => isset($v['mem_percent'])?$v['mem_percent']:'',
                "total" => isset($v['mem_total'])?$v['mem_total']:'',
                "used" => isset($v['mem_used'])?$v['mem_used']:'',
                "swap_free" => isset($v['swap_free'])?$v['swap_free']:null,
                "swap_total" => isset($v['swap_total'])?$v['swap_total']:null,
                "swap_used" => isset($v['swap_used'])?$v['swap_used']:null,
                "swap_used_per" => isset($v['swap_used_per'])?$v['swap_used_per']:null,
            ];
            $time[] = date("Y-m-d H:i:s", strtotime($v['time']));
        }

        $time = array_values(array_unique($time));
        $this->reduce($data, $time);
        return [
            "result" => $data,
            "time" => $time
        ];
    }

    public function getDeviceDisk($input=array()){//验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
//        $assetId = $request->input("assetId");
        $input['type'] = 'disk_partition';
        $input['deviceId'] = $this->getDeviceID($input);

//        $input['begin'] = $request->input("begin", date("Y-m-d H:i:s", strtotime("-1 hour")));
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $result = $this->hengwei->getDataByIdTypeDate($input);
        $data = [];
        $time = [];
        if(false === $result) {
            return false;
        }
        foreach($result as $v) {
            $vkey = isset($v['key']) ? $v['key'] : '';
            if(empty($vkey)) {
                $key = "default";
            }
            else {
                $key = $vkey;
            }

            if(!isset($data[$key])) {
                $data[$key] = [];
            }
            $data[$key][] = [
                "free" => isset($v['free']) ? $v['free'] : '',
                "used_percent" => isset($v['used_percent']) ? $v['used_percent'] : '',
                "total" => isset($v['total']) ? $v['total'] : '',
                "used" => isset($v['used']) ? $v['used'] : '',
                "label" => isset($v['label']) ? $v['label'] : '',
            ];
            $time[] = date("Y-m-d H:i:s", strtotime($v['time']));
        }

        $time = array_values(array_unique($time));
        $this->reduce($data, $time);
        return [
            "result" => $data,
            "time" => $time
        ];
    }

    public function getDeviceLoadavg($input=array()){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
//        $assetId = $request->input("assetId");
        $input['type'] = 'loadavg';
        $input['deviceId'] = $this->getDeviceID($input);

//        $input['begin'] = $request->input("begin", date("Y-m-d H:i:s", strtotime("-1 hour")));
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $result = $this->hengwei->getDataByIdTypeDate($input);

        $data = [];
        $time = [];

        if(!empty($result)) {
            foreach($result as $v) {
                $data["load_1m"][] = isset($v['load_1m']) ? $v['load_1m'] : '';
                $data["load_5m"][] = isset($v['load_5m']) ? $v['load_5m'] : '';
                $data["load_15m"][] = isset($v['load_15m']) ? $v['load_15m'] : '';
                $time[] = isset($v['time']) ? date("Y-m-d H:i:s", strtotime($v['time'])) : '';
            }
        }

        $time = array_values(array_unique($time));
        $this->reduce($data, $time);
        return [
            "result" => $data,
            "time" => $time
        ];
    }

    /***
     * @param array $input  eg: $input = array("mtype"=>1,"deviceId"=>"489","assetId"=>"1791");
     * @return array|bool
     * @throws ApiException
     */
    public function getDeviceAvail($input=array()) {
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }

        // 获取监控设备 ID
        $input['deviceId'] = $this->getDeviceID($input);
        $arr = [
            "cpu" => "cpu",
            "mem2" => "mem",
            "loadavg" => "loadavg",
            "disk_partition"=> "disk",
            "port" => "port"
        ];

        $result = [];
        foreach($arr as $k =>$v ) {
            try {
                if($k != "port") {
                    $input['type'] = $k;
                    // 根据设备ID和类型名获取数据
                    $this->hengwei->getOneByIdType($input);
                }
                else {
                    // 根据资产ID或监控设备ID获取一条带端口列表的数据
                    $this->hengwei->getSwitchboard($input);
                }

                $result[$v] = 1;
            }
            catch(ApiException $e) {
                list($code, $msg) = Code::getCode();
                if($code === Code::ERR_MONITOR_NOT_MATCH) {
                    $result[$v] = 0;
                }
                else {
                    throw $e;
                }
            }
        }
        Code::setCode(0);
        return $result;

    }


    protected function reduce(&$data, &$time) {
        $cnt = count($time);
        if($cnt <= self::POINTS) {
            return ;
        }

        $step = $cnt / self::POINTS;

        $i = 0;
        $newdata = [];
        $newtime = [];
        while(true) {
            if($i >= $cnt) {
                break;
            }

            foreach($data as $key => $value) {
                $newdata[$key][] = isset($value[$i]) ? $value[$i] : '';
            }

            $newtime[] = $time[$i];
            $i = $i + 1 * $step;
        }

        $data = $newdata;
        $time = $newtime;
    }


    /**
     * 更新监控
     * @param array $input
     * @return bool|string
     */
    public function updateMonitor($input=array()){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $assetId = isset($input['assetId']) ? $input['assetId'] : '';
        $core = isset($input['core']) ? $input['core'] : 0;
        $ip = isset($input["ip"]) ? $input["ip"] : '';
        $level = isset($input["level"]) ? $input["level"] : 0 ;
        if (empty($assetId)) {
            Code::setCode(Code::ERR_PARAMS,'资产ID不能为空');
            return false;
        }
        if ($assetId) {
            $where = array('asset_id' => $assetId);
            $data = $this->model->where($where)->first();

            $id = isset($data['id']) ? $data['id'] : 0;
            $device_id = isset($data['device_id']) ? $data['device_id'] : 0;
            if (empty($data) || !$device_id) {
                Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
                return false;
            }
            if(in_array($core, [AssetsMonitor::CORE_SWITCH, AssetsMonitor::CORE_SERVER]) && $core != $data['core']) { //如果是核交或者服务器，先判断是否已有2台
                $cnt = $this->model->where(["core" => $core])->count();
                if($cnt >= 2) {
                    Code::setCode(Code::ERR_MONITOR_DEVICE_MAX);
                    return false;
                }
            }
            $input['deviceId'] = $device_id;
            if(isset($input["read_community"]) && $input["read_community"] == "******") {
                unset($input["read_community"]);
            }
            if(isset($input["write_community"]) && $input["write_community"] == "******") {
                unset($input["write_community"]);
            }

            if(isset($input["auth_pass"]) && $input["auth_pass"] == "******") {
                unset($input["auth_pass"]);
            }
            if(isset($input["priv_pass"]) && $input["priv_pass"] == "******") {
                unset($input["priv_pass"]);
            }
            //$device_id调用监控系统需要用到
            $heDevice = $this->hengwei->updateDevice($input);
            if($heDevice) {
                $update = array('ip'=>$ip);
//                if($core == AssetsMonitor::CORE_SWITCH || $core == AssetsMonitor::CORE_ROUTER) {
                    if(isset($input['network_port']) && !empty($input['network_port'])) {
                        $update["port"] = $input['network_port'];
                    }
//                }

                if(!in_array($core, [AssetsMonitor::CORE_SWITCH, AssetsMonitor::CORE_SERVER]) && $core != $data['core']) { //变更设备类别,且不是核交
                    $this->model->where(["core" => $core])->update(['core' => 0]); //将需要变更的设备类别取出还原。
                }
                $update["core"] = $core;
                $info = $this->getHWDeviceInfo($input);
                $emInfo = isset($info[$id])?$info[$id]:array();
                unset($emInfo['ip']);
                $update = array_merge($update,$emInfo);
                if(!empty($update)) {
                    $this->update($data['id'], $update);
                }
                if(!empty($level)){
                    $data->level = $level;
                    $data->save();
                }

                Log::info("update monitor success. asset_id: $assetId device_id: $device_id");
                $msg = sprintf("更新监控设备。资产id：%s 监控设备ID：%s", $assetId, $device_id);
                userlog($msg);
            }
        }

        return true;
    }


    /**
     * 删除监控（解绑）
     * @param array $input
     * @return bool|string
     */
    public function delMonitor($assetId){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $where = array('asset_id' => $assetId);
        $data = $this->model->where($where)->first();
        if (empty($data)) {
            Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
            return false;
        }

        //需要确定当前设备是否有线路配置
        $linksCnt = $this->linksModel->where("source_asset_id","=", $assetId)
            ->orWhere("dest_asset_id","=", $assetId)
            ->count();
        if($linksCnt > 0) {
            Code::setCode(Code::ERR_LINKS_EXIST);
            return false;
        }

        $input['deviceId'] = isset($data['device_id']) ? $data['device_id'] : '';
        if(!empty($input['deviceId'])) {
            $this->hengwei->delDevice($input);
        }

        $del = $this->model->where($where)->forceDelete();
        if($del) {
            Log::info("delete monitor success. asset_id: $assetId device_id: ".$input['deviceId']);

            $msg = sprintf("删除监控设备。资产id：%s 监控设备ID：%s", $assetId, $input['deviceId']);
            userlog($msg);
            return true;
        }else{
            Code::setCode(Code::ERR_DEL_MONITOR_FAIL);
            return false;
        }
    }


    /**
     * 获取监控设备总数
     */
    public function getMonitorDeviceCount()
    {
        $data = $this->model->count();

        return $data;
    }



    /**
     * 删除监控（解绑）
     * @param array $input
     * @return bool|string
     */
    public function delAssetMonitor($deviceId){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $input['deviceId'] = $deviceId;
        $data = $this->model->where('device_id','=',$deviceId)->first();
        if($data){
            $assetId = $data->asset_id;
            $linksCnt = $this->linksModel->where("source_asset_id","=", $assetId)
                ->orWhere("dest_asset_id","=", $assetId)
                ->count();
            if($linksCnt > 0) {
                Code::setCode(Code::ERR_LINKS_EXIST);
                return false;
            }
            // 删除表里的这条记录
            $this->model->where('device_id','=',$deviceId)->forceDelete();
        }
        $this->hengwei->delDevice($input);  //删除恒维的监控
        return true;
    }



    /**
     * 根据资产类型获取资产监控设备
     * @param array $input
     * @return array
     */
    public function getAssetByCategory($input=array()){
        $result = array();
        $categoryId = isset($input['categoryId']) ? $input['categoryId'] : '';
        if(!$categoryId) {
            Code::setCode(Code::ERR_ASSET_CATEGORY_NOT);
            return false;
        }
        if($categoryId) {
            $model = $this->model->leftjoin("assets_device as AD", "assets_monitor.asset_id", "=", "AD.id")
                ->where("AD.category_id", "=", $categoryId)
                ->select("assets_monitor.*","AD.category_id","AD.name","AD.number");
            $ret = $model->get();
            $result = $ret->toArray();
        }

        return $result;
    }


    public function getList($where=array()){
        $model = $this->model;
        if($where){
            $model = $this->model->where($where);
        }
        $res = $model->get();
        return $res->toArray();
    }

    public function getListByInAssetId($where=array(),$whereIn=array()){
        $model = $this->model;
        if($where){
            $model = $this->model->where($where);
        }
        if($whereIn){
            $model = $model->whereIn('asset_id',$whereIn);
        }
        $res = $model->get();
        return $res->toArray();
    }


    /**
     * 获取监控设备ID
     * @param array $input
     * @return bool|string
     */
    public function getDeviceID($input=array()){
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $deviceId = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        if(!$deviceId) {
            if (!$assetId) {
                Code::setCode(Code::ERR_PARAMS, '资产ID不能为空');
                return false;
            }
            $where = array('asset_id' => $assetId);
            // assets_monitor 表
            $data = $this->model->where($where)->first();

            if (empty($data) || empty($data['device_id'])) {
                Code::setCode(Code::ERR_BIND_ASSETS_NOT);
                return false;
            }
            // 绑定资产 id
            $deviceId = isset($data['device_id']) ? $data['device_id'] : '';
        }
        return $deviceId;
    }




    /**
     * 获取监控设备信息
     * @param array $input
     * @return array|bool
     */
    public function getHWDeviceInfo($input=array(),$upFlag=false){
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            return false;
        }
        $assetId = isset($input['assetId']) ? trim($input['assetId']) : '';
        $where = array();//array('status'=>1);
        if($assetId){
            $where['asset_id'] = $assetId;
        }
        $amData = $this->getList($where);
//        return $amData;

        $result = array();
        if($amData) {
            foreach($amData as $v) {
                $id = isset($v['id']) ? trim($v['id']) : '';
                $assetId = isset($v['asset_id']) ? trim($v['asset_id']) : '';
                $deviceId = isset($v['device_id']) ? trim($v['device_id']) : '';
                $amPort = isset($v['port']) ? trim($v['port']) : '';
                $ip = isset($v['ip']) ? trim($v['ip']) : '';
                $input['deviceId'] = $deviceId;
                $input['assetId'] = $assetId;
                try{
//                    $available = $this->getDeviceAvail($input);
//                    $isLoadavg = isset($available['loadavg']) ? $available['loadavg'] : 0;
                    if(!$ip) {
                        $ip = $this->common->getDeviceIP($input);
                    }
                    $info = $this->hengwei->getDevicePerformanceById($input);

                    $cpu_percent = isset($info['cpu']) ? $info['cpu'] : 0;
                    $mem_percent = isset($info['memory']['mem_percent']) ? $info['memory']['mem_percent'] : 0;
                    $disk_percent = isset($info['disk']['used_percent']) ? $info['disk']['used_percent'] : 0;
                    $disk_total = isset($info['disk']['total']) ? $info['disk']['total'] : 0;
                    $disk_used = isset($info['disk']['used']) ? $info['disk']['used'] : 0;
//                    $disk_percent = $disk_used ? round($disk_total/$disk_used,2) : 0;
                    $status = $this->common->getDeviceStatus($input);
//                    $load = $isLoadavg ? $this->getDeviceCurrentLoadavg($input) : '';
//                    var_dump($load);exit;
                    //工作天数，单位：秒
                    $workDays = $this->common->getDeviceWorkDays($input);

                    $result[$id] = array(
                        'cpu_percent' => $cpu_percent,
                        'mem_percent' => $mem_percent,
                        'disk_percent' => $disk_percent,
                        'ip' => $ip,
                        'device_status' => $status ? 1 : 0,
//                        'loadavg' => $load ? json_encode($load) : '',
                        'work_days' => isset($workDays['result']) ? intval($workDays['result']) : 0,
                    );
                    $update = isset($result[$id]) ? $result[$id] : array();
                    if($update && $upFlag) {
                        $this->update($id, $update);
                    }
                }catch(\Exception $e){
//                    Log::error($e);
                    continue;
                }


            }

        }
        return $result;
    }


    /**
     * 当前负载
     * @param array $input
     * @return bool
     */
    public function getDeviceCurrentLoadavg($input=array())
    {
        //验证监控功能是否开启
        if (!\ConstInc::$mOpen) {
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
//        $assetId = $request->input("assetId");
        $input['type'] = 'loadavg';
        $input['deviceId'] = $this->getDeviceID($input);

//        $input['begin'] = $request->input("begin", date("Y-m-d H:i:s", strtotime("-1 hour")));
        $input['begin'] = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s");
        $input['end'] = isset($input['end']) ? $input['end'] : date("Y-m-d H:i:s");
        $result = $this->hengwei->getDataByIdTypeDate($input);
        $data = [];

        if (!empty($result) && isset($result[0])) {
            $res = $result[0];
            $data["load_1m"] = isset($res['load_1m']) ? $res['load_1m'] : '';
            $data["load_5m"] = isset($res['load_5m']) ? $res['load_5m'] : '';
            $data["load_15m"] = isset($res['load_15m']) ? $res['load_15m'] : '';

        }
        return $data;
    }


    /**
     * 获取资产监控列表
     * @param $request
     * @param string $categoryId
     * @param string $pid
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     */
    public function getDeviceList($request,$categoryId='',$pid='',$sortColumn = "created_at", $sort = "desc"){
        $search = $request->input("s");
        $where = [];

        if($this->user->isEngineer() || $this->user->isManager()) {
            $limitCategories = $this->user->getCategories();
        }

        $model = $this->model->join("assets_device as A","assets_monitor.asset_id","=","A.id");
        $model = $model->join("assets_category as B","A.sub_category_id","=","B.id");
        if(isset($limitCategories)) {
            $model = $model->whereIn("A.sub_category_id",$limitCategories);
        }

        $model = $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("A.number", "like", "%" . $search . "%");
                $query->orWhere("A.name", "like", "%" . $search . "%");
                $query->orWhere("assets_monitor.ip", "like", "%" . $search . "%");
            }
        });

        if($categoryId || $pid){
            if(!$pid){
                $pid = $categoryId;
            }else{
                $where[] = ['A.sub_category_id','=',$categoryId];
            }
            $where[] = ['A.category_id','=',$pid];

            $model = $model->where($where);
        }
        $fieldArrKeys[] = "A.id";
        $fieldArrKeys[] = "A.category_id";
        $fieldArrKeys[] = "A.sub_category_id";
        $fieldArrKeys[] = "A.number";
        $fieldArrKeys[] = "A.name";
        $fieldArrKeys[] = "B.name as category_name";
        $fieldArrKeys[] = "assets_monitor.*";
        $model = $model->select($fieldArrKeys);
        return $this->usePage($model,$sortColumn,$sort);
    }


    /**
     * 根据监控设备id（多个）获取资产列表
     * @param $input
     * @return array|bool
     */
    public function getAssetsByMDeviceIds($input){
        $result = array();
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : '';
        $deviceIds = array_filter(array_unique(explode(',',$deviceId)));

        if(!$deviceIds) {
            Code::setCode(Code::ERR_MONITOR_NOT_IDS);
            return false;
        }
        $model = $this->model->join("assets_device as AD", "assets_monitor.asset_id", "=", "AD.id")
            ->join("assets_enginerooms as C","AD.area","=","C.id")
            ->whereIn("device_id", $deviceIds);
        $fieldArrKeys = array(
            "assets_monitor.*","AD.category_id","AD.name","AD.number",
            "C.name as engineroom",
            "C.id as engine_id",
        );
        $ret = $model->select($fieldArrKeys)->get(); 
        $result = $ret->toArray();


        return $result;
    }


    /**
     * 批量新增资产和监控设备绑定
     * @param $input
     * @return bool|string
     */
    public function addBatchMonitor($params=array()) {
        //验证监控功能是否开启
        if(!\ConstInc::$mOpen){
            Code::setCode(Code::ERR_M_MONITOR_CLOSEED);
            return false;
        }
        $result = false;
        DB::beginTransaction();
        $inputList = getKey($params,'list');
        try {
            if($inputList) {
                $add = array();
                $up = array();
                foreach($inputList as $input) {
                    $date = date('Y-m-d H:i:s');
                    $assetId = isset($input["assetId"]) ? $input["assetId"] : '';
                    $ip = isset($input["ip"]) ? $input["ip"] : '';
                    $core = isset($input["core"]) ? $input["core"] : 0;
                    $level = isset($input["level"]) ? $input["level"] : 1;
                    $deviceId = isset($input["deviceId"]) ? $input["deviceId"] : 1;
//                    $orWhere = array('asset_id'=>$assetId,'device_id'=>$deviceId);
                    $where = array('device_id'=>$deviceId);
                    $data = $this->model->where($where)->first();
                    $id = isset($data['id']) ? $data['id'] : 0;
                    $asset_id = isset($data['asset_id']) ? $data['asset_id'] : '';
                    $param = [
                        "asset_id" => $assetId,
                        "device_id" => $deviceId,
                        "core" => $core,
                        "ip" => $ip,
                        "level" => $level
                    ];
                    $aWhere = array('asset_id'=>$assetId);
                    $aData = $this->model->where($aWhere)->first();
                    $aId = isset($aData['id']) ? $aData['id'] : 0;

                    if($id){
                        if(!$aData || ($aData && $id == $aId)) {
                            $update = $this->update($id, $param);
                            if ($update) {
                                $up[] = $id;
                            }
                        }
                    }else{
                        $param['created_at'] = $date;
                        $param['updated_at'] = $date;
                        if(!$aData) {
                            $add[] = $this->model->insert($param);
                        }
                    }
                }
                if(count($add) || count($up)){
                    $result = true;
                }

            }
            DB::commit();
        }catch(Exception $e){
            DB::rollback();
        }
        if(!$result){
            Code::setCode(Code::ERR_EMPTY_UNBING_MONITOR);
            return false;
        }
//        Log::info("add batch monitor success. asset_id: $assetId device_id: $deviceId");

        $msg = sprintf("将资产加入了监控。资产id：%s 监控主机：%s IP：%s", $assetId, $deviceId, $ip);
        userlog($msg);
        return $result;
    }


}