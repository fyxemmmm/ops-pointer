<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/2
 * Time: 15:55
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\EventComment;
use Illuminate\Http\Request;
//use App\Models\Assets\Engineroom;
use Auth;

class EventsCommentRepository extends BaseRepository
{
    protected $model;
    protected $date = '';


    public function __construct(EventComment $eventCommentModel)
    {
        $this->model = $eventCommentModel;
        $this->date = date("Y-m-d H:i:s");

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $event_id = isset($param['event_id'])?$param['event_id']:0;
        $content = isset($param['content'])?trim($param['content']):'';
        $feedback = isset($param['feedback'])?trim($param['feedback']):'';
        $uid = isset($param['uid'])?$param['uid']:0;
        $star_level = isset($param['star_level'])?$param['star_level']:5;
        $etype = isset($param['etype'])?$param['etype']:0;
        $data = array(
            "event_id" => $event_id,
            "content" => $content,
            "feedback" => $feedback,
            "user_id" => $uid,
            "star_level"=>$star_level,
            "etype" => $etype,
        );
//        dd($data);exit;
        $rs = EventComment::create($data);
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


    public function addBatch($arr=array(),$param=array()){
        $result = false;
        if($arr && is_array($arr) && $param){
            $insert = array();
            $created_at = isset($param['created_at']) ? $param['created_at'] : '';
            $updated_at = isset($param['updated_at']) ? $param['updated_at'] : '';
//            var_dump($arr,$param);exit;
            foreach($arr as $k=>$eventID){
                $date = date("Y-m-d H:i:s");
                $etype = isset($param['etype']) ? $param['etype'] : 0;
                $param['event_id'] = $eventID;
                if(!$created_at) {
                    $param['created_at'] = $date;
                }
                if(!$updated_at) {
                    $param['updated_at'] = $date;
                }
                $insert[$k] = $param;
                $where = array('event_id'=>$eventID,'etype' => $etype);
                $eventRes = $this->getOne($where);
//                var_dump($eventRes);exit;
                if($eventRes){
                    unset($insert[$k]);
                }

            }
//            var_dump($insert);exit;
            if($insert) {
                $result = $this->model->insert($insert);
            }
        }
//        var_dump($result);
        return $result;
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








}