<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Engineroom;
use App\Models\Monitor\AssetsMonitor;
use App\Models\Code;
use App\Exceptions\ApiException;
use App\Models\Assets\Device;
use DB;
use App\Models\Assets\MapRegionConfig;
use App\Models\Assets\MapRegion;
use App\Models\Assets\MapLinks;
use App\Http\Requests\Assets\EngineroomRequest;
use App\Repositories\Monitor\LinksRepository;
use Illuminate\Http\Request;
use App\Models\Monitor\Links;

class EngineroomRepository extends BaseRepository
{

    protected $mapRegionConfigModel;
    protected $mapRegionModel;
    protected $mapLinksModel;

    public function __construct(Engineroom $engineroomModel,
                                Device $deviceModel,
                                MapRegionConfig $mapRegionConfigModel,
                                MapRegion $mapRegionModel,
                                MapLinks $mapLinksModel,
                                LinksRepository $linksRepository,
                                Links $linksModel,
                                AssetsMonitor $assetsMonitor
                                )
    {
        $this->model = $engineroomModel;
        $this->deviceModel = $deviceModel;
        $this->mapRegionConfigModel = $mapRegionConfigModel;
        $this->mapRegionModel = $mapRegionModel;
        $this->mapLinksModel = $mapLinksModel;
        $this->linksRepository = $linksRepository;
        $this->linksModel = $linksModel;
        $this->assetMonitorModel = $assetsMonitor;
    }


    /**
     * 首页机房核心设备信息
     *
     * @return array
     * @throws ApiException
     */
    public function getCoreAssetsMsgForHome(){

        $engineroomAndDevicesArr = $this->getEngineroomAndAsset();

        if(!empty($engineroomAndDevicesArr)) {

            $engineroomMsg = $this->filterEngineroomMsg($engineroomAndDevicesArr);

            $res = $this->formatList($engineroomMsg);

            return $res;
        }else{
            // 也有可能没有一台机房有监控设备
            return $engineroomAndDevicesArr;
        }

    }

    /**
     * 计算对应机房的监控设备总数和设备状态
     *
     * @param array $newList
     * @param array $engineroomAndDevicesArr
     * @return array
     */
    public function filterEngineroomMsg($newList = array()){

        if(empty($newList)) throw new ApiException(Code::ERR_MODEL, ["无机房无监控设备信息"]);

        $new = [];
        // 计算对应机房下面的监控设备总数
        foreach ($newList as $kk => $vv){ // $kk 为机房的 id

            // 机房监控设备总数
            $deviceTotal = count($vv);
            $newListKey = $kk . '_' . $deviceTotal;

            foreach ($vv as $kkk => $vvv){ // $kkk 为设备的 id

                // 统计出所有被监控设备的状态情况
                $new[$newListKey]['all_device_status'][] = $vvv['monitor']['device_status'];
                // 统计出在线的所有情况（device_status == 1）
                if(1 == $vvv['monitor']['device_status']){
                    $new[$newListKey]['all_succ_device_status'][] = $vvv['monitor']['device_status'];
                }

                // 只挑选出核心设备
                if('是' == $vvv['coreSwitch']){
                    $new[$newListKey][] = $vvv;
                }

//                $new[$newListKey][] = $vvv;

            }

        }
        return $new;
    }

    /**
     * 对机房下面监控设备做数据处理（新首页）
     *
     * @param array $engineroomMsg 机房设备信息列表
     * @return array
     * @throws ApiException
     */
    public function formatList($engineroomMsg = array()){

        if(empty($engineroomMsg)) throw new ApiException(Code::ERR_MODEL, ["无机房监控设备信息"]);

        $newArr = [];
        $arr = [];
        foreach ($engineroomMsg as $eid => $v){

            // 设备总状态情况统计
            if(isset($v['all_device_status'])){
                $deviceStatusTotal = count($v['all_device_status']);
                unset($v['all_device_status']);
            }else{
                $deviceStatusTotal = 0;
            }

            // 设备在线状态情况统计
            if(isset($v['all_succ_device_status'])){
                $deviceSuccStatusTotal = count($v['all_succ_device_status']);
                unset($v['all_succ_device_status']);
            }else{
                $deviceSuccStatusTotal = 0;
            }

            // 设备在线率（该机房下所有监控设备在线总数/该机房下所有监控设备总状态[离线+在线]）
            if(0 == $deviceStatusTotal){
                $deviceStatusPercent = 0;
            }else{
                $deviceStatusPercent = number_format($deviceSuccStatusTotal / $deviceStatusTotal,2) * 100;
            }

            $number = stripos($eid,'_');
            $roomId = substr($eid,0,$number);
            $deviceTotal = substr($eid,$number + 1);

            $child = [];
            $core = [];

            if(empty($v)){
                $roomData = $this->model->findOrFail($roomId);
                $arr['id'] = $roomData->id ? $roomData->id : '';   // 机房id
                $arr['title'] = $roomData->name ? $roomData->name : '';  // 机房名称
                $arr['address'] = $roomData->address ? $roomData->address : ''; // 机房地址
                $arr['eq_total'] = $deviceTotal;    // 机房下所有核心设备总数
                $arr['online'] = $deviceStatusPercent;  // 机房下所在设备在线率
                $arr['level'] = isset($roomData->type) ? $roomData->type : null ;  // 机房类型（一级机房、二级机房、三级机房）
//                throw new ApiException(Code::ERR_PARAMS, ["该机房下此无设备"]);
            }else{

                foreach ($v as $vv){

                    // 核心设备数据
                    if('否' == $vv['coreSwitch']){
                        $arr['id'] = $vv['engineroom']['id'];   // 机房id
                        $arr['title'] = $vv['engineroom']['name'];  // 机房名称
                        $arr['address'] = $vv['engineroom']['address']; // 机房地址
                        $arr['eq_total'] = $deviceTotal;    // 机房下所有核心设备总数
                        $arr['online'] = $deviceStatusPercent;  // 机房下所在设备在线率
                        $arr['level'] = isset($vv['engineroom']['type']) ? $vv['engineroom']['type'] : null ;  // 机房类型（一级机房、二级机房、三级机房）
                    }else{
                        $arr['id'] = $vv['engineroom']['id'];   // 机房id
                        $arr['title'] = $vv['engineroom']['name'];  // 机房名称
                        $arr['address'] = $vv['engineroom']['address']; // 机房地址
                        $arr['eq_total'] = $deviceTotal;    // 机房下所有核心设备总数
                        $arr['online'] = $deviceStatusPercent;  // 机房下所在设备在线率
                        $arr['level'] = isset($vv['engineroom']['type']) ? $vv['engineroom']['type'] : null ;  // 机房类型（一级机房、二级机房、三级机房）
                        $child['assets_id'] =  $vv['id'];   // 设备 id
                        $child['name'] =  $vv['name'];  // 设备名称
                        $child['number'] =  $vv['number'];  // 设备编号
                        // 设备监控表 id
                        $child['monitor_id'] =  isset($vv['monitor']['id']) ? $vv['monitor']['id'] : 0;
                        // 设备在线天数
                        $child['days'] =  isset($vv['monitor']['work_days']) ? round($vv['monitor']['work_days'] / 86400) : '';
                        // 设备 cpu 使用率
                        $child['cpu'] =  isset($vv['monitor']['cpu_percent']) ? $vv['monitor']['cpu_percent'] : '';
                        // 设备内存使用率
                        $child['mem'] =  isset($vv['monitor']['mem_percent']) ? $vv['monitor']['mem_percent'] : '';
                        $core[] = $child;
                    }

                }
            }



            $arr['child'] = $core;
            $newArr[] = $arr;


        }

        return $newArr;

    }

    /**
     * 地图首页机房配置列表
     *
     * @return Engineroom[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getListForHome(){
        $data = $this->model
                     ->with("zone","building")
                     ->orderBy("id","desc")
                     ->get();
        return $data;
    }


    /**
     * 第一步：从 map_region_config 表中获取所有信息
     * @return MapRegionConfig[]|array|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * @throws ApiException
     */
    public function getMapRCData(){

        $data = $this->mapRegionConfigModel->with("enginerooms")->get();

        if(empty($data)){
            throw new ApiException(Code::ERR_MODEL, ["无区域配置信息"]);
        }else{
            $data = $data->toArray();
        }

        return $data;
    }

    /**
     * 第二步：从 map_links 表中取出所有机房设备以及机房状态
     * @return array => source_mrc_id_机房状态 为第一层数组键名，资产 id (source_asset_id)为第二层数组键名
     * @throws ApiException
     */
    public function getMaplinksData(){
        // 取出所有资产设备以及线路关联关系
        $data = $this->mapLinksModel->with("monitorLink")->get()->toArray();

        if(!empty($data)){

            // 源设备点和目标设备点需要同时考虑
            $newData = [];
            foreach ($data as $item){
                if($item['source_mrc_id']){
                    $newData[] = [
                        'id' => $item['id'],
                        'mrc_id' => $item['source_mrc_id'],
                        'asset_id' => $item['source_asset_id'],
                        'mlinks_id' => $item['mlinks_id'],
                        'monitor_link' => $item['monitor_link'],
                    ];
                }

                if($item['dest_mrc_id']){
                    $newData[] = [
                        'id' => $item['id'],
                        'mrc_id' => $item['dest_mrc_id'],
                        'asset_id' => $item['dest_asset_id'],
                        'mlinks_id' => $item['mlinks_id'],
                        'monitor_link' => $item['monitor_link'],
                    ];
                }

            }


            // 以 mrc_id 为键重组数组
            $arr = [];
            foreach ($newData as $v){
                $arr[$v['mrc_id']][] = $v;
            }

            // 以 mrc_id 为第一层数组键、 asset_id 为第二层数组键 构建新数组
            $newArr = [];
            foreach ($arr as $mrc_id => $value){
                $new = [];
                $statusArr = [];
                foreach ($value as $vv){

                    $new[$vv['asset_id']][] = $vv;

                    // 取出机房下所有设备的所有线路状态情况
                    if(isset($vv['monitor_link']['status'])){
                        $statusArr[] = $vv['monitor_link']['status'];
                    }
                }

                // 将机房状态拼接到新数组中去
                if(empty($statusArr)){
                    // 设备没有线路连接的情况下
                    $status = 0;
                }else{
                    // 1是正常， 0是Down，2是延迟
                    if(in_array(0,$statusArr)){
                        $status = 0;
                    }elseif(in_array(2,$statusArr)){
                        $status = 2;
                    }else{
                        $status = 1;
                    }
                }

                $key = $mrc_id . '_' . $status;
                $newArr[$key] = $new;
            }

            return $newArr;

        }else{
            return $data;
        }


    }

    /**
     * 第三步：将地区机房信息和资产设备线路信息合并
     * @return array
     * @throws ApiException
     */
    public function mergeMapRCDataAndMaplinksData(){
        // 取出地区和机房信息
        $areaAndEngineroom = $this->getMapRCData();

        // 取出所有资产设备以及线路关联关系
        $assetAndMlinkMsg = $this->getMaplinksData();

        if($assetAndMlinkMsg){

            foreach ($areaAndEngineroom as $k => &$v){
                // $mrcIdAndStatus 为 mrc_id '_' 机房状态
                foreach ($assetAndMlinkMsg as $mrcIdAndStatus => $value){
                    $number = stripos($mrcIdAndStatus,'_');
                    // 取出机房状态（根据设备状态求出）
                    $status = substr($mrcIdAndStatus,$number + 1);
                    // 取出 mrc_id
                    $key = substr($mrcIdAndStatus,0,$number);

                    if(intval($key) === intval($v['id'])){
                        $v['asset_device_link'] = $value;
                        $v['engineroom_status'] = $status;
                    }
                }
            }

        }

        return $areaAndEngineroom;

    }

    /**
     * 第四步：归纳出所有机房的监控设备信息
     * @return array 机房id为第一层数组键名、监控设备id为第二层数组键名
     * @throws ApiException
     */
    public function getEngineroomAndAsset(){

        $data = $this->mergeMapRCDataAndMaplinksData();

        if(!$data) throw new ApiException(Code::ERR_PARAMS, ["无机房信息"]);

        $roomIds = array_column($data,'er_id');

        // 所有机房所对应的设备
        $list = $this->deviceModel->whereIn("area",$roomIds)
                                    ->select('id','number','name','area','coreSwitch')
                                    ->with("engineroom", "monitor")
                                    ->get()
                                    ->toArray();

        if($list){
            $engineroomAssets = [];
            foreach ($list as $v){
                // 取出所有监控设备
                if(isset($v['monitor']['id'])){
                    // 以机房 id 为第一层数组的键名，设备 id 为第二层数组的键名重组数组
                    $engineroomAssets[$v['area']][$v['id']] = $v;
                }
            }
            return $engineroomAssets;
        }

        return $list;

    }

    // 第五步：根据所有可能性的机房和设备取监控设备数据

    /**
     * 第七步：对没有配置设备的机房补全数据结构
     * @return array
     * @throws ApiException
     */
    public function mergeCoreMsgToAllData(){
        // 带有核心设备的统计数据
        $coreData = $this->getCoreAssetsMsgForHome();

        $aeamData = $this->mergeMapRCDataAndMaplinksData();
//      // er id == id
        // 有可能机房下一台核心设备都没有
        $new = [];
        if($coreData){
            foreach ($aeamData as $k => $val){
                foreach ($coreData as $value){
                    if(intval($value['id']) == intval($val['er_id'])){
                        $value['px'] = $val['px'];
                        $value['py'] = $val['py'];
                        $value['status'] = isset($val['engineroom_status']) ? $val['engineroom_status'] : 1;
                        $new[] = $value;
                        unset($aeamData[$k]);
                    }
                }
            }
        }

        $emptyValue = [];
        // 没有配置设备的机房 ID 数组
        if(!empty($aeamData)){
            $notAssetsInEngineroom = array_column($aeamData,'er_id');
            if(!empty($notAssetsInEngineroom)){
                $emptyValue = $this->fillEmptyValueForEngineroom($notAssetsInEngineroom);
            }
        }

        $arr = array_merge($new,$emptyValue);
        $arr = $this->checkStatus($arr);   // 线路告警状态问题
        return $arr;

    }

    // 告警信息 如果线路出现了告警状态，则被这条线路连接的两个设备也会出现告警状态
    public function checkStatus($arr){
        $links_model = $this->linksModel;
        $device_model = $this->deviceModel;
        $assetMonitorModel = $this->assetMonitorModel;

        $er_id = array_column($arr,'id');

        // assets device 表中查找 area er_id对应
        $area_data = $device_model->select('id','area')->whereIn('area',$er_id)->get()->toArray();
        foreach($arr as $k=>&$v){
            if(!isset($v['asset_range'])){
                $v['asset_range'] = array();
            }

            foreach($area_data as $kk=>$vv){
                if($vv['area'] == $v['id']){
                    // asset_range 是asset_id的范围
                    $v['asset_range'][] = $vv['id'];
                }
            }
        }


        // 设备资产
        $device_status_range = $assetMonitorModel->where('device_status','!=',1)->get()->pluck('asset_id')->toArray();

        $source_asset_id = $links_model->select('source_asset_id')->where('status','!=','1')->get()->pluck('source_asset_id')->toArray();
        $dest_asset_id = $links_model->select('dest_asset_id')->where('status','!=','1')->get()->pluck('dest_asset_id')->toArray();
        $assets_range = array_unique(array_merge($source_asset_id,$dest_asset_id,$device_status_range));
        foreach($arr as $k=>&$v){
            foreach($v['asset_range'] as $kk=>$vv){
                if(in_array($vv,$assets_range)){
                    $v['status'] = 0;
                }
            }
            unset($v['asset_range']);
        }


        return $arr;
    }


    /**
     * 第六步：给没有配置设备的机房填充空值
     * @param array $notAssetsInEngineroom
     * @return array
     * @throws ApiException
     */
    public function fillEmptyValueForEngineroom($notAssetsInEngineroom = array()){
        if($notAssetsInEngineroom){
            $allMapRCData = $this->getMapRCData();
            $arr = [];
            $newArr = [];
            foreach ($allMapRCData as $v){
                if(in_array($v['er_id'],$notAssetsInEngineroom)){
                    $newArr['id'] = $v['er_id'];
                    $newArr['title'] = $v['enginerooms']['name'];
                    $newArr['address'] = $v['enginerooms']['address'];
                    $newArr['eq_total'] = 0;
                    $newArr['online'] = 0;
                    $newArr['level'] = $v['enginerooms']['type'];
                    $newArr['px'] = $v['px'];
                    $newArr['py'] = $v['py'];
                    $newArr['status'] = 1;
                    $newArr['child'] = [];
                    $arr[] = $newArr;
                }
            }
            return $arr;
        }else{
            return $notAssetsInEngineroom;
        }
    }




}