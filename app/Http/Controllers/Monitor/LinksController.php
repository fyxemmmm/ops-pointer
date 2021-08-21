<?php
/**
 * 监控
 */

namespace App\Http\Controllers\Monitor;

use App\Http\Requests\Monitor\LinksRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Monitor\LinksRepository;

class LinksController extends Controller
{
    protected $links;

    function __construct(LinksRepository $links)
    {
        $this->links = $links;
    }


    public function postAdd(LinksRequest $request){
        $this->links->add($request);
        return $this->response->send();
    }

    public function getList(LinksRequest $request) {
        $data = $this->links->getList($request);
        return $this->response->send($data);
    }

    public function postDel(LinksRequest $request) {
        $this->links->delete($request);
        return $this->response->send();
    }

    public function getDeviceByEngineroom(LinksRequest $request) {
        $result['result'] = $this->links->getDeviceByEngineroom($request);
        return $this->response->send($result);
    }

    public function getEdit(LinksRequest $request) {
        $data = $this->links->getInfo($request);
        return $this->response->send($data);
    }

    public function postEdit(LinksRequest $request) {
        $this->links->edit($request);
        return $this->response->send();
    }

    public function postLineDel(LinksRequest $request){
        $this->links->remove_line($request);
        return $this->response->send();
    }

    // 设备解绑
    public function postUntie(LinksRequest $request){
        $this->links->untie($request);
        return $this->response->send();
    }


    /**
     * 更新所有未同步绑定线路
     * @return mixed
     */
    public function getUpdateUnbindLinks(){
        $this->links->updateUnbindLinkList();
        return $this->response->send();
    }

}
