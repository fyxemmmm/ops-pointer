<?php

namespace App\Repositories\Inventory;

use App\Repositories\BaseRepository;
use App\Models\Inventory\Plan;
use App\Models\Code;
use DB;
use App\Exceptions\ApiException;
use App\Repositories\Assets\DeviceRepository;
use App\Models\Inventory\InventoryAsset;


class PlanDeviceRepository extends BaseRepository
{

    protected $planModel;
    protected $inventoryAssetModel;

    public function __construct(Plan $planModel,
                                InventoryAsset $inventoryAssetModel)
    {
        $this->planModel = $planModel;
        $this->inventoryAssetModel = $inventoryAssetModel;
    }


    /**
     * 获取盘点计划与资产列表
     * @return array
     */
    public function getList(){
        $currentTime = date("Y-m-d H:i:s");
        $lastMonth= date("Y-m-d H:i:s", strtotime("-1 month"));
        $tbl = 'inventory';
        $field = ['id', 'number', 'name', 'status', 'created_at', 'updated_at'];

        $model = $this->planModel->select($field)
                                 ->where($tbl.'.status',Plan::STATE_DOING)
                                 ->orWhere(function ($query) use ($tbl,$currentTime,$lastMonth) {
                                    $query->where($tbl.'.status',Plan::STATE_SCRAP)
                                          ->whereBetween($tbl.'.updated_at',[$lastMonth,$currentTime]);
                                 });

        $planData = $this->usePage($model)->toArray();

        $data = $planData['data'];

        if($data){
            // 分页后的盘点计划 id 数组
            $inventoryIds = array_column($data,'id');

            $IAtbl = 'inventory_asset';
            $select = [$IAtbl.'.inventory_id', $IAtbl.'.asset_id', 'id', 'rack', 'rack_pos', 'number', 'name', 'rfid'];

            $assetsData = $this->inventoryAssetModel->select($select)
                                                    ->join('assets_device AS B','inventory_asset.asset_id','=','B.id')
                                                    ->whereIn('inventory_asset.inventory_id',$inventoryIds)
                                                    ->get()
                                                    ->toArray();

            $new = [];
            foreach ($assetsData as &$val){
                $val['rack'] = DeviceRepository::transform('rack',$val['rack'],$tkey);
                $new[$val['inventory_id']][] = $val;
            }

            foreach ($data as &$v){
                foreach ($new as $inventoryId => $value){
                    $id = isset($v['id']) ? $v['id'] : '';
                    $v['assetLists'] = isset($new[$id]) ? $new[$id] : [];
                }
            }

        }

        $page = [];
        $page['data'] = $data;
        $child = [];
        $child['total'] = $planData['total'];
        $child['count'] = $planData['per_page'];
        // 当前页的数据总数
        if($planData['current_page'] == $planData['last_page']){
            $child['count'] = $planData['total'] - ($planData['current_page'] - 1) * $planData['per_page'];
        }else{
            $child['count'] = $planData['per_page'];
        }
        $child['per_page'] = $planData['per_page'];
        $child['current_page'] = $planData['current_page'];
        $child['total_pages'] = $planData['last_page'];
        $child['links']['previous'] = $planData['prev_page_url'];
        $page['meta']['pagination'] = $child;

        return $page;

    }


    /**
     * 发送盘点结果
     * @param $deviceRequest
     * @throws ApiException
     */
    public function postReport($deviceRequest){

        $input = $deviceRequest->input();

        if(empty($input)) throw new ApiException(Code::ERR_NO_PARAMS, ["参数不能为空"]);

        $item = Plan::findOrFail($input['inventory_id']);

        if (empty($item)) throw new ApiException(Code::ERR_EMPTY_DATA, ["无该盘点计划"]);

        $tbl = 'inventory_asset';
        $update = [];

        $data = [];
        foreach ($input as $val){
            if(is_array($val)) $data[] = $val;
        }

        if(empty($data)) throw new ApiException(Code::ERR_NO_PARAMS, ["asset_id or result"]);

        foreach ($data as $value){
            if(!isset($value['asset_id']) || !isset($value['result'])) throw new ApiException(Code::ERR_NO_PARAMS);
            $update[] = [
                'inventory_id' => $input['inventory_id'],
                'asset_id' => $value['asset_id'],
                'result' => $value['result'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if($update){
            // 拼接 sql 语句
            $updateSql = ' UPDATE ' . $tbl . ' SET ';
            $setSql = ' `result` = CASE ';
            $updatedAtSql = ' `updated_at` = CASE ';
            $resultBuilding = [];
            $updateBuilding = [];
            $whenResult = [];

            foreach ($update as $val){
                $resultBuilding[] = $val['inventory_id'];
                $resultBuilding[] = $val['asset_id'];
                $resultBuilding[] = $val['result'];
                $whenResult[] = 'WHEN `inventory_id` = ? AND asset_id = ? THEN ?  ';
                $updateBuilding[] = $val['inventory_id'];
                $updateBuilding[] = $val['asset_id'];
                $updateBuilding[] = $val['updated_at'];
            }
            $setSql .= implode('',$whenResult) . ' ELSE `result` END';
            $updatedAtSql .= implode('',$whenResult) . ' ELSE `updated_at` END';
            $sql = $updateSql . $setSql . ',' . $updatedAtSql;

            $buildings = array_merge($resultBuilding,$updateBuilding);

            DB::beginTransaction();

            DB::update($sql,$buildings);

            // 将盘点计划状态更改为已完成
            $this->planModel->where('id',$input['inventory_id'])->update(['status' => Plan::STATE_END]);

            DB::commit();

        }else{
            throw new ApiException(Code::ERR_EMPTY_DATA, ["无更新数据"]);
        }


    }


}