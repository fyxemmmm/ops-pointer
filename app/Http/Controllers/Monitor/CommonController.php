<?php
/**
 * 监控
 */

namespace App\Http\Controllers\Monitor;

//use App\Http\Requests\Monitor\CommonRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Monitor\CommonRepository;
use App\Models\Monitor\AssetsHostsRelationship;
use App\Repositories\Monitor\HostsRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use Auth;

class CommonController extends Controller
{

    protected $common;
    protected $hosts;
    protected $zbxAuth;

    function __construct(CommonRepository $common,HostsRepository $hosts,Request $request)
    {
        $this->common = $common;
        $this->hosts = $hosts;
        $this->zbxAuth = $request->session()->get("zbx_auth");
    }


    public function postCommon(Request $request){
        $input = $request->post() ? $request->post() : array();
        $methodZbx = isset($input['method'])?$input['method']:'';
        $paramsZbx = isset($input['params'])?$input['params']:'';
        $idZbx = isset($input['id'])?$input['id']:0;
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => $methodZbx,
            'params' => $paramsZbx,
            'id' => $idZbx
        );
        if('user.login' != strtolower($methodZbx)){
            $param['auth'] = $this->zbxAuth;
        }

        $jsonParam = json_encode($param);
//        var_dump(111,$json);echo '<br>';exit;

        $res = '';
        if($input) {
            $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        }else{
            throw new ApiException(Code::ERR_PARAMS_ZABBIX);
        }
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
//        return $this->response->send($res);

    }


    /**
     * 用户登录
     */
    public function postUserLogin(Request $request){
        $input = $request->post() ? $request->post() : array();
        //用户登录
        /*$param = array(
            'jsonrpc'=>'2.0',
            'method' => 'user.login',
            'params' => $input,
            'id' => 0
        );*/
//        var_dump($param);exit;
//        $jsonParam = json_encode($param);
        //测试数据params
        /*
         {
            "user": "admin",
            "password": "sysdev@yl123"//zabbix123
        }
        */
        if($input) {
            $result = $this->common->login($input);
//            var_dump($result);exit;
            return $this->response->send($result);
        }else{
            throw new ApiException(Code::ERR_PARAMS_ZABBIX);
        }

    }


    /**
     * 获取历史数据
     * @param Request $request
     */
    public function postGetHistory(Request $request)
    {
        $input = $request->post() ? $request->post() : array();
        //历史记录数据
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => 'history.get',
            'params' => $input,
            /*'params' => array(
                "output"=> "extend",
                "history"=>0,
//                "itemids"=>array(23275),
                "sortfield"=> "clock",
                "sortorder"=> "DESC",
                "limit"=> 10
            ),*/
            'auth' => $this->zbxAuth,//'9ef6b66018ab32b31279cb543e054d57',
            'id' => 0
        );

        //测试数据params
        /*{
            "output": "extend",
            "history": 0,
            "sortfield": "clock",
            "sortorder": "DESC",
            "limit": 10
        }*/

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';//exit;


        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取问题列表
     */
    public function postGetProblem(Request $request){
        $input = $request->post() ? $request->post() : array();

        //监测中->问题 step1
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => 'problem.get',
            'params' => $input,
                /*array(
                "output"=>"extend",
                "selectAcknowledges"=>"extend",
                "selectTags"=>"extend",
//                "objectids"=>"15318",
                "eventid"=>'1171',
                "recent"=>"true",
                "sortfield" => array("eventid"),
                "sortorder"=>"DESC"
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //测试数据params
        /*{
            "output": "extend",
    "selectAcknowledges": "extend",
    "selectTags": "extend",
    "eventid": "1171",
    "recent": "true",
    "sortfield": [
            "eventid"
        ],
    "limit":2,
    "sortorder": "DESC"
}*/


        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;


        $resJson = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $res = $this->common->formatJson($resJson);
        $problem = isset($res['result'])?$res['result']:array();

        $getTriggerids = $this->getIDs($problem,'objectid');
//        var_dump($getTriggerids,$problem);exit;


        //监测中->问题 通过step1获取到objectid对应trigger表里的triggerid 执行step2
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => 'trigger.get',
            'params' => array(
                "triggerids"=>$getTriggerids,
                "output"=>"extend",
                "selectFunctions"=>"extend",
                "selectHosts"=>"extend",
                "selectItems"=>"extend"
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);

//        echo $jsonParam;echo '<br>';exit;


        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $triggerRes = $this->common->formatJson($res);
//        var_dump($triggerRes);exit;
        $triggerRes = isset($triggerRes['result'])?$triggerRes['result']:'';
        $result = $this->formatProblem($problem,$triggerRes);

        return $this->response->send($result);
    }


    private function formatProblem($problem,$triggers=array()){
//        $result = array();
        if($problem && is_array($problem)){
            $uidArr = array();
            foreach($problem as $k=>$v){
                $ptriggerid = isset($v['objectid'])?$v['objectid']:'';
                $acknowledges = isset($v['acknowledges'])?$v['acknowledges']:'';
                $uids = $this->getIDs($acknowledges,'userid');
                if($uids){
                    $uidArr[] = $uids;
                }

                $problem[$k]['trigger_name'] = '';
                $problem[$k]['hostid'] = '';
                $problem[$k]['hostname'] = '';
//                $problem[$k]['users'] = $uids;
                if($triggers && is_array($triggers) && $ptriggerid){
                    foreach($triggers as $tk=>$tv){
                        $triggerid = isset($tv['triggerid'])?$tv['triggerid']:'';
                        if($ptriggerid == $tv['triggerid']){
//                            var_dump($tv);exit;
//                            $hosts = $tv['hosts'];
//                            $items = $tv['items'];
//                            $triggers = $tv;
//                            unset($triggers['hosts']);
//                            unset($triggers['items']);
                            $hostid = isset($tv['hosts'][0]['hostid'])?$tv['hosts'][0]['hostid']:'';
                            $hostname = isset($tv['hosts'][0]['host'])?$tv['hosts'][0]['host']:'';
                            $trigger_description = isset($tv['description'])?$tv['description']:'';
                            $problem[$k]['trigger_name'] = $trigger_description;
                            $problem[$k]['hostid'] = $hostid;
                            $problem[$k]['hostname'] = $hostname;
//                            $problem[$k]['items'] = $items;
                        }
                    }
                }
            }

//            $users = array_filter(array_unique($users));
//            var_dump($users);exit;
            $userids = array();
            if($uidArr) {
                foreach ($uidArr as $k => $varr) {
                    foreach ($varr as $v) {
                        $userids[] = $v;
                    }
                }
            }
//            $users = $this->getUsersByid($problem);
            $users = $this->getUsersByid($userids);
//            var_dump($users);exit;
            foreach($problem as $k=>$v){
                $acknowledges = isset($v['acknowledges'])?$v['acknowledges']:array();
                if($acknowledges && is_array($acknowledges)) {
                    foreach ($acknowledges as $ak => $av) {
                        $uid = $av['userid'];
                        $problem[$k]['acknowledges'][$ak]['uname'] = isset($users[$uid])?$users[$uid]:'';
                    }
                }
            }
        }
//        $result = $problem;
        return $problem;
    }



    private function getUsersByid($userids=array()){
        $result = array();

        if($userids) {

            /*foreach ($problem as $k => $v) {
                $acknowledges = isset($v['acknowledges']) ? $v['acknowledges'] : array();
                if ($acknowledges && is_array($acknowledges)) {
                    foreach ($acknowledges as $ak => $av) {
                        $userids[] = isset($av['userid']) ? $av['userid'] : array();
                    }
                }
            }*/
            $userids = array_filter(array_unique($userids));
            $input = array(
                "userids" => $userids
            );
//        var_dump($input);exit;
            $param = array(
                'jsonrpc' => '2.0',
                'method' => 'user.get',
                'params' => $input,
                /*array(
                "alias" => $user,
                "passwd" => "123456",
                "usrgrps" => array(
                    array(
                        "usrgrpid" => "7"
                    )
                ),
                "user_medias"=>array(
                    array(
                        "mediatypeid"=>"1",
                        "sendto"=>"support@company.com",
                        "active"=>0,
                        "severity"=>63,
                        "period"=>"1-7,00:00-24:00"
                    )
                )
            ),*/
                'auth' => $this->zbxAuth,
                'id' => 0
            );
            $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;


            $resJson = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
            $res = $this->common->formatJson($resJson);
            $uRes = isset($res['result']) ? $res['result'] : array();
            if ($uRes) {
                foreach ($uRes as $v) {
                    $uid = isset($v['userid']) ? $v['userid'] : '';
                    $alias = isset($v['alias']) ? $v['alias'] : '';
                    $name = isset($v['name']) ? $v['name'] : '';
                    $surname = isset($v['surname']) ? $v['surname'] : '';
                    $result[$uid] = $alias . ' (' . $name . ' ' . $surname . ')';
                }
            }
        }
        return $result;
    }


    /**
     * 获取问题总数
     */
    public function postGetProblemCount(Request $request)
    {
        $input = $request->post() ? $request->post() : array();

        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'problem.get',
            'params' => $input,
            /*array(
            "output"=>"extend",
            "selectAcknowledges"=>"extend",
            "selectTags"=>"extend",
//                "objectids"=>"15318",
            "eventid"=>'1171',
            "recent"=>"true",
            "sortfield" => array("eventid"),
            "sortorder"=>"DESC"
        ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $resJson = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($resJson);
        return $this->response->send($result);
    }


    /**
     * 新建用户
     */
    public function postUserCreate(Request $request){
        $input = $request->post() ? $request->post() : array();
        $user = 'wwww';
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'user.create',
            'params' => $input,
                /*array(
                "alias" => $user,
                "passwd" => "123456",
                "usrgrps" => array(
                    array(
                        "usrgrpid" => "7"
                    )
                ),
                "user_medias"=>array(
                    array(
                        "mediatypeid"=>"1",
                        "sendto"=>"support@company.com",
                        "active"=>0,
                        "severity"=>63,
                        "period"=>"1-7,00:00-24:00"
                    )
                )
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

//        {
//            "alias": "wwww",
//        "passwd": "123456",
//        "usrgrps": [
//            {
//                "usrgrpid": "7"
//            }
//        ],
//        "user_medias": [
//            {
//                "mediatypeid": "1",
//                "sendto": "support@company.com",
//                "active": 0,
//                "severity": 63,
//                "period": "1-7,00:00-24:00"
//            }
//        ]
//    }

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;


        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取单个事件和确认事件消息（多个）
     */
    public function postGetEventAcknowledges(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'event.get',
            'params' => $input,
                /*array(
                "eventids"=>"1171",
                "output" => "extend",
                "select_acknowledges"=>"extend",
//                "objectids"=>"15318",
                "sortfield" => array("clock","eventid"),
                "sortorder"=>"DESC",
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //测试数据params
//        {
//            "eventids": "1171",
//            "output": "extend",
//            "select_acknowledges": "extend",
//            "sortfield": [
//                    "clock",
//                    "eventid"
//                ],
//            "sortorder": "DESC",
//        }

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;


        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 确认告警事件
     */
    public function postAcknowledgeEdit(Request $request){
        $input = $request->post() ? $request->post() : array();
        $eventids = isset($_REQUEST['eventids']) ? $_REQUEST['eventids'] : '';
        $message = isset($_REQUEST['msg']) ? trim($_REQUEST['msg']) : '';
        $action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 0;
        //确认单个事件和留言
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'event.acknowledge',
            'params' => $input,
                /*array(
                "eventids" => $eventids,
                "message" => $message,
//                    "action" => $action
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';//exit;


        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);

    }


    /**
     * 获取触发器事件详情
     */
    public function postGetTriggerEventDetail(Request $request){
        $input = $request->post() ? $request->post() : array();
        //input triggerids = 15318 ,eventids=1171
        $triggerids = isset($input['triggerids'])?$input['triggerids']:'';

        $eventids = isset($input['eventids'])?$input['eventids']:'';

        //触发器
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => 'trigger.get',
            'params' => array(
                "triggerids"=>$triggerids,
                "output"=>"extend",
                "selectFunctions"=>"extend"
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//            echo $jsonParam;echo '<br>';exit;


        $triggerRes = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
//        print_r($res);
        $arrTrigger = json_decode($triggerRes,true);
        $triggerResult = isset($arrTrigger['result'])?$arrTrigger['result']:array();
        unset($jsonParam);


        //事件
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'event.get',
            'params' => array(
                "eventids"=>$eventids,
                "output" => "extend",
                "select_acknowledges"=>"extend",
//                "objectids"=>"15318",
                "sortfield" => array("clock","eventid"),
                "sortorder"=>"DESC"
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        $jsonParam = json_encode($param);
//            echo $jsonParam;echo '<br>';exit;


        $eventRes = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $arrEvent = json_decode($eventRes,true);
        $eventResult = isset($arrEvent['result'])?$arrEvent['result']:array();
        unset($jsonParam);


        //告警
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'alert.get',
            'params' => array(
                "output" => "extend",
                "eventids"=>$eventids,
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        $jsonParam = json_encode($param);
//            echo $jsonParam;echo '<br>';exit;


        $alertRes = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $arrAlert = json_decode($alertRes,true);
        $alertResult = isset($arrAlert['result'])?$arrAlert['result']:array();



        $result = array("trigger"=>$triggerResult,"event"=>$eventResult,"alert"=>$alertResult);
        return $this->response->send($result);
        //print_r(json_encode($res));
    }


    /**
     * 获取监控项列表
     */
    public function postGetItemList(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'item.get',
            'params' => $input,
            /*'params' => array(
                "output"=>"extend",
//                "hostids"=>"10255",
//                "search"=>array(
//                    "key_"=>"system"
//                ),
//                "sortfield"=>"name"
                "limit"=>2,
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;


        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);


    }


    /**
     * 添加监控项
     */
    public function postItemAdd(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'item.create',
            'params' => $input,
                /*array(
                "name"=>"测试监控项4",
                "key_"=>"net.if.list",
                "hostid"=>"10255",
                "type"=>0,
                "value_type"=>3,
                "interfaceid"=>"3",
//                "applications"=>array(
//                    "609",
//                    "610"
//                ),
//                "flags"=>4,//(只读)的项目。可能值：0 - a plain item;4 - a discovered item.
                "delay"=>"30s"
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
        //include\classes\items\CHelpItems.php 键值是固定的，没有api也不存在数据库里
    }


    /**
     * 监控项更新
     */
    public function postItemUpdate(Request $request){
        $input = $request->post() ? $request->post() : array();


        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'item.update',
            'params' => $input,
                /*array(
                "itemid"=>"28363",
                "status"=>1
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //
//        {
//            "itemid": "28363",
//            "status": 1
//        }

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取主机
     */
    public function postGetHost(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'host.get',
            'params' => $input,
                /*array(
                "output" => array("hostid","host")
                "fillte"=>array("host"=>array("Zabbix server","Linux server"))
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;
        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);


        //available 主机中可用性 0：无，1：正常，2：不正常
    }


    /**
     * 添加主机
     */
    public function postAddHost(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'host.create',
            'params' => $input,
            'auth' => $this->zbxAuth,
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
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 修改主机
     */
    public function postUpdateHost(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'method' => 'host.update',
            'params' => $input,
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        /*

        {
            "hostid": "10257",
            "status": 0
        }
         */

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;
        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取值映射
     */
    public function postGetValuemap(Request $request){
        $input = $request->post() ? $request->post() : array();

        //example2
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'valuemap.get',
            'params' => $input,
                /*array(
                "output" => "extend",
                "selectMappings" => "extend",
                "valuemapids" => $valuemapids,
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        //example1
        /*$param = array(
            'jsonrpc' => '2.0',
            'method' => 'valuemap.get',
            'params' => array(
                "output"=>"extend",
                "sortfield"=>array("name","valuemapid",),
//                    "sortorder"=>"ASC"//注意参数需要大写
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );*/


        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 主机里的 “agent代理程序的接口”，对应interface表
     */
    public function postGetHostinterface(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'hostinterface.get',
            'params' => $input,
            /*'params' => array(
                "output"=>"extend",
                "hostids"=>"10255",
//                "sortfield" => "interfaceid",
//                "sortorder"=>"DESC"//注意参数需要大写
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取模板
     */
    public function postGetTemplate(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'template.get',
            'params' => $input,
            /*'params' => array(
                "output"=>"extend",
                "groupids" => array(12),
//                "hostids"=>"10001",
//                "filter" => array("host"=>array("Template OS Linux","Template OS Windows")),
//                "sortfield" => "interfaceid",
//                "sortorder"=>"DESC"//注意参数需要大写
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 添加模板
     */
    public function postTemplateAdd(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'template.create',
            'params' => $input,
            /*'params' => array(
                "host"=>"test template4", //必填
                "groups" => array(//必填
                    "groupid"=>2,
                    "groupid"=>4
                ),
                "description" => "description test4 ",
//                "hosts" => array(
//                    array("hostid"=>"123")
//                )
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 更新模板
     */
    public function postTemplateUpdate(Request $request){
        $input = $request->post() ? $request->post() : array();
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'template.update',
            'params' => $input,
            /*'params' => array(
                "templateid" => "10258",
                "host" => "test template2", //必填
                "groups" => array(
                    "groupid" => "4" //必填
                ),
                "description" => "description test ",
//                "hosts" => array(
//                    array("hostid"=>"123")
//                )
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取web监测列表
     */
    public function postGetWeblist(Request $request){
        $input = $request->post() ? $request->post() : array();

        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'httptest.get',
            'params' => $input,
            /*'params' => array(
                "output" => "extend",
                "selectSteps" => "extend",
                "httptestids" => 9
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        $jsonParam = json_encode($param);
        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取web监测网络方案详细信息
     */
    public function getWebdetail()
    {
        $res = array();
        //item.get接口type=9
        //api接口https://www.zabbix.com/documentation/3.4/manual/api/reference/item/object

        //0 - Zabbix agent; 1 - SNMPv1 agent; 2 - Zabbix trapper; 3 - simple check;
        //4 - SNMPv2 agent; 5 - Zabbix internal; 6 - SNMPv3 agent; 7 - Zabbix agent (active);
        //8 - Zabbix aggregate; 9 - web item; 10 - external check; 11 - database monitor; 12 - IPMI agent;
        //13 - SSH agent; 14 - TELNET agent; 15 - calculated; 16 - JMX agent;17 - SNMP trap; 18 - Dependent item
        //先获取到itemid，在通过history.get方法获取历史数据
        //"itemids"=> array(28326,28327,28328,28329,28330,28331),

        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'httptest.get',
            'params' => array(
                "output"=>"extend",//['itemid', 'type', 'master_itemid', 'name', 'delay', 'units', 'hostid', 'history', 'trends','value_type', 'key_'],
                "selectSteps"=>"extend",
                "httptestids" => 2,
//                "search"=>array(
//                    "key_"=>"system"
//                ),
//                "sortfield"=>"name"
                "limit"=>10,
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );


        $jsonParam = json_encode($param);
//            echo $jsonParam;echo '<br>';exit;


        $webdetailRes = apiCurl($this->apiUrl, 'post', $this->headers, $jsonParam);
        $webdetailArr = json_decode($webdetailRes,true);
        $webdetailRes = isset($webdetailArr['result'])?$webdetailArr['result']:array();
        $httpstepids = array();
        if($webdetailRes){
            foreach ($webdetailRes as $val) {
                $stepsArr = isset($val['steps'])?$val['steps']:array();
                if($stepsArr){
                    foreach ($stepsArr as $sval) {
                        $httpstepid = isset($sval['httpstepid'])?$sval['httpstepid']:'';
                        if($httpstepid) {
                            $httpstepids[$httpstepid] = $httpstepid;
                        }
                    }
                }
            }
            if($httpstepids){

            }
        }
        var_dump($webdetailRes,$httpstepids);
        print_r($res);

    }


    /**
     * 获取图形
     */
    public function postGetGraph(Request $request)
    {
        $input = $request->post() ? $request->post() : '';
        $result['result'] = $this->getGraph($input);

        return $this->response->send($result);

    }


    private function checkGraphName($name=''){

        $baseTypes = array('cpuload','cpujumps','memory','disk','network');
        foreach($baseTypes as $type) {
            $tmpName = stristr(str_replace(' ', '', $name), $type);
            if($tmpName){
                return $type;
            }
        }
        return false;
    }


    private function zbxGetGraph($input = array()){
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'graph.get',
            'params' => $input,
            /*'params' => array(
                "output"=>"extend",
                "hostids" => "10107",
                "sortfield" => "name",
                "limit"=>10,
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $result;
    }


    private function getGraph($input){
        $type = isset($input['type']) ? $input['type'] : '';
        $assetId = isset($input['assetId']) ? $input['assetId'] : '';
        if ($type) {
            unset($input['type']);
        }
        $typeArr = '';
        if($type) {
            $typeArr = $type;
            if (!is_array($type)) {
                $typeArr = array(strtolower($type));
            }
        }
        $assetHost = $this->hosts->getByAsset($assetId);
        $hostids = isset($assetHost['zbx_hostid']) ? $assetHost['zbx_hostid'] : '';

//        var_dump($typeArr);exit;
        $result = array();
        //graph.get
        /*{
            "output": "extend",
        "hostids": 10084,
        "sortfield": "name"
    }*/
//        var_dump($input);exit;
        $input['hostids'] = $hostids;
        $resGraph = $this->zbxGetGraph($input);
        $res = isset($resGraph['result'])?$resGraph['result']:array();
//        var_dump($res);exit;
        $nRes = array();
        if($res){
            foreach($res as $k=>$v){
                $id = isset($v['graphid'])?$v['graphid']:'';
                $name = isset($v['name'])?$v['name']:'';
                $checkType = $this->checkGraphName($name);
                if ('cpuload' == $checkType) {
                    $nRes['cpuload'] = array('id'=>$id,'name' => $name);
                } elseif ('cpujumps' == $checkType) {
                    $nRes['cpujumps'] = array('id'=>$id,'name' => $name);
                } elseif ('memory'==$checkType) {
                    $nRes['memory'] = array('id'=>$id,'name' => $name);
                } elseif ('disk' == $checkType) {
                    $nRes['disk'][] = array('id'=>$id,'name' => $name);
                } elseif ('network' == $checkType) {
                    $nRes['network'][] = array('id'=>$id,'name' => $name);
                }
            }
            if($typeArr && is_array($typeArr)) {
                foreach ($typeArr as $type) {
                    if (isset($nRes[$type])) {
                        $result[$type] = $nRes[$type];
                    }
                }
            }else{
                $result = $nRes;
            }
        }

        return $result;
    }


    /**
     * 获取图形中检索图形项目+历史数据
     * @param Request $request
     * @return mixed
     */
    public function postGetGraphitem(Request $request){
        $input = $request->post()?$request->post():'';
        $time_from = isset($input['time_from'])?$input['time_from']:'';
        $time_till = isset($input['time_till'])?$input['time_till']:'';
        $limit = isset($input['limit'])?$input['limit']:'';
        $graphids = isset($input['graphids'])?$input['graphids']:'';
        $type = isset($input['type'])?$input['type']:'';
        $assetId = isset($input['assetId'])?$input['assetId']:'';//assetId资产id
        $graphType = array();
        if($graphids){
            $graphInput = array('output' => 'extend', 'assetId' => $assetId, 'graphids' => $graphids);
            $graphRes = $this->getGraph($graphInput);
            $graphType = array_keys($graphRes);
//            $graphType = isset($graphType[0])?$graphType[0]:'';
//            var_dump($graphRes,$graphType);exit;
        }else{
            $graphInput = array('output' => 'extend', 'assetId' => $assetId, 'type' => $type);
            $graphRes = $this->getGraph($graphInput);
            if ($graphRes && is_array($graphRes)) {
                foreach ($graphRes as $k => $val) {
                    if (in_array($k, array('cpuload', 'cpujumps', 'memory'))) {
                        $graphids = $val['id'];
                    } elseif (in_array($k, array('disk', 'network'))) {
                        $graphids = $val[0]['id'];
                    }
                }
            }
        }

//        var_dump($graphids,$graphRes);exit;



        //graphitem.get
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'graphitem.get',
            'params' => array(
                "graphids"=> $graphids,
                "output"=>"extend",
            ),
            /*'params' => array(
                "graphids"=> array(792,222),
                "output"=>"extend",
                "limit"=>10,
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

//        var_dump($input);exit;

        $jsonParam = json_encode($param);
//            echo $jsonParam;echo '<br>';exit;

        $gitemRes = '';
        if($input) {
            $gitemRes = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        }else{
            throw new ApiException(Code::ERR_PARAMS_ZABBIX);
        }
        unset($param);
        unset($jsonParam);
//        $result = $this->common->formatJson($gitemRes);

//        print_r($gitemRes);exit;
        $gitemArr = json_decode($gitemRes,true);
        $gitemRes = isset($gitemArr['result'])?$gitemArr['result']:array();
        $itemids = $this->getIDs($gitemRes,'itemid');
//        var_dump($gitemRes,$itemids);exit;
        $itemData = array();
        if($itemids){
            $itemData = $this->getItemData($itemids);
//            var_dump($itemData);exit;
        }

        //历史记录数据
        $param = array(
            'jsonrpc'=>'2.0',
            'method' => 'history.get',
            'params' => array(
                "output"=> "extend",
//                "history"=>3,
                "itemids"=> $itemids,
                "sortfield"=> "clock",
                "sortorder"=> "DESC",
            ),
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        if($time_from){
            $param['params']['time_from'] = $time_from;
        }
        if($time_till){
            $param['params']['time_till'] = $time_till;
        }
        if($limit){
            $param['params']['limit'] = $limit;
        }
        if('cpuload'==$type || in_array('cpuload',$graphType)){
            $param['params']['history'] = 0;
        }

        $jsonParam = json_encode($param);
//            echo $jsonParam;//echo '<br>';exit;


//        $res = apiCurl($this->apiUrl, 'post', $this->headers, $jsonParam);
        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
//        var_dump($res);exit;
        $result = $this->common->formatJsonHistory($res,$itemData);
//        var_dump($result);exit;
        return $this->response->send($result);
//        print_r($result);//exit;


    }


    private function getItemData($itemids=array()){
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'item.get',
            'params' => array(
                "itemids"=>$itemids
            ),
            /*'params' => array(
                "output"=>"extend",
//                "hostids"=>"10255",
//                "search"=>array(
//                    "key_"=>"system"
//                ),
//                "sortfield"=>"name"
                "limit"=>2,
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;


        $resJson = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $res = $this->common->formatJson($resJson);
        $data = isset($res['result'])?$res['result']:array();
        if($data){
            foreach($data as $val){
                $result[$val['itemid']] = $val['name'];
            }
        }
        return $result;
    }


    /**
     * 获取主机群组
     * @param Request $request
     * @return mixed
     */
    public function postGetHostgroup(Request $request){
        $input = $request->post()?$request->post():'';
        /*{
            "output": "extend"
    }*/
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'hostgroup.get',
            'params' => $input,
            /*'params' => array(
                "output"=>"extend",
            ),*/
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 获取触发器
     * @param Request $request
     * @return mixed
     */
    public function postGetTrigger(Request $request){
        $input = $request->post()?$request->post():'';
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'trigger.get',
            'params' => $input,
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //测试数据 $input
        /*{
            "triggerids": "15364",
            "output": "extend",
            "selectFunctions": "extend"
        }*/
        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 添加触发器
     * @param Request $request
     * @return mixed
     */
    public function postAddTrigger(Request $request){
        $input = $request->post()?$request->post():'';
        $param = array(
            'jsonrpc' => '2.0',
//            'method' => 'triggerprototype.create',
            'method' => 'trigger.create',
            'params' => $input,
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //测试数据 $input

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 更新触发器
     * @param Request $request
     * @return mixed
     */
    public function postUpdateTrigger(Request $request){
        $input = $request->post()?$request->post():'';
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'trigger.update',
            'params' => $input,
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //测试数据 $input

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * 更新触发器
     * @param Request $request
     * @return mixed
     */
    public function postUpdateTriggerprototype(Request $request){
        $input = $request->post()?$request->post():'';
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'triggerprototype.update',
            'params' => $input,
            'auth' => $this->zbxAuth,
            'id' => 0
        );
        //测试数据 $input

        $jsonParam = json_encode($param);
//        echo $jsonParam;echo '<br>';exit;

        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
    }


    /**
     * @param $array
     * @param $fieldName
     * @return array
     */
    public function getIDs($array,$fieldName){
        $res = array();
        if ($array && is_array($array)) {
            foreach($array as $k=>$val){
//                var_dump($k,$val);exit;
                if(isset($val[$fieldName])){
//                    $res[$val[$fieldName]] = $val[$fieldName];
                    $res[] = $val[$fieldName];
                }
            }
        }
        $res = array_filter(array_unique($res));
        return $res;

    }

    public function postTest(){
        echo 232323;exit;
    }




}
