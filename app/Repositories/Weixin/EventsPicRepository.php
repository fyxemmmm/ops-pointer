<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/2
 * Time: 15:55
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\EventPic;
use Illuminate\Http\Request;
//use App\Models\Assets\Engineroom;
use Auth;

class EventsPicRepository extends BaseRepository
{
    protected $userInfo = '';
    protected $date = '';


    public function __construct(EventPic $eventPicModel)
    {
        $this->model = $eventPicModel;
        $this->date = date("Y-m-d H:i:s");

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $event_id = isset($param['event_id'])?$param['event_id']:0;
        $src = isset($param['src'])?$param['src']:0;
        $etype = isset($param['type'])?$param['type']:0;
        $data = array(
            "event_id" => $event_id,
            "src" => $src,
            "etype" => $etype
        );
//        dd($data);exit;
        $result = EventPic::create($data);
//        dd($model['id']);exit;
        return $result;
    }


    /**
     * 批量新增
     * @param array $arr
     * @param int $eventID
     * @param int $etype 0:事件，1：OA事件
     * @return bool
     */
    public function addBatch($arr=array(),$eventID=0,$etype=0){
        $result = false;
        if($arr && is_array($arr) && $eventID){
            /*foreach($arr as $img){
                $param = array('event_id'=>$eventID,'src'=>$img);
                $rs = $this->add($param);
                $id = isset($rs['id'])?$rs['id']:0;
                if($id){
                    $result[] = $id;
                }
            }*/
            foreach($arr as $img){
                $date = date("Y-m-d H:i:s");
                $param[] = array(
                    'event_id'=>$eventID,
                    'src'=>$img,
                    'etype' => $etype,
                    'created_at' => $date,
                    'updated_at' => $date,
                );
            }
            $result = $this->model->insert($param);
        }
//        var_dump($result);
        return $result;

    }


    public function getList($where=array()) {
        $result = array();
        if($where) {
            $res = $this->model->where($where)->get();
            if($res){
                foreach($res as $k=>$v){
                    $result[] = $v['src'];
                }
            }
        }
        return $result;
    }








}