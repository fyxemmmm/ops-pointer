<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/27
 * Time: 17:20
 */

namespace App\Repositories\Monitor;

use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Models\Monitor\MonitorAlert;
use App\Models\Assets\Device;
use App\Models\Code;
use App\Exceptions\ApiException;
use App\Repositories\Workflow\EventsRepository;
use Log;
use App\Models\Workflow\Event;
use App\Repositories\Weixin\CommonRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Weixin\QyWxUserRepository;

class MonitorAlertRepository extends BaseRepository
{

    protected $qywxuser;
    protected $eventsRepository;

    public function __construct(MonitorAlert $monitoralertModel,
                                EventsRepository $eventsRepository,
                                UserRepository $userRepository,
                                WeixinUserRepository $weixinuser,
                                CommonRepository $common,
                                QyWxUserRepository $qywxuser
    ){
        $this->model = $monitoralertModel;
        $this->eventsRepository = $eventsRepository;
        $this->userRepository = $userRepository;
        $this->weixinuser = $weixinuser;
        $this->common = $common;

        $this->hostName = config("app.wx_url");
        $this->qywxuser = $qywxuser;
    }



    /**
     * 批量新增
     * @param array $arr
     * @param int $eventID
     * @return bool
     */
    public function addBatch($arr=array()){
        $result = false;
        if($arr && is_array($arr)){
            foreach($arr as $k=>$val){
                $date = date("Y-m-d H:i:s");
                $param[$k] = $val;
                $param[$k]['created_at'] = $date;
                $param[$k]['updated_at'] = $date;
            }
//            var_dump($param);exit;
            $result = $this->model->insert($param);
        }
//        var_dump($result);
        return $result;

    }


    /**
     * 获取一条监控数据
     * @param array $input
     * @return bool|string
     */
    public function getOne($where=array())
    {
        $result = '';
        if ($where) {
            $result = $this->model->where($where)->first();
        }

        return $result;
    }

    protected function sendCloseNotice($event) {
        $eventId = $event['id'];
        $engineers = $this->userRepository->getEngineers();
        $uids = array();
        if ($engineers) {
            foreach ($engineers as $v) {
                $uids[] = isset($v['id']) ? $v['id'] : 0;
            }
        }

        $uids = array_filter(array_unique($uids));
        $uids = $this->eventsRepository->checkAssetOperationAccess($event,$uids);
        Log::info($eventId.'_asset_operation_access:'.json_encode($uids));
        if(2 == \ConstInc::WX_PUBLIC){
            //企业微信通知消息
            $wxUsers = $this->qywxuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $useridArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $userid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    $useridArr[] = $userid;
                }
                $wxNotice = array(
                    'touser' => $useridArr,
                    'title' => '设备已恢复正常，点击查看详情',
                    'url' => $this->hostName . '/eventsdetail/' . $eventId,
                    'desc' => isset($event['remark']) ? $event['remark'] : '',
                    'eventID' => $eventId,
                    'state' => Event::$stateMsg[Event::STATE_CLOSE],
                    'dtype' => 1
                );
                $this->common->qySendTextcard($wxNotice);
                Log::info($eventId . '_send_qywx_notice_batch:' . json_encode($useridArr));
            }
        } else {
            //微信公众号通知消息
            $wxUsers = $this->weixinuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $openidArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $openid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    $openidArr[] = $openid;
                    $wxNotice = array(
                        'openid' => $openid,
                        'title' => '设备已恢复正常，点击查看详情',
                        'url' => $this->hostName . '/eventsdetail/' . $eventId,
                        'desc' => isset($event['remark']) ? $event['remark'] : '',
                        'eventID' => $eventId,
                        'state' => Event::$stateMsg[Event::STATE_CLOSE]
                    );
                    $this->common->sendWXNotice($wxNotice);
                }
                Log::info($eventId . '_send_wx_notice_batch:' . json_encode($openidArr));
            }
        }
    }

    protected function sendErrNotice($event) {
        $eventId = $event['id'];
        $engineers = $this->userRepository->getEngineers();
        $uids = array();
        if ($engineers) {
            foreach ($engineers as $v) {
                $uids[] = isset($v['id']) ? $v['id'] : 0;
            }
        }

        $uids = array_filter(array_unique($uids));
        $uids = $this->eventsRepository->checkAssetOperationAccess($event,$uids);
        Log::info($eventId.'_asset_operation_access:'.json_encode($uids));
        if(2 == \ConstInc::WX_PUBLIC){
            //企业微信通知消息
            $wxUsers = $this->qywxuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $useridArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $userid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    $useridArr[] = $userid;
                }
                $wxNotice = array(
                    'touser' => $useridArr,
                    'title' => '设备出现异常，点击查看详情',
                    'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                    'desc' => isset($event['description']) ? $event['description'] : '',
                    'eventID' => $eventId,
                    'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : '',
                    'dtype' => 1
                );
                $this->common->qySendTextcard($wxNotice);
                Log::info($eventId . '_send_qywx_notice_batch:' . json_encode($useridArr));
            }
        } else {
            //微信公众号通知消息
            $wxUsers = $this->weixinuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $openidArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $openid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    $openidArr[] = $openid;
                    $wxNotice = array(
                        'openid' => $openid,
                        'title' => '设备出现异常，点击查看详情',
                        'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                        'desc' => isset($event['description']) ? $event['description'] : '',
                        'eventID' => $eventId,
                        'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : ''
                    );
                    $this->common->sendWXNotice($wxNotice);
                }
                Log::info($eventId . '_send_wx_notice_batch:' . json_encode($openidArr));
            }
        }
    }

    public function triggerEvent($alertIds) {
        $result = $this->model->with("asset")->whereIn("alert_id", $alertIds)->get();
        $closeEvents = []; //恢复正常的
        foreach($result as $v) {
            if($v->status == 0) { //已解决的报警，关闭事件
                Log::info("trigger event close".$v->alert_id);
                $closeEvents = array_merge($closeEvents, $this->eventsRepository->closeByMonitor($v->event_id, $v->content, 'alert_event_id'));
            }
        }

        foreach($closeEvents as $event) {
            $this->sendCloseNotice($event);
        }
    }

    public function triggerEventByLink($alertIds) {
        $result = $this->model->with("link")->whereIn("alert_id", $alertIds)->get();
        $closeEvents = []; //恢复正常的
        foreach($result as $v) {
            if($v->status == 0) { //已解决的报警，关闭事件
                Log::info("trigger link event close".$v->alert_id);
                $closeEvents = array_merge($closeEvents, $this->eventsRepository->closeByMonitor($v->event_id, $v->content, 'alert_event_id'));
            }
        }

        foreach($closeEvents as $event) {
            $this->sendCloseNotice($event);
        }
    }


    public function getEventCnt($request) {
        return $this->eventsRepository->getAlertCnt($request);
    }

    /**
     * 获取24小时告警数
     * @param array $input
     * @return bool|mixed
     */
    public function getAlertCountDay($input=[]) {
        $date = date('Y-m-d H:i:s');
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $edate = isset($input['end']) ? $input['end'] : '';
        $edate = $edate ? date('Y-m-d H:i:s',strtotime($edate)) : '';
        $begin = !$begin && !$edate  ? date('Y-m-d H:i:s',time()-86400) : $begin;

        if(!$begin){
            if(!$edate) {
                $begin = date('Y-m-d H:i:s', time() - 86400);
            }else{
                $begin = date('Y-m-d H:i:s', strtotime($edate) - 86400);
            }
        }

        $input['begin'] = $begin;
        $input['end'] = $edate ? $edate : $date;

        return $this->getAlertHistory($input, 'alert_id', 'desc', true);
    }


    /**
     * 获取历史告警列表
     * @param array $input 参数
     * @param string $sortColumn 排序字段
     * @param string $sort 排序类型，升:asc,降:desc
     * @return mixed
     */
    public function getAlertHistory($input=[],$sortColumn='alert_id',$sort='desc', $onlyCnt = false){
        $search = isset($input['search']) ? $input['search'] : '';
        $level = $input['level'] ?? '';
        $status = $input['status'] ?? '';
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $end = isset($input['end']) ? $input['end'] : '';
        $end = $end ? date('Y-m-d H:i:s',strtotime($end)) : '';
        $where = array();

        $where[] = ['monitor_alert.sequence_id',"=", "1"];

        $model = $this->model;

        if(!empty($level) && $level > 0){
            $where[] = ['monitor_alert.level','=',$level];
        }

        if(in_array($status,[0,1]) && $status !== '' && $status >= 0){
            if($status == 0){
                $where[] = ['A.status','=',0];
            }else{
                $where[] = ['A.status','=',null];
            }
        }

        if(!empty($where)) {
            $model = $model->where($where);
        }

        $model->where(function ($query) use ($begin) {
            if (!empty($begin)) {
                $query->orWhere("monitor_alert.triggered_at",">=", $begin);
                $query->orWhere("A.triggered_at",">=", $begin);
            }
        });

       $model->where(function ($query) use ($end) {
            if (!empty($end)) {
                $query->orWhere("monitor_alert.triggered_at","<=", $end);
                $query->orWhere("A.triggered_at","<=", $end);
            }
        });


        $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                //$query->orWhere("monitor_alert.alert_id", "like", "%" . $search . "%");
                $query->orWhere("monitor_alert.content", "like", "%" . $search . "%");
//                $query->orWhere("monitor_alert.level", "=",  $search );
            }
        });
        $model->leftJoin("monitor_alert as A", function($q){
            $q->on("monitor_alert.event_id", '=', 'A.event_id')
                ->where('A.status', '=', "0"); //恢复
        });
        $model->leftJoin("workflow_events as B",function($q) {
           $q->on("monitor_alert.event_id","=","B.alert_event_id");
        });
        $model->leftJoin("users as C",function($q) {
            $q->on("B.user_id","=","C.id");
        });
        $model->select("monitor_alert.*", "A.status as new_status","A.triggered_at as new_triggered_at" ,"A.content as new_content","B.user_id","C.username");
        if($onlyCnt) {
            return $model->count();
        }
        return $this->usePage($model,$sortColumn,$sort);
    }




    public function getLevelOrStatusList($str){

        $str = $str.'Msg';
        $list = $this->model::$$str;
        $arr = [];
        foreach($list as $key => $value){
            $arr[] = [
                'id' => $key,
                'name' => $value
            ];
        }
//        array_unshift($arr,['id'=>-1,'name'=>'无']);
        return $arr;
        
    }

}