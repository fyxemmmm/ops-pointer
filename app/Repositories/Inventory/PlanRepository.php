<?php

namespace App\Repositories\Inventory;

use App\Repositories\BaseRepository;
use App\Models\Inventory\Plan;
use App\Models\Code;
use DB;
use App\Exceptions\ApiException;
use App\Repositories\Assets\DeviceRepository;
use App\Models\Assets\Device;
use App\Models\Inventory\InventoryAsset;


class PlanRepository extends BaseRepository
{

    protected $model;
    protected $deviceRepository;
    protected $deviceModel;
    protected $inventoryAssetModel;

    public function __construct(Plan $planModel,
                                DeviceRepository $deviceRepository,
                                Device $deviceModel,
                                InventoryAsset $inventoryAssetModel)
    {
        $this->model = $planModel;
        $this->deviceRepository = $deviceRepository;
        $this->deviceModel = $deviceModel;
        $this->inventoryAssetModel = $inventoryAssetModel;
    }


    /**
     * 盘点计划列表(状态为未开始 status = 0)
     * @param $request
     * @return mixed
     */
    public function getList($request){

        $search = $request->input("search");

        $model = $this->model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("number", "like", "%" . $search . "%" );
                $query->orWhere("name", "like", "%" . $search . "%");
            }
        });

        return $this->usePage($model);
    }


    /**
     * 盘点计划删除
     * @param $request
     * @return bool|mixed
     * @throws ApiException
     */
    public function postDel($request){

        if(!$request->filled('id')) throw new ApiException(Code::ERR_PARAMS, ['id']);

        $id = $request->post('id');

        $this->del($id);
    }


    /**
     * 盘点计划盘点或者废弃
     * @param $request
     * @return bool
     * @throws ApiException
     */
    public function getPointOrScrap($request){

        if(!$request->filled('id')) throw new ApiException(Code::ERR_PARAMS, ['id']);

        $data = $this->getParams($request);
        $update = [
            'status' => $data['status']
        ];

        // 开始盘点更新开始盘点时间
        if(Plan::STATE_DOING == $data['status']){
            $update['start_time'] = date('Y-m-d H:i:s');
        }

        $item = $this->model->where('id',$data['id'])->first();
        if(empty($item)) throw new ApiException(Code::ERR_MODEL, ["数据不存在"]);

        $this->model->where('id',$data['id'])->update($update);
    }

    /**
     * 新增盘点计划
     * @param $request
     */
    public function add($request){
        $data = $this->getParams($request);

        $data['number'] = date('YmdHis');

        if(!isset($data['asset_id'])){
            throw new ApiException(Code::ERR_PARAMS, ["资产 id 不能为空"]);
        }else{
            $asset_ids = array_unique($data['asset_id']);
        }

        DB::beginTransaction();
        $item = $this->store($data);
        $arr = [];
        if(is_array($asset_ids)){
            foreach ($asset_ids as $asset_id){
                $arr[] = [
                    'inventory_id' => $item->id,
                    'asset_id' => $asset_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }else{
            throw new ApiException(Code::ERR_PARAMS, ["数据参数有误"]);
        }
        $this->inventoryAssetModel->insert($arr);
        DB::commit();

        userlog("添加了新盘点计划".$data['name']);
    }

    /**
     * 得到一条盘点计划
     * @param $request
     * @return array
     * @throws ApiException
     */
    public function getDetail($request){
        $data = $this->getParams($request);

        $item = $this->model->findOrFail($data['id'])->toArray();
        if(empty($item)){
            throw new ApiException(Code::ERR_MODEL, ["无数据"]);
        }else{
            if(is_array($item)){
                $item['location_flag'] = isset($item['location_flag']) ? json_decode($item['location_flag'],true) : array();
            }

            if(isset($data['atype'])){
                if($data['atype']){
                    $item['asset_id'] = $this->inventoryAssetModel->where('inventory_id',$data['id'])->pluck('asset_id')->toArray();
                }
            }
        }
        return $item;
    }



    /**
     * 盘点资产详情展示页面
     * @param $request
     * @return array
     * @throws ApiException
     */
    public function getDetailsAssetList($request){

        $id = $request->query('id',0);
        $item = $this->model->findOrFail($id);
        if(empty($item)) throw new ApiException(Code::ERR_MODEL, ["无数据"]);

        $tbl = 'inventory_asset';
        $select = [
            $tbl.'.inventory_id',
            $tbl.'.asset_id',
            $tbl.'.result',
            $tbl.'.operation_type',
            'B.id AS assets_device_id',
            'B.sub_category_id',
            'B.number',
            'B.name',
            'B.location',
            'B.officeBuilding',
            'B.area',
            'B.department',
            'B.rack',
            'B.rack_pos',
            'C.id AS category_id',
            'C.name AS assets_category',
            'D.id AS zone_id',
            'D.name AS zone',
            'E.id AS building_id',
            'E.name AS office_building',
            'F.id AS engineroom_id',
            'F.name AS engineroom_name',
            'G.id AS department_id',
            'G.name AS department_name',
        ];

        $model = $this->inventoryAssetModel->select($select)
                        ->join('assets_device AS B',$tbl.'.asset_id','=','B.id')
                        ->leftJoin('assets_category AS C','B.sub_category_id','=','C.id')
                        ->leftJoin('assets_zone AS D','D.id','=','B.location')
                        ->leftJoin('assets_building AS E','E.id','=','B.officeBuilding')
                        ->leftJoin('assets_enginerooms AS F','F.id','=','B.area')
                        ->leftJoin('assets_department AS G','G.id','=','B.department')
                        ->where($tbl.'.inventory_id',$id);

        return $this->usePage($model,$tbl.'.asset_id');

    }

    /**
     * 盘点计划编辑
     * @param $request
     * @return mixed
     */
    public function edit($request){

        $data = $this->getParams($request);

        DB::beginTransaction();
        // 更新盘点计划数据
        $res = $this->update($data['id'],$data);
        $where = [];
        $where[] = ['inventory_id','=',$data['id']];
        $item = $this->inventoryAssetModel->where($where)->first();
        if(!empty($item)){
            // 删除盘点资产的数据
            $this->inventoryAssetModel->where($where)->delete();
        }

        if(isset($data['asset_id'])){
            $asset_ids = is_array($data['asset_id']) ? array_unique($data['asset_id']) : array();
            if($asset_ids){
                $arr = [];
                foreach ($asset_ids as $asset_id){
                    $arr[] = [
                        'inventory_id' => $data['id'],
                        'asset_id' => $asset_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                }
                $this->inventoryAssetModel->insert($arr);
            }else{
                throw new ApiException(Code::ERR_PARAMS, ["资产参数不能为空"]);
            }
        }

        DB::commit();
        userlog("修改了盘点计划".$data['id']);
        return $res;
    }


    /**
     * 修改盘点资产结果
     * @param $request
     * @throws ApiException
     */
    public function postEditResult($request){
        $input = $request->post();

        if(!isset($input['inventory_id']) || !isset($input['asset_id']) || !isset($input['result'])){
            throw new ApiException(Code::ERR_PARAMS, ["缺少必要参数"]);
        }else{
            $inventoryId = intval($input['inventory_id']);
            $assetId = intval($input['asset_id']);
            $result = intval($input['result']);
            $where = [];
            $where[] = ['inventory_id','=',$inventoryId];
            $where[] = ['asset_id','=',$assetId];
            $item = $this->inventoryAssetModel->where($where)->first();

            if(empty($item)){
                throw new ApiException(Code::ERR_PARAMS, ["数据参数有误"]);
            }else{
                // 更新数据
                $update = ['result' => $result, 'operation_type' => 1];
                $this->inventoryAssetModel->where($where)->update($update);
                userlog("修改了盘点资产的状态，将资产id为".$assetId."的结果修改为".$result);
            }
        }

    }

    /**
     * 获取提交参数
     * @param $request
     * @return array
     */
    public function getParams($request){
        $param = $request->input();
        $paramName = ['id', 'number', 'name', 'user', 'desc', 'status', 'plan_time', 'result', 'asset_id', 'location_flag', 'atype'];
        $data = [];
        foreach ($paramName as $field){
            // 对必填参数做数据处理
            if(isset($param[$field])){
                $val = $param[$field];
                switch ($field){
                    case 'id':
                    case 'status':
                    case 'atype':
                        $data[$field] = intval($val) >= 0 ? intval($val) : 0;
                        break;
                    case 'number':
                    case 'name':
                    case 'user':
                    case 'desc':
                        $data[$field] = trim($val);
                        break;
                    case 'plan_time':
                        $data[$field] = date('Y-m-d H:i:s',strtotime($val));
                        break;
                    case 'location_flag':
                        $data[$field] = json_encode($val);
                        break;
                    default:
                        $data[$field] = $val;
                        break;
                }
            }

        }

        // 返回提交的参数
        return array_merge($param,$data);
    }


    /**
     * 选择机房或者科室
     * @return array
     */
    public function getChoiceEnginerooms(){

        $engineroomsFlag = ['type' => 'er'];
        $departmentFlag = ['type' => 'dt'];

        // 机房
        $enginerooms = $this->deviceRepository->getErDtCategory($engineroomsFlag);

        // 科室
        $department = $this->deviceRepository->getErDtCategory($departmentFlag);

        // 将机房的信息加上标记
        if(!empty($enginerooms) || is_array($enginerooms)){
            foreach ($enginerooms as &$val){
                if(is_array($val['children'])){
                    // 给机房信息做标记
                    foreach ($val['children'] as &$vval){
                        if(is_array($vval['children'])){
                            $this->addFlag($vval['children'],$engineroomsFlag);
                        }
                    }
                }
            }
        }

        // 将科室的信息加上标记
        if(!empty($department) || is_array($department)){
            foreach ($department as &$value){
                if(is_array($value['children'])){
                    // 给机房信息做标记
                    foreach ($value['children'] as &$vvalue){
                        if(is_array($vvalue['children'])){
                            $this->addFlag($vvalue['children'],$departmentFlag);
                        }
                    }
                }
            }
        }

        $arr = [];
        foreach ($enginerooms as $k => $v){
            $mergeEAndD = [];
            foreach ($department as $kk => $vv){
                // 归纳出同一地区的数据
                if($v['id'] == $vv['id']){
                    $mergeEAndD['id'] = $v['id'];
                    $mergeEAndD['name'] = $v['name'];
                    $mergeEAndD['children'] = array_merge($v['children'],$vv['children']);
                    $arr[] = $mergeEAndD;
                    unset($enginerooms[$k]);
                    unset($department[$kk]);
                }
            }
        }

        $arr = array_merge($arr,$enginerooms,$department);

        if($arr){
            asort($arr);
            foreach ($arr as &$val){
                $newArr = [];
                foreach ($val['children'] as $va){
                    // 自定义数组排序
                    if('其它' == $va['name']){
                        $key = 99999;
                    }else{
                        if (preg_match_all('/\d+/', $va['name'] ,$matches)){
                            // 楼名称或科室名称含有数字的数据靠前
                            $key = implode($matches[0]) . $va['id'];
                        }else{
                            $key = 9 . $va['id'];
                        }
                    }
                    // 将同一楼下的机房和科室数据拼接
                    if(isset($newArr[$key])){
                        $newArr[$key]['children'] = array_merge($newArr[$key]['children'],$va['children']);
                    }else{
                        $newArr[$key] = $va;
                    }
                }
                ksort($newArr);
                $val['children'] = array_values($newArr);
            }
            return array_values($arr);
        }else{
            return $arr;
        }


    }

    /**
     * 给机房或科室添加标记
     * @param array $arr
     * @param array $flag
     * @return array
     */
    public function addFlag(&$arr = array(), $flag = array()){
        if(is_array($arr) || $arr){
            array_walk($arr, function (&$val, $key, $flag) {
                $val = array_merge($val, $flag);
                $val['id_key'] = $val['id'];
                $val['id'] = $val['ppid'] . '_' . $val['pid'] . '_' . $val['id'];

            }, $flag);
        }
    }




    /**
     * 根据机房id数组或者科室id数组选择资产
     * @param $request
     * @return mixed
     */
    public function getChoiceAssetsByErOrDt($request){

        $input = $request->input();
        // 机房 id 数组
        if(isset($input['er'])){
            if(is_array($input['er'])){
                $erIds = $input['er'];
            }else{
                $erIds = explode(',',$input['er']);
            }
        }else{
            $erIds = array();
        }

        // 科室 id 数组
        if(isset($input['dt'])){
            if(is_array($input['dt'])){
                $dtIds = $input['dt'];
            }else{
                $dtIds = explode(',',$input['dt']);
            }
        }else{
            $dtIds = array();
        }

        // id, 子分类id, 资产编号, 资产名称, 位置, 办公楼, 机房, 科室, 机柜关联设备id, 机柜U数
        $field = ['id','sub_category_id','number','name','location','officeBuilding','area','department','rack','rack_pos'];

        $model = $this->deviceModel
                      ->select($field)
                      ->with('sub_category','zone','office_building','engineroom','department');

        if($erIds || $dtIds){
            if($erIds) $model = $model->whereIn('area',$erIds);
            if($dtIds) $model = $model->orWhereIn('department',$dtIds);
        }else{
            $model = $model->whereRaw('1=0');
        }

        return $model->get();

    }


}