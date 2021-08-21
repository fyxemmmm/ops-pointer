<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/2
 * Time: 15:55
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Repositories\Auth\UserRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Weixin\QyWxUserRepository;
use Cache;
use App\Models\Code;
use App\Models\Auth\User;
use Log;

class CommonRepository extends BaseRepository
{
    private $weixinuser;
    private $user;
    private $qywxuser;
    private $userModel;


    public function __construct(WeixinUserRepository $weixinuser,
                                UserRepository $user,
                                QyWxUserRepository $qywxuser,
                                User $userModel
    )
    {
        $this->weixinuser = $weixinuser;
        $this->user = $user;
        $this->qywxuser = $qywxuser;
        $this->userModel = $userModel;

    }


    /**
     * 每隔116分钟更新一次access_token
     * @return array('accToken'=>'ZtA9Tu6f--wRTR4RjR6Avqc....', 'requestTime'=>12234234)
     */
    public function getAccessToken($appid='', $isClear=false) {
        $arrWxAppid = \ConstInc::WEIXINAPPIDS;
        $cacheAccessTokenKey = 'wxAccessToken';
        if(empty($appid)) {
            $appid = \ConstInc::WX_APPID;
        }
        $appsecret  = @$arrWxAppid[$appid];
        if(!isset($appsecret) || empty($appsecret)){
            //记录日志
            //$this->logger->addLog('Err', '调用微信接口：cgi-bin/token?grant_type=client_credential获取access_token失败，发生在基类Controller，错误信息：无效的APPID');
            $arrParams = array('errcode'=>'40013', 'errmsg'=>'Invalid appid.');
            return $arrParams;
            exit;
        }
        //在这里实现access_token的问题，要兼顾到obj of redis
        $currTime = time();
        if(!$isClear) {
//            $arrToken = array();
            //取redis中的access_token
            $arrToken = Cache::get($cacheAccessTokenKey);

//            $arrToken = $this->redishandle->getHashValue(array('accToken', 'requestTime'), $appid);//'ACCESS_TOKEN_ID'换成
            //print_r($arrToken);echo $currTime-$arrToken['requestTime'];exit;
            //判断有无超时，限时300s
            if( isset($arrToken['requestTime']) &&
                ($currTime-$arrToken['requestTime'])< \ConstInc::ACCESS_TOKEN_TIMEOUT &&
                ($currTime-$arrToken['requestTime'])>=0 ) {
                return $arrToken;
            }
        }

        //curl调用微信端的ACCESS_TOKEN
        $arrToken = requestWeixinInterface('access_token',array('appid'=>$appid, 'appsecret'=>$appsecret));
//        if(!isset($arrToken['access_token']) || empty($arrToken['access_token'])) {
//            //记录日志
//            //$this->logger->addLog('Err','调用微信接口：cgi-bin/token?grant_type=client_credential获取access_token失败，发生在基类Controller，错误信息：'.  var_export($arrToken, true));
//            return false;
//        }

        //设置redis值
        $accToken = isset($arrToken['access_token']) ? $arrToken['access_token'] : '';
        $arrParams = array('accToken'=>$accToken, 'requestTime'=>$currTime);
        if($accToken) {
            if (!Cache::put($cacheAccessTokenKey, $arrParams, 7000/60)) {
//        if(!$this->redishandle->setMultiHashValue($arrParams, $appid)) {//'ACCESS_TOKEN_ID'换成
//            //记录日志，set失败
//            //$this->logger->addLog('Err', 'setMultiHashValue函数出错，无法将access_token写入redis，发生在基类Controller');
//            return false;
            }
        }else{
            echoLog($arrToken,'getaccToken err');
        }
        //$this->logger->addLog('Info', 'access_token变更后定时记录：access_token值：'. $arrToken['access_token']. '， 请求时间：'. date('Y-m-d H:i:s', $currTime));
        return $arrParams;
    }


    public function getWeixinUserInfo($param=array()){
        $res = array();
        $openid = isset($param['openid'])?$param['openid']:'';//'o6UHYw15VkW6QghcqsXggReOWqug';//
        $event = isset($param['Event'])?$param['Event']:'';
        $userid = isset($param['userid'])?$param['userid']:'';
        $where = array('openid'=>$openid);
        if($openid) {
            $wxUserID = false;
            $wxUser = $this->weixinuser->getOne($where);
            if($userid) {
                $where2 = array('userid' => $userid);
                $wxUserID = $this->weixinuser->getOne($where2);
            }
            $wxuid = isset($wxUser['userid'])?$wxUser['userid']:0;
            $res = $wxUser;
            if (!$wxUser) {
                $accessToken = $this->getAccessToken();
                $accessToken['openId'] = $openid;
                $wxUserinfo = requestWeixinInterface('user_info', '', $accessToken);
                if ($wxUserinfo) {
                    if($userid && !$wxUserID) {
                        $wxUserinfo['userid'] = $userid;
                    }
                    $add = $this->weixinuser->add($wxUserinfo);
                    $id = isset($add['id'])?$add['id']:0;
                    if($id){
                        $wxUserinfo['id'] = $id;
                    }
                    $res = $wxUserinfo;
                }
            } else{
                $set = array();
                if (in_array($event,array('unsubscribe','subscribe'))) {
                    if ('unsubscribe' == $event) {
                        $set = array('subscribe' => 0);
                    } elseif ('subscribe' == $event) {
                        $accessToken = $this->getAccessToken();
                        $accessToken['openId'] = $openid;
                        $set = requestWeixinInterface('user_info', '', $accessToken);
                    }
                }
                if($userid && !$wxuid && !$wxUserID) {
                    $set['userid'] = $userid;
                }
                $this->weixinuser->update($set, $where);
            }

        }
        return $res;
    }



    public function getMenu(){
        $accessToken = $this->getAccessToken();
        $res = requestWeixinInterface('get_menu', $accessToken);
        return $res;
    }

    public function addMenu($param=array()){
        $accessToken = $this->getAccessToken();
        $res = requestWeixinInterface('create_menu', $accessToken,$param,'post');
        return $res;
    }

    public function delMenu(){
        $accessToken = $this->getAccessToken();
        $res = requestWeixinInterface('del_menu', $accessToken);
        return $res;
    }


    public function test($openid=''){
        $res = '';
        if($openid) {
            $where = array('openid' => $openid);
            $set = array('subscribe' => 0);
            $res = $this->weixinuser->update($set, $where);
        }
        return $res;
    }


    /**
     * 发送模板消息
     * @param string $openid
     * @param array $data
     * @param string $url
     * @param string $tid
     * @return array|string
     */
    public function sendTemplateMsg($openid='',$data=array(),$url='',$tid=''){
        $res = '';
        $tid = $tid ? $tid : \ConstInc::TEMPLATE_MESSAGE;
        if($tid && $openid && $data) {
            $dataArr = array(
                "touser"=>$openid,
                "template_id" => $tid,
                "url" => $url,
                "miniprogram" => array(//选填，跳小程序所需数据，不需跳小程序可不用传该数据
                    "appid"=>"",//所需跳转到的小程序appid（该小程序appid必须与发模板消息的公众号是绑定关联关系）
                    "pagepath"=>""//所需跳转到小程序的具体页面路径，支持带参数,（示例index?foo=bar）
                ),
                "data" => $data
            );

            $accessToken = $this->getAccessToken();
            $res = requestWeixinInterface('send_template', $accessToken,json_encode($dataArr),'post');
        }
        return $res;

    }


    /**
     * 通知微信用户或工程师、主管、管理
     * @param array $data
     * @return bool
     */
    public function sendWXNotice($data=array()){
        $res = false;
        $title = isset($data['title'])?$data['title']:'用户提交了一起新事件，点击查看';
        //接收消息用户的openid
        $openid = isset($data['openid'])?$data['openid']:'';
        $url = isset($data['url'])?$data['url']:'';
        //描述
        $des = isset($data['desc'])?$data['desc']:'';
        //事件单号
        $eventID = isset($data['eventID'])?$data['eventID']:'';
        //事件状态
        $state = isset($data['state'])?$data['state']:'';

        if($openid) {
            $templateData = array(
                "first" => array(
                    "value" => $title,
                    "color" => "#173177"
                ),
                "keyword1" => array(
                    "value" => $eventID,
                    "color" => "#173177"
                ),
                "keyword2" => array(
                    "value" => date('Y-m-d H:i:s'),
                    "color" => "#173177"
                ),
                "keyword3" => array(
                    "value" => $state,
                    "color" => "#173177"
                ),
                "keyword4" => array(
                    "value" => $des,
                    "color" => "#173177"
                ),
                /*"remark"=>array(
                    "value"=>"请对我们的服务作出评价，5分为最高，如有其他意见或建议，请咨询40012345678。谢谢您的支持！",
                    "color"=>"#173177"
                ),*/
            );
            $send = $this->sendTemplateMsg($openid, $templateData, $url);
            $res = $send ? true : false;
        }
        return $res;
    }


    /**
     * 获取网页授权access_token/openid等
     * @param $request
     */
    public function getWeixinSnsinfo($request,$url=''){
        $input = $request->input() ? $request->input() : '';
        $bkurl = isset($input['bkurl']) ? $input['bkurl'] : '';
        $redirectUrl = $url?$url:$request->url().'?bkurl='.$bkurl;//'http://' . $request->getHost() . '/home/user/getsnsaccesstoken';
//        dd($request->url());
        $code = isset($input['code']) ? $input['code'] : '';
        $appSecret = \ConstInc::WEIXINAPPIDS[\ConstInc::WX_APPID];
        if (isset($input['code'])) {
//            dd($code,\ConstInc::WX_APPID,$appSecret,$code);//exit;
            //3.拉取用户的openid

            $token = getSnsAccessAoken(\ConstInc::WX_APPID, $appSecret, $code);
            //var_dump($result);exit;
            $input['openid'] = isset($token['openid'])?$token['openid']:'';
            $result = $input;
            return $result;
//            return isset($token['openid'])?$token['openid']:'';
        } else {
            header('Location:' . snsBase($redirectUrl, \ConstInc::WX_APPID));
        }
    }



    /**
     * 企业微信获取access_token
     * 每隔116分钟更新一次access_token
     * @return array('accToken'=>'ZtA9Tu6f--wRTR4RjR6Avqc....', 'requestTime'=>12234234)
     */
    public function getQyAccessToken($appid='', $agentid='',$isClear=false) {
        $arrQyAppid = \ConstInc::WW_CORPSECRET;
        $cacheAccessTokenKey = 'qyAccessToken';
        if(empty($appid)) {
            $appid = \ConstInc::WW_CORPID;
        }
        if(!$agentid){
            $agentid = \ConstInc::WW_AGENTID;
        }
        $appsecret  = @$arrQyAppid[$agentid];
        if(!isset($appsecret) || empty($appsecret)){
            //记录日志
            $arrParams = array('errcode'=>'40013', 'errmsg'=>'Invalid appid.');
            return $arrParams;
        }
        //在这里实现access_token的问题，要兼顾到obj of redis
        $currTime = time();
        if(!$isClear) {
            //取redis中的access_token
            $arrToken = Cache::get($cacheAccessTokenKey);
            $requestTime = isset($arrToken['requestTime']) ? $arrToken['requestTime'] : 0;
            $dvTime = $requestTime-$currTime;
            //判断有无超时
            if( $requestTime && $dvTime > 0 ) {
                return $arrToken;
            }
        }

        //curl调用企业微信端的ACCESS_TOKEN
        $arrToken = requestQyWxInterface('access_token',array('appid'=>$appid, 'appsecret'=>$appsecret));
        $err = getkey($arrToken,'errcode','');
        if($err) {
            Log::error("qy access_token".json_encode($arrToken));
        }

        //设置redis值
        $accToken = isset($arrToken['access_token']) ? $arrToken['access_token'] : '';
        $requestTimeExp = $currTime+\ConstInc::ACCESS_TOKEN_TIMEOUT;
        $arrParams = array(
            'accToken'=>$accToken,
            'requestTime'=>$requestTimeExp,
            'requestDatetime' => date('Y-m-d H:i:s',$requestTimeExp),
            'time' => date('Y-m-d H:i:s',$currTime)
        );
        if($accToken) {
            if (!Cache::put($cacheAccessTokenKey, $arrParams, \ConstInc::ACCESS_TOKEN_TIMEOUT/60)) {}
        }else{
            echoLog($arrToken,'get qyaccToken err');
        }
        return $arrParams;
    }


    /**
     * 企业微信获取code
     * @param $request
     * @param string $url
     * @return mixed
     */
    public function getQyCode($request,$url=''){
        $input = $request->input() ? $request->input() : '';
        $bkurl = isset($input['bkurl']) ? $input['bkurl'] : '';
        $redirectUrl = $url?$url:$request->url().'?bkurl='.$bkurl;//'http://' . $request->getHost() . '/home/user/getsnsaccesstoken';
        $code = $request->input('code');
        if ($code) {
            return $code;
        }
//        $param = array(
//            'appid'=>\ConstInc::WW_CORPID,
//            'redirectUri' => $redirectUri,
//            'scope' => 'snsapi_userinfo',
//            'agentid'=> \ConstInc::WW_AGENTID,
//        );
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?';
        $url .= 'appid='.\ConstInc::WW_CORPID;
        $url .= '&redirect_uri='.$redirectUrl;
        $url .= '&response_type=code';
        $url .= '&scope=snsapi_privateinfo';
        $url .= '&agentid='.\ConstInc::WW_AGENTID;
        $url .= '&state=ywzz_weixin#wechat_redirect';

        header('Location:' . $url);

//        $codeRes = requestQyWxInterface('code',$param);
    }


    /**
     * 企业微信根据code获取成员信息
     * @param $request
     * @param string $url
     * @return array
     */
    public function getQyUserinfo($request,$url='',$isClear=false){
        $user = array();
        $cacheKey = 'qyWxUserInfo';


        /*$currTime = time();
        if(!$isClear) {
            //取redis中的access_token
            $user = Cache::get($cacheKey);
            $requestTime = isset($user['requestTime']) ? $user['requestTime'] : 0;
            $dvTime = $requestTime-$currTime;
            $errcode = isset($user['errcode']) ?$user['errcode'] : '';
            //判断有无超时
            if(0 === $errcode) {
                if ($requestTime && $dvTime > 0) {
                    return $user;
                }
            }

        }*/

        $tokenArr = $this->getQyAccessToken();
        $code = $this->getQyCode($request,$url);
        $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
        if($code && $token){
            $user = requestQyWxInterface('getuserinfo',array('accToken'=>$token, 'code'=>$code));
            if($user && is_array($user)) {
                $err = getkey($user, 'errcode', '');
                if($err) {
                    Log::error("qy getuserinfo".json_encode($user));
                }
            }
        }

        //设置redis值
        /*$arrParams = $user;
        $requestTimeExp = $currTime+\ConstInc::WW_CODE_USER_TIMEOUT;
        $arrParams['requestTime'] = $requestTimeExp;
        $arrParams['requestDatetime'] = date('Y-m-d H:i:s',$requestTimeExp);
        $arrParams['time'] = date('Y-m-d H:i:s',$currTime);
//        var_dump($arrParams,$currTime+\ConstInc::WW_CODE_USER_TIMEOUT,$currTime);exit;
        if($user) {
            if (!Cache::put($cacheKey, $arrParams, 1000/60)) {}
        }else{
            echoLog($user,'get qyuserinfo err');
        }*/

        return $user;
    }


    /**
     * 企业微信使用user_ticket获取成员详情
     * @param $request
     * @param string $url
     * @return array
     */
    public function getQyUserdetail($request,$url=''){
        $user = $this->getQyUserinfo($request,$url);
        $dataRes = array();

        $tokenArr = $this->getQyAccessToken();
        $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
        $user_ticket = isset($user['user_ticket']) ? $user['user_ticket'] : '';
        $dataArr = json_encode(array('user_ticket' => $user_ticket));
        $param = array('accToken'=>$token);
//        var_dump($tokenArr,$user);exit;

        if($user_ticket && $token){
            $dataRes = requestQyWxInterface('getuserdetail',$param,$dataArr,'POST');
            $err = getkey($dataRes,'errcode','');
            if($err !== 0) {
                Log::error("qy getuserdetail".json_encode($dataRes));
            }
//            var_dump(222,$dataRes);exit;
        }
        $dataRes['user_ticket'] = $user_ticket;

        return $dataRes;

    }


    /**
     * 企业微信-推送文本卡片消息（通知）
     * @param array $param
     * @return array
     */
    public function qySendTextcard($param=array()){

        $dataRes = array();
        $tokenArr = $this->getQyAccessToken();
        $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
        $touser = getkey($param,'touser','');
        //数组转化为字符串并且去重去空
        $tousers = '';
        if($touser){
            $tousers = is_array($touser) ? implode('|',array_filter(array_unique($touser))) : trim($touser);
        }
        $title = getkey($param,'title','');
        $desc = getkey($param,'desc','');
        $url = getkey($param,'url','');
        $btntxt = getkey($param,'btntxt','详情');
        $toparty = getkey($param,'toparty','');
        $totag = getkey($param,'totag','');
        $type = getkey($param,'dtype','');

        if($type){
            $eventID = getkey($param,'eventID',0);
            $state = getkey($param,'state','');
            $description = getkey($param,'desc','');
            $date = date('Y-m-d');
            $desc = "<div class=\"gray\">$date</div>";
            $desc .= "<div >事件单号：$eventID</div>";
            $desc .= "<div >发起时间：".date('Y-m-d H:i:s')."</div>";
            $desc .= "<div >当前状态：$state</div>";
            $desc .= "<div >事件摘要：$description</div>";
        }

        if($tousers && $title && $desc && $url && \ConstInc::WW_AGENTID) {
            //$dataArr需要json_encode
            $dataArr = array(
                "touser" => $tousers,//"JiMi",必填
                "toparty" => $toparty,//选填
                "totag" => $totag,//选填
                "msgtype" => "textcard",//必填
                "agentid" => \ConstInc::WW_AGENTID,//必填
                "textcard" => array(
                    "title" => $title,//必填
                    "description" => $desc,//必填
                    "url" => $url,//必填
                    "btntxt" => $btntxt//选填
                )
            );

            if ($token) {
                $dataRes = requestQyWxInterface('send', $tokenArr, json_encode($dataArr), 'POST');
            }
        }
        return $dataRes;
    }


    /**
     * 企业微信-获取用户信息
     * @param array $param
     * @return array|mixed
     */
    public function getQyWxUserInfo($param=array()){
        $res = array();
        $user_id = isset($param['user_id'])?$param['user_id']:'';
//        $event = isset($param['Event'])?$param['Event']:'';
        $userid = isset($param['userid'])?$param['userid']:'';
        $user_ticket = getkey($param,'user_ticket','');
        $where = array('userid'=>$userid);
//        var_dump($where);exit;
        if($userid) {
            $wxUserID = false;
            $wxUser = $this->qywxuser->getOne($where);
            $wxuid = isset($wxUser['user_id'])?$wxUser['user_id']:0;
            if($user_id) {
                $where2 = array('user_id' => $user_id);
                $wxUserID = $this->qywxuser->getOne($where2);
            }
            $res = $wxUser;
            if (!$wxUser) {
                $tokenArr = $this->getQyAccessToken();
                $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
                $dataArr = json_encode(array('user_ticket' => $user_ticket));
                $param = array('accToken'=>$token);
                $wxUserinfo = array();
                if($user_ticket && $token){
                    $wxUserinfo = requestQyWxInterface('getuserdetail',$param,$dataArr,'POST');
                    $err = getkey($wxUserinfo,'errcode','');
                    if($err !== 0) {
                        Log::error("qy user_getuserdetail".json_encode($wxUserinfo));
                    }
                }
//                var_dump($param,$dataArr,$wxUserinfo);exit;
                if ($wxUserinfo) {
                    if($user_id && !$wxUserID) {
                        $wxUserinfo['user_id'] = $user_id;
                    }
                    $add = $this->qywxuser->add($wxUserinfo);
                    $id = isset($add['id'])?$add['id']:0;
                    if($id){
                        $wxUserinfo['id'] = $id;
                    }
                    $res = $wxUserinfo;
                }
            } else{
                $set = array();
                if($user_id && !$wxuid && !$wxUserID) {
                    $set['user_id'] = $user_id;
                }
                $this->qywxuser->update($set, $where);
            }

        }
        return $res;
    }


    /**
     * 企业微信-获取部门列表
     * @return mixed|null
     */
    public function getQyDepartment(){
        $tokenArr = $this->getQyAccessToken();
        $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
        $param = array('accToken'=>$token,'id'=>'');
        $res = requestQyWxInterface('department',$param);
        $err = getKey($res,'errcode','');
        if($err !== 0) {
            Log::error("qy department".json_encode($res));
        }
        return getKey($res,'department',array());
    }


    /**
     * 企业微信-获取部门成员详情
     * @return array
     */
    public function getQyUserList(){
        $department = $this->getQyDepartment();
//        var_dump($department);exit;
        $tokenArr = $this->getQyAccessToken();
        $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
        $param = array('accToken'=>$token);
        $result = array();
        if($department){
            foreach($department as $v) {
                $param['department_id'] = getKey($v,'id',0);
                $param['fetch_child'] = '';
                $res = requestQyWxInterface('userlist', $param);
                $err = getKey($res, 'errcode', '');
                if($err !== 0){
                    Log::error("qy department".json_encode($res));
                }else {
                    $result = array_merge($result,getKey($res,'userlist',array()));
                }
            }
        }
//        return $result ? array_slice($result,5,2) : array(); //test
        return $result;
    }



    public function qyRegUpdate($param=array()){
        $res = array();
        $phone = '';//test //'18101722804';//
        $user_ticket = getkey($param,'user_ticket','');
        $userid = getkey($param,'userid','');
        $identity_id = getKey($param,'identity_id',4);
        $password = getKey($param,'password');
        $where = array('userid'=>$userid);
        if($userid) {
            $wxUserinfo = array();
            $wxUserinfo = array('userid'=>$phone,'mobile'=>$phone);
            $tokenArr = $this->getQyAccessToken();
            $token = isset($tokenArr['accToken']) ? $tokenArr['accToken'] : '';
            $dataArr = json_encode(array('user_ticket' => $user_ticket));
            $param = array('accToken'=>$token);
            $flag = false;
            if($user_ticket && $token){
                $wxUserinfo = requestQyWxInterface('getuserdetail',$param,$dataArr,'POST');
                $err = getkey($wxUserinfo,'errcode','');
                if($err !== 0) {
                    Log::error("qy user_getuserdetail".json_encode($wxUserinfo));
                }else{
                    $flag = true;
                }
            }


            $qyuserid = getKey($wxUserinfo,'userid','');
            $phone = getKey($wxUserinfo,'mobile','');
            $name = getKey($wxUserinfo,'name','');
            $email = getKey($wxUserinfo,'email','');

            $user = '';
            $phoneLen = strlen($phone);

            $pwd = \ConstInc::WW_USER_DEFAULT_PWD;
            if($phone && 11==$phoneLen) {
                $orWhere = array('phone' => $phone, 'name' => $phone);
                $user = $this->user->getByNamePhonew($orWhere);

                if(!$user) {
                    $userInsert = array('name' => $phone,
                        'username' => $name ? $name : $phone,
                        'email' => $email,
                        'phone' => $phone,
                        'identity_id' => $identity_id,
                        'password' => bcrypt($pwd)
                    );
                    $uAdd = $this->user->qyAdd($userInsert);
                }

                //多库环境下，切库
                $db = null;
                if (\ConstInc::MULTI_DB) {
                    $dbs = $this->getDbs();
                    $db = $dbs[0];
                    //                $db = $user->db;
                    \DB::setDefaultConnection($db); //切换到实际的数据库
                    $user = $this->user->getByNamePhonew($orWhere);
                    $userWxid = isset($user['wxid']) ? $user['wxid'] : 0;
                    $resID = isset($user['id']) ? $user['id'] : 0;
                } else {
                    $user = $this->user->getByNamePhonew($orWhere);
                    $userWxid = isset($user['wxid']) ? $user['wxid'] : 0;
                    $resID = isset($user['id']) ? $user['id'] : 0;
                }

            }

            $where = array('mobile'=>$phone);
            $wxUser = $this->qywxuser->getOne($where);
            $user_id = isset($wxUser['user_id'])?$wxUser['user_id']:0;
            $wxid = isset($wxUser['id']) ? $wxUser['id'] : 0;
            $res = $wxUser;

            $wxUid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
            //当前获取到的企业用户id不等于企业用户表里的userid时才更新
            $flag = $flag && $wxUid != $qyuserid ? true : false;


            if (!$wxUser || !$user) {
//                var_dump($param,$dataArr,$wxUserinfo);exit;
                if ($wxUserinfo) {
                    //新增用户表
                    if (!$user) {
                        $user = User::create([
                            'name' => $phone,
                            'username' => $name ? $name : $phone,
                            'email' => $email,
                            'phone' => $phone,
                            'identity_id' => $identity_id,
                            'password' => bcrypt($pwd),
                        ]);
                        $resID = isset($user['id']) ? $user['id'] : 0;
                    }
                    $qyWhere = array('userid' => $userid);
                    if(!$wxUser) {
                        $qyUser = $this->qywxuser->getOne($where);
                        $wxid = isset($qyUser['id']) ? $qyUser['id'] : 0;
                        $user_id = isset($qyUser['user_id']) ? $qyUser['user_id'] : 0;
                    }
                    if(!$wxid) {
                        //新增企业用户表
                        $wxUserinfo['user_id'] = $resID;
                        $add = $this->qywxuser->add($wxUserinfo);
                        $wxid = isset($add['id']) ? $add['id'] : 0;
                        $wxUserinfo['wxid'] = $wxid;
                    }elseif(!$user_id){
                        //更新企业用户表
                        if($resID) {
                            $qyup = array('user_id'=> $resID);
                            if($flag){
                                $qyup['userid'] = $qyuserid;
                                $qyup['name'] = $name;
                            }
                            $this->qywxuser->update($qyup, $qyWhere);
                        }
                    }
                    //更新用户表的wxid
                    if (!$wxUser && $wxid) {
                        $param = array('wxid' => $wxid);
                        if($flag){
                            $param['username'] = $name ? $name : $phone;
                        }
                        $this->user->update($resID,$param);
                    }
                    $res = $wxUserinfo;
                }

            } else{
               if($wxUser && $user) {
                   if($resID && $wxid) {
                       if (!$userWxid || $flag) {
                           $param = array('wxid' => $wxid);
                           if ($flag) {
                               $param = array('username' => $name ? $name : $phone);
                           }
                           $this->user->update($resID, $param);
                       }
                       if (!$user_id || $flag) {
                           $qyWhere = array('id' => $wxid);
                           $qyup = array('user_id' => $resID);
                           if ($flag ) {
                               $qyup = array('userid' => $qyuserid,'name'=>$name);
                           }
                           $this->qywxuser->update($qyup, $qyWhere);
                       }
                   }
               }

            }

        }

        return $res;
    }



    public function getDbs(){
        $dbs = array();
        $connections = array_keys(\Config::get("database.connections",[]));
        foreach($connections as $conn) {
            if(strpos($conn, "opf_") === 0) {
                $dbs[] = $conn;
            }
        }
        return $dbs;
    }















}