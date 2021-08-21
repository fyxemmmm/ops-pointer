<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/12
 * Time: 11:35
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\EventTrack;
use Illuminate\Http\Request;
//use App\Models\Assets\Engineroom;
use Auth;

class EventsTrackRepository extends BaseRepository
{
    protected $date = '';


    public function __construct(EventTrack $eventTrackModel)
    {
        $this->model = $eventTrackModel;
        $this->date = date("Y-m-d H:i:s");

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $result = 0;
        $event_id = isset($param['eventId'])?$param['eventId']:0;
        $description = isset($param['description'])?$param['description']:'';
        $asset_id = isset($param['assetId'])?$param['assetId']:0;
        $step = isset($param['step'])?$param['step']:0;
        $etype = isset($param['etype'])?$param['etype']:0;
        $data = array(
            "event_id" => $event_id,
            "description" => $description,
            "asset_id" =>$asset_id,
            "step" =>$step,
            "state" =>$step,
            "etype" => $etype,
        );
//        dd($data);exit;
        $where = array('event_id'=>$event_id,'state'=>$step,'etype'=>$etype);
        $oneData = $this->getOne($where);
        if(!$oneData) {
            $rs = EventTrack::create($data);
            $result = isset($rs['id']) ? $rs['id'] : 0;
        }
//        dd($model['id']);exit;
        return $result;
    }


    /**
     * 列表数据
     * @param array $where
     * @param int $number
     * @param string $sort
     * @param string $sortColumn
     * @return mixed
     */
    public function getList($where=array()){
        $model = $this->model->where($where);
        return $this->usePage($model);
    }


    /**
     * 获取一条数据
     * @param array $where
     * @return mixed
     */
    public function getOne($where=array()){
        $res = array();
        if($where) {
            $res = $this->model->where($where)->first();
        }
        return $res;
    }


    /**
     * 根据事件ID获取列表数据
     * @param string $field
     * @param array $ids
     * @param int $etype 0:事件，1：OA事件
     * @return mixed
     */
    public function getListByEventIds($field='',$ids=array(),$etype=0){
        $res = array();
        if($ids && is_array($ids)) {
            $field = $field ? $field : 'id';
            $where = array('etype'=>$etype);
            $res = $this->model->whereIn($field, $ids)->where($where)->get();
        }
        return $res;
    }


    /**
     * 批量添加
     * @param array $param
     * @return array
     */
    public function addBatch($param=array()){
        $result = array();
        if($param){
            $result = $this->model->insert($param);
        }
        return $result;

    }







}