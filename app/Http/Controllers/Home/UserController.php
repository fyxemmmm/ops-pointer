<?php
/**
 * 用户  
 */

namespace App\Http\Controllers\Home;
//namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Weixin\CommonRepository;
use App\Repositories\Weixin\SmsInfoRepository;
use App\Repositories\Auth\UserRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Workflow\OaRepository;
use App\Repositories\Weixin\QyWxUserRepository;
use App\Models\Auth\User;
use App\Models\Code;
use App\Models\Home\SmsInfo;
use App\Exceptions\ApiException;
use App\Support\Response;
//use App\Support\submail\SUBMAILAutoload;
use App\Support\Submail;
use App\Support\WxJssdk;
use Auth;
use DB;
use App\Support\GValue;
use Log;

class UserController extends Controller
{

    protected $common;
    protected $response;
    protected $user;
    protected $submail;
    protected $smsinfo;
    protected $weixinuser;
    protected $userInfo;
    protected $hostName;
    protected $events;
    protected $eventoa;
    protected $qywxuser;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    function __construct(CommonRepository $common,
                         UserRepository $user,
                         SmsInfoRepository $smsinfo,
                         WeixinUserRepository $weixinuser,
                         EventsRepository $events,
                         Request $request,
                         OaRepository $eventoa,
                         QyWxUserRepository $qywxuser)
    {
        $this->common = $common;
        $this->user = $user;
        $this->smsinfo = $smsinfo;
        $this->weixinuser = $weixinuser;
        $this->events = $events;

        $this->response = new Response();
        $this->submail = new Submail();
        $getAccessToken = \ConstInc::WX_PUBLIC == 2 ? $this->common->getQyAccessToken() : $this->common->getAccessToken();
        $this->wxjsskd = new WxJssdk($getAccessToken);
        $this->hostName = 'http://'.$_SERVER['SERVER_NAME'];
        $this->eventoa = $eventoa;
        $this->qywxuser = $qywxuser;


    }

    public function index(Request $request){
        echo 'index';

    }


    public function getSignPackage(Request $request){

//        echo base_path();exit;
//        $url = $request->url();
        $input = $request->input()?$request->input():'';
        $front_url = isset($input['front_url'])?$input['front_url']:'';
        $sign = $this->wxjsskd->getSignPackage($front_url);
//        var_dump($sign);exit;
        return $this->response->send($sign);;
    }

    /**
     * 获取手机验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyCodePhone(Request $request)
    {
        $result['result'] = '';
        $data['result'] = array();
        $input = $request->post() ? $request->post() : array();
        $phone = isset($input['phone']) ? $input['phone'] : '';
        if ($phone) {
            if (!isMobile($phone)) {
                throw new ApiException(Code::ERR_PARAMS, ["手机号不正确"]);
            } else {
                $orWhere = array('phone'=>$phone,'name'=>$phone);
                $user = $this->user->getByNamePhone($orWhere);
                if($user) {
                    $db = $user->db;
                    if(\ConstInc::MULTI_DB && !empty($db)) {
                        DB::setDefaultConnection($db);
                        GValue::$currentDB = $db;
                    }
                }

                // 发送验证码
                $data = $this->submail->sendSMS($phone);
                // $data = ["status" => "success", "send_id" => "926e8dfd2c0b2c79019365d0ae881207", "fee" => 1, "sms_credits" => "726", "transactional_sms_credits" => "0", "phone" => "18502728040", "phoneCode" => "440404"];

                $phoneCode = isset($data['phoneCode']) ? $data['phoneCode'] : '';
                $status = isset($data['status']) ? $data['status'] : '';
                $result['result'] = array(
                    'status' => $status,
                    'code' => $phoneCode
                );
                $this->savePhoneCode($data);
            }
        } else {
            throw new ApiException(Code::ERR_PARAMS, ["手机号不能为空"]);
        }
//        dd($data);exit;

        return $this->response->send($result);
    }


    /**
     * 保存验证码入库
     * @param array $param
     * @return bool
     */
    private function savePhoneCode($param=array()){
        $result = false;
        $status = isset($param['status'])?$param['status']:'';
        $phone = isset($param['phone'])?$param['phone']:'';
        $phoneCode = isset($param['phoneCode'])?$param['phoneCode']:'';
        $code = isset($param['code'])?$param['code']:'';
        $send_id = isset($param['send_id'])?$param['send_id']:'';
        $msg = isset($param['msg'])?$param['msg']:'';
        $error = '';
        if('error' == $status){
            $error = 'code:'.$code.',msg:'.$msg;
        }
        if($status && $phone && $phoneCode && $send_id){
            $smsinfo = SmsInfo::create([
                'phone' => $phone,
                'code' => $phoneCode,
                'send_id' => $send_id,
                'error' => $error
            ]);
            $resID = isset($smsinfo['id']) ? $smsinfo['id'] : 0;
            $result = $resID?true:false;
        }
        return $result;
    }


    public function getLogin(Request $request){

        //  http://operation.test/home/user/login
        // dump($request->url());exit;

       // dd($request->url());exit;//\Request::getRequestUri()
//        session()->forget('wxOpenid');

        if(2 == \ConstInc::WX_PUBLIC){
            //企业微信号
            $qyUser = $this->common->getQyUserdetail($request);
            $qyUserid = isset($qyUser['userid']) ? $qyUser['userid'] : '';
            $qyUserTicket = getkey($qyUser,'user_ticket','');
            $qyUserMobile = getkey($qyUser,'mobile','');
            $request->session()->put('qyWxUserid',$qyUserid);
            $request->session()->put('qyWxUserTicket',$qyUserTicket);;
            $request->session()->put('qyWxUserMobile',$qyUserMobile);
//            var_dump($qyUser,session('qyWxUserid'),session('qyWxUserTicket'));exit;
            /*if(!$qyUserid){
                header('Location:' . $this->hostName.'/error');exit;
            }*/

            $sessuInfo = Auth::user();
            $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';

            $input = $request->input() ? $request->input() : '';
            $bkurl = isset($input['bkurl']) ? $input['bkurl'] : '';

            if ($qyUserid) {
                $strBkurl = $bkurl ? '/' . urlencode($bkurl) : '';
                $url = $this->hostName . '/qyshow' . $strBkurl;
                $param = array(
                    'user_ticket'=> $qyUserTicket,
                    'userid' => $qyUserid,
                    'identity_id' => 4,

                );
                $this->common->qyRegUpdate($param);

                if (!$sessuInfo) {
//                    var_dump($url);exit;
                    header('Location:' . $url);
                    return;
                }
                if (in_array($identity_id, array(4, 5))) {
                    $url = $this->hostName . '/eventsadd';
                } elseif (in_array($identity_id, array(1, 2, 3))) {
                    $url = $this->hostName . '/eventsaddengin';
                }
                header('Location:' . $url);
                $result['result'] = array('qyWxUserid' => $qyUserid);//array('id'=>1,'name'=>'test');

                return $this->response->send($result);
            }

        }else {
            //微信公众号
            if(2 == \ConstInc::WX_PUBLIC){
                header('Location:' . $this->hostName.'/error');exit;
            }
            $wxInfo = $this->common->getWeixinSnsinfo($request);
            $openid = isset($wxInfo['openid']) ? $wxInfo['openid'] : '';

            // 将用户 openid 保存到 session中
            $request->session()->put('wxOpenid', $openid);
            $sessuInfo = Auth::user();
            $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
            $sessOpenid = session('wxOpenid');
            Log::info('login_set_wx_sessopenid:',array($openid,$sessOpenid));
//        var_dump('aa==',$sessOpenid,"==bb");
            $type = $request->input('type');
            $bkurl = isset($wxInfo['bkurl']) ? $wxInfo['bkurl'] : '';

            if ($openid) {
                $strBkurl = $bkurl ? '/' . urlencode($bkurl) : '';
                $url = $this->hostName . '/apishow' . $strBkurl;
                if (!$sessuInfo) {
                    header('Location:' . $url);
                    return;
                }
                if (in_array($identity_id, array(4, 5))) {
                    $url = $this->hostName . '/eventsadd';
                } elseif (in_array($identity_id, array(1, 2, 3))) {
                    $url = $this->hostName . '/eventsaddengin';
                }
                header('Location:' . $url);
                $result['result'] = array('wxOpenid' => $sessOpenid);//array('id'=>1,'name'=>'test');
//        dd($base);exit;
//        snsBase();
//        return view('home.login');

                return $this->response->send($result);
            }
        }



    }


    public function showTest(){
        $sessOpenid = session('wxOpenid');
        var_dump('aa==',$sessOpenid,"==bb");
    }


    public function getForgetwxSession(){
        session()->forget('wxOpenid');
        $sessOpenid = session('wxOpenid');
        var_dump('aa==',$sessOpenid,"==bb");
    }
    public function getForgetUserSession(){
        Auth::logout();
        var_dump(Auth::user());
    }



    public function getSnsinfo(Request $request){
        $openid = $this->common->getWeixinSnsinfo($request);
        $result['result']  = $openid;
        return $this->response->send($result);
    }


    /**
     * 用户登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginAct(Request $request){
//        $result['result'] = '';
        if(2 == \ConstInc::WX_PUBLIC){
            //echo 111;exit;
            //企业微信号
            $result['result'] = $this->qyWxLoginNoPwdAct($request);
//            $result['result'] = $this->qyWxLoginAct($request);
        }else{
            if(2 == \ConstInc::WX_PUBLIC){
                header('Location:' . $this->hostName.'/error');exit;
            }
            //echo 222;exit;
            //微信公众号（服务号）
            $result['result'] = $this->wxLoginAct($request);
        }

        return $this->response->send($result);

    }


    /**
     * 微信公众号（服务号）登录
     * @param $request
     * @return array
     * @throws ApiException
     */
    public function wxLoginAct($request){
        $result = array();
        $input = $request->input() ? $request->input() : array();
        $phone = isset($input['phone'])?$input['phone']:'';
        $pwd = isset($input['password'])?$input['password']:'';
        $phoneCode = isset($input['phone_code'])?$input['phone_code']:'';
        $lenNewPwd = strlen($pwd);

        $sessOpenid = session('wxOpenid');
        $sessDb = session('db');
        $resID = 0;
//        var_dump($input);exit;
        if(!$phone || !$pwd){
            throw new ApiException(Code::ERR_PARAMS, ["手机号、密码不能为空！"]);
        }else{
//        elseif (!isMobile($phone)) {
//            throw new ApiException(Code::ERR_PARAMS, ["手机号格式不正确"]);
//        }else{
            if($lenNewPwd < 4 || $lenNewPwd > 20){
                throw new ApiException(Code::ERR_PARAMS, ["密码长度不能小于4或大于20个字符！"]);
            }
            $orWhere = array('phone'=>$phone,'name'=>$phone);
//            var_dump($input);exit;
            $user = $this->user->getByNamePhone($orWhere);
//            var_dump($user);exit;
            $userWxid = isset($user['wxid'])?$user['wxid']:0;
            $resID = isset($user['id']) ? $user['id'] : 0;
            $orgId = $resID;
            $orgUser = $user;
            if($resID){
                $identity_id = isset($user['identity_id']) ? $user['identity_id'] : '';
                if(1 == $identity_id){
                    throw new ApiException(Code::ERR_WXLOGIN_ADMIN);
                }
                //多库环境下，切库
                $db = null;
                if(\ConstInc::MULTI_DB && !empty($user->db)) {
                    $db = $user->db;
                    \DB::setDefaultConnection($db); //切换到实际的数据库
                    $user = $this->user->getByNamePhone($orWhere);
                    $userWxid = isset($user['wxid'])?$user['wxid']:0;
                    $resID = isset($user['id']) ? $user['id'] : 0;
                }

                $where2 = array('userid' => $resID);
                $wxUser = $this->weixinuser->getOne($where2);
                if(!$wxUser) {
                    $wxParam = array('openid' => $sessOpenid, 'userid' => $resID);
                    $wxRes = $this->common->getWeixinUserInfo($wxParam);
                    $wxid = isset($wxRes['id']) ? $wxRes['id'] : 0;
                    //weixinuser表中有数据，users表中没有wxid,
                    //且weixinuser表中有数据id不等于users表中的wxid
                    if ($wxid && !$userWxid && $userWxid != $wxid) {
                        $param = array('wxid' => $wxid);
                        $this->user->update($resID, $param);
                    }
                }else {
                    //验证当前微信openid和注册openid是否相同
                    $wxOpenid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    Log::info('loginact_wx_sessopenid:',array($wxOpenid,$sessOpenid));
                    if ($wxOpenid != $sessOpenid) {
                        throw new ApiException(Code::ERR_WX_LOGIN_ACCOUNT);
                    }
                }

                //检查验证码是否有效
                if($phone && $phoneCode) {
                    $checkPhone = $this->checkPhoneCode($phone, $phoneCode);
                    $status = isset($checkPhone['status']) ? $checkPhone['status'] : '';
                    $msg = isset($checkPhone['msg']) ? $checkPhone['msg'] : '';
                    if ('success' != $status) {
                        throw new ApiException(Code::ERR_PARAMS, [$msg]);
                    }
                }

                $flag = false;
                //登录
                if(Auth::attempt(["phone" => $phone , "password" => $pwd])){
                    $flag = true;
                }elseif(Auth::attempt(["name" => $phone, "password" => $pwd])){
                    $flag = true;
                }
                if(!$flag){
                    throw new ApiException(Code::ERR_PARAMS, ["手机号或密码不正确"]);
                }

                if(\ConstInc::MULTI_DB && !empty($db)) {
                    Auth::setUser($orgUser);
                    $request->session()->put(Auth::getName(), $orgId); //记录下全局的用户id
                    $request->session()->put("db", $db); //记录下全局的用户id
                }

                /*if(!password_verify($pwd,$user->password)){
                    throw new ApiException(Code::ERR_PARAMS,["手机号或密码不正确"]);
                }else{
                    $resPhone = isset($user['phone'])?$user['phone']:'';
                    $resID = isset($user['id'])?$user['id']:'';
                    $identity_id = isset($user['identity_id'])?$user['identity_id']:'';
                    $result['result'] = $resID;

                    //保存数据到session
                    $userInfo = array(
                        'id' => $resID,
                        'phone' => $resPhone,
                        'identity_id' => $identity_id,
                        'openid'=>$sessOpenid
                    );
                    $request->session()->put('userInfo',$userInfo);
                }*/
                $result = $this->getSessUinfo();
            }elseif(!$resID && !$phoneCode){
                if(!\ConstInc::WX_REGISTER) {
                    throw new ApiException(Code::ERR_WX_REGISTER_CLOSE);
                }
                $where2 = array('openid' => $sessOpenid);
                if(\ConstInc::MULTI_DB && !empty($sessDb)) {
                    \DB::setDefaultConnection($sessDb); //切换到实际的数据库
                }
                $wxUser = $this->weixinuser->getOne($where2);
                $wxuid = isset($wxUser['userid'])?$wxUser['userid']:0;
                if($wxUser && $wxuid){
                    throw new ApiException(Code::ERR_WXUSER_NOTUSER);
                }
                throw new ApiException(Code::ERR_FIRST_LOGIN);
            }elseif($phoneCode){
                //注册
                //TODO 注册关闭，暂时不加分库逻辑
                if(\ConstInc::WX_REGISTER) {
                    $username = isset($input['username']) ? $input['username'] : '';
                    $email = isset($input['email']) ? $input['email'] : '';
                    $pwd = isset($input['password']) ? $input['password'] : '';
//        var_dump($input);exit;
                    $checkPhone = $this->checkPhoneCode($phone, $phoneCode);
                    $status = isset($checkPhone['status']) ? $checkPhone['status'] : '';
                    $msg = isset($checkPhone['msg']) ? $checkPhone['msg'] : '';
                    if ('success' != $status) {
                        throw new ApiException(Code::ERR_PARAMS, [$msg]);
                    } else {
                        $whereWx = array('openid' => $sessOpenid);
                        $wxUser = $this->weixinuser->getOne($whereWx);
                        $wxid = isset($wxUser['id']) ? $wxUser['id'] : 0;

                        $addUser = User::create([
                            'name' => $phone,
                            'username' => $username ? $username : $phone,
                            'email' => $email,
                            'phone' => $phone,
                            'identity_id' => User::USER_NORMAL,
                            'password' => bcrypt($pwd),
                            'wxid' => $wxid
                        ]);
                        $resID = isset($addUser['id']) ? $addUser['id'] : 0;
//                    $result['result'] = $resID;
                        Auth::login($addUser);
                        $result = $this->getSessUinfo();

                        if ($resID) {
                            $wxParam = array('openid' => $sessOpenid, 'userid' => $resID);
                            $wxRes = $this->common->getWeixinUserInfo($wxParam);
                            $wxid = isset($wxRes['id']) ? $wxRes['id'] : 0;
                            if ($wxid && !$userWxid && $userWxid != $wxid) {
                                $param = array('wxid' => $wxid);
                                $this->user->update($resID, $param);
                            }
                        }
                    }
                }else{
                    throw new ApiException(Code::ERR_WX_REGISTER_CLOSE);
                }

            }

        }
        Log::info('wxloginact:'.$result);
        return $result;
    }


    /**
     * 企业微信登录
     * @param $request
     * @return array
     * @throws ApiException
     */
    public function qyWxLoginAct($request){
        $result = array();
        $input = $request->input() ? $request->input() : array();
        $phone = isset($input['phone'])?$input['phone']:'';
        $pwd = isset($input['password'])?$input['password']:'';
        $phoneCode = isset($input['phone_code'])?$input['phone_code']:'';
        $lenNewPwd = strlen($pwd);

//        $sessQyWxUserid = 'JiMi';//session('qyWxUserid');
//        $sessQyWxUserTicket = 'EPTtlpTn3Vgfc37rjH_NbhJrEXj477b8MRTjV6WtSwmJYiB5SACzGOa1MH9KS-0PYUEE5MoAL2-zzvHELcSJ_Q';//session('qyWxUserTicket');
        $sessQyWxUserid = session('qyWxUserid');
        $sessQyWxUserTicket = session('qyWxUserTicket');
        $sessDb = session('db');
        $resID = 0;
//        var_dump($sessQyWxUserid,$sessQyWxUserTicket);exit;
        if(!$phone || !$pwd){
            throw new ApiException(Code::ERR_PARAMS, ["手机号、密码不能为空！"]);
        }else {
//        elseif (!isMobile($phone)) {
//            throw new ApiException(Code::ERR_PARAMS, ["手机号格式不正确"]);
//        }else{
            if ($lenNewPwd < 4 || $lenNewPwd > 20) {
                throw new ApiException(Code::ERR_PARAMS, ["密码长度不能小于4或大于20个字符！"]);
            }
            $orWhere = array('phone' => $phone, 'name' => $phone);
//            var_dump($input);exit;
            $user = $this->user->getByNamePhone($orWhere);
//            var_dump($user);exit;
            $userWxid = isset($user['wxid']) ? $user['wxid'] : 0;
            $resID = isset($user['id']) ? $user['id'] : 0;
            $orgId = $resID;
            $orgUser = $user;
            if ($resID) {
                $identity_id = isset($user['identity_id']) ? $user['identity_id'] : '';
                if (1 == $identity_id) {
                    throw new ApiException(Code::ERR_WXLOGIN_ADMIN);
                }
                //多库环境下，切库
                $db = null;
                if (\ConstInc::MULTI_DB && !empty($user->db)) {
                    $db = $user->db;
                    \DB::setDefaultConnection($db); //切换到实际的数据库
                    $user = $this->user->getByNamePhone($orWhere);
                    $userWxid = isset($user['wxid']) ? $user['wxid'] : 0;
                    $resID = isset($user['id']) ? $user['id'] : 0;
                }

                $where2 = array('user_id' => $resID);
                $wxUser = $this->qywxuser->getOne($where2);
//                var_dump($wxUser);exit;

                    //企业微信用户表
                    $wxParam = array('userid' => $sessQyWxUserid, 'user_id' => $resID,'user_ticket'=>$sessQyWxUserTicket);
                    $wxRes = $this->common->getQyWxUserInfo($wxParam);
                    $wxid = isset($wxRes['id']) ? $wxRes['id'] : 0;
                    if ($wxid && !$userWxid && $userWxid != $wxid) {
                        $param = array('wxid' => $wxid);
                        $this->user->update($resID, $param);
                    }

                    $wxUserid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    if ($wxUserid != $sessQyWxUserid) {
//                        throw new ApiException(Code::ERR_WX_LOGIN_ACCOUNT);
                    }


                //检查验证码是否有效
                if ($phone && $phoneCode) {
                    $checkPhone = $this->checkPhoneCode($phone, $phoneCode);
                    $status = isset($checkPhone['status']) ? $checkPhone['status'] : '';
                    $msg = isset($checkPhone['msg']) ? $checkPhone['msg'] : '';
                    if ('success' != $status) {
                        throw new ApiException(Code::ERR_PARAMS, [$msg]);
                    }
                }

                $flag = false;
                //登录
                if (Auth::attempt(["phone" => $phone, "password" => $pwd])) {
                    $flag = true;
                } elseif (Auth::attempt(["name" => $phone, "password" => $pwd])) {
                    $flag = true;
                }
                if (!$flag) {
                    throw new ApiException(Code::ERR_PARAMS, ["手机号或密码不正确"]);
                }

                if (\ConstInc::MULTI_DB && !empty($db)) {
                    Auth::setUser($orgUser);
                    $request->session()->put(Auth::getName(), $orgId); //记录下全局的用户id
                    $request->session()->put("db", $db); //记录下全局的用户id
                }
                $result = $this->getSessUinfo();
            } elseif (!$resID && !$phoneCode) {
                if (!\ConstInc::WX_REGISTER) {
                    throw new ApiException(Code::ERR_WX_REGISTER_CLOSE);
                }
                $where2 = array('userid' => $sessQyWxUserid);
                if (\ConstInc::MULTI_DB && !empty($sessDb)) {
                    \DB::setDefaultConnection($sessDb); //切换到实际的数据库
                }
                $wxUser = $this->qywxuser->getOne($where2);
                $wxuid = isset($wxUser['userid']) ? $wxUser['userid'] : 0;
                if ($wxUser && $wxuid) {
                    throw new ApiException(Code::ERR_WXUSER_NOTUSER);
                }
                throw new ApiException(Code::ERR_FIRST_LOGIN);
            } elseif ($phoneCode) {
                //注册
                //TODO 注册关闭，暂时不加分库逻辑
                if (\ConstInc::WX_REGISTER) {
                    $username = isset($input['username']) ? $input['username'] : '';
                    $email = isset($input['email']) ? $input['email'] : '';
                    $pwd = isset($input['password']) ? $input['password'] : '';
//        var_dump($input);exit;
                    $checkPhone = $this->checkPhoneCode($phone, $phoneCode);
                    $status = isset($checkPhone['status']) ? $checkPhone['status'] : '';
                    $msg = isset($checkPhone['msg']) ? $checkPhone['msg'] : '';
                    if ('success' != $status) {
                        throw new ApiException(Code::ERR_PARAMS, [$msg]);
                    } else {
                        $whereWx = array('userid' => $sessQyWxUserid);
                        $wxUser = $this->qywxuser->getOne($whereWx);
                        $wxid = isset($wxUser['id']) ? $wxUser['id'] : 0;

                        $addUser = User::create([
                            'name' => $phone,
                            'username' => $username ? $username : $phone,
                            'email' => $email,
                            'phone' => $phone,
                            'identity_id' => User::USER_NORMAL,
                            'password' => bcrypt($pwd),
                            'wxid' => $wxid
                        ]);
                        $resID = isset($addUser['id']) ? $addUser['id'] : 0;
//                    $result['result'] = $resID;
                        Auth::login($addUser);
                        $result = $this->getSessUinfo();

                        if ($resID) {
                            $wxParam = array('userid' => $sessQyWxUserid, 'user_id' => $resID,'user_ticket'=>$sessQyWxUserTicket);
                            $wxRes = $this->common->getQyWxUserInfo($wxParam);
                            $wxid = isset($wxRes['id']) ? $wxRes['id'] : 0;
                            if ($wxid && !$userWxid && $userWxid != $wxid) {
                                $param = array('wxid' => $wxid);
                                $this->user->update($resID, $param);
                            }
                        }
                    }
                } else {
                    throw new ApiException(Code::ERR_WX_REGISTER_CLOSE);
                }

            }
        }
        Log::info('qyloginact:'.$result);
        return $result;
    }



    /**
     * 企业微信登录
     * @param $request
     * @return array
     * @throws ApiException
     */
    public function qyWxLoginNoPwdAct($request){
        $result = array();

        $sessQyWxUserid = session('qyWxUserid');//$request->input('phone');//
        $sessQyWxUserTicket = session('qyWxUserTicket');
        $sessQyWxUserMobile = session('qyWxUserMobile');
        $sessDb = session('db');
        $phone = $sessQyWxUserMobile;//$request->input('phone');//
        $resID = 0;
        $param = array(
            'user_ticket'=> $sessQyWxUserTicket,
            'userid' => $sessQyWxUserid,
            'phone' => $phone,
        );
//        var_dump($sessDb);exit;

        //多库环境下，切库
        $db = null;
        if(\ConstInc::MULTI_DB && $sessDb) {
            \DB::setDefaultConnection($sessDb); //切换到实际的数据库
        }

        if(!$phone) {
            //Todo 这里暂时不能切库需要做处理
//            $qyUser = $this->common->getQyWxUserInfo($param);
//            $phone = isset($qyUser['mobile'])?$qyUser['mobile']:'';
        }

//        var_dump($sessQyWxUserid,$sessQyWxUserTicket);exit;
        if($phone) {
            $orWhere = array('phone' => $phone, 'name' => $phone);
//            var_dump($orWhere);exit;
            //分库时第一次查询的是基础库(opf_commom)
            $user = $this->user->getByNamePhonew($orWhere);
            $userWxid = isset($user['wxid']) ? $user['wxid'] : 0;
            $resID = isset($user['id']) ? $user['id'] : 0;
            $orgId = $resID;
            $orgUser = $user;
//            var_dump($user->toArray());exit;
            if ($resID) {
                if($user->deleted_at){
                    throw new ApiException(Code::ERR_USER_DELETE);
                }
                $identity_id = isset($user['identity_id']) ? $user['identity_id'] : '';
                if (1 == $identity_id) {
                    throw new ApiException(Code::ERR_WXLOGIN_ADMIN);
                }

                //多库环境下，切库
                if (\ConstInc::MULTI_DB) {
                    $dbs = $this->common->getDbs();
                    $db = $dbs[0];
                    if ($sessDb != $db) {
                        \DB::setDefaultConnection($db); //切换到实际的数据库
                        //切库后再次查询当前用户所在的库
                        $user = $this->user->getByNamePhonew($orWhere);
                        $resID = isset($user['id']) ? $user['id'] : 0;
                        if($user->deleted_at){
                            throw new ApiException(Code::ERR_USER_DELETE);
                        }
                    }
                }

                $where2 = array('user_id' => $resID);
                $wxUser = $this->qywxuser->getOne($where2);
                $wxUserid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                if ($wxUserid != $sessQyWxUserid) {
                    throw new ApiException(Code::ERR_WX_LOGIN_ACCOUNT);
                }

                if (\ConstInc::MULTI_DB) {
                    if($orgUser) {
                        Auth::setUser($orgUser);
                    }
                    if (!empty($db) && !$sessDb) {
                        $request->session()->put(Auth::getName(), $orgId); //记录下全局的用户id
                        $request->session()->put("db", $db); //记录下全局的用户id
                    }
                }else{
                    $request->session()->put(Auth::getName(), $user->id); //记录下全局的用户id
                    Auth::setUser($user);
                }
                $result = $this->getSessUinfo();
            } else {
                $param['identity_id'] = 4;
                $this->common->qyRegUpdate($param);
                $orWhere = array('phone' => $phone, 'name' => $phone);
                $user = $this->user->getByNamePhonew($orWhere);
                //用户信息写入session
                if ($user) {
                    if (\ConstInc::MULTI_DB) {
                        if($orgUser) {
                            Auth::setUser($orgUser);
                        }
                        if (!empty($db) && !$sessDb) {
                            $request->session()->put(Auth::getName(), $orgId); //记录下全局的用户id
                            $request->session()->put("db", $db); //记录下全局的用户id
                        }
                    }else{
                        $request->session()->put(Auth::getName(), $user->id); //记录下全局的用户id
                        Auth::setUser($user);
                    }
                    $result = $this->getSessUinfo();
                } else {
                    throw new ApiException(Code::ERR_WX_LOGIN);
                }
            }
        }else{
            throw new ApiException(Code::ERR_WX_LOGIN);
        }

        return $result;
    }


    /**
     * 检查验证码是否有效
     * @param $phone
     * @param $phoneCode
     * @return array|bool
     */
    private function checkPhoneCode($phone,$phoneCode){
        $result = false;
        $date = date("Y-m-d H:i:s");
        $datetime = strtotime($date);
//        dd($res);exit;

        $where = array('phone' => $phone,'code' => $phoneCode);
        $res = $this->smsinfo->getOne($where);
        if (!$res) {
            $result = array('status' => 'error', 'msg' => '验证码无效！');
        } elseif ($res) {
            $createTime = isset($res['created_at']) ? strtotime($res['created_at']) : '';
            $tmptime = ($datetime - $createTime) / 60;
            $result = array('status' => 'success', 'msg' => 'ok');
            if ($tmptime > 10) {
                $result = array('status' => 'error', 'msg' => '验证码已过期');
            }

//            dd($tmptime);exit;
        }


        return $result;
    }

    public function register(){
        return view('home.register');
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function __registerAct(Request $request){

        $input = $request->post() ? $request->post() : array();
        $phone = isset($input['phone'])?$input['phone']:'';
        $username = isset($input['username'])?$input['username']:'';
        $email = isset($input['email'])?$input['email']:'';
        $pwd = isset($input['password'])?$input['password']:'';
//        var_dump($input);exit;

        if($phone && $pwd) {

            $user = User::create([
                'name' => $phone,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'identity_id' => User::USER_NORMAL,
                'password' => bcrypt($pwd),
            ]);
            $resID = isset($user['id']) ? $user['id'] : 0;
            $result['result'] = $resID;

            //保存数据到session
//            if($user){
//                $userInfo = array(
//                    'id' => $resID,
//                    'phone' => $phone
//                );
//                session()->put('userInfo',$userInfo);
//            }
            return $this->response->send($result);
        }else{
            throw new ApiException(Code::ERR_PARAMS,["手机号或密码不正确"]);
        }

    }




    /**
     * 显示忘记密码
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function forgotpwd(){
        return view('home.forgotpwd');
    }

    /**
     * 修改忘记密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotpwdAct(Request $request){
        $result = false;
        $result['result'] = false;

        $input = $request->post() ? $request->post() : array();
        $phone = isset($input['phone'])?$input['phone']:'';
        $pwd = isset($input['password'])?$input['password']:'';
        $phoneCode = isset($input['phone_code'])?$input['phone_code']:'';
        $lenNewPwd = strlen($pwd);

//        var_dump($input);exit;
        if(!$phone || !$pwd || !$phoneCode){
            throw new ApiException(Code::ERR_PARAMS, ["手机号、密码、验证码不能为空！"]);
        }elseif (!isMobile($phone)) {
            throw new ApiException(Code::ERR_PARAMS, ["手机号格式不正确"]);
        }elseif($lenNewPwd < 4 || $lenNewPwd > 20){
            throw new ApiException(Code::ERR_PARAMS, ["密码长度不能小于4或大于20个字符！"]);
        }else{
            $param['phone'] = $phone;
//            var_dump($input);exit;
            $user = $this->user->getOne($param);
            $uid= isset($user['id'])?$user['id']:'';
            if(!$user){
                throw new ApiException(Code::ERR_PARAMS, ["账号不存在，请检查后重试！"]);
            }
//            var_dump($user);exit;
            if (!isMobile($phone)) {
                throw new ApiException(Code::ERR_PARAMS, ["手机号格式不正确"]);
            }
            $checkPhone = $this->checkPhoneCode($phone, $phoneCode);
            $status = isset($checkPhone['status']) ? $checkPhone['status'] : '';
            $msg = isset($checkPhone['msg']) ? $checkPhone['msg'] : '';
            if ('success' != $status) {
                throw new ApiException(Code::ERR_PARAMS, [$msg]);
            }
            if($uid && $pwd){
                $param = array('password'=>bcrypt($pwd));
                $up = $this->user->update($uid,$param);
//                var_dump($up->id);exit;
                if(isset($up->id) && $up->id){
                    $result['result'] = true;
                }
            }


        }
        return $this->response->send($result);
    }


    public function getwxMedia(){
        $medidid = 'tpKV8-q6sOqoq9i3QyIO8MW9KneikLJAqibcuMleug8b8eYoD6aT1lkR_o7XmU8_';
        $this->wxjsskd->getMedia($medidid);
    }


    public function logout(){
        Auth::logout();
        session()->flush();
        return $this->response->send(array('result'=>true));
    }


    private function getSessUinfo(){
        $sessuInfo = Auth::user();//session('userInfo');
        $sessOpenid = session('wxOpenid');
        $qyWxUserid = session('qyWxUserid');
        $uInfo = array(
            'id' => isset($sessuInfo['id'])?$sessuInfo['id']:'',
            'role' => isset($sessuInfo['identity_id'])?$sessuInfo['identity_id']:'',
            'phone' => isset($sessuInfo['phone'])?$sessuInfo['phone']:'',
            'username' => isset($sessuInfo['username']) ? $sessuInfo['username'] : '',
            'openid' => $sessOpenid,
            'qywx_userid' => $qyWxUserid,
        );
        return $uInfo;
    }


    public function trackNoticeShow(Request $request){
        $input = $request->input() ? $request->input() : '';
        $eventId = isset($input['eventId']) ? $input['eventId'] : 0;
        $eventoaId = isset($input['eventoaId']) ? $input['eventoaId'] : 0;
        $sessuInfo = Auth::user();
        $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
//        var_dump($sessuInfo);exit;

        if (!$eventId && !$eventoaId) {
            header('Location:' . $this->hostName.'/error');exit;
            //throw new ApiException(Code::ERR_PARAM_EMPTY);
        }

        if(empty($sessuInfo)) { //多库情况下，不获取backUrl
            $backUrl = $request->getRequestUri();
            $url = $this->hostName.'/home/user/login?bkurl='.urlencode($backUrl);
            header('Location:' . $url);exit;
        }

        if($eventId) {
            $where = array();
            $where[] = ["id", "=", $eventId];
            $event = $this->events->getOne($where);
//            $event = $this->events->getById($eventId);
//            var_dump($event);exit;
            if (!$event) {
                header('Location:' . $this->hostName.'/error');exit;
            } else {
                $event = $event ? $event->toArray() : array();
                if ($event) {
                    $source = isset($event['source']) ? $event['source'] : '';
                    $state = isset($event['state']) ? $event['state'] : '';
                    $is_comment = isset($event['is_comment']) ? $event['is_comment'] : '';
                    $user_id = isset($event['user_id']) ? $event['user_id'] : '';
                    $report_id = isset($event['report_id']) ? $event['report_id'] : '';
                    $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
                    $category_id = isset($event['category_id']) ? $event['category_id'] : '';
                    $categoryArr = array(3, 4);
                    switch ($state) {
                        case "0" :
                            if (in_array($identity_id, array(4, 5))) {
                                $url =  '/eventsdetail/' . $eventId;
                            } else {
                                //3 == $source微信端或后台category_id是变更，维护并且$source != 3
                                if ((in_array($category_id, $categoryArr) && $source != 3) || in_array($source, [2, 3])) {
                                    $url =  '/eventsjiedan/' . $eventId;
                                } else {
                                    $url =  '/eventsdetail/' . $eventId;
                                }
                            }
                            break;
                        case "1" :
                            if (($sessuid != $user_id) || (in_array($identity_id, array(4, 5)))) {
                                $url =  '/eventsdetail/' . $eventId;
                            } else {
                                if ((in_array($category_id, $categoryArr) && $source != 3) || in_array($source, [2, 3])) {
                                    $url =  '/eventshandling/' . $eventId;
                                } else {
                                    $url =  '/eventsdetail/' . $eventId;
                                }
                            }
                            break;
                        case "2" :
                            if (($sessuid != $user_id) || (in_array($identity_id, array(4, 5)))) {
                                $url =  '/eventsdetail/' . $eventId;
                            } else {
                                if (3 == $category_id) {
                                    //变更
                                    $url =  '/eventsbiangeng/' . $eventId;
                                } elseif (4 == $category_id) {
                                    //维护
                                    $url =  '/eventsweihu/' . $eventId;
                                } elseif (5 == $category_id) {
                                    //下架
                                    $url =  '/eventsxiajia/' . $eventId;
                                } else {
                                    $url =  '/eventsdetail/' . $eventId;
                                }
                            }
                            break;
                        case "3" :
                            if (1 == $is_comment) {
                                if ((4 == $identity_id && $report_id == $sessuid) || in_array($identity_id,array(2,5))) {
                                    $url =  '/eventsreport2/' . $eventId;
                                } else {
                                    $url =  '/eventsdetail/' . $eventId;
                                }
                            } elseif (0 == $is_comment) {
                                if (in_array($identity_id, array(4, 5)) && $report_id == $sessuid) {
                                    $url =  '/eventsreport1/' . $eventId;
                                } else {
                                    $url =  '/eventsdetail/' . $eventId;
                                }
                            }
                            break;
                        case "4" :
                            $url =  '/eventsdetail/' . $eventId;
                            break;
                        default:
                            break;

                        //                    var_dump($event);exit;
                    }
                }
            }
        }elseif($eventoaId){
            $where = array();
            $where[] = ["id", "=", $eventoaId];
            $eventoa = $this->eventoa->getOne($where)->toArray();
            if (!$eventoa) {
                header('Location:' . $this->hostName.'/error');exit;
            }
            $state = isset($eventoa['state']) ? $eventoa['state'] : '';
            $is_comment = isset($eventoa['is_comment']) ? $eventoa['is_comment'] : '';
            $user_id = isset($eventoa['user_id']) ? $eventoa['user_id'] : '';
            $report_id = isset($eventoa['report_id']) ? $eventoa['report_id'] : '';
            $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
            switch ($state) {
                case "0" :
                    if (($user_id && $sessuid != $user_id) || (in_array($identity_id, array(4, 5)))) {
                        $url =  '/oaeventsdetail/' . $eventoaId;
                    } else {
                        $url =  '/oaeventsjiedan/' . $eventoaId;
                    }
                    break;
                case "1" :
                    if (($sessuid != $user_id) || (in_array($identity_id, array(4, 5)))) {
                        $url =  '/oaeventsdetail/' . $eventoaId;
                    } else {
                        $url =  '/oaeventsjiedan/' . $eventoaId;
                    }
                    break;
                case "2" :
                    if (($sessuid != $user_id) || (in_array($identity_id, array(4, 5)))) {
                        $url =  '/oaeventsdetail/' . $eventoaId;
                    } else {
                        $url =  '/oaeventshandling/' . $eventoaId;
                    }
                    break;
                case "3" :
                    if (1 == $is_comment) {
                        if ((4 == $identity_id && $report_id == $sessuid) || in_array($identity_id,array(2,5))) {
                            $url =  '/oaeventsreport2/' . $eventoaId;
                        } else {
                            $url =  '/oaeventsdetail/' . $eventoaId;
                        }
                    } elseif (0 == $is_comment) {
                        if (in_array($identity_id, array(4, 5)) && $report_id == $sessuid) {
                            $url =  '/oaeventsreport/' . $eventoaId;
                        } else {
                            $url =  '/oaeventsdetail/' . $eventoaId;
                        }
                    }
                    break;
                case "4" :
                    $url =  '/oaeventsdetail/' . $eventoaId;
                    break;
                default:
                    $url =  '/oaeventsdetail/' . $eventoaId;
                    break;

                //                    var_dump($event);exit;
            }

        }

        if(!$sessuInfo){
            $url = $this->hostName.'/home/user/login?bkurl='.urlencode($url);
            header('Location:' . $url);exit;
        }else {
//        var_dump($url);exit;
            header('Location:' . $this->hostName.$url);
        }
    }



    public function getTest(){
        $sessuInfo = Auth::user();
        $sessOpenid = session('wxOpenid');
        $qyWxUserid = session('qyWxUserid');
        return $this->response->send(array('result'=>$sessuInfo,$sessOpenid,$qyWxUserid));
    }








}



