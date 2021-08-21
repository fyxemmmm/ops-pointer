<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MenuRequest;
use App\Repositories\Auth\MenuRepository;


class MenuController extends Controller
{

    protected $menuRepository;

    function __construct(MenuRepository $menuRepository){
        $this->menuRepository = $menuRepository;
    }


    /**
     * 菜单列表
     * @param  MenuRequest $request [description]
     * @return [type]               [description]
     */
    public function getList(MenuRequest $request)
    {
        $data = $this->menuRepository->getList($request);
        $menu = $this->menuRepository->getMenu();
        $this->response->addMeta(["menu" => $menu]);
        return $this->response->send($data);
    }

    /**
     * 添加
     * @param  MenuRequest $request [description]
     * @return [type]               [description]
     */
    public function postAdd(MenuRequest $request)
    {
        $data = $this->menuRepository->add($request);
        return $this->response->send($data);
    }

    /**
     * 编辑显示页面
     * @param  MenuRequest $request [description]
     * @return [type]               [description]
     */
    public function getEdit(MenuRequest $request)
    {
        $data = $this->menuRepository->view($request);
        return $this->response->send($data);
    }

    /**
     * 编辑页面提交数据
     * @param  FieldsRequest $request [description]
     * @return [type]                 [description]
     */
    public function postEdit(MenuRequest $request) {
        $this->menuRepository->edit($request);
        return $this->response->send();
    }

    /**
     * 删除
     * @param  MenuRequest $request [description]
     * @return [type]               [description]
     */
    public function postDel(MenuRequest $request) {
        $data = $this->menuRepository->delete($request);
        return $this->response->send();
    }





}
