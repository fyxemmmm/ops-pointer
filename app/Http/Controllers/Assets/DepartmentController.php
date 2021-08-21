<?php
/**
 * 科室
 */

namespace App\Http\Controllers\Assets;

use App\Http\Requests\Assets\DepartmentRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Assets\BuildingRepository;
use App\Repositories\Assets\DepartmentRepository;
use App\Models\Code;

class DepartmentController extends Controller
{

    protected $model;
    protected $building;

    function __construct(DepartmentRepository $model, BuildingRepository $buildingRepository)
    {
        $this->model = $model;
        $this->building = $buildingRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(DepartmentRequest $request)
    {
        $search = $request->input("search");
        $buildingId = $request->input("buildingId");
        $zoneId = $request->input("zoneId");

        $where = null;
        if(!empty($zoneId)) {
            $where[] = ['zone_id', '=', $zoneId];
        }
        if(!empty($buildingId)) {
            $where[] = ['building_id', '=', $buildingId];
        }
        if(!empty($search)) {
            $where[] = ['name', 'like', "%". $search . "%"];
        }
        $data = $this->model->page($where);

        return $this->response->send($data);
    }

    public function getAll(DepartmentRequest $request)
    {
        $zoneId = $request->input("zoneId");
        $buildingId = $request->input("buildingId");
        $where = null;
        if(!empty($zoneId)) {
            $where[] = ['zone_id', '=', $zoneId];
        }
        if(!empty($buildingId)) {
            $where[] = ['building_id', '=', $buildingId];
        }
        $data = $this->model->all($where);

        return $this->response->send($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postAdd(DepartmentRequest $request)
    {
        $buildingId = $request->input("buildingId",0);
        if(empty($buildingId)) {
            $buildingId = 0;
        }
        $zoneId = $request->input("zoneId",0);
        if(!empty($buildingId) && !$this->building->checkBuildingZone($buildingId, $zoneId)) {
            Code::setCode(Code::ERR_PARAMS,null, ["buildingId"]);
            return $this->response->send();
        }

        $input = $request->input();
        $input['building_id'] = $buildingId;
        $input['zone_id'] = $zoneId;
        $this->model->store($input);
        userlog("添加了科室：".$request->input("name"));
        return $this->response->send();
    }

    /**
     * 删除
     * @param DepartmentRequest $request
     * @return mixed
     */
    public function postDel(DepartmentRequest $request) {
        $result = $this->model->getById($request->input('id'));
        //todo 判断资产关联
        $this->model->del($request->input('id'));
        userlog("删除了科室：".$result->name);
        return $this->response->send();
    }



    /**
     * 编辑
     * @param DepartmentRequest $request
     */
    public function getEdit(DepartmentRequest $request) {
        $data = $this->model->getById($request->input("id"));
        return $this->response->send($data);
    }

    /**
     * 编辑
     * @param DepartmentRequest $request
     * @return mixed
     */
    public function postEdit(DepartmentRequest $request)
    {
        $buildingId = $request->input("buildingId",0);
        if(empty($buildingId)) {
            $buildingId = 0;
        }
        $zoneId = $request->input("zoneId",0);
        if(!empty($buildingId) && !$this->building->checkBuildingZone($buildingId, $zoneId)) {
            Code::setCode(Code::ERR_PARAMS,null, ["buildingId"]);
            return $this->response->send();
        }

        $result = $this->model->getById($request->input('id'));

        $input = $request->input();
        $input['building_id'] = $buildingId;
        $input['zone_id'] = $zoneId;
        $this->model->update($request->input("id"), $input);

        $log = "";
        if($result->name != $request->input('name')) {
            $log .= "名称由 ".$result->name." 变更为 ".$request->input('name');
        }

        if($result->zone_id != $request->input('zoneId')) {
            $log .= "地区id由 ".$result->zone_id." 变更为 ".$request->input('zoneId');
        }
        if($result->building_id != $request->input('buildingId')) {
            $log .= "楼id由 ".$result->building_id." 变更为 ".$request->input('buildingId');
        }

        if(!empty($log)) {
            userlog("修改了科室信息：$log");
        }

        return $this->response->send();
    }
}
