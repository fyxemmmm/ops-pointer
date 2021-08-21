<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/5/10
 * Time: 10:30
 */

namespace App\Repositories\Report;

use App\Repositories\Auth\UserRepository;
use App\Repositories\Weixin\EventsTrackRepository;
use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Workflow\EventsSuspendRepository;
use App\Repositories\BaseRepository;
use App\Models\Workflow\Event;
use App\Models\Assets\Solution;
use App\Models\Auth\Engineer;
use App\Models\Code;
use DB;
use App\Exceptions\ApiException;

class EventsRepository extends BaseRepository
{

    protected $model;
    protected $userRepository;
    protected $eventstrack;
    protected $eventsComment;
    protected $eventssuspend;

    public $terminalCategory = [5,6];

    public function __construct(Event $eventModel,
                                EventsTrackRepository $eventstrack,
                                EventsCommentRepository $eventsComment,
                                Solution $solutionModel,
                                UserRepository $userRepository,
                                Engineer $engineerModel,
                                EventsSuspendRepository $eventssuspend
    ){
        $this->userRepository = $userRepository;
        $this->model = $eventModel;
        $this->eventstrack = $eventstrack;
        $this->eventsComment = $eventsComment;
        $this->solutionModel = $solutionModel;
        $this->engineerModel = $engineerModel;
        $this->eventssuspend = $eventssuspend;
    }


    /**
     * 每周运维事件时效统计报告，本周终端运维时效统计，本周最长响应（到场，处理）时间 TOP 3事件
     * @param $request
     * @return mixed
     */
    public function getWeekPrescription($request=array()) {
        $this->model = $this->model->where('source',3);
        $this->model = $this->model->whereIn('state',array(3,4));
        $inputBegin = $request->input("begin");
        $inputEnd = $request->input("end");
        $seTimes = getStartEndTime($inputBegin,$inputEnd,7);
        $between = isset($seTimes['date'])?$seTimes['date']:'';
        $timeArr = isset($seTimes['time'])?$seTimes['time']:'';

        $dayTotal = intval(($timeArr[1] - $timeArr[0])/60/60/24);//多少天
//        var_dump($seTimes,$between,$dayTotal);exit;
        if($dayTotal > 6){
            throw new ApiException(Code::ERR_TIME_EXCEED_WEEK);
        }
        $this->model = $this->model->whereBetween("created_at", $between);

        //周总事件列表
        $all = $this->model->get();//$this->usePage($this->model);
        $eventsArr = $all->toArray();
        //$eventsData = isset($events['data']) ? $events['data'] : '';
//        var_dump($events);exit;
        $eventIDs = array();
        $endArr = array();
        $closeArr = array();
        $endGoodArr = array();
        $processTimeArr = array();
        $responseTimeArr = array();
        $arrivalTimeArr = array();
        $topProcessTime = array();
        $topResponseTime = array();
        $topArrivalTime = array();
        $eData = array();
        $notPraise = array();

        $tmpEvents = array();
        if($eventsArr){
            foreach($eventsArr as $v) {
                $id = isset($v['id']) ? $v['id'] : '';
                $state = isset($v['state']) ? $v['state'] : '';
                $eventIDs[] = $id;
                if (3 == $state) {
                    $endArr[] = $id;
                }
                if (4 == $state) {
                    $closeArr[] = $id;
                }
                $tmpEvents[$id]['id'] = $id;
                $tmpEvents[$id]['description'] = isset($v['description']) ? $v['description'] : '';

            }
//            var_dump($eventIDs);exit;
            $eventIDs = array_filter(array_unique($eventIDs));
            $etRes = array();
            $eComment = array();
            if($eventIDs){
                $etRes = $this->eventstrack->getListByEventIds('event_id',$eventIDs);
                $etRes = $etRes->toArray();
//                var_dump($etRes);exit;
                $ecRes = $this->eventsComment->getListByEventIds('event_id',$eventIDs);
                $ecRes = $ecRes->toArray();
//                var_dump($ecRes);exit;
                foreach($ecRes as $v){
                    $eID = isset($v['event_id'])?$v['event_id']:'';
                    if($eID) {
                        $eComment[$eID] = isset($v['star_level']) ? $v['star_level'] : '';
                    }
                }
//                var_dump($eComment);exit;
            }
            foreach($eventsArr as $k=>$v){
                $event_id = isset($v['id']) ? $v['id'] : 0;
                $state = isset($v['state']) ? $v['state'] : '';
                $process_time = isset($v['process_time']) ? $v['process_time'] : '';
                $response_time = isset($v['response_time']) ? $v['response_time'] : '';
                $distance_time = isset($v['distance_time']) ? $v['distance_time'] : '';
                $reportDate = isset($v['report_at']) ? $v['report_at'] : '';
                $eData[$k]['id'] = $event_id;
                $eData[$k]['state'] = isset($v['state']) ? $v['state'] : '';
                $eData[$k]['report_time'] = $reportDate;
                $eData[$k]['create_time'] = isset($v['created_at']) ? $v['created_at'] : '';
                $eData[$k]['description'] = isset($v['description']) ? $v['description'] : '';
                $star_level = isset($eComment[$event_id]) ? $eComment[$event_id] : '';
                $eData[$k]['star_level'] = '';
                $eData[$k]['start'] = '';
                $eData[$k]['end'] = '';
                $eData[$k]['close'] = '';
                if(3 == $state){
                    $eData[$k]['star_level'] = $star_level;
                    if(5 == $star_level) {
                        $endGoodArr[] = $event_id;
                    }
                }
                $etracks = array();
                foreach($etRes as $kk=>$vv){
                    $eid = isset($vv['event_id']) ? $vv['event_id'] : 0;
                    $etState = isset($vv['state']) ? $vv['state'] : 0;
                    if($event_id == $eid) {
                        $step = isset($vv['step']) ? $vv['step'] : 0;
                        if(1 == $step){
                            $eData[$k]['receipt'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                        }elseif (2 == $step) {
                            $eData[$k]['start'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                        } elseif (3 == $step) {
                            $eData[$k]['end'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                        } elseif (4 == $step) {
                            $eData[$k]['close'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                        }
                        $tmp = array(
                            'created_at' => isset($vv['created_at']) ? $vv['created_at'] : '',
                            'state_name' => Event::$stateMsg[$etState],
                        );
                        $etracks[] = $tmp;
                    }

                }
                $tmpEvents[$event_id]['events_tracks'] = $etracks;
                $processTime = '';
                if(3 == $state) {
                    $start = isset($eData[$k]['start']) ? $eData[$k]['start'] : '';
                    $end = isset($eData[$k]['end']) ? $eData[$k]['end'] : '';
                    $receipt = isset($eData[$k]['receipt']) ? $eData[$k]['receipt'] : '';
                    $startTime = $start ? strtotime($start):'';
                    $endTime = $end ? strtotime($end) : '';
                    $receiptTime = $receipt ? strtotime($receipt) : '';
                    $reportTime = $reportDate ? strtotime($reportDate) : '';
                    //处理时间:完成-开始处理
                    if($process_time || ($startTime && $endTime>=$startTime)) {
                        $pTime = $process_time ? $process_time : $endTime - $startTime;
                        $processTime = $pTime > 0 ? round($pTime / 60) : 0;
                        $processTimeArr[] = $processTime;
                        $topProcessTime[$event_id] = $processTime;
                    }

                    //响应时间:接单-上报
                    if($response_time || ($receipt && $receiptTime>=$reportTime)) {
                        $rTime = $response_time ? $response_time : $receiptTime - $reportTime;
                        $responseTime = $rTime > 0 ? round($rTime / 60) : 0;
                        $responseTimeArr[] = $responseTime;
                        $topResponseTime[$event_id] = $responseTime;
                    }

                    //到场时间:开始处理-上报(2018.11.08前的算法)
                    //路程时间:开始处理-接单
                    if($distance_time || ($receiptTime && $startTime>=$receiptTime)) {
                        $dTime = $distance_time ? $distance_time : $startTime - $receiptTime;
                        $arrivalTime = $dTime > 0 ? round($dTime / 60) : 0;
                        $arrivalTimeArr[] = $arrivalTime;
                        $topArrivalTime[$event_id] = $arrivalTime;
                    }
                    if(5 != $star_level && $star_level > 0) {
                        $notPraise[$event_id] = $star_level;
                    }

                }

                $eData[$k]['processTime'] = $processTime;

            }
//            var_dump($events);exit;
        }
        $events['result'] = $eData;//array_values($eData);


        $topProcess = array();
        $topArrival = array();
        $topResponse = array();
        $notPraiseData = array();

        //本周最长到场时间 TOP 3事件
        $top2 = 0;
        arsort($topArrivalTime,SORT_REGULAR);
        foreach($topArrivalTime as $k=>$v){
            if ($top2 < 3) {
                if (isset($tmpEvents[$k])) {
                    $topArrival[$top2] = $tmpEvents[$k];
                }
                $topArrival[$top2]['timeUse'] = $v;
            }
            $top2++;

        }
        $events['topArrival'] = $topArrival;

        //本周最长响应时间 TOP 3事件
        $top1 = 0;
        arsort($topResponseTime,SORT_REGULAR);
        foreach($topResponseTime as $k=>$v){
            if($top1 < 3) {
                if (isset($tmpEvents[$k])) {
                    $topResponse[$top1] = $tmpEvents[$k];
                }
                $topResponse[$top1]['timeUse'] = $v;
            }
            $top1++;

        }
        $events['topResponse'] = $topResponse;

        //本周最长处理时间 TOP 3事件
        $top3 = 0;
        arsort($topProcessTime,SORT_REGULAR);
        foreach($topProcessTime as $k=>$v) {
            if ($top3 < 3) {
                if (isset($tmpEvents[$k])) {
                    $topProcess[$top3] = $tmpEvents[$k];
                }
                $topProcess[$top3]['timeUse'] = $v;
            }
            $top3++;
        }
//        var_dump($topProcess);exit;
        $events['topProcess'] = $topProcess;

//        var_dump($notPraise,$tmpEvents);exit;
        $npNum = 0;
        foreach($notPraise as $k=>$v) {
//            var_dump($tmpEvents[$npk]);
            if(isset($tmpEvents[$k])) {
                $notPraiseData[$npNum] = $tmpEvents[$k];
            }
            $notPraiseData[$npNum]['star_level'] = $v;
            $npNum++;
        }
        $events['notPraise'] = $notPraiseData;

        unset($tmpEvents);



        //本周共处理事件
        $allCount = count($eData);
        $events['allCount'] = $allCount;
        //完成事件
        $endCount = count($endArr);
        $events['endCount'] = $endCount;
        //关闭事件
        $events['closeCount'] = count($closeArr);
        //完成事件中获得好评
        $endGoodCount = count($endGoodArr);
        $events['endGoodCount'] = $endGoodCount;
        //好评率=好评/完成事件
        $endGoodRate = $endCount ? round($endGoodCount/$endCount*100,2) : 0;
        $events['endGoodRate'] = $endGoodRate.'%';
        //处理事件共计耗时(分钟)
        $totalProcess = array_sum($processTimeArr);
        $events['totalProcess'] = $totalProcess;
        //平均每天处理事件:总事件/总天数
        $events['avgDay'] = $dayTotal ? ceil($endCount/$dayTotal) : 0;
        //平均每天处理时间耗时(分钟):总耗时/总天数
        $events['avgDayTime'] = $dayTotal ? ceil($totalProcess/$dayTotal) : 0;
        $events['dayTotal'] = $dayTotal;

        //平均响应时间:分钟/事件
        $totalResponse = array_sum($responseTimeArr);
        $events['avgResponse'] = $endCount ? ceil($totalResponse/$endCount) : 0;
        //平均到场时间:分钟/事件
        $totalArrival = array_sum($arrivalTimeArr);
        $events['avgArrival'] = $endCount ? ceil($totalArrival/$endCount) : 0;
        //平均处理时间:分钟/事件
        $events['avgProcess'] = $endCount ? ceil($totalProcess/$endCount) : 0;


        return $events;
    }


    /**
     * 年度终端运维事件处理时效
     * @return array
     */
    public function getYearProcessPrescription(){
        $yearWeeks = getYearWeeks();
        $events = array();
        if($yearWeeks) {
            foreach ($yearWeeks as $week => $seTime) {
//            var_dump($seTime);
//            $seTime = isset($yearWeeks[$week]) ? $yearWeeks[$week] : '';
                $start = isset($seTime['start']) ? $seTime['start'] : '';
                $end = isset($seTime['end']) ? $seTime['end'] : '';

                if (is_null($start) && is_null($end)) {
                    throw new ApiException(Code::ERR_WEEK_TIME);
                }
                $where = array('source' => 3, 'state' => 3);
                $between = [$start, $end];
                $all = $this->model->where($where)->whereBetween("created_at", $between)->get();//$this->usePage($this->model);
                $eventsArr = $all->toArray();
//        var_dump($eventsArr);

                $eventIDs = array();
                $processTimeArr = array();
                $responseTimeArr = array();
                $arrivalTimeArr = array();
                $eData = array();

                if ($eventsArr) {
                    foreach ($eventsArr as $v) {
                        $id = isset($v['id']) ? $v['id'] : '';
                        $eventIDs[] = $id;
                    }
//            var_dump($eventIDs);exit;
                    $eventIDs = array_filter(array_unique($eventIDs));
                    $etRes = array();
                    if ($eventIDs) {
                        $etRes = $this->eventstrack->getListByEventIds('event_id', $eventIDs);
                        $etRes = $etRes->toArray();
//                var_dump($etRes);exit;
                    }
                    foreach ($eventsArr as $k => $v) {
                        $event_id = isset($v['id']) ? $v['id'] : 0;
                        $eData[$k]['id'] = $event_id;
                        $state = isset($v['state']) ? $v['state'] : '';
                        $created_at = isset($v['created_at']) ? $v['created_at'] : '';
                        $reportDate = isset($v['report_at']) ? $v['report_at'] : '';
                        $process_time = isset($v['process_time']) ? $v['process_time'] : '';
                        $response_time = isset($v['response_time']) ? $v['response_time'] : '';
                        $distance_time = isset($v['distance_time']) ? $v['distance_time'] : '';

                        foreach ($etRes as $vv) {
                            $eid = isset($vv['event_id']) ? $vv['event_id'] : 0;
                            if ($event_id == $eid) {
                                $step = isset($vv['step']) ? $vv['step'] : 0;
                                if (1 == $step) {
                                    $eData[$k]['receipt'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                                } elseif (2 == $step) {
                                    $eData[$k]['start'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                                } elseif (3 == $step) {
                                    $eData[$k]['end'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                                } elseif (4 == $step) {
                                    $eData[$k]['close'] = isset($vv['created_at']) ? $vv['created_at'] : '';
                                }
                            }
                        }
                        if (3 == $state) {

                            $start = isset($eData[$k]['start']) ? $eData[$k]['start'] : '';
                            $end = isset($eData[$k]['end']) ? $eData[$k]['end'] : '';
                            $receipt = isset($eData[$k]['receipt']) ? $eData[$k]['receipt'] : '';
                            $startTime = $start ? strtotime($start):'';
                            $endTime = $end ? strtotime($end) : '';
                            $receiptTime = $receipt ? strtotime($receipt) : '';
                            $reportTime = $reportDate ? strtotime($reportDate) : '';


                            //处理时间:完成-开始处理
                            if($process_time || ($startTime && $endTime>=$startTime)) {
                                $pTime = $process_time ? $process_time : $endTime - $startTime;
                                $processTime = $pTime > 0 ? round(($pTime) / 60) : 0;
                                $processTimeArr[] = $processTime;
                            }

                            //响应时间:接单-上报
                            if($response_time || ($reportTime && $receiptTime>=$reportTime)) {
                                $rTime = $response_time ? $response_time : $receiptTime - $reportTime;
                                $responseTime = $rTime > 0 ? round(($rTime) / 60) : 0;
                                $responseTimeArr[] = $responseTime;
                            }

                            //到场时间:开始处理-上报(2018.11.08前的算法)
                            //路程时间:开始处理-接单
                            if($distance_time || ($receiptTime && $startTime>=$receiptTime)) {
                                $dTime = $distance_time ? $distance_time : $startTime - $receiptTime;
                                $arrivalTime = $dTime>0 ? round(($dTime) / 60) : 0;
                                $arrivalTimeArr[] = $arrivalTime;
                            }

                        }
                    }
                }
//        var_dump($eData);exit;
                //本周共处理事件
                $allCount = count($eData);
                //处理事件共计耗时(分钟)
                $totalProcess = array_sum($processTimeArr);
                //平均响应时间:分钟/事件
                $totalResponse = array_sum($responseTimeArr);
//        $events['avgResponse'] = ceil($totalResponse/$allCount);
                //平均到场时间:分钟/事件
                $totalArrival = array_sum($arrivalTimeArr);
//        $events['avgArrival'] = ceil($totalArrival/$allCount);
                //平均处理时间:分钟/事件
//        $events['avgProcess'] = ceil($totalProcess/$allCount);

                $events[] = array(
                    'week' => $week,
//                    'allCount' => $allCount,
//                    'totalProcess' => $totalProcess,
                    'avgResponse' => $allCount ? ceil($totalResponse / $allCount) : 0,
                    'avgArrival' => $allCount ? ceil($totalArrival / $allCount) : 0,
                    'avgProcess' => $allCount ? ceil($totalProcess / $allCount) : 0
                );
            }
        }

        return $events;
    }

    public function getMaintain($request) {
        //维护处理时间
        $model = $this->model->join("events_track as A","workflow_events.id","=","A.event_id")
            ->join("workflow_maintain as B","workflow_events.id","=","B.event_id")
            ->join("assets_device as C","workflow_events.asset_id","=","C.id")
            ->where("A.step","=",2)
            ->where(["source" => Event::SRC_TERMINAL])->where("workflow_events.state" ,"=", Event::STATE_END)
            ->whereIn("C.category_id",\ConstInc::$terminalCategory)
            ->groupBy("solution_id")->select("solution_id",DB::raw("ceil(sum(workflow_events.process_time)/60) as times, count(solution_id) as cnt"));

        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("workflow_events.updated_at", $between);
        }

        $ret = $model->get()->keyBy("solution_id")->toArray();

        $solutions = $this->solutionModel->with("wrong")->get()->groupBy("wrong_id");

        $data = [];
        foreach($solutions as $wrong_id => $v) {
            $current = [
                "wrong_id" => $wrong_id,
                "wrong"  => $v[0]->wrong->name,
                "cnt" => 0,
                "times" => 0,
                "solutions" => []
            ];
            $current_solution = [];
            $current_cnt = 0;
            $current_times = 0;
            foreach($v as $vv) {
                $cnt = isset($ret[$vv->id]) ? $ret[$vv->id]['cnt'] : 0;
                $times = isset($ret[$vv->id]) ? $ret[$vv->id]['times'] : 0;
                $current_solution[] = [
                    "solution_id" => $vv->id,
                    "solution" => $vv->name,
                    "cnt" => $cnt,
                    "times" => $times
                ];
                $current_cnt += $cnt;
                $current_times += $times;
            }
            $current["solutions"] = $current_solution;
            $current["times"] = $current_times;
            $current["cnt"] = $current_cnt;
            $data[] = $current;
        }
        return $data;
    }

    public function getMaintainDetail($request) {
        $solutionId = $request->input("solutionId");
        $solution = $this->solutionModel->find($solutionId);
        if(empty($solution)) {
            Code::setCode(Code::ERR_PARAMS, ["solutionId错误"]);
            return false;
        }

        $model = $this->model->join("events_track as A","workflow_events.id","=","A.event_id")
            ->join("workflow_maintain as B","workflow_events.id","=","B.event_id")
            ->join("assets_device as C","workflow_events.asset_id","=","C.id")
            ->where("B.solution_id","=",$solutionId)
            ->where("A.step","=",2)
            ->where(["source" => Event::SRC_TERMINAL])->whereIn("workflow_events.state" , [Event::STATE_END,Event::STATE_CLOSE])
            ->whereIn("C.category_id",\ConstInc::$terminalCategory)
            ->groupBy("user_id")->select("user_id", DB::raw("sum(timestampdiff(minute,A.updated_at, workflow_events.updated_at)) as times, count(*) as cnt"));

        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("workflow_events.updated_at", $between);
        }

        $ret = $model->get();
        $data = [];
        foreach($ret as $v) {
            $data[] = [
                "user_id" => $v->user_id,
                "times" => $v->times,
                "cnt" => $v->cnt,
                "username" => $v->user->username
            ];
        }

        return ["solution_id" => $solutionId, "solution" => $solution->name, "result" => $data];
    }


    /**
     * 获取终端数据统计,终端事件分类统计
     * @param array $request
     * @return array
     */
    public function getTerminalEvent($request=array()) {
        $events = array();
        $inputBegin = $request->input("begin");
        $inputEnd = $request->input("end");
        /*$sDate = '';
        if($inputBegin){
            $sDate = date('Y-m-01', strtotime($inputBegin));
        }else{
            throw new ApiException(Code::ERR_START_DATE_EMPTY);
        }

        $eDate = $inputEnd ? date('Y-m-t 23:59:59', strtotime($inputEnd)) : date('Y-m-t 23:59:59');*/

        /*$this->model = $this->model->where('source',3);
        $this->model = $this->model->whereIn('state',array(3));
        $this->model = $this->model->whereBetween("created_at", $between);
        $eventsArr = $this->model->get();*/
        /*$where = array(
            'source' => 3,
            'state' => 3
        );
        $eventsArr = $this->model->where($where)->whereBetween("created_at", $between)->get();*/
        $seDate = array('start'=>$inputBegin,'end'=>$inputEnd);
        $seDateArr = getTimeSlotByDate($seDate);
//        var_dump(count($seDateArr),$seDateArr);exit;
        $where = array('wrong_id'=>5);
        $solutions = $this->solutionModel->getListByWhere($where);
        $ret = array();
        $monthTotal = array();
        $monthData = array();
        $retTypeTotal = '';
        $retType = array();
        $retTypeTemp = array();
        $retTypeTimeTotal = '';
        if($seDateArr) {
            if(count($seDateArr) >12){
                throw new ApiException(Code::ERR_MAX_YEAR);
            }
//            var_dump($solutions);exit;
            foreach($seDateArr as $k=>$between) {
                $tEventMonth = $this->getTerminalEventByMonth($between);
                if(!empty($tEventMonth)){
                    $ret[$k] = $tEventMonth;
                }
            }
//            var_dump(json_encode($ret,true));exit;
            if(!empty($ret) && is_array($ret)) {
                $countArr = array();
                foreach ($ret as $ek=>$eArr) {
                    $cntArr = array();
                    foreach($eArr as $kk=>$vv) {
                        $solution_id = isset($vv['solution_id']) ? $vv['solution_id'] : '';
                        if ($solutions) {
                            foreach ($solutions as $sv) {
                                $stid = isset($sv['id']) ? $sv['id'] : '';
                                $stname = isset($sv['name']) ? $sv['name'] : '';
                                if ($stid == $solution_id) {
                                    $eArr[$kk]['solution_name'] = $stname;
                                }
                            }
                        }
                        if(isset($retTypeTemp[$solution_id])){
                            $retTypeTemp[$solution_id][] = $eArr[$kk];
                        }else{
                            $retTypeTemp[$solution_id][] = $eArr[$kk];
                        }
                        $cntArr[] = isset($vv['cnt']) ? $vv['cnt'] : '';
//                        $tmpArr[$ek][$kk]['solution_name'] = '';
                        $monthData[$ek]['list'] = $eArr;
                    }
                    $count = array_sum($cntArr);
                    $countArr[] = $count;
                    $monthData[$ek]['count'] = $count;
                }
                $monthTotal = array_sum($countArr);
            }

        }
        //var_dump($retTypeTemp);exit;
//        return $retTypeTemp;
        if($retTypeTemp){
            $num = 0;
            $countArr = array();
            $timeTotal = array();
            foreach($retTypeTemp as $k=>$vArr){
                $cntArr = array();
                $timesArr = array();
                foreach($vArr as $v) {
                    $cntArr[] = isset($v['cnt']) ? $v['cnt'] : '';
                    $timesArr[] = isset($v['times']) ? $v['times'] : '';
                    $solution_name = isset($v['solution_name']) ? $v['solution_name'] : '';
                }
                $count = array_sum($cntArr);
                $countArr[] = $count;
                $timect = array_sum($timesArr);
                $timeTotal[] = $timect;
                $retType[$num]['solution_id'] = $k;
                $retType[$num]['solution_name'] = $solution_name;
                $retType[$num]['time_count'] = $timect;
                $retType[$num]['count'] = $count;
                $num++;
            }
            $retTypeTotal = array_sum($countArr);
            $retTypeTimeTotal = array_sum($timeTotal);
        }

        $events['retType'] = $retType;
        $events['retTypeTotal'] = $retTypeTotal;
        $events['retTypeTimeTotal'] = $retTypeTimeTotal;
        $events['month'] = $monthData;
        $events['monthTotal'] = $monthTotal;
        $events['solutions'] = $solutions;

        return $events;
    }


    /**
     * 根据时间段获取终端完成事件
     * @param array $between array('2018-01-01','2018-01-31 23:59:59')
     * @return array
     */
    private function getTerminalEventByMonth($between=array()){
        $ret = array();
        if($between) {
            $model = $this->model->join("workflow_maintain as M", "workflow_events.id", "=", "M.event_id")
                ->where("M.wrong_id", "=", 5)
                ->where(["source" => Event::SRC_TERMINAL])->where("workflow_events.state", "=", Event::STATE_END)
                ->groupBy("solution_id")
                ->select("solution_id", DB::raw("sum(timestampdiff(minute,workflow_events.created_at,workflow_events.updated_at)) as times, count(workflow_events.id) as cnt"));
            $model->whereBetween("workflow_events.created_at", $between);
            $ret = $model->get();
            $ret = $ret->toArray();
        }
        return $ret;
    }


    public function getEngineers($request) {
        $engineers = $this->userRepository->getEngineers(true, true);

        $data = $this->model->with('category',"user","assigner","asset")
            ->where("user_id","!=",0)
            ->whereIn("workflow_events.state" ,[Event::STATE_ACCESS, Event::STATE_WAIT, Event::STATE_ING])
            ->orderBy('workflow_events.created_at','desc')
            ->get();

        foreach($data as &$v) {
            $v->number = $v->asset->number;
            $category = $v->category->name;
            unset($v->category);
            $v->category = $category;

            $assigner = !empty($v->assigner)?$v->assigner->username:null;
            unset($v->assigner);
            $v->assigner = $assigner;

            $v->username = $v->user->username;

            if($v->source === 0) {
                $source_name = "我的事件";
            }
            else if ($v->source === 1) {
                $source_name = "主管分派";
            }
            else if ($v->source === 2) {
                $source_name = "监控报警";
            }
            else {
                $source_name = "终端上报";
            }
            $v->source_name = $source_name;
            unset($v->asset);
            unset($v->user);
        }
        $data = $data->groupBy("user_id")->toArray();

        $userEvents = [];
        $free = []; //空闲
        foreach($engineers as $engineer) {
            $current = [
                "user_id" => $engineer->id,
                "name" => $engineer->name,
                "username" => $engineer->username,
                "events" => []
            ];
            if(isset($data[$engineer->id])) {
                $current["events"] = $data[$engineer->id];
            }
            
            if(empty($current["events"])) {
                $free[] = $engineer->id;
            }
            $userEvents[] = $current;
        }

        //根据工种获取工程师分类
        $engineerList = $this->engineerModel->with("user")->get();
        foreach($engineerList as &$v) {
            $v->users = [];
            if(!empty($v->user)) {
                $users = [];
                foreach($v->user as $vv) {
                    $users[] = [
                        "id" => $vv->id,
                        "name" => $vv->name,
                        "username" => $vv->username,
                        "free" => in_array($vv->id, $free) ? 1: 0
                    ];
                }
                $v->users = $users;
            }
            unset($v->user);
        }


        $data = [
            "userList" => $userEvents,
            "engineerList" => $engineerList,
        ];

        return $data;
    }

}
