<?php

namespace App\Http\Controllers\Auth;

use App\Models\Auth\User;
use App\Http\Controllers\Controller;
use App\Repositories\Auth\UserRepository;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\UserRequest;
use App\Models\Code;

class UserController extends Controller
{

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    protected $userRepository;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(UserRepository $userRepository)
    {
        //$this->middleware('guest');
        $this->userRepository = $userRepository;
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     */
    public function postCreate(UserRequest $request)
    {
        $this->userRepository->add($request);
        return $this->response->send();
    }

    public function postEdit(UserRequest $request) {
        $this->userRepository->edit($request);
        return $this->response->send();
    }

    public function getList(UserRequest $request) {
        $data = $this->userRepository->getList($request);
        return $this->response->send($data);
    }

    public function getEdit(UserRequest $request){
        $item = $this->userRepository->getEdit($request);
        return $this->response->send($item);
    }

    public function getUserEngineer(UserRequest $request) {
        $data = $this->userRepository->getUserEngineer($request);
        return $this->response->send($data);
    }

    public function postUserEngineer(UserRequest $request) {
        $this->userRepository->setUserEngineer($request);
        return $this->response->send();
    }

    public function postEditPasswd(UserRequest $request) {
        $this->userRepository->editPasswd($request);
        return $this->response->send();
    }

    public function postDel(UserRequest $request) {
        $id = $request->input("id");
        if($id == User::ADMIN_ID || $id == User::SYSADMIN_ID) {
            Code::setCode(Code::ERR_ADMIN_DEL);
        }
        else {
            $this->userRepository->del($id);
            userlog("删除了用户，用户ID: $id");
        }
        return $this->response->send();
    }
}
