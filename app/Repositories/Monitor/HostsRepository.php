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
use App\Models\Monitor\AssetsHostsRelationship;
use App\Models\Code;
use App\Exceptions\ApiException;
use Log;
use Auth;

class HostsRepository extends BaseRepository
{

    protected $zbxAuth;

    public function __construct(AssetsHostsRelationship $assetshostsrelModel,Request $request)
    {
        $this->model = $assetshostsrelModel;

        $this->zbxAuth = $request->session()->get("zbx_auth");
    }

    public function getByAsset($assetId) {
        $data = $this->model->where("asset_id","=",$assetId)->first();
        if(empty($data)) {
            //Code::setCode(Code::ERR_HOST_BIND);
            return false;
        }
        return $data;
    }

    public function addMonitor($request) {
        $assetId = $request->input("assetId");

        $data = $this->model->where("asset_id","=",$assetId)->first();
        $zbx_hostid = isset($data['zbx_hostid'])?$data['zbx_hostid']:0;
        if(!empty($data) && $zbx_hostid) {
            Code::setCode(Code::ERR_MONITOR_EXISTS);
            return false;
        }

        //添加zabbix监控
        $params = [
            "host" => $request->input("host"),
            "interfaces" => [
                "type" => $request->input("type"),
                "main" => 1,
                "useip" => 1,
                "ip" => $request->input("ip"),
                "dns" => "8.8.8.8",
                "port" => $request->input("port"),
            ],
            "groups" => [
                "groupid" => $request->input("groupId"),
            ],
            "templates" => [
                "templateid" => $request->input("templateId"),
            ]
        ];
        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'host.create',
            'params' => $params,
            'auth' => $this->zbxAuth,
            'id' => 0
        );

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

        $result = $this->requestZbx($param);
        if(empty($result) || isset($result['error'])) {
            Log::error("add monitor fail ",["result" => $result]);
            return $result;
        }
        $hostid = $result['result']['hostids'][0];
        $insert = [
            "asset_id" => $assetId,
            "zbx_hostid" => $hostid,
        ];
//        $this->store($insert);
        $this->addAssetsHostsRelationship($insert);
        Log::info("add monitor success. asset_id: $assetId hostid: $hostid");

        $msg = sprintf("将资产加入了监控。资产id：%s 主机：%s IP：%s", $assetId, $request->input("host"), $request->input("ip"));
        userlog($msg);
        return $result;
    }

    public function requestZbx($param) {
        $jsonParam = json_encode($param);
        $res = apiCurl(\ConstInc::ZABBIX_API_URL, 'post', \ConstInc::HEADERS_JSON, $jsonParam);
        Log::info("request zabbix .params: $jsonParam  res: $res");
        if($res){
            $data = json_decode($res,true);
            if(isset($data['error'])){
                Code::setCode(Code::ERR_ZABBIX_RET);
            }
            return $data;
        }
        else {
            Code::setCode(Code::ERR_ZABBIX_RET);
        }
    }


    /**
     * 新增资产和监控主机绑定
     * @param $input
     * @return int
     * @throws ApiException
     */
    public function addAssetsHostsRelationship($input) {
        $result = 0;

        $getData = $this->model->where(["asset_id" => $input['asset_id'],"zbx_hostid"=>$input['zbx_hostid']])->first();
        $id = isset($getData['ahid'])?$getData['ahid']:0;
//        var_dump($id,$input,$getData);exit;

        $data = $input;
//        $data['asset_id'] = $input['asset_id'];
//        var_dump($data);exit;
        if(!$getData) {
            $res = $this->store($data);
            if ($res) {
                $result = $res['id'] ? $res['id'] : 0;
            }
        }else{
            try {
                $result = $this->model->where(['ahid' => $id])->update($input);
            }catch (Exception $e){
                throw new ApiException(Code::ERR_QUERY,$e);
            }

//            $result = $this->update($id,$input);
        }
        return $result;

    }


    public function getOne($input=array()){
        $result = '';
        $assetId = isset($input['asset_id'])?$input['asset_id']:'';
        if($assetId) {
            $where = array('asset_id' => $assetId);
            $data = $this->model->where($where)->first();
            if(empty($data)) {
                Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
                return false;
            }

            $hostid = $data->zbx_hostid;

            //获取zabbix监控
            $params = [
                "hostids" => [$hostid],
                "selectInterfaces" => "extend",
                "selectParentTemplates"=>"extend"
            ];
            $param = array(
                'jsonrpc' => '2.0',
                'method' => 'host.get',
                'params' => $params,
                'auth' => $this->zbxAuth,
                'id' => 0
            );


            $zbx = $this->requestZbx($param);
            if(empty($zbx) || isset($zbx['error'])) {
//                Log::error("add monitor fail ",["result" => $result]);
                Code::setCode(Code::ERR_ASSETS_ZBXHOST_GET);
                return false;
            }
            $resZbx = isset($zbx['result'][0]) ? $zbx['result'][0] : array();
//            var_dump($resZbx);exit;
            $parentTemplates = isset($resZbx['parentTemplates']) ? $resZbx['parentTemplates'] : array();
            $interfaces = isset($resZbx['interfaces']) ? $resZbx['interfaces'] : array();
            $templates = array();
            $ipArr = array();
            foreach($parentTemplates as $k=>$v){
                $tid = isset($v['templateid']) ? $v['templateid'] : '';
                $templates[$tid] = isset($v['name']) ? $v['name'] : '';
            }
            foreach($interfaces as $k=>$v){
                $hostid = isset($v['hostid']) ? $v['hostid'] : '';
                $ipArr[$hostid]['ip'] = isset($v['ip']) ? $v['ip'] : '';
                $ipArr[$hostid]['port'] = isset($v['port']) ? $v['port'] : '';
            }
            $result = array(
                "hostid" => isset($resZbx['hostid']) ? $resZbx['hostid'] : '',
                "available" => isset($resZbx['available']) ? $resZbx['available'] : '',
                "host_name" => isset($resZbx['host']) ? $resZbx['host'] : '',
                "templates" => $templates,
                "ips" => $ipArr,
                "zbx_status" => isset($resZbx['status']) ? $resZbx['status'] : ''

            );

        }
//        var_dump($result);exit;
        return $result;
    }


    public function updateHost($request){
        $assetId = $request->input("assetId");

        if($assetId){
            $where = array('asset_id' => $assetId);
            $data = $this->model->where($where)->first();
            if(empty($data)) {
                Code::setCode(Code::ERR_ASSETS_ZBXHOST_NOT);
                return false;
            }
            $hostid = $data->zbx_hostid;
        }
        $ip = $request->input("ip");
        $port = $request->input("port");
        $groupId = $request->input("groupId");
        $templateId = $request->input("templateId");

        //添加zabbix监控
        $params = array('hostid'=>$hostid,
            'interfaces' => array('type'=>1)
        );

        if($ip){
            $params['interfaces']['ip'] = $ip;
        }
        if($port){
            $params['interfaces']['port'] = $port;
        }
        if($groupId){
            $params['groups']['groupid'] = $groupId;
        }
        if($templateId){
            $params['templates']['templateid'] = $templateId;
        }


        $param = array(
            'jsonrpc' => '2.0',
            'method' => 'host.update',
            'params' => $params,
            'auth' => $this->zbxAuth,
            'id' => 0
        );

        $result = $this->requestZbx($param);
        if(empty($result) || isset($result['error'])) {
            Log::error("update monitor fail ",["result" => $result]);
            return $result;
        }
        $zbxRes = isset($result['result']) ? $result['result'] : array();

        return $zbxRes;
    }

    /**
     * 监控主机和资产解绑
     * @param array $input
     * @return bool
     */
    public function assetsHostsUntie($input=array()){
        $asset_id = isset($input['asset_id']) ? $input['asset_id'] : 0;
        if(!$asset_id){
            Code::setCode(Code::ERR_PARAMS,'资产ID不能为空或不合法');
            return false;
        }
        $getData = $this->getByAsset($asset_id);
        $id = isset($getData['ahid'])?$getData['ahid']:0;
        $zbx_hostid = isset($getData['zbx_hostid'])?$getData['zbx_hostid']:0;

        if(!$id) {
            Code::setCode(Code::ERR_BIND_ASSETS_NOT);
            return false;
        }
        if(!$zbx_hostid){
            Code::setCode(Code::ERR_ASSETS_IS_UNTIE);
            return false;
        }
        $param = array('zbx_hostid'=>0);
        $result = $this->model->where(['ahid' => $id])->update($param);
//        var_dump($result);exit;
        return $result;
    }




}