<?php
/**
 * 我的信息
 */

namespace App\Http\Controllers\Home;
//namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Weixin\CommonRepository;
use App\Repositories\Weixin\SmsInfoRepository;
use App\Repositories\Auth\UserRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Weixin\FeedbackRepository;
use App\Models\Auth\User;
use App\Models\Code;
use App\Models\Home\SmsInfo;
use App\Exceptions\ApiException;
use App\Support\Response;
use App\Support\Submail;
use Auth;
use App\Repositories\Auth\ActionConfigRepository;
use App\Http\Requests\Auth\ActionConfigRequest;

class MyInfoController extends Controller
{

    protected $common;
    protected $response;
    protected $user;
    protected $submail;
    protected $smsinfo;
    protected $weixinuser;
    protected $feedback;
    protected $actionconfig;

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
                         FeedbackRepository $feedback,
                         ActionConfigRepository $actionconfig
    ){
        $this->common = $common;
        $this->user = $user;
        $this->smsinfo = $smsinfo;
        $this->weixinuser = $weixinuser;
        $this->feedback = $feedback;
        $this->actionconfig = $actionconfig;

        $this->response = new Response();
        $this->submail = new Submail();


    }

    public function index(){
        $result = array('result' => '');
        $uObj = Auth::user();
        if ($uObj) {
            $info = array(
                'name' => $uObj->name,
                'email' => $uObj->email,
            );
            $this->response->setUser($info);
        }
        return $this->response->send($result);
    }


    /**
     * 显示个人信息
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function modifyInfo(){
        $result = array();
        $uObj = Auth::user();
        $uid = isset($uObj->id)?$uObj->id:0;
        if(!$uid){
            throw new ApiException(Code::ERR_HTTP_UNAUTHORIZED);
        }

        $user = $this->user->getById($uid);
        if($user){
            $result = array(
                'username' => isset($user->username)?$user->username:'',
                'email' => isset($user->email)?$user->email:'',
                'telephone' => isset($user->telephone)?$user->telephone:'',
            );
        }
        return $this->response->send($result);
    }


    /**
     * 修改个人信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function modifyInfoAct(Request $request){
        $result = array('result'=>false);
        $input = $request->post() ? $request->post() : array();
        $uObj = Auth::user();
        $uid = isset($uObj->id)?$uObj->id:0;
        if(!$uid){
            throw new ApiException(Code::ERR_HTTP_UNAUTHORIZED);
        }
        $email = isset($input['email']) ? $input['email']:'';
        $param = array(
            'username' => isset($input['username']) ? $input['username']:'',
            'email' => $email,
            'telephone' => isset($input['telephone']) ? $input['telephone'] : '',
        );
        $upRes = $this->user->update($uid,$param);
        if($upRes){
            $result = array('result'=>true);
        }
        return $this->response->send($result);
    }


    public function modifyPwd(){
        return view('home.reset');
    }


    /**
     * 保存修改密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function modifyPwdAct(Request $request){
        $input = $request->post() ? $request->post() : array();
        $result['result'] = false;
        $sUserinfo = Auth::user();//session('userInfo');
        $uid = isset($input['id'])?$input['id']:0;
        $suid = isset($sUserinfo['id'])?$sUserinfo['id']:0;
        $uid = $uid?$uid:$suid;
        if($uid) {
            $param['id'] = $uid;
//            var_dump($input);exit;
            $user = $this->user->getOne($param);
//            var_dump($user);exit;
            $oldPwd = isset($input['old']) ? $input['old'] : '';
            $newPwd = isset($input['new']) ? $input['new'] : '';
            $confirmPwd = isset($input['confirm']) ? $input['confirm'] : '';
            $lenNewPwd = strlen($newPwd);
//            var_dump($newPwd, $confirmPwd);exit;
            if (!password_verify($oldPwd, $user->password)) {
                throw new ApiException(Code::ERR_PARAMS, ["旧密码不正确！"]);
            } elseif($lenNewPwd < 4 || $lenNewPwd > 20){
                throw new ApiException(Code::ERR_PARAMS, ["密码长度不能小于4或大于20个字符！"]);
            }elseif($newPwd && strcmp($newPwd, $confirmPwd)!=0 ){//$newPwd != $confirmPwd
                throw new ApiException(Code::ERR_PARAMS, ["两次密码不一致！"]);
            }
            if($uid && $newPwd){
                $param = array('password'=>bcrypt($newPwd));
                $up = $this->user->update($uid,$param);
//                var_dump($up->id);exit;
                if(isset($up->id) && $up->id){
                    $result['result'] = true;
                }
            }
        }
        return $this->response->send($result);
    }


    /**
     * 意见反馈
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function feedbackAct(Request $request){
        $input = $request->input() ? $request->input() : '';
        $content = isset($input['content']) ? trim($input['content']) : '';

        if(!$content){
            throw new ApiException(Code::ERR_CONTENT_EMPTY);
        }
        $uObj = Auth::user();
        $uid = isset($uObj->id)?$uObj->id:0;
        if(!$uid){
            throw new ApiException(Code::ERR_HTTP_UNAUTHORIZED);
        }
        $date = date('Y-m-d');
        $start = $date.' 00:00:00';
        $end = $date.' 24:00:00';
        $where = array('user_id'=>$uid);
        $where[] = ["created_at",">=",$start];
        $where[] = ["created_at","<=",$end];
        $count = $this->feedback->getCount($where);
        if($count >= 3){
            throw new ApiException(Code::ERR_FEEDBACK_UPPER_LIMIT);
        }
        $input['user_id'] = $uid;
        $fbAdd = $this->feedback->add($input);
        $result = array('result'=>$fbAdd);
        return $this->response->send($result);
    }

    /**
     * 微信端按钮配置状态列表
     * @param ActionConfigRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function actionConfigList(ActionConfigRequest $request){

        if(NULL === $request->input('type')){
            // 微信端按钮类型为 2
            $request['type'] = 2;
        }

        $result = $this->actionconfig->getList($request);
        $data = [];
        foreach ($result as $value){
            $data[$value['key']] = $value['status'] ? TRUE : FALSE;
        }
        return $this->response->send($data);
    }













}



