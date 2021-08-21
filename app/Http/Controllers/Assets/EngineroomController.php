<?php
/**
 * 机房
 */

namespace App\Http\Controllers\Assets;

use App\Http\Requests\Assets\EngineroomRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Assets\BuildingRepository;
use App\Repositories\Assets\EngineroomRepository;
use App\Models\Code;

class EngineroomController extends Controller
{

    protected $engineroom;
    protected $building;

    function __construct(EngineroomRepository $engineroom, BuildingRepository $buildingRepository)
    {
        $this->engineroom = $engineroom;
        $this->building = $buildingRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(EngineroomRequest $request)
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
        $data = $this->engineroom->page($where);

        return $this->response->send($data);
    }

    public function getAll(EngineroomRequest $request)
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
        $data = $this->engineroom->all($where);

        return $this->response->send($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postAdd(EngineroomRequest $request)
    {
        $type = NULL !== $request->input("type") ? intval($request->input("type")) : 0;
        $address = NULL !== $request->input("address") ? trim($request->input("address")) : '';
        $buildingId = $request->input("buildingId",0);
        $zoneId = $request->input("zoneId",0);
        if(empty($buildingId)) {
            $buildingId = 0;
        }
        if(!empty($buildingId) && !$this->building->checkBuildingZone($buildingId, $zoneId)) {
            Code::setCode(Code::ERR_PARAMS,null, ["buildingId"]);
            return $this->response->send();
        }

        if($type <= 0){
            Code::setCode(Code::ERR_PARAMS,null, ["type"]);
            return $this->response->send();
        }

        $input = $request->input();
        $input['building_id'] = $buildingId;
        $input['zone_id'] = $zoneId;
        $input['type'] = $type;
        $input['address'] = $address;
        $this->engineroom->store($input);
        userlog("添加了机房：".$request->input("name"));
        return $this->response->send();
    }

    /**
     * 删除
     * @param EngineroomRequest $request
     * @return mixed
     */
    public function postDel(EngineroomRequest $request) {
        $result = $this->engineroom->getById($request->input('id'));
        //todo 判断资产关联
        $this->engineroom->del($request->input('id'));
        userlog("删除了机房：".$result->name);
        return $this->response->send();
    }



    /**
     * 编辑
     * @param EngineroomRequest $request
     */
    public function getEdit(EngineroomRequest $request) {
        $data = $this->engineroom->getById($request->input("id"));
        return $this->response->send($data);
    }

    /**
     * 编辑
     * @param EngineroomRequest $request
     * @return mixed
     */
    public function postEdit(EngineroomRequest $request)
    {
        $type = NULL !== $request->input("type") ? intval($request->input("type")) : 0;
        $address = NULL !== $request->input("address") ? trim($request->input("address")) : '';
        $buildingId = $request->input("buildingId",0);
        $zoneId = $request->input("zoneId",0);
        if(empty($buildingId)) {
            $buildingId = 0;
        }
        if(!empty($buildingId) && !$this->building->checkBuildingZone($buildingId, $zoneId)) {
            Code::setCode(Code::ERR_PARAMS,null, ["buildingId"]);
            return $this->response->send();
        }

        if($type <= 0){
            Code::setCode(Code::ERR_PARAMS,null, ["type"]);
            return $this->response->send();
        }

        $result = $this->engineroom->getById($request->input('id'));

        $input = $request->input();
        $input['building_id'] = $buildingId;
        $input['zone_id'] = $zoneId;
        $input['type'] = $type;
        $input['address'] = $address;
        $this->engineroom->update($request->input("id"), $input);

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
        if($result->type != $request->input('type')) {
            $log .= "楼type由 ".$result->type." 变更为 ".$request->input('type');
        }
        if($result->address != $request->input('address')) {
            $log .= "楼address由 ".$result->address." 变更为 ".$request->input('address');
        }

        if(!empty($log)) {
            userlog("修改了机房信息：$log");
        }

        return $this->response->send();
    }


    /**
     * 获取首页机房核心设备信息
     *
     * @param EngineroomRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getCoreAssetsMsgForHome()
    {
        $data = $this->engineroom->mergeCoreMsgToAllData();
        return $this->response->send($data);
    }

    /**
     * 地图首页机房配置列表
     * @return mixed
     */
    public function getListForHome()
    {
        $data = $this->engineroom->getListForHome();
        return $this->response->send($data);
    }




}
