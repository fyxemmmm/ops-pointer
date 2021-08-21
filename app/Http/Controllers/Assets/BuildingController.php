<?php
/**
 * 楼
 */

namespace App\Http\Controllers\Assets;

use App\Http\Requests\Assets\BuildingRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Assets\BuildingRepository;


class BuildingController extends Controller
{

    protected $building;

    function __construct(BuildingRepository $building)
    {
        $this->building = $building;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(BuildingRequest $request)
    {
        $zoneId = $request->input("zoneId");
        $search = $request->input("search");

        $where = null;
        if(!empty($zoneId)) {
            $where[] = ['zone_id', '=', $zoneId];
        }
        if(!empty($search)) {
            $where[] = ['name', 'like', "%". $search . "%"];
        }
        $data = $this->building->page($where);

        return $this->response->send($data);
    }

    public function getAll(BuildingRequest $request)
    {
        $zoneId = $request->input("zoneId");
        $where = null;
        if(!empty($zoneId)) {
            $where[] = ['zone_id', '=', $zoneId];
        }
        $data = $this->building->all($where);

        return $this->response->send($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postAdd(BuildingRequest $request)
    {
        $input = $request->input();
        $input["zone_id"] = $request->input("zoneId",0);
        $this->building->store($input);
        userlog("添加了楼：".$request->input("name"));
        return $this->response->send();
    }

    /**
     * 删除
     * @param EngineroomRequest $request
     * @return mixed
     */

    public function postDel(BuildingRequest $request) {
        $result = $this->building->getById($request->input('id'));
        if($this->building->delete($request->input('id')) ) {
            userlog("删除了楼：".$result->name);
        }
        return $this->response->send();
    }


    /**
     * 编辑
     * @param EngineroomRequest $request
     */
    public function getEdit(BuildingRequest $request) {
        $data = $this->building->getById($request->input("id"));
        return $this->response->send($data);
    }

    /**
     * 编辑
     * @param EngineroomRequest $request
     * @return mixed
     */
    public function postEdit(BuildingRequest $request)
    {
        $result = $this->building->getById($request->input('id'));
        $input = $request->input();
        $input["zone_id"] = $request->input("zoneId",0);
        $this->building->update($request->input("id"), $input);

        $log = "";
        if($result->name != $request->input('name')) {
            $log .= "名称由 ".$result->name." 变更为 ".$request->input('name');
        }

        if($result->zone_id != $request->input('zoneId')) {
            $log .= "地区id由 ".$result->zone_id." 变更为 ".$request->input('zoneId');
        }

        if(!empty($log)) {
            userlog("修改了楼信息：$log");
        }

        return $this->response->send();
    }






}
