<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/7/27
 * Time: 17:30
 */

namespace App\Repositories\Workflow;

use App\Models\Workflow\Category;
use App\Models\Workflow\EventsSuspend;
use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Workflow\OaRepository;
use App\Exceptions\ApiException;
use App\Models\Code;
use App\Models\Workflow\Event;
use App\Models\Workflow\Oa;
use Illuminate\Http\Request;
use DB;
use Auth;

class EventsSuspendRepository extends BaseRepository
{

    protected $userRepository;
    protected $categoryModel;
    protected $eventoa;

    public function __construct(EventsSuspend $eventssuspendModel,
                                EventsRepository $events,
                                OaRepository $eventoa)
    {
        $this->model = $eventssuspendModel;
        $this->events = $events;
        $this->eventoa = $eventoa;
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
     * 根据条件查询列表
     * @param array $where
     * @return array
     */
    public function getListByWhere($where=array()){
        $res = array();
        if($where) {
            $res = $this->model->where($where)->get();
        }
        return $res;
    }


    /**
     * 新增或更新事件暂停
     * @param array $input
     * @param array $event
     * @return bool|int|mixed|null
     */
    public function addUpdate($input=array(),$event=array()){
        $eventId = getkey($input,'eventId');
//        $esid = getkey($input,'esid');
        $etype = intval(getkey($input,'etype'));
        $content = getkey($input,'content');
        $suspend = intval(getkey($input,'suspend'));
        $estate = intval(getkey($input,'estate'));
        $date = date('Y-m-d H:i:s');
        $datetime = strtotime($date);
        $result = false;
        if(!$event) {
            if(\ConstInc::WX_OAETYPE == $etype){
                $event = $this->eventoa->getById($eventId);
            }else {
                $event = $this->events->getById($eventId);
            }
        }
        $state = isset($event['state']) ? $event['state'] : '';
        $stateArr = \ConstInc::WX_OAETYPE == $etype ? array(Oa::STATE_ING) : array(Event::STATE_ING);
        if(!in_array($state,$stateArr)){
            Code::setCode(Code::ERR_STATE_NOT_SUSPEND);
            return false;
        }
        $suspendStauts = isset($event['suspend_status']) ? $event['suspend_status'] : '';
        if($suspend){
            if($suspendStauts){
                Code::setCode(Code::ERR_SUSPEND_OPENED);
                return false;
            }else {
                $where = array('event_id' => $eventId);
                $resOne = $this->model->where($where)->orderBy("id", "desc")->first();
                $resOne = $resOne ? $resOne->toArray() : array();
                $endDate = isset($resOne['end_at']) ? $resOne['end_at'] : '';
                if ($resOne && !$endDate) {
                    Code::setCode(Code::ERR_SUSPEND_OPENED);
                    return false;
                }
            }
            $insert = array(
                'event_id' => $eventId,
                'etype' => $etype,
                'estate' => $estate,
                'start_at' => $date,
                'content' => $content,
            );
            $rs = EventsSuspend::create($insert);
            $result = isset($rs['id'])?$rs['id']:0;
        }elseif($suspendStauts){
            $resOne = $this->getById($suspendStauts);
            if(!$resOne){
                Code::setCode(Code::ERR_MODEL);
//                throw new ApiException(Code::ERR_MODEL);EXIT;
                return false;
            }
            $startDate = isset($resOne['start_at']) ? strtotime($resOne['start_at']) : '';
            $endDate = isset($resOne['end_at']) ? $resOne['end_at'] : '';
            $usetime = $startDate && $datetime>$startDate ? $datetime - $startDate : 0;
//            var_dump($startDate,$usetime);exit;

            $update = array(
                'end_at' => $date,
                'usetime' => $usetime
            );
//            $where = array('id'=>$esid);
//            var_dump($where,$update,$endDate);//exit;
            if(!$endDate) {
                $up = $this->update($suspendStauts, $update);
                $result = $up ? $suspendStauts : false;
            }else{
                Code::setCode(Code::ERR_SUSPEND_CLOSEED);
                return false;
            }
        }else{
            Code::setCode(Code::ERR_SUSPEND_CLOSEED);
            return false;
        }
        return $result;
    }


    public function getUsetimeByEventIds($field='',$ids=array(),$etype=0){
        $data = array();
        if($ids && is_array($ids)) {
            $field = $field ? $field : 'id';
            $where = array('etype'=>$etype);
            $data = $this->model->select(DB::raw('sum(usetime) as usetime'),'event_id')->whereIn($field,$ids)->where($where)->groupBy('event_id')->get();
//            $data = $this->model->sum('usetime');
            $data = $data ? $data->toArray() : array();
        }
        return $data;
    }


    public function getUsetimeByEventId($id='',$etype=0){
        $data = array();
        if($id) {
            $where = array('event_id'=>$id,'etype' => $etype);
            $data = $this->model->select(DB::raw('sum(usetime) as usetime'),'event_id')->where($where)->groupBy('event_id')->first();
            $data = $data ? $data->toArray() : array();
        }
        return $data;
    }





}