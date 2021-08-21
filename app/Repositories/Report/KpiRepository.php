<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Report;

use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Models\Workflow\Event;
use App\Models\Code;
use DB;

class KpiRepository extends BaseRepository
{

    protected $eventModel;
    protected $userRepository;

    public function __construct(Event $eventModel, UserRepository $userRepository){
        $this->eventModel = $eventModel;
        $this->userRepository = $userRepository;
    }

    public function calc($request) {
        $terminalCategory = \ConstInc::$terminalCategory;
        //选择终端用户的用户ID
        $users = $this->userRepository->getEngineersByCategory($terminalCategory);
        if(empty($users)) {
            return null;
        }
        $userId = [];
        $data = [];
        foreach($users as $user) {
            $userId[] = $user->id;
            $data[$user->id] = [
                "user_id" => $user->id,
                "username" => $user->username,
                "allNum" => 0, //事件总数
                "endNum" => 0, //事件完成数
                "allTime" => 0, //事件处理时间
                "avgTime" => 0, //平均个事件处理时间
                "endPer" => 0, //事件解决率
                "worktime" => 0, //标准工时偏差
                "score" => 0, //好评率
            ];
        }

        $between = $this->searchTime($request);

        //处理事件总数
        $all = $this->eventModel->where(["source" => Event::SRC_TERMINAL])
            //->whereIn("state" , [Event::STATE_END,Event::STATE_CLOSE])
            ->whereIn("user_id", $userId);
        if($between) {
            $all->whereBetween("created_at", $between);
        }
        $all = $all->groupBy("user_id")->select("user_id",DB::raw("count(1) as cnt"))
            ->get()->pluck("cnt", "user_id")->toArray();

        //事件完成
        $end = $this->eventModel->where(["source" => Event::SRC_TERMINAL, "state" => Event::STATE_END])
            ->whereIn("user_id", $userId);
        if($between) {
            $end->whereBetween("created_at", $between);
        }
        $end = $end->groupBy("user_id")->select("user_id",DB::raw("count(1) as cnt"))
            ->get()->pluck("cnt", "user_id")->toArray();

        //事件处理时间
        $time = $this->eventModel->join("events_track as A","workflow_events.id","=","A.event_id")
            ->where("A.step","=",2)
            ->where("A.etype","=",0)
            ->where(["source" => Event::SRC_TERMINAL])->whereIn("workflow_events.state" , [Event::STATE_END,Event::STATE_CLOSE]);
        if($between) {
            $time->whereBetween("workflow_events.created_at", $between);
        }
        $time = $time->whereIn("user_id", $userId)
            ->groupBy("user_id")->select("user_id",DB::raw("ceil(sum(workflow_events.process_time)/60) as cnt"))
            ->get()->pluck("cnt", "user_id")->toArray();

        //5星好评数
        $star = $this->eventModel->where(["source" => Event::SRC_TERMINAL])
            ->whereIn("state" , [Event::STATE_END,Event::STATE_CLOSE])
            ->whereIn("workflow_events.user_id", $userId)
            ->join("events_comment as A","workflow_events.id","=","A.event_id")
            ->where("A.star_level","=","5");
        if($between) {
            $star->whereBetween("workflow_events.created_at", $between);
        }
        $star = $star->groupBy("workflow_events.user_id")->select("workflow_events.user_id",DB::raw("count(1) as cnt"))
            ->get()->pluck("cnt", "user_id")->toArray();

        foreach($all as $k => $v) {
            $data[$k]['allNum'] = $v;
        }

        foreach($end as $k => $v) {
            $data[$k]['endNum'] = $v;
        }

        foreach($time as $k => $v) {
            $data[$k]['allTime'] = intval($v);
        }


        $max = [
            "allNum" => 0, //事件总数
            "endNum" => 0, //事件完成数
            "avgTime" => 0, //平均个事件处理时间
            "endPer" => 0, //事件解决率
            //"worktime" => 0, //标准工时偏差
            "score" => 0, //好评率
        ];
        foreach($data as $k => &$v) {
            $v['avgTime'] = $v['allNum'] === 0 ? 0 : round($v['allTime'] / $v['allNum'], 2);
            $v['endPer'] = $v['allNum'] === 0 ? 0 : round($v['endNum'] / $v['allNum'], 2);

            if(isset($star[$k])) {
                $v['score'] = $v['endNum'] === 0 ? 0 : round($star[$k] / $v['endNum'], 2);
            }

            if($v['allNum'] >= $max['allNum']) {
                $max['allNum'] = $v['allNum'];
            }
            if($v['endNum'] >= $max['endNum']) {
                $max['endNum'] = $v['endNum'];
            }
            if($max['avgTime'] === 0 || ($v['avgTime'] <= $max['avgTime'] && $v['avgTime'] > 0)) {
                $max['avgTime'] = $v['avgTime']; //最小值
            }
            if($v['endPer'] >= $max['endPer']) {
                $max['endPer'] = $v['endPer'];
            }
//            if($v['worktime'] >= $max['worktime']) {
//                $max['worktime'] = $v['worktime'];
//            }
            if($v['score'] >= $max['score']) {
                $max['score'] = $v['score'];
            }

        }

        //计算6维
        $dimension = [];

        unset($v);
        //dd($max);
        foreach($data as $k => $v) {
            $dimension[$k]['allNum'] = 0;
            $dimension[$k]['endNum'] = 0;
            $dimension[$k]['avgTime'] = 0;
            $dimension[$k]['endPer'] = 0;
            //$dimension[$k]['worktime'] = 10;
            $dimension[$k]['score'] = 0;
            if($max['allNum'] !== 0) {
                $dimension[$k]['allNum'] = round($v['allNum'] / $max['allNum'] * 10, 1);
            }
            if($max['endNum'] !== 0) {
                $dimension[$k]['endNum'] = round($v['endNum'] / $max['endNum'] * 10, 1);
            }
            if($max['avgTime'] != 0) {
                if($v['avgTime'] == 0) {
                    $dimension[$k]['avgTime'] = 0;
                }
                else {
                    //$dimension[$k]['avgTime'] = round((2 - $v['avgTime'] / $max['avgTime']) * 10, 1);
                    $dimension[$k]['avgTime'] = round((10 * $max['avgTime'] / $v['avgTime']) , 1);
                    if($dimension[$k]['avgTime'] < 0) {
                        $dimension[$k]['avgTime'] = 0;
                    }
                }
            }
            if($max['endPer'] != 0) {
                $dimension[$k]['endPer'] = round($v['endPer'] / $max['endPer'] * 10, 1);
            }
            if($max['score'] !== 0) {
                $dimension[$k]['score'] = round($v['score'] / $max['score'] * 10, 1);
            }

            //$result = $dimension[$k]['allNum'] + $dimension[$k]['endNum'] + $dimension[$k]['avgTime'] +
            //   $dimension[$k]['endPer'] + $dimension[$k]['worktime'] + $dimension[$k]['score'];
            $result = $dimension[$k]['allNum'] + $dimension[$k]['endNum'] + $dimension[$k]['avgTime'] +
               $dimension[$k]['endPer'] + $dimension[$k]['score'];

            $dimension[$k]['result'] = $result;
            /*
            if($result >= 55 && $result <= 60) {
                $dimension[$k]['result'] = "好";
            }
            else if ($result >= 50 && $result < 55) {
                $dimension[$k]['result'] = "中";
            }
            else {
                $dimension[$k]['result'] = "差";
            }
            */
            $data[$k]['dimension'] = $dimension[$k];
        }

        return array_values($data);
    }

}
