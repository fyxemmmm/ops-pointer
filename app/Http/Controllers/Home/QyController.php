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
use App\Repositories\Weixin\QyWxUserRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Workflow\OaRepository;
use App\Models\Auth\User;
use App\Models\Code;
use App\Models\Home\SmsInfo;
use App\Exceptions\ApiException;
use App\Support\Response;
use App\Support\Submail;
use App\Support\WxJssdk;
use Auth;
use DB;
use App\Support\GValue;

class QyController extends Controller
{

    protected $common;
    protected $response;
    protected $user;
    protected $submail;
    protected $smsinfo;
    protected $qywxuser;
    protected $userInfo;
    protected $hostName;
    protected $events;
    protected $eventoa;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    function __construct(CommonRepository $common,
                         UserRepository $user,
                         SmsInfoRepository $smsinfo,
                         QyWxUserRepository $qywxuser,
                         EventsRepository $events,
                         Request $request,
                         OaRepository $eventoa)
    {
        $this->common = $common;
        $this->user = $user;
        $this->smsinfo = $smsinfo;
        $this->qywxuser = $qywxuser;
        $this->events = $events;

        $this->response = new Response();
        $this->submail = new Submail();
        $getAccessToken = $this->common->getAccessToken();
        $this->wxjsskd = new WxJssdk($getAccessToken);
        $this->hostName = 'http://'.$_SERVER['SERVER_NAME'];
        $this->eventoa = $eventoa;


    }

    public function index(Request $request){
        echo 'index';

    }


    public function getLogin(Request $request){

        //  http://operation.test/home/user/login
        // dump($request->url());exit;

       // dd($request->url());exit;//\Request::getRequestUri()
//        session()->forget('wxOpenid');

//        $wxInfo = $this->common->getWeixinSnsinfo($request);
//        $openid = isset($wxInfo['openid']) ? $wxInfo['openid'] : '';
        $user = $this->common->getQyUserdetail($request);
        $qyUserid = isset($user['userid']) ? $user['userid'] : '';

        $request->session()->put('qyUserid',$qyUserid);


//        var_dump($user);exit;



    }



    public function postSendMsg(Request $request){
        $input = $request->input();
        $date = date('Y-m-d');
        $desc = "<div class=\"gray\">$date</div>";
        $desc .= "<div >事件单号：111</div>";
        $desc .= "<div >发起时间：".date('Y-m-d H:i:s')."</div>";
        $desc .= "<div >当前状态：待处理</div>";
        $desc .= "<div >事件摘要：电脑故障电脑故障电脑故障电脑故障电脑故障电脑故障电脑故障电脑故障</div>";
//        $desc .= "<div class=\"highlight\">请于2016年10月10日前联系行政同事领取</div>";
        $input = array(
            'touser'=>array('JiMi'),
            'title' => "用户提交了一起新事件，点击查看",
            "desc" => $desc,
            'url'=> 'url'
        );
        $result = $this->common->qySendTextcard($input);

        return $this->response->send($result);
    }




    public function loginAct(Request $request){
        $result['result'] = '';

        $input = $request->post() ? $request->post() : array();
        $phone = isset($input['phone'])?$input['phone']:'';
        $pwd = isset($input['password'])?$input['password']:'';
        $phoneCode = isset($input['phone_code'])?$input['phone_code']:'';
        $lenNewPwd = strlen($pwd);

        $sessQyWxUserid = session('qyWxUserid');
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

                $where2 = array('user_id' => $resID);
                $qyWxUser = $this->qywxuser->getOne($where2);
                if(!$qyWxUser) {
                    $wxParam = array('userid' => $sessUserid, 'userid' => $resID);
                    $wxRes = $this->common->getWeixinUserInfo($wxParam);
                    $wxid = isset($wxRes['id']) ? $wxRes['id'] : 0;
                    if ($wxid && !$userWxid && $userWxid != $wxid) {
                        $param = array('wxid' => $wxid);
                        $this->user->update($resID, $param);
                    }
                }else {
                    $qyWxuserid = isset($qyWxUser['userid']) ? $qyWxUser['userid'] : '';
                    if ($qyWxuserid != $sessUserid) {
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

                $result['result'] = $this->getSessUinfo();
            }elseif(!$resID && !$phoneCode){
                if(!\ConstInc::WX_REGISTER) {
                    throw new ApiException(Code::ERR_WX_REGISTER_CLOSE);
                }
                $where2 = array('userid' => $sessUserid);
                if(\ConstInc::MULTI_DB && !empty($sessDb)) {
                    \DB::setDefaultConnection($sessDb); //切换到实际的数据库
                }
                $wxUser = $this->qywxuser->getOne($where2);
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
                        $whereWx = array('userid' => $sessUserid);
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
                        $result['result'] = $this->getSessUinfo();

                        if ($resID) {
                            $wxParam = array('userid' => $sessUserid, 'userid' => $resID);
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

        return $this->response->send($result);

    }







}



