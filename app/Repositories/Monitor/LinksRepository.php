<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/3/21
 * Time: 10:45
 */

namespace App\Repositories\Monitor;

use App\Models\Assets\MapLinks;
use App\Models\Monitor\Links;
use App\Models\Monitor\LinksData;
use App\Models\Monitor\AssetsMonitor;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Auth\UserRepository;
use App\Models\Assets\Device;
use App\Repositories\BaseRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use App\Repositories\Monitor\AssetsMonitorRepository;
use Log;
use DB;
use Auth;

class LinksRepository extends BaseRepository
{
    protected $model;
    protected $linksDataModel;
    protected $mapLinksModel;
    protected $device;
    protected $assetMonitor;
    protected $hengwei;
    protected $user;

    const CORESWITCH = "coreSwitch"; //核心设备

    public function __construct(Links $links,
                                LinksData $linksDataModel,
                                DeviceRepository $device,
                                AssetsMonitor $assetMonitor,
                                HengweiRepository $hengwei,
                                UserRepository $user,
                                MapLinks $mapLinksModel,
                                AssetsMonitorRepository $assets_monitor
)
    {
        $this->model = $links;
        $this->linksDataModel = $linksDataModel;
        $this->device = $device;
        $this->assetMonitor = $assetMonitor;
        $this->hengwei = $hengwei;
        $this->user = $user;
        $this->mapLinksModel = $mapLinksModel;
        $this->assets_monitor = $assets_monitor;
    }

    /**
     * 添加线路
     * @param $request
     * @return bool|mixed
     * @throws ApiException
     */
    public function add($request) {
        $fromName = $this->getName($request->input("fromAssetId"), $request->input("fromPortId"), $fromDevice, $fromPortDesc);
        $toName = $this->getName($request->input("toAssetId"), $request->input("toPortId"), $toDevice, $toPortDesc);

        $input = $request->input();
        $input['fromDeviceId'] = $fromDevice->device_id;
        $input['toDeviceId'] = $toDevice->device_id;
        $input['from_based'] = 1; //该参数暂不做配置
        $input["source_asset_id"] = $input['fromAssetId'];
        $input["source_port_id"] = $input['fromPortId'];
        $input["dest_asset_id"] = $input['toAssetId'];
        $input["dest_port_id"] = $input['toPortId'];
        $input["custom_speed_up"] = $input['speedUp'];
        $input["custom_speed_down"] = $input['speedDown'];
        $input["custom_speed_up_limit"] = $input['speedUpLimit'];
        $input["custom_speed_down_limit"] = $input['speedDownLimit'];
        $input["source_port_desc"] = $fromPortDesc;
        $input["dest_port_desc"] = $toPortDesc;

        $name = $request->input("name");
        if(empty($name)) {
            $input['name'] = $fromName. "---". $toName;
        }

        if($this->checkExists($request)) {
            Code::setCode(Code::ERR_LINKS_DUP);
            return false;
        }

        $linkId = $this->hengwei->addLink($input);
        if(false === $linkId) {
            return false;
        }
        $input['link_id'] = $linkId;

        return $this->store($input);
    }


    /**
     * 检查线路是否重复，针对同样的设备同样的网卡，同方向
     * @param $request
     */
    protected function checkExists($request) {
        $input = $request->input();
        $where = [];
        if(isset($input['id']) && !empty($input['id'])) {
            $model = $this->getById($input['id']);
            $where["source_asset_id"] = $model->source_asset_id;
            $where["dest_asset_id"] = $model->dest_asset_id;
            $model = $this->model->where("id","!=",$input['id']);
        }
        else {
            $where["source_asset_id"] = $input['fromAssetId'];
            $where["dest_asset_id"] = $input['toAssetId'];
            $model = $this->model;
        }

        $where["source_port_id"] = $input['fromPortId'];
        $where["dest_port_id"] = $input['toPortId'];
        $where["forward"] = $input['forward'];


        $cnt = $model->where($where)->count();
        return $cnt > 0 ;
    }


    /**
     * 获取单个线路信息
     * @param $request
     * @return mixed
     */
    public function getInfo($request) {
        $linkId = $request->input("linkId");
        $model = $this->model
            ->join("assets_device as A","monitor_links.source_asset_id","=","A.id")
            ->join("assets_device as B","monitor_links.dest_asset_id","=","B.id")
            ->join("assets_enginerooms as C","A.area","=","C.id")
            ->join("assets_enginerooms as D","B.area","=","D.id");
            if($linkId) {
                $model->where("monitor_links.link_id", "=", $linkId);
            }else{
                $model->where("monitor_links.id", "=", $request->input("id"));
            }

        if($this->user->isEngineer() || $this->user->isManager()) {
            $limitCategories = $this->user->getCategories();
        }
        if(isset($limitCategories)) {
            $model = $model
                ->whereIn("A.sub_category_id",$limitCategories)
                ->whereIn("B.sub_category_id",$limitCategories);
        }

        $fieldArrKeys[] = "monitor_links.*";
        $fieldArrKeys[] = "A.name as source_name";
        $fieldArrKeys[] = "A.number as source_number";
        $fieldArrKeys[] = "C.name as source_engineroom";
        $fieldArrKeys[] = "B.name as dest_name";
        $fieldArrKeys[] = "B.number as dest_number";
        $fieldArrKeys[] = "D.name as dest_engineroom";
        $model = $model->select($fieldArrKeys);
        return $model->first();
    }


    /**
     * 删除
     * @param $request
     * @return bool|mixed
     */
    public function delete($request) {
        $id = $request->input("id");
        $device = $this->getById($id);
        $linkId = $device->link_id;
        if(!empty($linkId)) {
            $ret = $this->hengwei->delDevice(['deviceId' => $linkId]);
            if(false === $ret) {
                return false;
            }
        }

        //删除地图配置的线路
        $this->mapLinksModel->where("mlinks_id", $id)->delete();

        //删除线路下载数据
        $this->linksDataModel->where("monitor_link_id", $id)->delete();

        return $this->del($request->input("id"));
    }

    /**
     * 线路列表
     * @return mixed
     * @throws ApiException
     */
    public function getList($request,$sortColumn = "created_at", $sort = "desc") {
        $search = $request->input("search");
        $status = $request->input("status");

        $model = $this->model
            ->join("assets_device as A","monitor_links.source_asset_id","=","A.id")
            ->join("assets_device as B","monitor_links.dest_asset_id","=","B.id")
            ->join("assets_enginerooms as C","A.area","=","C.id")
            ->join("assets_enginerooms as D","B.area","=","D.id");

        if(!is_null($status)) {
            $model = $model->where("monitor_links.status","=",$status);
        }

        $model = $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("C.name", "like", "%".$search."%");
                $query->orWhere("D.name", "like", "%".$search."%");
                $query->orWhere("A.number", "like", "%".$search."%");
                $query->orWhere("B.number", "like", "%".$search."%");
                $query->orWhere("monitor_links.name", "like", "%".$search."%");
                $query->orWhere("monitor_links.id", "=", $search);
            }
        });

        if($this->user->isEngineer() || $this->user->isManager()) {
            $limitCategories = $this->user->getCategories();
        }
        if(isset($limitCategories)) {
            $model = $model
                ->whereIn("A.sub_category_id",$limitCategories)
                ->whereIn("B.sub_category_id",$limitCategories);
        }

        $fieldArrKeys[] = "monitor_links.*";
        $fieldArrKeys[] = "A.name as source_name";
        $fieldArrKeys[] = "A.number as source_number";
        $fieldArrKeys[] = "C.name as source_engineroom";
        $fieldArrKeys[] = "B.name as dest_name";
        $fieldArrKeys[] = "B.number as dest_number";
        $fieldArrKeys[] = "D.name as dest_engineroom";
        $model->with("sourceAssetsMonitor", "destAssetsMonitor");
        $model = $model->select($fieldArrKeys);
        return $this->usePage($model,$sortColumn,$sort);
    }

    /**
     * 取设备名字
     * @param $assetId
     * @param $portId
     * @param $device
     * @return string
     * @throws ApiException
     */
    protected function getName($assetId, $portId, &$device, &$board) {
        $device = $this->assetMonitor->where(["asset_id" => $assetId])->first();
        if(!$device) {
            throw new ApiException(Code::ERR_BIND_ASSETS_NOT);
        }

        $boards = $this->hengwei->getSwitchboard(["deviceId" => $device->device_id]);
        if(!$boards) {
            throw new ApiException(Code::ERR_GET_MONITOR_FAIL);
        }

        foreach($boards as $v) {
            if($v['if_index'] == $portId) {
                $board = $v['if_index']."[".$v['if_name'];
                if(!empty($v['if_descr']) && $v['if_descr'] != $v['if_name']) {
                    $board .= "(".$v['if_descr'].")";
                }
                $board .= "]";
            }
        }

        if(empty($board)){
            throw new ApiException(Code::ERR_HW_BOARD_NOT_FOUND);
        }

        return sprintf("%s[%s(%s)]-%s", $device->ip, $device->asset->name, $device->asset->number, $board);
    }

    /**
     * 根据条件进行搜索，无分页
     * @param $request
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     *
     */
    public function getDeviceByEngineroom($request) {
        if($this->user->isEngineer() || $this->user->isManager()) {
            $limitCategories = $this->user->getCategories();
        }

        $model = $this->assetMonitor->join("assets_device as A","assets_monitor.asset_id","=","A.id");
        $model = $model->join("assets_category as B","A.sub_category_id","=","B.id");
        $model->whereIn("state", [Device::STATE_USE]);
        $model->whereNull("A.deleted_at");
        $model->where(self::CORESWITCH, "是");

        if(isset($limitCategories)) {
            $model = $model->whereIn("A.sub_category_id",$limitCategories);
        }

        $engineroomId = $request->input("engineroomId");
        $model->where("A.area","=",$engineroomId);

        $fieldArrKeys[] = "A.id";
        $fieldArrKeys[] = "A.category_id";
        $fieldArrKeys[] = "A.sub_category_id";
        $fieldArrKeys[] = "A.number";
        $fieldArrKeys[] = "A.name";
        $fieldArrKeys[] = "B.name as category_name";
        $fieldArrKeys[] = "assets_monitor.device_id";
        $fieldArrKeys[] = "assets_monitor.ip";
        return $model->select($fieldArrKeys)->get();
    }

    public function edit($request) {
        $device = $this->getById($request->input("id"));

        $fromName = $this->getName($device->source_asset_id, $request->input("fromPortId"), $fromDevice, $fromPortDesc);
        $toName = $this->getName($device->dest_asset_id, $request->input("toPortId"), $toDevice, $toPortDesc);

        $input = $request->input();
        $input['linkId'] = $device->link_id;
        $input["source_port_id"] = $input['fromPortId'];
        $input["dest_port_id"] = $input['toPortId'];
        $input["custom_speed_up"] = $input['speedUp'];
        $input["custom_speed_down"] = $input['speedDown'];
        $input["custom_speed_up_limit"] = $input['speedUpLimit'];
        $input["custom_speed_down_limit"] = $input['speedDownLimit'];
        $input["source_port_desc"] = $fromPortDesc;
        $input["dest_port_desc"] = $toPortDesc;

        $name = $request->input("name");
        if(empty($name)) {
            $input['name'] = $fromName. "---". $toName;
        }

        if($this->checkExists($request)) {
            Code::setCode(Code::ERR_LINKS_DUP);
            return false;
        }

        if($this->hengwei->editLink($input)) {
            return $this->update($request->input("id"), $input);
        }
        return false;
    }

    /**
     * cmd 数据采集，并且更新线路延迟状态
     * @return array
     */
    public function collect() {
        $total = $this->model->count();
        $offset = 0;
        $limit = 100;
        $newIds = [];
        do {
            $result = $this->model->offset($offset)->limit($limit)->get();
            foreach($result as $v) {
                try{
                    $info = $this->hengwei->getLinkInfo($v->link_id);
		/**
		array:1 [
  			0 => array:26 [
    				"ifBit" => 0
    				"ifDiscards" => 0
				...
  			]
			]
		**/
                }
                catch(ApiException $e) {
                    Log::error("collect error: $e");
                    continue;
                }
                if(!$info) {
                    continue;
                }
		if(isset($info[0]) && is_array($info[0])) {
                        $info = $info[0];
                }

                if($info['ifStatus'] == 1) { //计算延迟
                    //上行，出带宽
                    if($v->custom_speed_up_limit <= $info['ifOutOctetsPercent'] * 100 ||
                        $v->custom_speed_down_limit <= $info['ifInOctetsPercent'] * 100
                    ) {
                        $status = 2;
                    }
                    else {
                        $status = 1;
                    }
                }
                else {
                    $status = 0;
                }
                $this->update($v->id, ["status" => $status]);

                $info['monitor_link_id'] = $v->id;
                unset($info['raw']);
                $obj = new LinksData();
                $obj->fill($info);
                $obj->save();
                $newIds[] = $obj->id;
            }

            $offset += $limit;
        }while($offset < $total);

        return $newIds;
    }

    /**
     * 删除线路
     * @return mixed
     * @throws ApiException
     */
    public function remove_line($request){
        $link_id = $request->input("id");
        $line_info = $this->model->where('link_id',$link_id)->first();
        if($line_info){
        $id = $line_info->id;
        if(!empty($line_info->link_id)) {
            try {
                $this->hengwei->delDevice(['deviceId' => $line_info->link_id]);
            } catch (\Exception $e) {
                Code::setCode(Code::ERR_LINKS_DEL_ERROR);
                return false;
            }
        }
        $maplinksinfo = $this->mapLinksModel->where("mlinks_id", $id)->first();
        if($maplinksinfo){
        //删除地图配置的线路
        $this->mapLinksModel->where("mlinks_id", $id)->delete();
        }
        //删除线路下载数据
        $linksDatainfo = $this->linksDataModel->where("monitor_link_id", $id)->first();
        if($linksDatainfo){
        $this->linksDataModel->where("monitor_link_id", $id)->delete();
        }
        return $this->del($id);
        }else{
            if(!empty($link_id)) {
                try {
                    $this->hengwei->delDevice(['deviceId' => $link_id]);
                } catch (\Exception $e) {
                    Code::setCode(Code::ERR_LINKS_DEL_ERROR);
                    return false;
                }
            }
            return true;
        }

    }


//    监控设备解绑删除
    public function untie($request){
        $device_id = $request->input('id');
        $this->assets_monitor->delAssetMonitor($device_id);
        return true;
    }

    /**
     * 更新所有未同步绑定线路
     * @return array
     */
    public function updateUnbindLinkList(){
        $result = array();
        //获取总数
        $nDeviceCount = $this->hengwei->getNetworkLinkCount();
        $limit = \ConstInc::$mPages;
//        $nDevice = array();
        $ids = array();
        $diff = array();

        $pageMax = ceil($nDeviceCount/$limit);
//        var_dump($nDeviceCount,$pageMax);exit;
        $update = array();
        if($pageMax) {
            $date = date('Y-m-d H:i:s');
            $insert = array();
            //翻页取数据
            for ($i = 1; $i <= $pageMax; $i++) {
                $offset = ($i - 1) * $limit;
                $nDevice = $this->getNetworkLinkAdd($offset, $limit);
                $input = getKey($nDevice,'input',array());//新增的数据
                $assetIds = getKey($nDevice,'assetIds',array());//资产id对应监控设备id
                $linkIds = getKey($nDevice,'linkIds');//所有线路id
                $updateLinkIdArr = getKey($nDevice,'updateLinkIdArr',array());//需要更新的线路id
//                dd($nDevice);
                if ($input) {
                    foreach ($input as $k=>$v) {
                        $linkId = getKey($v,'link_id');
                        $source_asset_id = getKey($v,'source_asset_id');
                        $dest_asset_id = getKey($v,'dest_asset_id');
                        //监控设备id转化成我asset_id
                        $sourceAssetId = isset($assetIds[$source_asset_id]) ? $assetIds[$source_asset_id] : '';
                        $destAssetId = isset($assetIds[$dest_asset_id]) ? $assetIds[$dest_asset_id] : '';
                        $v['source_asset_id'] = $sourceAssetId;
                        $v['dest_asset_id'] = $destAssetId;
                        if(!in_array($linkId,$linkIds)) {
                            //新增
                            $v['created_at'] = $date;
                            $v['updated_at'] = $date;
                            $insert[$k] = $v;
                        }elseif(in_array($linkId,$linkIds) && $sourceAssetId && $destAssetId && in_array($linkId,$updateLinkIdArr)){
                            //更新
                            $upParam = array(
                                'source_asset_id' => $sourceAssetId,
                                'dest_asset_id' => $destAssetId,
                                'source_port_id'=> getKey($v,'source_port_id'),
                                'dest_port_id'=> getKey($v,'dest_port_id'),
                            );
                            $upWhere = array('link_id'=>$linkId);
                            $update = $this->model->where($upWhere)->update($upParam);
                        }
                    }
                }
                //批量添加
//                dd($insert);
                if($insert) {
                    $result = $this->model->insert($insert);
                }
            }
        }
        if(!$result && !$update){
            Code::setCode(Code::ERR_NOT_SYNC_BIND_LINKS);
            return false;
        }else{
            return true;
        }

    }


    /**
     * 恒维设备线路列表格式化
     * @return array
     */
    public function getNetworkLinkAdd(){
        $assetIdArr = array();
        $insert = array();
        $linkIds = array();
        $result = array('linkIds'=>$linkIds,'assetIds'=>$assetIdArr,'input'=>$insert);
        $nDevice = $this->hengwei->getNetworkLink($offset=0,$limit=0);
//        return $nDevice;
        if($nDevice){
            foreach($nDevice as $k=>$v){
                $fileds = getKey($v,'fields');
                $fromDevice = getKey($fileds,'from_device');
                $toDevice = getKey($fileds,'to_device');
                $assetIdArr[] = $fromDevice;
                $assetIdArr[] = $toDevice;
                $linkIds[] = getKey($fileds,'id');
                $custom_speed_up = getKey($fileds,'custom_speed_up');
                $custom_speed_down = getKey($fileds,'custom_speed_down');
                $forward = getKey($fileds,'forward');
                $insert[$k] = array(
                    'link_id' => getKey($fileds,'id'),
                    'source_asset_id' => $fromDevice,
                    'source_port_id' => getKey($fileds,'from_if_index'),
                    'dest_asset_id' => $toDevice,
                    'dest_port_id' => getKey($fileds,'to_if_index'),
                    'custom_speed_up' => $custom_speed_up>0 ? $custom_speed_up/1000/1000:0,
                    'custom_speed_down' => $custom_speed_down>0 ? $custom_speed_down/1000/1000:0,
                    'custom_speed_up_limit' => 70,//默认值
                    'custom_speed_down_limit' => 70,//默认值
                    'level' => getKey($fileds,'level'),
                    'forward' =>  'true'  == $forward ? 1 : 0,
                    'from_based' => 1, //该参数暂不做配置
                    'name' => getKey($fileds,'name'),
                    'status' => 1,//默认值
                );
            }

            $assetArr = array();
            if($assetIdArr){
                $whereIn = array_filter(array_unique($assetIdArr));
                $assetm = $this->assetMonitor->whereIn('device_id',$whereIn)->get();
                if($assetm) {
                    $assetm = $assetm->toArray();
                    foreach($assetm as $v){
                        $did = getKey($v,'device_id');
                        $assetArr[$did] = getKey($v,'asset_id');
                    }

                }
            }
            $linkIdArr = array();
            $updateLinkIdArr = array();
            if($linkIds){
                $link = $this->model->whereIn('link_id',$linkIds)->get();
                if($link) {
                    $link = $link->toArray();
                    foreach($link as $v){
                        $source_asset_id = getKey($v,'source_asset_id');
                        $dest_asset_id = getKey($v,'dest_asset_id');
                        $source_port_id = getKey($v,'source_port_id');
                        $dest_port_id = getKey($v,'dest_port_id');
                        $linkIdArr[] = getKey($v,'link_id');
                        if(!$source_asset_id || !$dest_asset_id || !$source_port_id || !$dest_port_id){
                            $updateLinkIdArr[] = getKey($v,'link_id');
                        }
                    }

                }

            }
            $result = array('linkIds'=>$linkIdArr,'assetIds'=>$assetArr,'input'=>$insert,'updateLinkIdArr'=>$updateLinkIdArr);
        }

        return $result;

    }


}
