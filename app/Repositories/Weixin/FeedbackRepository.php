<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/19
 * Time: 15:00
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\Feedback;

class FeedbackRepository extends BaseRepository
{
    protected $model;
    protected $date = '';


    public function __construct(Feedback $feedbackModel)
    {
        $this->model = $feedbackModel;
        $this->date = date("Y-m-d H:i:s");

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $content = isset($param['content'])?trim($param['content']):'';
        $userid = isset($param['user_id'])?$param['user_id']:0;
        $state = isset($param['state'])?$param['state']:0;
        $data = array(
            "content" => $content,
            "user_id" => $userid,
            "state" => $state,
        );
//        dd($data);exit;
        $rs = Feedback::create($data);
        $result = isset($rs['id'])?$rs['id']:0;
//        dd($model['id']);exit;
        return $result;
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
     * 统计总数
     * @param array $where
     * @return array
     */
    public function getCount($where=array()){
        $res = array();
        if($where) {
            $res = $this->model->where($where)->count();
        }
        return $res;
    }








}