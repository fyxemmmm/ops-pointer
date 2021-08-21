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
use App\Models\Monitor\MonitorCurrentAlert;
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

class MonitorCurrentAlertRepository extends BaseRepository
{

    const MONITOR_LEVEL = 3; //>=3的会报警
    protected $qywxuser;
    protected $deviceModel;

    public function __construct(MonitorCurrentAlert $monitorcurrentalertModel,
                                MonitorAlert $monitoralertModel,
                                EventsRepository $eventsRepository,
                                UserRepository $userRepository,
                                WeixinUserRepository $weixinuser,
                                CommonRepository $common,
                                QyWxUserRepository $qywxuser,
                                Device $deviceModel
    ){
        $this->model = $monitorcurrentalertModel;
        $this->monitoralertModel = $monitoralertModel;
        $this->eventsRepository = $eventsRepository;
        $this->userRepository = $userRepository;
        $this->weixinuser = $weixinuser;
        $this->common = $common;
        $this->deviceModel = $deviceModel;

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
                        'title' => '设备已恢复正常，点击查看详情',
                        'url' => $this->hostName . '/eventsdetail/' . $eventId,
                        'desc' => isset($event['remark']) ? $event['remark'] : '',
                        'eventID' => $eventId,
                        'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : ''
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
            Log::info($eventId . '_asset_operation_access:' . json_encode($uids));
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

    /**
     * 报警事件触发
     * @param $alertIds
     */
    public function triggerEvent($alertIds) {
        $result = $this->model->with("asset")->whereIn("alert_id", $alertIds)->get();
        $events = [];
        foreach($result as $v) {
            $level = intval($v->level);
            if($v->status != 0 && $level >= self::MONITOR_LEVEL) {
                Log::info("trigger event ".$v->alert_id);
                if(!$v->asset->isEmpty()){
                    $device = $v->asset[0];
                    if($device->state == Device::STATE_USE) {
                        Log::info("add monitor ".$device->id." ".$v->content);
                        $eventId = $this->eventsRepository->addByMonitor($device->id, $v->content, $v->event_id, 'alert_event_id');
                        if(false !== $eventId) {
                            $events[] = $eventId;
                        }
                    }
                    else {
                        Log::info("device state not in use. not add monitor ".$device->id." ".$v->content);
                    }
                }
            }
        }

        foreach($events as $event) {
            $this->sendErrNotice($event);
        }
    }

    /**
     * 报警事件触发,线路
     * @param $alertIds
     */
    public function triggerEventByLink($alertIds) {
        $result = $this->model->with("link")->whereIn("alert_id", $alertIds)->get();
        $events = [];
        foreach($result as $v) {
            $level = intval($v->level);
            if($v->status != 0 && $level >= self::MONITOR_LEVEL) {
                Log::info("trigger link event ".$v->alert_id);
                if($v->link->exists){
                    $link = $v->link;
                    if($link->from_based == 1) { //数据来源，1为source
                        $device = $this->deviceModel->find($link->source_asset_id);
                    }
                    else {
                        $device = $this->deviceModel->find($link->dest_asset_id);
                    }
                    if(empty($device)) {
                        Log::info("link device not found. not add monitor ".$link->id." ".$v->content);
                        continue;
                    }
                    if($device->state == Device::STATE_USE) {
                        Log::info("add monitor ".$device->id." ".$v->content);
                        $eventId = $this->eventsRepository->addByMonitor($device->id, $v->content, $v->event_id, 'alert_event_id');
                        if(false !== $eventId) {
                            $events[] = $eventId;
                        }
                    }
                    else {
                        Log::info("link device state not in use. not add monitor ".$device->id." ".$v->content);
                    }
                }
            }
        }

        foreach($events as $event) {
            $this->sendErrNotice($event);
        }
    }


    public function getEventCnt($request) {
        return $this->eventsRepository->getAlertCnt($request);
    }


    /**
     * 获取历史告警列表
     * @param array $input 参数
     * @param string $sortColumn 排序字段
     * @param string $sort 排序类型，升:asc,降:desc
     * @return mixed
     */
    public function getAlertHistory($input=[],$sortColumn='alert_id',$sort='desc'){
        $search = isset($input['search']) ? $input['search'] : '';
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $end = isset($input['end']) ? $input['end'] : '';
        $end = $end ? date('Y-m-d H:i:s',strtotime($end)) : '';
        $where = array();
        if($begin) {
            $where[] = ["triggered_at",">=", $begin];
        }
        if($end) {
            $where[] = ["triggered_at","<=", $end];
        }
        $model = $this->model;
        if(!empty($where)) {
//            var_dump($where);exit;
            $model = $model->where($where);
        }
        $model = $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("alert_id", "like", "%" . $search . "%");
                $query->orWhere("content", "like", "%" . $search . "%");
                $query->orWhere("level", "like", "%" . $search . "%");
            }
        });;
        return $this->usePage($model,$sortColumn,$sort);
    }


    /**
     * 获取24小时告警总数
     * @param array $input
     * @return string
     */
    public function getAlertCountDay($input=array()){
        $result = 0;
//        $date = date('Y-m-d H:i:s');
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $begin = $begin ? date('Y-m-d H:i:s',strtotime($begin)) : '';
        $end = isset($input['end']) ? $input['end'] : '';
        $end = $end ? date('Y-m-d H:i:s',strtotime($end)) : '';
        $begin = !$begin && !$end  ? date('Y-m-d H:i:s',time()-86400) : $begin;

        if(!$begin){
            if(!$end) {
                $begin = date('Y-m-d H:i:s', time() - 86400);
            }else{
                $begin = date('Y-m-d H:i:s', strtotime($end) - 86400);
            }
        }
        $where = array();
        if($begin) {
            $where[] = ["triggered_at",">=", $begin];
        }
        if($end) {
            $where[] = ["triggered_at","<=", $end];
        }
        if(!empty($where)) {
            $result = $this->model->where($where)->count();
        }
        return $result;
    }



}