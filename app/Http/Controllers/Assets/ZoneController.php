<?php
/**
 * 地区
 */

namespace App\Http\Controllers\Assets;

use App\Http\Requests\Assets\ZoneRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Assets\ZoneRepository;


class ZoneController extends Controller
{

    protected $zone;

    function __construct(ZoneRepository $zone)
    {
        $this->zone = $zone;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(ZoneRequest $request)
    {
        $search = $request->input("search");
        $where = null;
        if(!empty($search)) {
            $where[] = ['name', 'like', "%". $search . "%"];
        }
        $data = $this->zone->page($where);
        return $this->response->send($data);
    }

    public function getAll(ZoneRequest $request)
    {
        $data = $this->zone->all();

        return $this->response->send($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postAdd(ZoneRequest $request)
    {
        $input = $request->input();
        $this->zone->store($input);
        return $this->response->send();
    }

    /**
     * 删除
     * @param EngineroomRequest $request
     * @return mixed
     */

    public function postDel(ZoneRequest $request) {
        $result = $this->zone->getById($request->input('id'));
        if($this->zone->delete($request->input('id')) ) {
            userlog("删除了地区：".$result->name);
        }
        return $this->response->send();
    }



    /**
     * 编辑
     * @param EngineroomRequest $request
     */
    public function getEdit(ZoneRequest $request) {
        $data = $this->zone->getById($request->input("id"));
        return $this->response->send($data);
    }

    /**
     * 编辑
     * @param EngineroomRequest $request
     * @return mixed
     */
    public function postEdit(ZoneRequest $request)
    {
        $result = $this->zone->getById($request->input('id'));
        $input = $request->input();
        $this->zone->update($request->input("id"), $input);
        userlog("修改了地区：名称由 ".$result->name." 变更为 ".$request->input('name'));
        return $this->response->send();
    }
}
