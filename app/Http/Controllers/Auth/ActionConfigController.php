<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActionConfigRequest;
use App\Repositories\Auth\ActionConfigRepository;

class ActionConfigController extends Controller
{
    protected $actionConfigRepository;

    function __construct(ActionConfigRepository $actionConfigRepository)
    {
        $this->actionConfigRepository = $actionConfigRepository;
    }

    /**
     * 菜单列表
     * @param  ActionConfigRequest $request [description]
     * @return [json]                       [description]
     */
    public function getList(ActionConfigRequest $request)
    {
        $data = $this->actionConfigRepository->getList($request);
        return $this->response->send($data);
    }

    /**
     * 修改功能数据提交之后
     * @param  ActionConfigRequest $request [description]
     * @return [type]                       [description]
     */
    public function postEdit(ActionConfigRequest $request)
    {
        $data = $this->actionConfigRepository->edit($request);
        return $this->response->send();
    }

    // 新增 / 编辑
    public function postAdd(ActionConfigRequest $request){
        $this->actionConfigRepository->addFun($request);
        return $this->response->send();
    }

    public function postDel(ActionConfigRequest $request){
        $this->actionConfigRepository->delFun($request->input('id'));
        return $this->response->send();
    }

}
