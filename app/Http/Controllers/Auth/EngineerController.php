<?php
/**
 * 机房
 */

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EngineerRequest;
use App\Repositories\Auth\UserRepository;

class EngineerController extends Controller
{

    protected $userRepository;

    function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(EngineerRequest $request)
    {
        $data = $this->userRepository->getEngineerList($request);

        return $this->response->send($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postAdd(EngineerRequest $request)
    {
        $this->userRepository->addEngineer($request);
        return $this->response->send();
    }

    /**
     * 删除
     * @param EngineroomRequest $request
     * @return mixed
     */

    public function postDel(EngineerRequest $request) {
        $this->userRepository->delEngineer($request->input('id'));
        return $this->response->send();
    }

    /**
     * 编辑
     * @param EngineroomRequest $request
     */
    public function getEdit(EngineerRequest $request) {
        $data = $this->userRepository->getEngineer($request->input("id"));
        return $this->response->send($data);
    }

    /**
     *
     *
     * 编辑
     * @param EngineroomRequest $request
     * @return mixed
     */
    public function postEdit(EngineerRequest $request)
    {
        $this->userRepository->editEngineer($request);
        return $this->response->send();
    }

    public function getCategory(EngineerRequest $request) {
        $data = $this->userRepository->getEngineerCategory($request);
        return $this->response->send($data);
    }

    public function postCategory(EngineerRequest $request) {
        $this->userRepository->setEngineerCategory($request);
        return $this->response->send();
    }


    /**
     * 获取工程师和工程师主管
     * @return mixed
     */
    public function getusers(){
        $res = $this->userRepository->getEngineers();
        return $this->response->send($res);
    }

}
