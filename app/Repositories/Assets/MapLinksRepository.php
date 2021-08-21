<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Exceptions\ApiException;
use App\Models\Assets\Device;
use App\Repositories\BaseRepository;
use App\Models\Assets\MapLinks;
use App\Models\Assets\MapRegionConfig;
use App\Models\Code;
use App\Repositories\Monitor\LinksRepository;

class MapLinksRepository extends BaseRepository
{
    protected $date = '';
    protected $mrcmodel;
    protected $linksRepo;
    protected $deviceModel;

    public function __construct(MapLinks $model,MapRegionConfig $mrcmodel, LinksRepository $linksRepo, Device $deviceModel)
    {
        $this->model = $model;
        $this->mrcmodel = $mrcmodel;
        $this->linksRepo = $linksRepo;
        $this->deviceModel = $deviceModel;
        $this->date = date("Y-m-d H:i:s");
    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()) {
        $rs = array();
        if($param) {
            $rs = MapLinks::create($param);
        }
        $result = isset($rs['id'])?$rs['id']:0;
        return $result;
    }

    /**
     * 获取线路信息，并检查assetId
     * @param $linkId
     * @return mixed
     */
    public function checkLinkAsset($linkId, $assetId, &$source) {
        $link = $this->linksRepo->getById($linkId, false);
        if(empty($link)) {
            throw new ApiException(Code::ERR_LINKS_NOT_FOUND);
        }

        if($link->source_asset_id == $assetId) {
            $source = true;
        }
        else if($link->dest_asset_id == $assetId) {
            $source = false;
        }
        else {
            throw new ApiException(Code::ERR_LINKS_ASSET_NOT_FOUND);
        }
        return $link;
    }


    /**
     * 通过资产获取mrc的id
     * @param $assetId
     * @return int
     * @throws ApiException
     */
    protected function getMrcId($assetId) {
        $device = $this->deviceModel->where(["id" => $assetId])->first();
        if(empty($device)) {
            throw new ApiException(Code::ERR_MODEL);
        }
        $engineroom = \ConstInc::ENGINEROOM;
        $er_id = $device->$engineroom;
        $ret = $this->mrcmodel->where(["er_id" => $er_id])->first();
        if(empty($ret)) {
            return 0;
        }
        return $ret->id;
    }


    /**
     * 批量新增
     * @param array $params
     * @return bool
     */
    public function addMapLinks($links, $mrcId){
        if($links && $mrcId){
            foreach($links as $val) {
                $date = date("Y-m-d H:i:s");
                $linkArr = getKey($val, 'link', array());
                $assetId = getKey($val, 'asset_id', 0);
                if ($linkArr) {
                    $linkArr = array_unique($linkArr);
                    foreach($linkArr as $v) {
                        $link = $this->checkLinkAsset($v, $assetId, $source);
                        if($source) {
                            $source_mrc_id = $mrcId;
                            $source_asset_id = $link->source_asset_id;
                            $dest_mrc_id = $this->getMrcId($link->dest_asset_id);
                            $dest_asset_id = $link->dest_asset_id;
                        }
                        else {
                            $source_mrc_id = $this->getMrcId($link->source_asset_id);
                            $source_asset_id = $link->source_asset_id;
                            $dest_mrc_id = $mrcId;
                            $dest_asset_id = $link->dest_asset_id;
                        }

                        //检查线路是否已经存在，做更新
                        $linkExist = $this->model->where("mlinks_id",$v)->first();
                        if(!empty($linkExist)) {
                            $linkExist->source_mrc_id = $source_mrc_id;
                            $linkExist->source_asset_id = $source_asset_id;
                            $linkExist->dest_mrc_id = $dest_mrc_id;
                            $linkExist->dest_asset_id = $dest_asset_id;
                            $linkExist->updated_at = $date;
                            $linkExist->save();
                        }
                        else {
                            $param = array(
                                'source_mrc_id' => $source_mrc_id,
                                'source_asset_id' => $source_asset_id,
                                'dest_mrc_id' => $dest_mrc_id,
                                'dest_asset_id' => $dest_asset_id,
                                'mlinks_id' => $v,
                                'created_at' => $date,
                                'updated_at' => $date,
                            );
                            $this->model->insert($param);
                        }
                    }
                }
            }
        }
        return true;
    }


    public function linkEdit($links, $id){
        $this->model->where('source_mrc_id', $id)->orWhere("dest_mrc_id",$id)->delete();
        $this->addMapLinks($links, $id);
    }


    protected function getLinkByMrc(array $mrcId, $source = true, &$assetArr = []) {
        if($source) {
            $field = "source_mrc_id";
            $pre = "source";
        }
        else {
            $field = "dest_mrc_id";
            $pre = "dest";
        }
        $links = $this->model->whereIn($field,$mrcId)->get()->toArray();
        if($links){
            foreach($links as $v){
                $assetId = getKey($v,$pre.'_asset_id');
                $asset_id_key = "asset_".$assetId;
                $mrc_id_key = "mrc_".getKey($v,$pre.'_mrc_id');
                $mlinks_id = getKey($v,'mlinks_id');
                if(!isset($assetArr[$mrc_id_key])) {
                    $assetArr[$mrc_id_key] = [];
                }
                if(!isset($assetArr[$mrc_id_key][$asset_id_key])) {
                    $assetArr[$mrc_id_key][$asset_id_key] = ['asset_id' => $assetId, "link" => []];
                }

                if(!in_array($mlinks_id, $assetArr[$mrc_id_key][$asset_id_key]['link'])) {
                    $assetArr[$mrc_id_key][$asset_id_key]['link'][] = getKey($v,'mlinks_id');
                }
            }
        }
    }

    public function delLinkByMrc($mrcId) {
        //如果地区机房删除，直接删除对应连线
        $this->model->where("source_mrc_id",$mrcId)->orWhere("dest_mrc_id",$mrcId)->delete();
        /*
        $ret = $this->model->where("source_mrc_id",$mrcId)->get();
        foreach($ret as $model) {
            if($model->dest_mrc_id == 0) {
                $model->delete();
            }
            else {
                $model->source_mrc_id = 0;
                $model->save();
            }
        }

        $ret = $this->model->where("dest_mrc_id",$mrcId)->get();
        foreach($ret as $model) {
            if($model->source_mrc_id == 0) {
                $model->delete();
            }
            else {
                $model->dest_mrc_id = 0;
                $model->save();
            }
        }
        */
    }

    public function getList($region_id){
        $region = $this->mrcmodel->where('mr_id',$region_id)->with('enginerooms')->get()->toArray();
        $id = array_column($region,'id');
        $this->getLinkByMrc($id, true, $assetArr);
        $this->getLinkByMrc($id, false, $assetArr);

        foreach ($region as $key => $value) {
            $id = getKey($value,'id');
            $erooms = isset($value['enginerooms']) ? $value['enginerooms'] : array();
            $region[$key]['er_name'] = isset($erooms['name']) ? $erooms['name'] : '';
            unset($region[$key]['enginerooms']);
            if(isset($assetArr["mrc_".$id])){
               $region[$key]['links'] = array_values($assetArr["mrc_".$id]);
            }
        }

        return $region;

    }

    public function getPos() {
        $links = $this->model->join("monitor_links","mlinks_id","=","monitor_links.id")
            ->where("source_mrc_id","!=", 0)
            ->where("dest_mrc_id","!=",0)
            ->with("sourceMapRegionConfig","destMapRegionConfig")
            ->select("map_links.*","monitor_links.status")
            ->get();
        return $links;
    }

    public function getLists() {
        return $this->all()->pluck("mlinks_id")->toArray();
    }

}