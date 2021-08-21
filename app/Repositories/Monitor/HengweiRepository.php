<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/3/21
 * Time: 10:45
 */

namespace App\Repositories\Monitor;

use Illuminate\Http\Request;
use App\Repositories\BaseRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Monitor\AssetsMonitor;
use App\Models\Monitor\Links;
use App\Support\GValue;
use ConstInc;

class HengweiRepository extends BaseRepository
{

    protected $hwToken;
    const DEF_RETURN = array('result' => '');
    const SPEED = 1000000000;

    //恒维提供的等级
    public $alertLevel = array(
        1 => '提示级',
        2 => '低级',
        3 => '中级',
        4 => '高级',
        5 => '紧急级'
    );
    const HWTIMEOUT = '30s';
    protected $assetsmonitorModel;
    protected $links;
    protected $mTokenFilename;
    protected $url;


    public function __construct(AssetsMonitor $assetsmonitorModel, Links $monitorLinks)
    {
        $this->assetsmonitorModel = $assetsmonitorModel;
        $this->links = $monitorLinks;
        $this->url = ConstInc::$mApiUrl;
    }


    /**
     * 恒维-api登录获取token
     * @return array
     */
    public function hwApiLogin(){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $result = '';//self::DEF_RETURN;
        $url = $this->url.'/api/token?_method=POST&username='.ConstInc::$mApiUsername.'&password='.ConstInc::$mApiPwd;
        $resJson = apiCurl($url, 'get');
        if($resJson) {
            $data = json_decode($resJson, true);
            $result = isset($data) ? $data : '';
        }
        return $result;
    }

    protected function checkEnable() {
        if(!ConstInc::$mOpen){
            return false;
        }
        return true;
    }


    public function getHWToken($force = false){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $datetime = time();
//        $hwToken = $this->request->session()->get("hw_token");
//        $hw_token_expires = $this->request->session()->get("hw_token_expires");

        $jsonToken = $this->getStorageToken(true);
        $hwToken = isset($jsonToken['token']) ? $jsonToken['token'] : '';
        $hw_token_expires = isset($jsonToken['expires']) ? $jsonToken['expires'] : '';
        $timeDiff = intval($hw_token_expires)-$datetime;
        if($force || !$hwToken || $timeDiff<=0){
            $hwLogin = $this->hwApiLogin();
            $hwToken = isset($hwLogin['token']) ? $hwLogin['token'] : '';
            $expires_in = isset($hwLogin['expires_in']) ? intval($hwLogin['expires_in']) : 0;
            $expires = $datetime+$expires_in-10;
            if($hwToken) {
                $tokenData = array('token' => $hwToken, 'expires' => $expires, 'time' => date("Y-m-d H:i:s"));

//            $this->request->session()->put("hw_token",$hwToken);
//            $this->request->session()->put("hw_token_expires",$expires);
                $mTokenFilename = GValue::$currentDB.'/'.\ConstInc::$mTokenFilename;
                Storage::put($mTokenFilename, json_encode($tokenData));
            }

        }
        return $hwToken;
    }


    /**
     * 获取storage文件里的token
     * @return string
     */
    public function getStorageToken($isAll=false){
        $jsonToken = array();
        $result = '';
        $mTokenFilename = GValue::$currentDB.'/'.\ConstInc::$mTokenFilename;
        if(Storage::exists($mTokenFilename)) {
            $tokenFile = Storage::get($mTokenFilename);
            $jsonToken = json_decode($tokenFile, true);
        }
        $hwToken = isset($jsonToken['token']) ? $jsonToken['token'] : '';
//        $hw_token_expires = isset($jsonToken['expires']) ? $jsonToken['expires'] : '';
        $this->hwToken = $hwToken;
        if($isAll){
            $result = $jsonToken;
        }else{
            $result = $hwToken;
        }

        return $result;
    }

    /**
     * 新增资产和监控设备绑定
     * @param $request
     * @return bool|string
     */
    public function addDevice($input) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $ip = isset($input["ip"]) ? $input["ip"] : '';
        $port = isset($input["port"]) ? $input["port"] : '';
        //设备级别
        $level = isset($input["level"]) ? $input["level"] : 1 ;
        $custom_name = isset($input["custom_name"]) ? $input["custom_name"] : '';
        $read_community = isset($input["read_community"]) ? $input["read_community"] : '';
        $write_community = isset($input["write_community"]) ? $input["write_community"] : '';
        $version = isset($input["version"]) ? $input["version"] : '';
        //snmp V3参数 start
        $secLevel = isset($input["sec_level"]) ? $input["sec_level"] : '' ;
        $secName = isset($input["sec_name"]) ? $input["sec_name"] : '' ;
        $authProto = isset($input["auth_proto"]) ? $input["auth_proto"] : '' ;
        $authPass = isset($input["auth_pass"]) ? $input["auth_pass"] : '' ;
        $privProto = isset($input["priv_proto"]) ? $input["priv_proto"] : '' ;
        $privPass = isset($input["priv_pass"]) ? $input["priv_pass"] : '' ;
        //end

        if(!$ip){
            Code::setCode(Code::ERR_PARAMS,'ip不能为空');
            return false;
        }

        $url = $this->url.'/ds/v2/mo/@create?';
        $param = array(
            "custom_name" => $custom_name,
            "tags" => array(),
            "fields" => array(
                "address"=> $ip,
                "level" => $level
            ),
            "access_params"=>array(
                "snmp" => array(
                    "version" => $version ? $version : "v2",
                    "port" => $port ? $port : '161',

                )
            )
        );


        if('v3' == $version){
            $param['access_params']['snmp']['sec_level'] = $secLevel ? $secLevel : '';
            $param['access_params']['snmp']['sec_name'] = $secName ? $secName : '';
            $param['access_params']['snmp']['auth_proto'] = $authProto ? $authProto : '';
            $param['access_params']['snmp']['auth_pass'] = $authPass ? $authPass : '';
            $param['access_params']['snmp']['priv_proto'] = $privProto ? $privProto : '';
            $param['access_params']['snmp']['priv_pass'] = $privPass ? $privPass : '';
        }else {
            $param['access_params']['snmp']['read_community'] = $read_community ? $read_community : "public";
            $param['access_params']['snmp']['write_community'] = $write_community ? $write_community : "public";
        }
        $jsonParam = json_encode($param);
        $device_id = $this->requestApi($url, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        if(!$device_id){
            Log::error("add hw monitor fail. ip: $ip");
            Code::setCode(Code::ERR_ADD_MONITOR_FAIL);
            return false;
        }

        Log::info("add hw monitor success.device_id: $device_id");
        return $device_id;
    }



    /**
     * 根据设备ID获取一条记录
     * @param array $input
     * @return bool|string
     */
    public function getDeviceById($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
//        $result = '';
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : '';
        if (!$deviceId) {
            Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
            return false;
        }
        $url = $this->url.'/ds/v2/mo/' . $deviceId . '?includes=access_params';
        $result = $this->requestApi($url, 'get',\ConstInc::HEADERS_JSON);
        return $result;

    }


    /**
     * 更新监控
     * @param array $input
     * @return bool|string
     */
    public function updateDevice($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : '';
        $ip = isset($input["ip"]) ? $input["ip"] : '';
        $port = isset($input["port"]) ? $input["port"] : '';
        $custom_name = isset($input["custom_name"]) ? $input["custom_name"] : '';
        $read_community = isset($input["read_community"]) ? $input["read_community"] : '';
        $write_community = isset($input["write_community"]) ? $input["write_community"] : '';
        $version = isset($input["version"]) ? $input["version"] : '';
        $level = isset($input["level"]) ? $input["level"] : 5 ;
        //snmp V3参数 start
        $secLevel = isset($input["sec_level"]) ? $input["sec_level"] : '' ;
        $secName = isset($input["sec_name"]) ? $input["sec_name"] : '' ;
        $authProto = isset($input["auth_proto"]) ? $input["auth_proto"] : '' ;
        $authPass = isset($input["auth_pass"]) ? $input["auth_pass"] : '' ;
        $privProto = isset($input["priv_proto"]) ? $input["priv_proto"] : '' ;
        $privPass = isset($input["priv_pass"]) ? $input["priv_pass"] : '' ;
        //end
        if (!$deviceId) {
            Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
            return false;
        }
        //to do
        //$device_id调用监控系统需要用到
        $param = array();
        if($custom_name){
            $param['custom_name'] = $custom_name;
        }
        if($ip){
            $param['fields']['address'] = $ip;
        }
        if($level){
            $param['fields']['level'] = $level;
        }
        if($port){
            $param['access_params']['snmp']['port'] = $port;
        }
        if($version){
            $param['access_params']['snmp']['version'] = $version;
        }

        if('v3' == $version) {
            $param['access_params']['snmp']['auth_proto'] = $authProto ? $authProto : '';
            $param['access_params']['snmp']['priv_proto'] = $privProto ? $privProto : '';
            if ($secLevel) {
                $param['access_params']['snmp']['sec_level'] = $secLevel;
            }
            if ($secName) {
                $param['access_params']['snmp']['sec_name'] = $secName;
            }
            if ($authPass) {
                $param['access_params']['snmp']['auth_pass'] = $authPass;
            }
            if ($privPass) {
                $param['access_params']['snmp']['priv_pass'] = $privPass;
            }
        }else{
            if($read_community){
                $param['access_params']['snmp']['read_community'] = $read_community;
            }
            if($write_community){
                $param['access_params']['snmp']['write_community'] = $write_community;
            }
        }

        $jsonParam = json_encode($param);

        $url = $this->url.'/ds/v2/mo/'.$deviceId.'?timeout='.self::HWTIMEOUT;
//            var_dump($url,$jsonParam);exit;
        $result = $this->requestApi($url, 'put',\ConstInc::HEADERS_JSON, $jsonParam);
        if(!$result){
            Log::error("update hw monitor fail. device_id: $deviceId");
            Code::setCode(Code::ERR_UPDATE_MONITOR_FAIL);
            return false;
        }else{
            Log::info("update hw monitor success. device_id: $deviceId");
        }


        return $result;
    }


    /**
     * 删除监控
     * @param array $input
     * @return bool|string
     */
    public function delDevice($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : 0;
        if (!$deviceId) {
            Code::setCode(Code::ERR_PARAMS,'监控设备id不能为空');
            return false;
        }

        $url = $this->url.'/ds/v2/mo/' . $deviceId.'?';
        $result = $this->requestApi($url, 'delete', \ConstInc::HEADERS_JSON);
        if($result) {
            Log::info("delete hw monitor success. device_id: $deviceId");
            return true;
        }else{
            Log::info("delete hw monitor fail. device_id: $deviceId");
//            Code::setCode(Code::ERR_HW_NOT_RESPONSE_DATA);
            return false;
        }
    }



    /**
     * 恒维-获取所有网络设备
     * @param string $token
     * @return array
     */
    public function getDevices(){
        $url = $this->url.'/ds/v2/mo?by_type=network_node';
        $result = $this->requestApi($url, 'get');
        return $result;
    }


    /**
     * cpu memory disk 使用率
     * @param array $input
     * @return array|bool
     */
    public function getDevicePerformanceById($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $result = array(
            'cpu'=>'',
            'memory' => '',
            'disk' => ''
        );
        $did = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        if(!$did){
            Code::setCode(Code::ERR_PARAMS,'监控设备id不能为空');
            return false;
        }

        $urlCpu = $this->url.'/ds/v2/mo/'.$did.'/runtime/metric/cpu/last?timeout='.self::HWTIMEOUT;
        $resJsonCpu = $this->requestApi($urlCpu, 'get', \ConstInc::HEADERS_JSON);
        if ($resJsonCpu) {
            $result['cpu'] = isset($resJsonCpu['cpu']) ? $resJsonCpu['cpu'] : '';
        }
        //Memory
        $urlMem = $this->url.'/ds/v2/mo/'.$did.'/runtime/metric/mem/last?timeout='.self::HWTIMEOUT;
        $resJsonMem = $this->requestApi($urlMem, 'get', \ConstInc::HEADERS_JSON);
        if ($resJsonMem) {
            $data = $resJsonMem;//json_decode($resJsonMem, true);
            $mem_total = 0;
            $mem_used = 0;
            if(isset($data['total'])) {
                $mem_total = $data['total'];
            }
            $mem_total = $mem_total / 1024;

            if(isset($data['used'])) {
                $mem_used = $data['used'];
            }
            $mem_used = $mem_used / 1024;

            $result['memory'] = array(
                'mem_percent' => isset($data['used_per']) ? round($data['used_per'],2) : '',
                'mem_total' => $mem_total,
                'mem_used' => $mem_used
            );
        }
        //磁盘
        $urlDisk = $this->url.'/ds/v2/mo/'.$did.'/runtime/metric/disk_partition/last?timeout='.self::HWTIMEOUT;
        try {
            $resJsonDisk = $this->requestApi($urlDisk, 'get', \ConstInc::HEADERS_JSON);
        }
        catch(ApiException $e) {
            Log::error($e);
            list($code, $msg) = Code::getCode();
            if($code == Code::ERR_MONITOR_NOT_MATCH) { //不匹配的错误忽略
                Code::setCode(0);
            }
            $resJsonDisk = false;
        }
        if ($resJsonDisk) {
            $data = $this->getDiskFormat($resJsonDisk);
            $disk_total = isset($data['total']) ? $data['total'] : '';
            $disk_total  = $disk_total ? $disk_total/1024 : '';
            $disk_used = isset($data['used']) ? $data['used'] : '';
            $disk_used = $disk_used ? $disk_used/1024 : '';
            $result['disk'] = array(
                'total' => $disk_total,
                'used' => $disk_used,
                'used_percent' => isset($data['used_percent']) ? round($data['used_percent'],2) : ''
            );
        }
        return $result;
    }


    /**
     * 线路流量
     * @param array $input
     * @return array|bool
     */
    public function getDeviceLinkById($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $did = isset($input['deviceId']) ? trim($input['deviceId']) : '';
        $type = getKey($input,'type','link_flux');

        if(!$did){
            Code::setCode(Code::ERR_PARAMS,'监控设备id不能为空');
            return false;
        }

        if(!$type){
            Code::setCode(Code::ERR_PARAMS,'类型名不能为空');
            return false;
        }
        $url = $this->url.'/ds/v2/'.$did.'/runtime/metric/'.$type.'?timeout='.self::HWTIMEOUT;
        $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);

        return $result;
    }


    /**
     * 根据设备id获取告警总数
     * @param array $input
     * @return bool|string
     */
    public function getAlertCountById($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $did = isset($input['device_id']) ? $input['device_id'] : '';
        if(!$did){
            Code::setCode(Code::ERR_PARAMS,'设备id不能为空');
            return false;
        }

        $url = $this->url.'/ts/alert_cookies/count?@managed_id='.$did;
        $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);
        return $result;
    }


    /**
     * 获取24小时告警总数
     * @param array $input
     * @return string
     * @deprecated  改用数据库获取
     */
    public function __getAlertCountDay($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $date = date('Y-m-d H:i:s');
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : '';
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

        $result = $this->getAlertHistoryCount($input);
        return $result;
    }


    /**
     * 获取当前告警总数
     * @param array $input
     * @return string
     */
    public function getAlertCount($input=array(),$paramStr='',$am='', $type = 0){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $result = '';
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $end = isset($input['end']) ? $input['end'] : '';
        $end = $end ? date('Y-m-d H:i:s',strtotime($end)) : '';
        $param = '';

        if(!$am) {
            $am = $this->getAssetmonitorDeviceId($type);
        }
        if($paramStr){
            $param = $paramStr;
        }else {
            if ($am) {
                $param .= '@managed_id=[in]' . $am . '&';
            }
            if ($begin) {
                $datestr = '[>=]' . $begin;
                $datestr = urlencodeTime($datestr);
                $param .= '@triggered_at=' . $datestr . '&';
            }
            if ($end) {
                $datestr = '[<=]' . $end;
                $datestr = urlencodeTime($datestr);
                $param .= '@triggered_at=' . $datestr . '&';
            }
        }


        if($am) {
            $url = $this->url.'/ts/alert_cookies/count?' . $param;
            $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);
        }

        return $result;
    }


    /**
     * 获取当前告警列表
     * @param array $input
     * @return
     */
    public function getAlertList($input=array(), $type = 0){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $date = date('Y-m-d H:i:s');
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : '';
        $offset = isset($input['offset']) ? $input['offset'] : '';
        $page = isset($input['page']) ? intval($input['page']) : '';//代表第几页，和offset参数二选一
        $limit = isset($input['limit']) && $input['limit'] ? intval($input['limit']) : 10;
        $offset = $offset ? $offset : getPageOffset($page,$limit);
        $pageAll = isset($input['pageall']) ? trim($input['pageall']) : '';
        $content = isset($input['content']) ? trim($input['content']) : '';
        $param = '';
        $amDids = $this->getAssetmonitorDeviceId($type);

        if($amDids){
            $param .= '@managed_id=[in]'.$amDids.'&';
        }

        if($begin){
            $datestr = '[>=]'.$begin;
            $datestr = urlencodeTime($datestr);
            $param .= '@triggered_at='.$datestr.'&';
        }
        if($edate){
            $datestr = '[<=]'.$edate;
            $datestr = urlencodeTime($datestr);
            $param .= '@triggered_at='.$datestr.'&';
        }
        if($content){
            $param .= '@content='.urlencode("[like]%$content%").'&';
        }


        if($amDids) {
            $hCount = $this->getAlertCount($input, $param, $amDids, $type);
            if (!$pageAll) {
                if ($limit > 100) {
                    Code::setCode(Code::ERR_PAGE_TOO_MUCH100);
                    return false;
                }
                if ($offset) {
                    $param .= 'offset=' . $offset . '&';
                }
                if ($limit) {
                    $param .= 'limit=' . $limit . '&';
                }
            }


            $url = $this->url.'/ts/alert_cookies?' . $param;
            $res = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);
            if ($res) {
                foreach ($res as $k => $v) {
                    $result[$k] = $v;
                    $result[$k]['triggered_at'] = isset($v['triggered_at']) ? date('Y-m-d H:i:s', strtotime($v['triggered_at'])) : '';
                }
                return array('total' => $hCount, 'result' => $result);
            }
        }

        return array('total'=>0,'result'=>[]);

    }

    /**
     * 请求接口
     * @param $url
     * @param string $method
     * @param array $headers
     * @param array $params
     * @return string
     * @throws ApiException
     */

    protected function requestApi($url, $method = 'get', $headers = [], $params =[], $retry = 0) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $token = $this->getHWToken();
        $realurl = $url . '&token=' . $token;
        if(empty($token)) {
            Log::error("get hw monitor get error.no token");
            throw new ApiException(Code::ERR_GET_MONITOR_TOKEN_ERR);
        }

        $ret = apiCurl($realurl, $method, $headers, $params);
        if(!$ret) {
            Log::error("request api null");
            throw new ApiException(Code::ERR_MONITOR_RETURN_NULL);
        }
        $data = json_decode($ret, true);

        if(isset($data['errors'])){
            if(isset($data['errors'][0]) && isset($data['errors'][0]['code'])) {
                Log::error("hw monitor notmatch .error : $ret");
                if(strpos($data['errors'][0]['code'], "406") === 0) {
                    throw new ApiException(Code::ERR_MONITOR_NOT_MATCH);
                }
                switch($data['errors'][0]['code']) {
                    case 404:
                        Log::error("hw monitor code error: 404");
//                        throw new ApiException(Code::ERR_MONITOR_RETURN_ERRORS,["找不到该设备"]);
                    case 500:
                        Log::error($data['errors'][0]['message']);
                        //dd($realurl, $method,$params,$data['errors']);
                        throw new ApiException(Code::ERR_MONITOR_RETURN_ERRORS,[$data['errors'][0]['message']]);
                }
            }
            Log::error("hw monitor error : $ret");
        }
        else if(isset($data['code']) && $data['code'] != 200) {
            if($data['code'] == "401") {
                $this->getHWToken(true);
                if($retry++ < 1) {
                    $ret = $this->requestApi($url, $method, $headers, $params, $retry);
                    return $ret;
                }
            }
            Log::error("hw monitor code error: $ret");
            throw new ApiException(Code::ERR_MONITOR_RETURN_ERRCODE);
        }
        else if(isset($data['message'])){
            Log::error("hw monitor message: $ret");
//            throw new ApiException(Code::ERR_MONITOR_RETURN_MSSSAGE);
        }
        if(isset($data['data'])) {
            return $data['data'];
        }elseif(isset($data['value'])){
            return $data['value'];
        }
        else {
            return [];
        }
    }


    /**
     * 根据设备ID和类型名、时间获取数据
     * @param array $input
     * @return array
     */
    public function getDataByIdTypeDate($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $did = isset($input['deviceId']) ? $input['deviceId'] : '';
        $type = isset($input['type']) ? $input['type'] : '';
        $start = isset($input['begin']) ? $input['begin'] : '';
        $end = isset($input['end']) ? $input['end'] : '';
        if (!$did) {
            $did_errmsg = '设备id不能为空';
            if(in_array($type,['interface_flux','link_flux'])){
                $did_errmsg = '未获取到或未配置监控端口';
            }
            Code::setCode(Code::ERR_PARAMS,$did_errmsg);
            return false;
        }
        if(!$type){
            Code::setCode(Code::ERR_PARAMS,'类型名不能为空');
            return false;
        }
        if(!$start){
            Code::setCode(Code::ERR_PARAMS,'开始时间不能为空');
            return false;
        }

        $url = $this->url.'/ds/v2/mo/' . $did . '/runtime/metric/'.$type;

        $starttime = strtotime($start);
        if($end) {
            $endtime = strtotime($end);
        }
        else {
            $endtime = time();
        }

        if($endtime - $starttime > 86400) {
            $cur_starttime = $starttime;
            $result = [];
            while(true) {
                $cur_endtime = $cur_starttime + 86400;
                if($cur_endtime > $endtime) {
                    $cur_endtime = $endtime;
                }
                $cur_start = urlencodeTime(date("Y-m-d H:i:s", $cur_starttime));
                $cur_end = urlencodeTime(date("Y-m-d H:i:s", $cur_endtime));
                $cururl = $url . '?begin_at='.$cur_start."&end_at=".$cur_end;
                $ret = $this->requestApi($cururl, 'get', \ConstInc::HEADERS_JSON);
                $result = array_merge($result, $ret);
                $cur_starttime = $cur_endtime + 1;
                if($cur_starttime > $endtime) {
                    break;
                }
            }
        }
        else {
            $url .= '?begin_at='.urlencodeTime($start);
            if($end) {
                $url .= "&end_at=".urlencodeTime($end);
            }
            $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);
        }

        return $result;
    }


    /**
     * 根据设备ID和类型名获取数据
     * @param array $input
     * @return bool|string
     */
    public function getOneByIdType($input=array()){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $did = isset($input['deviceId']) ? $input['deviceId'] : '';
        $type = isset($input['type']) ? $input['type'] : '';
        if (!$did) {
            Code::setCode(Code::ERR_PARAMS,'设备id不能为空');
            return false;
        }
        if(!$type){
            Code::setCode(Code::ERR_PARAMS,'类型名不能为空');
            return false;
        }
        $url = $this->url.'/ds/v2/mo/' . $did . '/runtime/metric/'.$type.'/last?timeout='.self::HWTIMEOUT;
        $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);

        return $result;
    }


    /**
     * 获取历史告警列表
     * @param array $input
     * @param bool $isCount 是否查询总数
     * @param int $type 1：设备 2：线路 0：所有
     * @return string
     */
    public function getAlertHistory($input=array(),$isCount=true, $type = 0){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $result = '';
        $hCount = 0;

        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : '';
        $offset = isset($input['offset']) ? $input['offset'] : 0;
        $page = isset($input['page']) ? intval($input['page']) : '';//代表第几页，和offset参数二选一
        $limit = isset($input['limit']) && $input['limit'] ? intval($input['limit']) : 10;
        $offset = $offset ? $offset : getPageOffset($page,$limit);
        $pageAll = isset($input['pageall']) ? trim($input['pageall']) : '';
        $content = isset($input['content']) ? trim($input['content']) : '';
        $param = '';
        $amDids = $this->getAssetmonitorDeviceId($type);

        if($amDids){
            $param .= '@managed_id=[in]'.$amDids.'&';
        }

        if($begin){
            $datestr = '[>=]'.$begin;
            $datestr = urlencodeTime($datestr);
            $param .= '@triggered_at='.$datestr.'&';
        }
        if($edate){
            $datestr = '[<=]'.$edate;
            $datestr = urlencodeTime($datestr);
            $param .= '@triggered_at='.$datestr.'&';
        }
        if($content) {
            $param .= '@content=' . urlencode("[like]%$content%") . '&';
        }
        if($amDids) {
            //获取历史告警总数
            $hCount = $isCount ? $this->getAlertHistoryCount($input, $param, $amDids, $type) : 0;

            if (!$pageAll) {
                if ($offset) {
                    $param .= 'offset=' . $offset . '&';
                }
                if ($limit) {
                    $param .= 'limit=' . $limit . '&';
                }

                if ($limit > 100) {
                    Code::setCode(Code::ERR_PAGE_TOO_MUCH100);
                    return false;
                }
            }

            $url = $this->url.'/ts/alerts?' . $param;
            $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);
        }

        return array('total'=>$hCount,'result'=>$result);
    }


    /**
     * 获取历史告警总数
     * @param array $input
     * @return bool|string
     * @deprecated 从接口获取改为数据库获取
     */
    public function getAlertHistoryCount($input=array(),$paramStr='',$am='',$type = 0){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : '';
        $param = '';
        $result = '';

        if(!$am) {
            $am = $this->getAssetmonitorDeviceId($type);
        }
        if ($paramStr) {
            $param = $paramStr;
        } else {
            if ($am) {
                $param .= '@managed_id=[in]' . $am . '&';
            }
            if ($begin) {
                $datestr = '[>=]' . $begin;
                $datestr = urlencodeTime($datestr);
                $param .= '@triggered_at=' . $datestr . '&';
            }
            if ($edate) {
                $datestr = '[<=]' . $edate;
                $datestr = urlencodeTime($datestr);
                $param .= '@triggered_at=' . $datestr . '&';
            }

        }
        if($am) {
            $url = $this->url.'/ts/alerts/count?' . $param;
            $result = $this->requestApi($url, 'get', \ConstInc::HEADERS_JSON);
        }

        return $result;
    }


    /**
     * 根据资产ID或监控设备ID获取一条带端口列表的数据
     * @param $input
     * @return bool|string
     */
    public function getSwitchboard($input){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $did = isset($input['deviceId']) ? $input['deviceId'] : '';
        if (!$did) {
            Code::setCode(Code::ERR_PARAMS,'设备id不能为空');
            return false;
        }


        $url = $this->url.'/ds/v2/mo/'.$did.'?includes=components.interfaces';

        $data = $this->requestApi($url, "get");
        $result = isset($data['components']['interfaces']) ? $data['components']['interfaces'] : '';

        return $result;
    }

    /**
     * 获取监控设备基础信息
     * @param $deviceId
     * @return bool|string
     * @throws ApiException
     */
    public function getDevice($deviceId) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        if (!$deviceId) {
            Code::setCode(Code::ERR_PARAMS,'设备id不能为空');
            return false;
        }

        $url = $this->url.'/ds/v2/mo/'.$deviceId;

        $data = $this->requestApi($url, "get");
        dd($data);
        $result = isset($data['components']['interfaces']) ? $data['components']['interfaces'] : '';

        return $result;
    }


    private function getDiskFormat($data=array()){
        $result = array();
        if($data){
            foreach($data as $v){
                if(isset($result['free'])){
                    $result['free'] += $v['free'];
                }else{
                    $result['free'] = $v['free'];
                }

                if(isset($result['total'])){
                    $result['total'] += $v['total'];
                }else{
                    $result['total'] = $v['total'];
                }

                if(isset($result['used'])){
                    $result['used'] += $v['used'];
                }else{
                    $result['used'] = $v['used'];
                }

                if($result['total'] > 0) {
                    $result['used_percent'] = $result['used'] / $result['total'] * 100;
                }
                else {
                    $result['used_percent'] = 0;
                }
            }
        }
        return $result;
    }

    /**
     * @param int $type 1：设备 2：线路
     * @return string
     */
    public function getAssetmonitorDeviceId($type = 1){
        if($type == 1) {
            $result = $this->assetsmonitorModel->get(["device_id"])->pluck("device_id")->toArray();
        }
        else if($type == 2){
            $result = $this->links->get(["link_id"])->pluck("link_id")->toArray();
        }
        else {
            $devices = $this->assetsmonitorModel->get(["device_id"])->pluck("device_id")->toArray();
            $links = $this->links->get(["link_id"])->pluck("link_id")->toArray();
            $result = array_merge($devices, $links);
        }

        if(!empty($result)) {
            return join(",", $result);
        }
        else {
            return '';
        }
    }

    public function addLink($input) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $level = 5; //1~5
        $linkType = 136;

        $customName = isset($input['name']) ? $input['name'] : "";
        $types = ["network_link"];
        $fields = [
            "level" => isset($input['level'])?$input['level']:$level,
            "custom_speed_up" => isset($input['speedUp']) ? $input['speedUp'] * 1000 * 1000 : self::SPEED,
            "custom_speed_down" => isset($input['speedDown']) ? $input['speedDown'] * 1000 * 1000 : self::SPEED,
            "description" => isset($input['remark']) ? $input['remark'] : "",
            "from_device" => $input['fromDeviceId'],
            "from_if_index" => $input['fromPortId'],
            "to_device" => $input['toDeviceId'],
            "to_if_index" => $input['toPortId'],
            "link_type" => $linkType,
            "forward" => $input['forward'] ? true : false ,
            "from_based" => $input['from_based'] ? true : false ,
            "managed_type" => "network_link",
            "category" =>  "-1"
        ];

        $linkId = $this->add($customName, $types, $fields);
        if(false === $linkId) {
            Code::setCode(Code::ERR_ADD_LINK_FAIL);
            return false;
        }
        return $linkId;
    }

    public function editLink($input) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $level = 5;

        $customName = isset($input['name']) ? $input['name'] : "";
        $fields = [
            "level" => isset($input['level'])?$input['level']:$level,
            "custom_speed_up" => isset($input['speedUp']) ? $input['speedUp'] * 1000 * 1000 : self::SPEED,
            "custom_speed_down" => isset($input['speedDown']) ? $input['speedDown'] * 1000 * 1000 : self::SPEED,
            "description" => isset($input['remark']) ? $input['remark'] : "",
            "from_if_index" => $input['fromPortId'],
            "to_if_index" => $input['toPortId'],
            "forward" => $input['forward'] ? true : false ,
        ];

        return $this->edit($input['linkId'], $customName, $fields);
    }

    /**
     * 通用添加
     * @param $customName
     * @param $types
     * @param $fields
     * @return bool|string
     * @throws ApiException
     */
    public function add($customName, $types, $fields) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $url = $this->url.'/ds/v2/mo/@create?';
        $param = array(
            "custom_name" => $customName,
            "tags" => array(),
            "types" => $types,
            "fields" => $fields
        );
        $jsonParam = json_encode($param);

        $result = $this->requestApi($url, 'post', ConstInc::HEADERS_JSON, $jsonParam);
        if(!$result){
            Log::error("add hw monitor fail. $types ");
            return false;
        }

        Log::info("add hw monitor success.result: $result");
        return $result;
    }

    /**
     * 通用编辑
     * @param $deviceId
     * @param $customName
     * @param $fields
     * @return bool|string
     * @throws ApiException
     */
    public function edit($deviceId, $customName, $fields) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $url = $this->url.'/ds/v2/mo/'.$deviceId.'?timeout='.self::HWTIMEOUT;
        $param = array(
            "custom_name" => $customName,
            "fields" => $fields
        );
        $jsonParam = json_encode($param);

        $result = $this->requestApi($url, 'put', ConstInc::HEADERS_JSON, $jsonParam);
        if(!$result){
            Log::error("edit hw monitor fail.  ");
            return false;
        }

        Log::info("edit hw monitor success.result: $result");
        return $result;
    }

    public function getLinkStatus($deviceId) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $url = $this->url."/ds/v2/mo/".$deviceId."/runtime/metric/link_status/last?timeout=".self::HWTIMEOUT;

        $result = $this->requestApi($url, 'get', ConstInc::HEADERS_JSON);
        if(!$result || !isset($result["ifStatus"])){
            Log::error("get monitor link status fail.");
            return false;
        }

        $status = $result["ifStatus"];
        Log::info("get monitor link status success.result: ", ["result" => $result]);
        return $status;
    }

    public function getLinkInfo($deviceId) {
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }

        $url = $this->url."/ds/v2/mo/".$deviceId."/runtime/metric/link_flux/last?timeout=".self::HWTIMEOUT;

        $result = $this->requestApi($url, 'get', ConstInc::HEADERS_JSON);

        if(!$result){
            Log::error("get monitor link info fail.");
            return false;
        }

        return $result;
    }


    /**
     * 页面集成签名
     * @param string $token
     * @return array|bool|mixed
     */
    public function signature($token=''){
        //验证监控功能是否开启
        if(!$this->checkEnable()) {
            return false;
        }
        $result = array();

        if($token) {
            $url = $this->url.'/sso/signature?username=admin&session_id=&token='.$token;
            $result = apiCurl($url, 'get', ConstInc::HEADERS_JSON);
            if($result){
                $result = json_decode($result, true);
                if (!$result) {
                    Log::error("get monitor link info fail.");
                    return false;
                }
            }
        }

        return $result;
    }


    /**
     * 获取所有网络设备（可分页）
     * @param int $offset
     * @param int $limit
     * @return string
     */
    public function getNetworkDevice($offset=0,$limit=0){
        $type = 'network_device';
        $result = $this->getListByType($type,$offset,$limit);
        return $result;
    }


    /**
     * 获取所有网络设备总数
     * @return string
     */
    public function getNetworkDeviceCount(){
        $type = 'network_device';
        $result = $this->getCountByType($type);
        return $result;
    }


    /**
     * 获取所有网络设备线路（可分页）
     * @param int $offset
     * @param int $limit
     * @return string
     */
    public function getNetworkLink($offset=0,$limit=0){
        $type = 'network_link';
        $result = $this->getListByType($type,$offset,$limit);
        return $result;
    }


    /**
     * 获取所有网络设备线路总数
     * @return string
     */
    public function getNetworkLinkCount(){
        $type = 'network_link';
        $result = $this->getCountByType($type);
        return $result;
    }


    /**
     * 通用根据类型获取列表
     * @param string $type
     * @param int $offset
     * @param int $limit
     * @return string
     */
    public function getListByType($type='',$offset=0,$limit=0){
        $url = $this->url.'/ds/v2/mo?by_type='.$type;
        if($offset){
            $url .= $url.'&offset='.$offset;
        }
        if($limit){
            $url .= $url.'&limit='.$limit;
        }

        $result = $this->requestApi($url, 'get');
        return $result;
    }


    /**
     * 通用根据类型获取总数
     * @param string $type
     * @return string
     */
    public function getCountByType($type=''){
        $url = $this->url.'/ds/v2/mo/count?by_type='.$type;
        $result = $this->requestApi($url, 'get');
        return $result;
    }


}