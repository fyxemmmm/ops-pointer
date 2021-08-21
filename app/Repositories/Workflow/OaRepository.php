<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Workflow;

use App\Models\Workflow\Category;
use App\Models\Workflow\Oa;
use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Models\Code;
use Illuminate\Http\Request;
use DB;
use Auth;

class OaRepository extends BaseRepository
{

    protected $userRepository;
    protected $categoryModel;

    public function __construct(Oa $oaModel, UserRepository $userRepository, Category $categoryModel)
    {
        $this->model = $oaModel;
        $this->categoryModel = $categoryModel;
        $this->userRepository = $userRepository;
    }

    /**
     * 用户上报
     * @param $input
     * @return bool
     */
    public function wxReport($input=array()) {
        if (!$this->userRepository->isNormal() && !$this->userRepository->isUserLeader()) {
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        $sessuInfo = Auth::user();
        $date = date("Y-m-d H:i:s");
        $insert = [
            "user_id" => 0,
            "assigner_id" => 0,
            "device_name" => getKey($input, "device_name"),
            "report_name" => getKey($input,"report_name"),
            "report_id" => isset($sessuInfo['id']) ? $sessuInfo['id'] : 0,
            "mobile" => getKey($input,"mobile"),
            "state" => Oa::STATE_WAIT,
            "company" => getKey($input,"company"),
            "location" => getKey($input,"location"),
            "problem" => getKey($input,"problem"),
            "created_at" => $date,
            "updated_at" => $date,
            "source" => Oa::SRC_TERMINAL,
            "report_at" => $date,
        ];

        $userId = getKey($input,"user_id",0);
        //用户主管派发
        if(!empty($userId)) {
            if (!$this->userRepository->isUserLeader()) {
                Code::setCode(Code::ERR_EVENT_ASSIGN);
                return false;
            }

            if (!$this->userRepository->isEngineer($userId) && !$this->userRepository->isManager($userId)) {
                Code::setCode(Code::ERR_OAEVENT_PROCESSER_NO_AUTHORITY);
                return false;
            }

            $assignerId = $this->userRepository->getUser()->id;
            $insert['user_id'] = $userId;
            $insert['assigner_id'] = $assignerId;
        }
        $model = $this->store($insert);
        userlog("用户通过微信上报OA事件,事件ID：".$model->id);
        return $model;
    }

    /**
     * 微信工程师自建或者主管分派
     * @param $input
     * @return bool
     */
    public function wxAdd($input) {
        if (!$this->userRepository->isEngineer() && !$this->userRepository->isManager()) {
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        $uid = $this->userRepository->getUser()->id;
        $uid = $uid ? $uid : 0;
        $report_at = getKey($input,"report_at",'');
        $report_atTime = $report_at ? strtotime($report_at) : '';
        $date = date("Y-m-d H:i:s");
        $currentTime = strtotime($date);

        //响应时间=接单-上报
        $response_time = $report_atTime && $currentTime > $report_atTime ?($currentTime - $report_atTime):0;

        $insert = [
            "device_name" => getKey($input, "device_name"),
            "report_name" => getKey($input,"report_name"),
            "mobile" => getKey($input,"mobile"),
            "company" => getKey($input,"company"),
            "state" => Oa::STATE_ACCEPT,
            "location" => getKey($input,"location"),
            "problem" => getKey($input,"problem"),
            "report_at" => $report_at,
            "accept_at" => $date,
            "created_at" => $date,
            "updated_at" => $date,
            "source" => Oa::SRC_SELF,
            "user_id" => $uid,
            "assigner_id" => $uid,
            "response_time" => $response_time
        ];

        $userId = getKey($input,"user_id");
        if(!empty($userId)) {
            if(!$this->userRepository->isManager()) { //主管才可分派
                Code::setCode(Code::ERR_EVENT_USER);
                return false;
            }

            $insert['user_id'] = $userId;
            $insert['source'] = Oa::SRC_ASSIGN;
//            $insert['assigner_id'] = $uid;
            if($userId != $uid) {
                $insert['state'] = Oa::STATE_WAIT;
            }
        }
        $model = $this->store($insert);
        userlog("工程师通过微信上报OA事件,事件ID：".$model->id);
        return $model;
    }

    /**
     * 获取事件处理详情
     * @param $input
     * @return mixed
     */
    public function wxProcess($input) {
        $id = getKey($input, "id");
        $event = $this->getById($id);
        return $event;
    }

    /**
     * 主管分派
     * @param array $input
     * @return array|bool
     */
    public function wxAssign($input=array(),$event=array()) {
        $id = getKey($input, "id");
        $assignerId = $this->userRepository->getUser()->id;
        $userId = getKey($input,"user_id");
        if(!$event){
            $event = $this->getById($id);
        }

        if($event->state !== Oa::STATE_WAIT) {
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        if(!$this->userRepository->isManager()) { //主管才可分派
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        if(!empty($event->user_id)) { //已派单状况
            Code::setCode(Code::ERR_ALREADY_ASSIGN);
            return false;
        }

        $update = array(
            "assigner_id"   => $assignerId,
            "user_id"       => $userId,
            "updated_at" => date("Y-m-d H:i:s"),
        );

        //更新事件状态
        $this->update($id, $update);
        userlog("工程师通过微信派单  OA事件ID：$id");

        return $update;
    }


    /**
     * 工程师微信接单
     * @param $input
     * @return bool
     */
    public function wxAccept($input=array(),$event=array()) {
        $id = getKey($input, "id");
        if(!$event){
            $event = $this->getById($id);
        }
        if($event->state !== Oa::STATE_WAIT) {
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }
        $userId = $this->userRepository->getUser()->id;
        $current = date("Y-m-d H:i:s");
        $currentTime = strtotime($current);
        $report_at = isset($event['report_at']) ? strtotime($event['report_at']) : 0;

        //响应时间=接单-上报
        $response_time = $report_at && $currentTime > $report_at ?($currentTime - $report_at):0;
        $update = array(
            "state" => Oa::STATE_ACCEPT,
            "accept_at" => $current,
            "updated_at" => $current,
            "response_time" => $response_time
        );
        if(!empty($event->user_id)) { //已派单状况
            if($userId != $event->user_id) {
                Code::setCode(Code::ERR_EVENT_USER);
                return false;
            }
        }else{
            $update['user_id'] = $userId;
            $update['assigner_id'] = $userId;
        }
        //更新事件状态
        $this->update($id, $update);
        userlog("工程师通过微信接单  OA事件ID：$id");
        return true;
    }

    /**
     * 工程师到达现场（开始处理）
     * @param $input
     * @return bool
     */
    public function wxReach($input=array(),$event=array()) {
        $id = getKey($input, "id");
        $userId = $this->userRepository->getUser()->id;
        if(!$event){
            $event = $this->getById($id);
        }
        if($event->state !== Oa::STATE_ACCEPT) {
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        if($userId != $event->user_id) {
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        $current = date("Y-m-d H:i:s");
        $currentTime = strtotime($current);
        $accept_at = isset($event['accept_at']) ? strtotime($event['accept_at']) : 0;

        //路程时间=处理-接单
        $distance_time = $accept_at && $currentTime > $accept_at ?($currentTime - $accept_at):0;
        $update = array(
            "state" => Oa::STATE_ING,
            "updated_at" => $current,
            "reached_at" => $current,
            "distance_time" => $distance_time
        );

        //更新事件状态
        $this->update($id, $update);
        userlog("工程师通过微信到达 OA事件ID：$id");
        return true;
    }

    /**
     * 事件完成
     * @param $input
     * @return bool
     */
    public function wxFinish($input=array(),$event=array()) {
        $id = getKey($input, "id");
        $userId = $this->userRepository->getUser()->id;
        if(!$event){
            $event = $this->getById($id);
        }
        if($event->state !== Oa::STATE_ING) {
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        if($event->suspend_status>0){
            //挂起事件不能做完成操作
            Code::setCode(Code::ERR_SUSPEND_NOT_END);
            return false;
        }

        if($userId != $event->user_id) {
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        $current = date("Y-m-d H:i:s");
//        $source = $event->source;
        $datetime = strtotime($current);
        $reachedtime = strtotime($event->reached_at);

        $suspendUsetime = isset($event['suspend_usetime']) ? intval($event['suspend_usetime']) : 0;
        //处理时间=完成-处理-挂起
        $process_time = $reachedtime && $datetime>$reachedtime ? $datetime - $reachedtime - $suspendUsetime : 0;


        $update = array(
            "state" => Oa::STATE_END,
            "updated_at" => $current,
            "finished_at" => $current,
            "category_id" => getKey($input,"categoryId"), //事件类型
            "object" => getKey($input,"object"), //事件对象
            "description" => getKey($input,"description"), //处理描述
            "process_time" => $process_time,
        );

        //更新事件状态
        $this->update($id, $update);
        userlog("工程师通过微信完成 OA事件ID：$id");
        return true;
    }

    /**
     * 事件列表
     * @param Request $request
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     */
    public function wxList(Request $request, $sortColumn = "id", $sort = "desc") {
        $search = $request->input("s");
        $state = $request->input("state");
        $where = [];
        if(!is_null($state)) {
            $where[] = ["state","=", $state];
        }

        $model = $this->model->with('category',"user","assigner")->where($where);
        $tbl = "workflow_oa";

        if(!empty($search)) {
            //搜索资产名称，IP，资产编号，事件处理人
            $model->where(function ($query) use ($search, $tbl) {
                    if (!empty($search)) {
                        $query->orWhere("$tbl.id", "=", $search );
                        $query->orWhere("$tbl.problem", "like","%" . $search . "%" );
                    }
                });
        }

        $userId = $this->userRepository->getUser()->id;

        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("$tbl.accept_at", $between);
        }

        if ($this->userRepository->isEngineer()) { //工程师只能看自己的以及未分配的

            $model->where(function ($query) use ($tbl, $userId) {
                $query->WhereIn("$tbl.user_id", [0,$userId]);
            });
        }
        else if ($this->userRepository->isNormal() || $this->userRepository->isUserLeader()) {//用户只能看自己上报的
            $model->where(["$tbl.report_id" => $userId]);
        }
        $model->select(["$tbl.*"]);

        return $this->usePage($model,$sortColumn,$sort);
    }


    /**
     * 自建或者被分派
     * @param $request
     * @param null $oaId
     * @return bool
     */
    public function add(Request $request,$event=array()) {
        $oaId = $request->input("id");
        $userId = $this->userRepository->getUser()->id;
        $userId = $userId ? $userId : 0;
        $categoryId = $request->input("categoryId",0);

        if (!$this->userRepository->isEngineer() && !$this->userRepository->isManager()) {
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        $report_at = strtotime($request->input("report_at"));
        $reached_at = strtotime($request->input("reached_at"));
        $accept_at = strtotime($request->input("accept_at"));


        //响应时间=接单-上报
        $response_time = $report_at && $accept_at > $report_at ? $accept_at - $report_at : 0;

        //路程时间=处理-接单
        if($reached_at && $accept_at) {
            $distance_time = $reached_at > $accept_at ? ($reached_at - $accept_at) : 0;
        }
        else{
            $distance_time = null;
        }

        $current = date("Y-m-d H:i:s");
        $finished_at = $request->input("finished_at");
        $datetime = strtotime($finished_at);

        $suspendUsetime = isset($event['suspend_usetime']) ? intval($event['suspend_usetime']) : 0;
        //处理时间=完成-处理-挂起
        $process_time = $reached_at && $datetime>$reached_at ? $datetime - $reached_at - $suspendUsetime : 0;


        if(!empty($oaId)) {
            $model = $this->getById($oaId);
            if($model->user_id != $userId) {
                Code::setCode(Code::ERR_EVENT_PERM);
                return false;
            }

            if($model->state == Oa::STATE_END || $model->state == Oa::STATE_CLOSE) {
                Code::setCode(Code::ERR_EVENT_STATE);
                return false;
            }

            if($model->suspend_status>0){
                //挂起事件不能做完成操作
                Code::setCode(Code::ERR_SUSPEND_NOT_END);
                return false;
            }


            $update = [
                "device_name" => $request->input("deviceName"),
                "location" => $request->input("location"),
                "company" => $request->input("company"),
                "category_id" => $categoryId,
                "state" => Oa::STATE_END,
                "object" => $request->input("object"),
                "report_name" => $request->input("reportName"),
                "mobile" => $request->input("mobile"),
                "remark" => $request->input("remark"),
                "description" => $request->input("description"),
                "problem" => $request->input("problem"),
                "accept_at" => $request->input("accept_at"),
                "reached_at" => $request->input("reached_at"),
                "report_at" => $request->input("report_at",$current),
                "finished_at" => $finished_at,
                "updated_at" => $current,
                "response_time" => $response_time,
                "process_time" => $process_time,
                "distance_time" => $distance_time
            ];
            $model->fill($update);
            $model->save();
            userlog("处理OA事件，事件ID: $oaId");
        }
        else {
            $insert = [
                "device_name" => $request->input("deviceName"),
                "location" => $request->input("location"),
                "company" => $request->input("company"),
                "category_id" => $categoryId,
                "user_id" => $userId,
                "assigner_id" => $userId,
                "state" => Oa::STATE_END,
                "source" => Oa::SRC_SELF,
                "report_name" => $request->input("reportName"),
                "mobile" => $request->input("mobile"),
                "object" => $request->input("object"),
                "remark" => $request->input("remark"),
                "description" => $request->input("description"),
                "problem" => $request->input("problem"),
                "accept_at" => $request->input("accept_at"),
                "reached_at" => $request->input("reached_at"),
                "report_at" => $request->input("report_at",$current),
                "finished_at" => $request->input("finished_at"),
                "created_at" => $current,
                "updated_at" => $current,
                "response_time" => $response_time,
                "process_time" => $process_time,
                "distance_time" => $distance_time
            ];
            $this->store($insert);
            userlog("新建OA事件");
        }
        return true;
    }


    /**
     * 分派
     * @param $request
     * @return bool
     */
    public function assign(Request $request) {
        if (!$this->userRepository->isManager() && !$this->userRepository->isAdmin() && !$this->userRepository->isUserLeader()) {
            Code::setCode(Code::ERR_EVENT_ASSIGN);
            return false;
        }

        $uid = $this->userRepository->getUser()->id;
        $userId = $request->input("userId",0);
        if (!$this->userRepository->isEngineer($userId) && !$this->userRepository->isManager($userId)) {
            Code::setCode(Code::ERR_EVENT_USER);
            return false;
        }

        $current = date("Y-m-d H:i:s");
        $insert = [
            "user_id" => $userId,
            "assigner_id" => $uid,
            "report_id" => $uid,
            "device_name" => $request->input("deviceName"),
            "location" => $request->input("location"),
            "report_name" => $request->input("reportName"),
            "company" => $request->input("company"),
            "mobile" => $request->input("mobile"),
            "state" => Oa::STATE_WAIT,
            "source" => Oa::SRC_ASSIGN,
            "problem" => $request->input("problem"),
            "accept_at" => $request->input("accept_at"),
            "report_at" => $request->input("report_at",$current),
            "created_at" => $current,
            "updated_at" => $current
        ];
        $this->store($insert);

        userlog("分派OA事件");
        return true;
    }

    public function getAssign(Request $request) {
        if($this->userRepository->isManager() || $this->userRepository->isAdmin() || $this->userRepository->isUserLeader()) {
            $result = $this->userRepository->getEngineers();
            $users = [];
            foreach($result as $v) {
                $users[] = [
                    "id" => $v->id,
                    "name" => $v->name,
                    "username" => $v->username,
                ];
            }
            $data["users"] = $users;
            return $data;
        }
        else {
            Code::setCode(Code::ERR_EVENT_ASSIGN);
            return false;
        }
    }

    public function getAdd(Request $request) {
        if (!$this->userRepository->isEngineer() && !$this->userRepository->isManager()) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }

        $oaId = $request->input("id");
        $data = [];
        $data['state'] = $this->model->getState();
        $data['categories'] = $this->getCategory();
        $data['object'] = $this->model->getObject();
        if(!empty($oaId)) {
            $data['info'] = $this->getById($oaId);
        }
        return $data;
    }

    public function getCategory() {
        return $this->categoryModel->where("id",">",100)->selectRaw("id as value, name as msg")->get()->toArray();
    }

    public function getCategoryOne($where='') {
        $result = array();
        if($where) {
            $result = $this->categoryModel->where($where)->first();
        }
        return $result;
    }


    public function get($oaId) {
        $model = $this->getById($oaId);
        return $model;
    }

    protected function canClose($event=array()){
        $identity_id = $this->userRepository->getUser()->identity_id;
        $uid = $this->userRepository->getUser()->id;
        $result = false;
        if(4 == $identity_id){//用户
            if($event->report_id == $uid && 0 == $event->state){
                $result = true;
            }
        }else if(5 == $identity_id){//用户主管
            if($event->report_id == $uid || $event->assigner_id == $uid) {
                $result = true;
            }
        }else{
            if($event->assigner_id == $uid){
                $result = true;
            }
        }

        return $result;
    }

    public function close($data=array(),$event=array()) {
        $id = getKey($data, "id");
        if(!$event){
            $event = $this->getById($id);
        }
        $remark = getKey($data, "remark");

        if(!$this->canClose($event)) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }
        if($event->state === Oa::STATE_END || $event->state === Oa::STATE_CLOSE) {
            //事件已完成，不用后续操作
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        $update = array(
            "state" => Oa::STATE_CLOSE,
            "remark" => $remark,
            "updated_at" => date("Y-m-d H:i:s"),
            "finished_at" => date("Y-m-d H:i:s"),
            "close_uid"=>$this->userRepository->getUser()->id
        );
        //更新事件状态
        $this->update($data['id'], $update);
        userlog("关闭OA事件，事件id：".$data['id']);

        return true;
    }

    public function getList(Request $request, $sortColumn = "id", $sort = "desc") {
        $search = $request->input("search");
        $state = $request->input("state");
        $source = $request->input("source");
        $categoryId = $request->input("categoryId");
        $object = $request->input("object");
        $userId = $request->input("userId");
        $where = [];

        if(!is_null($state)) {
            $where[] = ["state","=", $state];
        }
        if(!is_null($source)) {
            $where[] = ["source","=", $source];
        }
        if(!is_null($categoryId)) {
            $where[] = ["category_id","=", $categoryId];
        }
        if(!is_null($object)) {
            $where[] = ["object","=", $object];
        }
        if(!is_null($userId)) {
            $where[] = ["user_id","=", $userId];
        }

        $model = $this->model->with('category',"user","assigner")->where($where);
        $tbl = "workflow_oa";

        if(!empty($search)) {
            //搜索资产名称，IP，资产编号，事件处理人
            $model->leftJoin("users as B","$tbl.user_id","=", "B.id")
                ->where(function ($query) use ($search, $tbl) {
                    if (!empty($search)) {
                        $query->orWhere("$tbl.company", "like", "%" . $search . "%");
//                        $query->orWhere("B.username", "like", "%" . $search . "%");
                        $query->orWhere("$tbl.id", "like", "%" . $search . "%");
                    }
                });
        }

        $userId = $this->userRepository->getUser()->id;

        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("$tbl.accept_at", $between);
        }

        if ($this->userRepository->isEngineer()) { //工程师只能看自己的以及未分配的
            $model->where(function ($query) use ($tbl, $userId) {
                $query->whereNull("$tbl.user_id");
                $query->orWhere("$tbl.user_id", "=", $userId);
            });
        }
        else if ($this->userRepository->isNormal() || $this->userRepository->isUserLeader()) {//用户只能看自己上报的
            $model->where(["$tbl.report_id" => $userId]);
        }
        $model->select(["$tbl.*"]);

        return $this->usePage($model,$sortColumn,$sort);
    }

    public function getMeta() {
        $data['state'] = $this->model->getState();
        $data['categories'] = $this->getCategory();
        $data['object'] = $this->model->getObject();
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
     * 批量更新
     * @param array $where
     * @param array $param
     * @return string
     */
    public function updateBatch($where=array(),$param=array()){
        $up = '';
        list($id,$vids) = $where;
//        var_dump($id,$vids);exit;
        if($where && $param){
            $up = $this->model->whereIn($id,$vids)->update($param);
        }
        return $up;

    }


    /**
     * 事件挂起或恢复
     * @param array $input
     * @param array $event
     * @return bool
     */
    public function updateSuspendstatus($input=array(),$event=array()){
        $eventId = getKey($input,'eventId');
        $suspend_status = intval(getkey($input,'suspend'));
        $update = [
            "suspend_status" => $suspend_status
        ];
        if(!$eventId){
//            throw new ApiException(Code::ERR_PARAMS,['事件ID不能为空']);
            Code::setCode(Code::ERR_PARAMS,'事件ID不能为空');
            return false;
        }
        if(!$event){
            $event = $this->getById($eventId);
        }
        $suspendStatus = isset($event['suspend_status']) ? $event['suspend_status'] : '';
        if($suspend_status != $suspendStatus) {
            $this->update($eventId, $update);
        }
        return true;
    }



    /**
     * oa事件报表
     * @param $request
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     */
    public function getReportForm($request,$export = false, $sortColumn = "id", $sort = "desc") {
        $state = $request->input("state");
        $source = $request->input("source");
        $object = $request->input("object");
        $actCategory = $request->input("actCategory");
        $users = $request->input("users");
        $responseTime = $request->input("responseTime");
        $processTime = $request->input("processTime");
        $distanceTime = $request->input("distanceTime");
        $company = $request->input("company");
        $where = [];
        $tbl = "workflow_oa";

        if(!is_null($state)) {
            $where[] = [$tbl.".state","=", $state];
        }
        if(!is_null($source)) {
            $where[] = [$tbl.".source","=", $source];
        }
        if(!is_null($company)){
            $where[] = [$tbl.".company","like",'%'.$company.'%'];
        }


        $model = $this->model->with('category',"user","assigner","suspend")->where($where);
        $objectArr = array_filter(array_unique(explode(',',$object)),'arrayFilterCallbak');
        if($objectArr){
            $model = $model->whereIn($tbl.".object",$objectArr);
        }
        $actCategoryArr = array_filter(array_unique(explode(',',$actCategory)),'arrayFilterCallbak');
        if($actCategoryArr){
            $model = $model->whereIn($tbl.".category_id",$actCategoryArr);
        }
        $usersArr = array_filter(array_unique(explode(',',$users)),'arrayFilterCallbak');
        if($usersArr){
            $model = $model->whereIn($tbl.".user_id",$usersArr);
        }

        //搜索资产名称，IP，资产编号，事件处理人
        $model->leftJoin("users as B","$tbl.user_id","=", "B.id");

        if (false !== ($between = $this->searchTime($request))) {
            $begin = isset($between[0]) ? $between[0] : '';
            $end = isset($between[1]) ? $between[1] : '';
            if($begin && $end) {
                $model->whereBetween("$tbl.updated_at", $between);
            }else{
                $beTime = array();
                if($begin){
                    $beTime[] = ["$tbl.updated_at",">=" ,$begin];
                }elseif($end){
                    $beTime[] = ["$tbl.updated_at","<=" ,$end];
                }
                if($beTime) {
                    $model->where($beTime);
                }
            }
        }


        //响应时间
        if($responseTime){
            $response = Oa::$timeArr[$responseTime];
            if(Oa::TIME_5 == $responseTime){
                $tmpWhere[] = ["$tbl.response_time",">=" ,$response];
                $model->where($tmpWhere);
            }else{
                $tmpWhere[] = ["$tbl.response_time",">=" ,$response[0]];
                $tmpWhere[] = ["$tbl.response_time","<" ,$response[1]];
                $model->where($tmpWhere);
//                $model->whereBetween("$tbl.response_time", $response);
            }
        }
        //处理时间
        if($processTime){
            $process = Oa::$timeArr[$processTime];
            if(Oa::TIME_5 == $processTime){
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

        $model2 = clone $model;
        $total = $model2->select(DB::raw("sum($tbl.process_time) as total_process_time"),
            DB::raw("sum($tbl.response_time) as total_response_time"),
            DB::raw("sum($tbl.distance_time) as total_distance_time"))->first();
        unset($model2);
        $model->select(["$tbl.*"]);
        if(false === $export) {
            $resObj = $this->usePage($model, $sortColumn, $sort);
        }
        else {
            $resObj = $model->orderBy($sortColumn, $sort)->get();
        }

        $res = $resObj ? $resObj->toArray() : array();

        $result = array('result'=>array());
        $list = array();
        if(isset($res['data'])) {
            $data = $res['data'];
        }
        else {
            $data = $res;
        }

        foreach($data as $k=>$v){
            $category = $v['category'];
            $assigner = $v['assigner'];
            $user = $v['user'];
            $suspend = $v['suspend'];
            unset($v['category']);
            unset($v['assigner']);
            unset($v['user']);
            unset($v['suspend']);

            $list[$k] = $v;
            $source = isset($v['source'])?$v['source']:'';
            $created_at = !empty($v['updated_at']) ? $v['updated_at'] : $v['updated_at'];
            $object = isset($v['object']) ? $v['object'] : '';
            $list[$k]['created_date'] = $created_at ? date('Y-m-d',strtotime($created_at)):'';
            $list[$k]['created_time'] = $created_at ? date('H:i',strtotime($created_at)):'';
            $list[$k]['source_name'] = isset(Oa::$sourceMsg[$source]) ? Oa::$sourceMsg[$source]:'';
            $list[$k]['category'] = isset($category['name'])?$category['name']:'';
            $list[$k]['assigner'] = isset($assigner['username'])?$assigner['username']:'';
            $list[$k]['user'] = isset($user['username'])?$user['username']:'';
            $list[$k]['object_name'] = isset(Oa::$objectMsg[$object])?Oa::$objectMsg[$object]:'';
            $list[$k]['state_name'] = isset(Oa::$stateMsg[$v['state']]) ? Oa::$stateMsg[$v['state']]:'';
            $list[$k]['process_time_human'] = sec2Time($v['process_time'],1);
            $list[$k]['response_time_human'] = sec2Time($v['response_time'],1);
            $list[$k]['distance_time_human'] = sec2Time($v['distance_time'],1);

            if(!empty($suspend)){
                $new = [];
                foreach ($suspend as $key => $val){
                    $new[] = $key + 1 . '.' . $val['content'] . ' ';
                }
                $str = implode($new);
                $list[$k]['contentStr'] = $str;
            }else{
                $list[$k]['contentStr'] = '';
            }

        }

        if($list) {
            $result['result'] = $list;
            if(false === $export) {
                $result['meta'] = array(
                    'pagination' => array(
                        'total' => isset($res['total']) ? $res['total'] : 0,
                        'per_page' => isset($res['per_page']) ? $res['per_page'] : 0,
                        'current_page' => isset($res['current_page']) ? $res['current_page'] : 0,
                        'total_process_time' => isset($total['total_process_time']) ? sec2Time($total['total_process_time'],1) : 0,
                        'total_response_time' => isset($total['total_response_time']) ? sec2Time($total['total_response_time'],1) : 0,
                        'total_distance_time' => isset($total['total_distance_time']) ? sec2Time($total['total_distance_time'],1) : 0,
                    )
                );
            }
            else {
                $result['meta'] = [
                    [
                        'total' => count($list),
                        'total_process_time' => isset($total['total_process_time']) ? sec2Time($total['total_process_time'],1) : 0,
                        'total_response_time' => isset($total['total_response_time']) ? sec2Time($total['total_response_time'],1) : 0,
                        'total_distance_time' => isset($total['total_distance_time']) ? sec2Time($total['total_distance_time'],1) : 0,
                    ]
                ];
            }
        }

        return $result;
    }


    public function getSource(){
        return $this->model->getSource();
    }


    public function getState(){
        return $this->model->getState();
    }

    public function getObject(){
        return $this->model->getObject();
    }


}