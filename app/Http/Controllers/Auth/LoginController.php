<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Repositories\Monitor\HengweiRepository;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use App\Repositories\Auth\UserRepository;
use App\Models\Auth\User;
use App\Models\Code;
use Auth;
use App\Repositories\Auth\ActionConfigRepository;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    public $userRepository;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';
    protected $hengweiRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct(UserRepository $userRepository,
                                HengweiRepository $hengweiRepository,
                                ActionConfigRepository $actionConfigRepository)
    {
        $this->userRepository = $userRepository;
        $this->hengweiRepository = $hengweiRepository;
        $this->actionConfigRepository = $actionConfigRepository;
    }

    protected function username() {
        return "name";
    }

    protected function validateLogin(Request $request)
    {

        $this->validate($request, [
            'name' => 'required|string',
            'password' => 'required|string',
            ],
            [
                "name.required" => "请填写账户",
                "password.required" => "请填写密码"
            ]);
    }

    public function postLogin(Request $request) {
        $user = $this->userRepository->getUserByName($request->input($this->username()));
        if(empty($user)) {
            $user = $this->userRepository->getUserByPhone($request->input($this->username()));
        }
        $identity_id = isset($user['identity_id']) ? $user['identity_id'] :'';
        $id = isset($user['id']) ? $user['id'] :'';

        if($identity_id === User::USER_NORMAL) {
            Code::setCode(Code::ERR_NORMAL_LOGIN);
            return $this->response->send();
        }

        $db = null;
        if(\ConstInc::MULTI_DB && isset($user['db']) && !empty($user['db'])) {
            $db = $user->db;
            \DB::setDefaultConnection($db); //切换到实际的数据库
            $user = $this->userRepository->getUserByName($request->input($this->username()));
            if(empty($user)) {
                $user = $this->userRepository->getUserByPhone($request->input($this->username()));
            }
        }

        $this->login($request);
        $hwData = array('monitorHW'=>array());

        //登录恒维监控，获取token
        if(\ConstInc::$mOpen) {
            $this->hengweiRepository->getHWToken(true);
        }
        /*
         * todo 密码到期修改
        if(!$this->userRepository->checkPwdUpdated($user->id)) {
            Code::setCode(Code::ERR_PASSWD_EXPIRE);
            return $this->response->send();
        }
        */

        if(!empty($db)) {
            //$request->session()->put("db", $db);
            $request->session()->put(Auth::getName(), $id); //记录下全局的用户id
        }

        $this->response->setMenu();
        $this->response->setUser();

        // 按钮配置数据格式处理
        $actionConfigList = $this->actionConfigRepository->getList($request);
        $configArray = [];
        foreach ($actionConfigList as $row) {
            $configArray[$row['key']] = $row['status'] ? TRUE : FALSE;
        }

        $this->response->addMeta(["actionConfigList" => $configArray]);
        userlog("登陆了后台管理系统");
        return $this->response->send();
    }

    protected function attemptLogin(Request $request)
    {
        $name = $request->input($this->username());
        $pwd = $request->input("password");
        $flag = false;
        if($this->guard()->attempt(["name" => $name , "password" => $pwd], $request->filled('remember'))){
            $flag = true;
        }elseif($this->guard()->attempt(["phone" => $name, "password" => $pwd], $request->filled('remember'))){
            $flag = true;
        }
        return $flag;
    }

    public function postLogout(Request $request) {
        userlog("登出了后台管理系统");
        $this->guard()->logout();

        $request->session()->invalidate();
        return $this->response->send();
    }

}
