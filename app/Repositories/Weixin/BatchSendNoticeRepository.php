<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/12/14
 * Time: 15:00
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Workflow\WxBatchSendNotice;


class BatchSendNoticeRepository extends BaseRepository
{
    protected $date = '';


    public function __construct(WxBatchSendNotice $WxBatchSendNotice)
    {
        $this->model = $WxBatchSendNotice;
        $this->date = date("Y-m-d H:i:s");

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $result = 0;
        $openids = isset($param['openids'])?$param['openids']:0;
        $event_id = isset($param['eventId'])?$param['eventId']:0;
        $description = isset($param['description'])?$param['description']:'';
        $state = isset($param['state'])?$param['state']:0;
        $etype = isset($param['etype'])?$param['etype']:0;
        $url = isset($param['url'])?$param['url']:0;
        $title = isset($param['title'])?$param['title']:0;
        $data = array(
            "openids" => $openids,
            "event_id" => $event_id,
            "description" => $description,
            "state" =>$state,
            "etype" => $etype,
            "url" => $url,
            "title" => $title,
        );
//        dd($data);exit;
        $where = array('event_id'=>$event_id,'state'=>$state,'etype'=>$etype);
        $oneData = $this->getOne($where);
        if(!$oneData) {
            $rs = WxBatchSendNotice::create($data);
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
     * 列表数据
     * @param array $where
     * @param int $number
     * @param string $sort
     * @param string $sortColumn
     * @return mixed
     */
    public function getListAll($where=array()){
        $model = $this->model;
        if($where){
            $model = $model->where($where);
        }
        $data = $model->get();
        return $data;
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







}