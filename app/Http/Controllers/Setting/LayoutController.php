<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/11
 * Time: 13:56
 */

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Repositories\Setting\LayoutRepository;
use App\Http\Requests\Setting\LayoutRequest;

class LayoutController extends Controller
{

    protected $layoutRepository;

    function __construct(LayoutRepository $layoutRepository)
    {
        $this->layoutRepository = $layoutRepository;
    }

    public function getList(){
        $data = $this->layoutRepository->getList();
        return $this->response->send($data);
    }

    public function getDetail(LayoutRequest $request){
        $data = $this->layoutRepository->getDetail($request);
        return $this->response->send($data);
    }

    public function postAdd(LayoutRequest $request){
        $this->layoutRepository->save($request);
        return $this->response->send();
    }

    public function postEdit(LayoutRequest $request){
        $this->layoutRepository->edit($request);
        return $this->response->send();
    }

    public function postDel(LayoutRequest $request){
        $this->layoutRepository->delete($request);
        return $this->response->send();
    }
// 前台传一个id过来，把这条数据设置为默认的
    public function postSetDefault(LayoutRequest $request){
        $this->layoutRepository->setDefault($request);
        return $this->response->send();
    }

}
