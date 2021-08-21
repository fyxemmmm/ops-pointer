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
use App\Models\Workflow\Oa;
use App\Models\Code;
use App\Repositories\Auth\UsersPreferencesRepository;
use App\Exceptions\ApiException;
use DB;

class SheetRepository extends BaseRepository
{

    protected $eventModel;
    protected $oaModel;
    protected $userRepository;
    protected $userPreferences;

    public $eventFields = [
        [ "sname" => "user", "cname" => "处理人", "field" => "user_id" ],
        [ "sname" => "asset", "cname" => "资产编号", "field" => "asset_id" ],
        [ "sname" => "category", "cname" => "事件类型", "field" => "category_id" ],
        [ "sname" => "source", "cname" => "事件来源", "field" => "source"],
        [ "sname" => "state", "cname" => "事件状态", "field" => "state"],
        [ "sname" => "responseTime", "cname" => "响应时间", "field" => "response_time"],
        [ "sname" => "distanceTime", "cname" => "路程时间", "field" => "distance_time"],
        [ "sname" => "processTime", "cname" => "处理时间", "field" => "process_time"],
    ];

    public $oaFields = [
        [ "sname" => "user", "cname" => "处理人", "field" => "user_id" ],
        [ "sname" => "object", "cname" => "事件对象", "field" => "object" ],
        [ "sname" => "category", "cname" => "事件类型", "field" => "category_id" ],
        [ "sname" => "source", "cname" => "事件来源", "field" => "source"],
        [ "sname" => "state", "cname" => "事件状态", "field" => "state"],
        [ "sname" => "responseTime", "cname" => "响应时间", "field" => "response_time"],
        [ "sname" => "distanceTime", "cname" => "路程时间", "field" => "distance_time"],
        [ "sname" => "processTime", "cname" => "处理时间", "field" => "process_time"],
    ];

    public $eventTime  = [
        [ "sname" => "day", "cname" => "天"],
        [ "sname" => "week", "cname" => "周"],
        [ "sname" => "month", "cname" => "月"],
        [ "sname" => "year", "cname" => "年"],
    ];
    const EVENT_FIELD = "event_sheets";
    const OA_FIELD = "oa_sheets";


    public function __construct(Event $eventModel,
                                Oa $oaModel,
                                UserRepository $userRepository,
                                UsersPreferencesRepository $userPreferencesRepository){
        $this->eventModel = $eventModel;
        $this->oaModel = $oaModel;
        $this->userRepository = $userRepository;
        $this->userPreferences = $userPreferencesRepository;
    }

    public function getReportChart($request) {
        if($request->input("sheet","event") == "oa") {
            $preference = $this->userPreferences->getPreferences(self::OA_FIELD);
            $eventFields = $this->oaFields;
        }
        else {
            $preference = $this->userPreferences->getPreferences(self::EVENT_FIELD);
            $eventFields = $this->eventFields;
        }

        foreach($eventFields as &$v) {
            $v['checked'] = false;
        }

        $data = [
            "2d" => [
                "type" => $eventFields
            ],
            "3d" => [
                "time" => $this->eventTime,
                "type" => $eventFields
            ]
        ];
        if(!empty($preference)) {
            foreach($data as $k => &$v){
                if(isset($preference[$k])) {
                    foreach($v as $kk => &$vv) {
                        if(isset($preference[$k][$kk])) {
                            foreach($vv as &$field) {
                                $key = $field["sname"];
                                if(is_array($preference[$k][$kk]) && in_array($key, $preference[$k][$kk])) {
                                    $field["checked"] = true;
                                }
                                else if($key == $preference[$k][$kk]){
                                    $field["checked"] = true;
                                }
                                else {
                                    $field["checked"] = false;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $data;
    }

    public function postReportChart($request) {
        $data = $request->input("content");
        $sheet = $request->input("sheet","event");
        if(is_null($data)) {
            Code::setCode(Code::ERR_PARAMS, null, ["content"]);
            return false;
        }

        if(!isset($data["2d"]) || !isset($data['3d'])) {
            Code::setCode(Code::ERR_PARAMS, null, ["content"]);
            return false;
        }

        if($sheet == "oa") {
            $this->userPreferences->setPreferences(self::OA_FIELD, $data);
        }
        else {
            $this->userPreferences->setPreferences(self::EVENT_FIELD, $data);
        }
    }


    public function genChart($request) {
        $time = $request->input("time");
        $sheet = $request->input("sheet","event");
        $field = $this->getField($request->input("type"), $sheet);

        $tbl = ($sheet == "oa")?"workflow_oa":"workflow_events";
        $model = $this->preProcess($request, $tbl);

        if(!empty($time)) {
            $data = $this->gen3D($model, $field, $tbl, $request);
        }
        else {
            $data = $this->gen2D($model, $field, $tbl, $request);
        }

        return $data;
    }

    /**
     * 数据库预处理
     * @param $request
     * @param $tbl
     * @return $model
     */
    protected function preProcess($request, $tbl) {
        $state = $request->input("state");
        $source = $request->input("source");
        $assetIds = $request->input("assetIds");
        $actCategory = $request->input("actCategory");
        $users = $request->input("users");
        $responseTime = $request->input("responseTime");
        $processTime = $request->input("processTime");
        $object = $request->input("object");
        $distanceTime = $request->input("distanceTime");
        $company = $request->input("company");


        $where = [];

        if(!is_null($state)) {
            $where[] = [$tbl.".state","=", $state];
        }
        if(!is_null($source)) {
            $where[] = [$tbl.".source","=", $source];
        }
        if(!is_null($company)){
            $where[] = [$tbl.".company","like",'%'.$company.'%'];
        }

        if($tbl === "workflow_oa") {
            $model = $this->oaModel->with('category',"user")->where($where);
            $objectArr = array_filter(array_unique(explode(',',$object)));
            if($objectArr){
                $model = $model->whereIn($tbl.".object",$objectArr);
            }
        }
        else {
            $model = $this->eventModel->with('category',"user","asset")->where($where);
            $assetIdArr = array_filter(array_unique(explode(',',$assetIds)));
            if($assetIdArr){
                $model = $model->whereIn($tbl.".asset_id",$assetIdArr);
            }
            $model->leftJoin("assets_device as A","$tbl.asset_id","=","A.id");
        }

        $actCategoryArr = array_filter(array_unique(explode(',',$actCategory)));
        if($actCategoryArr){
            $model = $model->whereIn($tbl.".category_id",$actCategoryArr);
        }

        $usersArr = array_filter(array_unique(explode(',',$users)));
        if($usersArr){
            $model = $model->whereIn($tbl.".user_id",$usersArr);
        }

        //搜索资产名称，IP，资产编号，事件处理人
        $model->leftJoin("users as B","$tbl.user_id","=", "B.id");


        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("$tbl.updated_at", $between);
        }

        if($responseTime){
            $response = Event::$timeArr[$responseTime];
            if(Event::TIME_5 == $responseTime){
                $tmpWhere[] = ["$tbl.response_time",">=" ,$response];
                $model->where($tmpWhere);
            }else{
                $tmpWhere[] = ["$tbl.response_time",">=" ,$response[0]];
                $tmpWhere[] = ["$tbl.response_time","<" ,$response[1]];
                $model->where($tmpWhere);
//                $model->whereBetween("$tbl.response_time", $response);
            }
        }
        if($processTime){
            $process = Event::$timeArr[$processTime];
            if(Event::TIME_5 == $processTime){
                $tmpWhere[] = ["$tbl.process_time",">=" ,$process];
                $model->where($tmpWhere);
            }else {
                $tmpWhere[] = ["$tbl.process_time",">=" ,$process[0]];
                $tmpWhere[] = ["$tbl.process_time","<" ,$process[1]];
                $model->where($tmpWhere);
//                $model->whereBetween("$tbl.process_time", $process);
            }
        }

        //路程时间
        if($distanceTime){
            $distance = Oa::$timeArr[$distanceTime];
            if(Oa::TIME_5 == $distanceTime){
                $tmpWhere[] = ["$tbl.distance_time",">=" ,$distance];
                $model->where($tmpWhere);
            }else {
                $tmpWhere[] = ["$tbl.distance_time",">=" ,$distance[0]];
                $tmpWhere[] = ["$tbl.distance_time","<" ,$distance[1]];
                $model->where($tmpWhere);
//                $model->whereBetween("$tbl.distance_time", $distance);
            }
        }

        return $model;
    }


    /**
     * 2D图
     * @param $model
     * @param $field
     * @param $tbl
     * @return array
     */
    protected function gen2D($model, $field, $tbl, $request) {
        if(!in_array($field, ["process_time", "response_time","distance_time"])) {
            $model->groupBy("$tbl.$field");
            $result = $model->select(DB::raw("count($tbl.id) as total"),"$tbl.$field")->get();
            $data = [];

            foreach($result as $v) {
                $name = $this->transform($v, $field);
                if(is_null($name)) {
                    $name = "[无]";
                }
                $data[] = [
                    "name" => $name,
                    "cnt" => $v->total
                ];
            }
        }
        else {  //处理时间等另外处理
            $timeArr = Event::$timeArr;
            $someTime = $request->input($request->input("type"));
            $caseSql = [];
            foreach($timeArr as $k => $v) {
                if(!empty($someTime) && $k != $someTime) {
                    continue;
                }
                $msg = Event::$timeArrMsg[$k];
                if(is_array($v)) {
                    $caseSql[] = "sum(CASE WHEN $field >= {$v[0]} and $field < {$v[1]} THEN 1 ELSE 0 END) as $msg";
                }
                else {
                    $caseSql[] = "sum(CASE WHEN $field >= $v THEN 1 ELSE 0 END) as $msg ";
                }
            }

            $result = $model->select(DB::raw(join(",",$caseSql)))->first();
            $data = [];
            foreach($timeArr as $k => $v) {
                $name = Event::$timeArrMsg[$k];
                if(!is_null($result->$name)) {
                    $data[] = [
                        "name" => $name,
                        "cnt" => $result->$name
                    ];
                }
            }
        }
        return $data;
    }

    /**
     * 3D图
     * @param $model
     * @param $field
     * @param $tbl
     * @return array
     */
    protected function gen3D($model, $field, $tbl, $request) {
        $time = $request->input("time");
        $fmt = "";
        switch($time) {
            case "day":
                $fmt = "date_format($tbl.updated_at,'%Y-%m-%d') as day";
                break;
            case "week":
                $fmt = "date_format($tbl.updated_at,'%Y年%u周') as day";
                break;
            case "month":
                $fmt = "date_format($tbl.updated_at,'%Y年%m月') as day";
                break;
            case "year":
                $fmt = "date_format($tbl.updated_at,'%Y') as day";
                break;
            default:
                throw new ApiException(Code::ERR_PARAMS, ["time"]);
        }

        if(!in_array($field, ["process_time", "response_time","distance_time"])) {
            $model->groupBy(["day","$tbl.$field"]);
            $result = $model->select(
                DB::raw("count($tbl.id) as total"),
                "$tbl.$field",
                DB::raw($fmt)
            )->get();
            $data = [];
            foreach($result as $v) {
                $name = $this->transform($v, $field);
                if(is_null($name)) {
                    $name = "[无]";
                }
                $data[] = [
                    "name" => $name,
                    "time" => $v->day,
                    "cnt" => $v->total
                ];
            }
        }
        else {  //处理时间等另外处理
            $timeArr = Event::$timeArr;
            $caseSql = [];
            $someTime = $request->input($request->input("type"));
            $model->groupBy(["day"]);
            foreach($timeArr as $k => $v) {
                if(!empty($someTime) && $k != $someTime) {
                    continue;
                }
                $msg = Event::$timeArrMsg[$k];
                if(is_array($v)) {
                    $caseSql[] = "sum(CASE WHEN $field >= {$v[0]} and $field < {$v[1]} THEN 1 ELSE 0 END) as $msg";
                }
                else {
                    $caseSql[] = "sum(CASE WHEN $field >= $v THEN 1 ELSE 0 END) as $msg ";
                }
            }

            $result = $model->select(
                DB::raw(join(",",$caseSql)),
                DB::raw($fmt)
            )->get();
            $data = [];
            foreach($result as $row) {
                foreach($timeArr as $k => $v) {
                    $name = Event::$timeArrMsg[$k];
                    if(!is_null($row->$name)) {
                        $data[] = [
                            "name" => $name,
                            "time" => $row->day,
                            "cnt" => $row->$name
                        ];
                    }
                }
            }
        }

        //数组输出优化
        $result = [];
        $times = [];
        $names = [];
        foreach($data as $v) {
            if (!in_array($v['name'], $names)) {
                $names[] = $v['name'];
            }
            if (!in_array($v['time'], $times)) {
                $times[] = $v['time'];
            }
            if(!isset($result[$v['time']])) {
                $result[$v['time']] = [];
            }

            $result[$v['time']][$v['name']] = $v['cnt'];

        }

        $values = array_fill_keys($names, []);
        foreach($names as $name) {
            foreach($result as &$v) {
                if(!isset($v[$name])) {
                    $values[$name][] = 0;
                }
                else {
                    $values[$name][] = $v[$name];
                }
            }
        }

        $data = [
            "times" => $times,
            "values" => $values
        ];
        return $data;
    }

    protected function getField($type, $sheet) {
        $fields = ($sheet == "oa")?$this->oaFields:$this->eventFields;
        foreach($fields as $v) {
            if($v['sname'] === $type) {
                return $v['field'];
            }
        }
        throw new ApiException(Code::ERR_PARAMS, ["type"]);
    }

    protected function transform($record, $field) {
        switch($field) {
            case "user_id" :
                if($record->user_id === 0) {
                    return "[无]";
                }
                return $record->user->username;
            case "category_id" :
                if(empty($record->category_id)) {
                    return "[无]";
                }
                return $record->category->name;
            case "asset_id":
                if(empty($record->asset_id)) {
                    return "[无]";
                }
                return $record->asset->number."(".$record->asset->name.")";
            case "object":
                return isset(Oa::$objectMsg[$record->object])?Oa::$objectMsg[$record->object]:"[无]";
            case "state":
                return Event::$stateMsg[$record->state];
            case "source":
                return Event::$sourceMsg[$record->source];
        }
    }


}
