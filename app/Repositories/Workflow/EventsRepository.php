<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/2
 * Time: 10:52
 */

namespace App\Repositories\Workflow;

use App\Exceptions\ApiException;
use App\Repositories\Assets\DeviceRepository;
use App\Models\Assets\CategoryFields;
use App\Repositories\Auth\UserRepository;
use App\Repositories\Assets\CategoryRepository;
use App\Repositories\BaseRepository;
use App\Models\Workflow\Category;
use App\Models\Workflow\Event;
use App\Models\Workflow\Multievent;
use App\Models\Workflow\Device as EventDevice;
use App\Models\Workflow\Multidevice;
use App\Models\Assets\Device;
use App\Models\Code;
use App\Models\Auth\User;
use App\Repositories\Workflow\Events\BaseEventsRepository;
use App\Repositories\Weixin\EventsTrackRepository;
use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Weixin\EventsPicRepository;
use App\Models\Assets\Solution;
use App\Models\Workflow\Maintain;
use App\Models\Workflow\Oa;
use App\Models\Assets\Wrong;
use DB;
use Log;
use Auth;

class EventsRepository extends BaseRepository
{

    protected $categoryModel;
    protected $userRepository;
    protected $eventDeviceModel;
    protected $categoryRepository;
    protected $deviceRepository;
    protected $deviceModel;
    protected $multiEventModel;
    protected $eventsTrackRepository;
    protected $solutionModel;
    protected $maintainModel;
    protected $eventsComment;
    protected $eventsPic;
    protected $OaModel;
    protected $wrongModel;

    public function __construct(Category $categoryModel,
                                Event $eventModel,
                                Multievent $multieventModel,
                                EventDevice $eventDeviceModel,
                                MultiDevice $multiDeviceModel,
                                Device $deviceModel,
                                UserRepository $userRepository,
                                CategoryRepository $categoryRepository,
                                DeviceRepository $deviceRepository,
                                CategoryFields $categoryFieldsModel,
                                EventsTrackRepository $eventsTrackRepository,
                                Solution $solutionModel,
                                Maintain $maintainModel,
                                EventsCommentRepository $eventsComment,
                                EventsPicRepository $eventsPic,
                                Oa $OaModel,
                                Wrong $wrongModel
                                )
    {
        $this->categoryModel = $categoryModel;
        $this->userRepository = $userRepository;
        $this->model = $eventModel;
        $this->multiEventModel = $multieventModel;
        $this->multiDeviceModel = $multiDeviceModel;
        $this->eventDeviceModel = $eventDeviceModel;
        $this->categoryRepository = $categoryRepository;
        $this->deviceModel = $deviceModel;
        $this->deviceRepository = $deviceRepository;
        $this->categoryFieldsModel = $categoryFieldsModel;
        $this->eventsTrackRepository = $eventsTrackRepository;
        $this->solutionModel = $solutionModel;
        $this->maintainModel = $maintainModel;
        $this->eventsComment = $eventsComment;
        $this->eventsPic = $eventsPic;
        $this->OaModel = $OaModel;
        $this->wrongModel = $wrongModel;
    }

    public function getCategories($batch) {
        return $this->categoryModel->where(["batch" => $batch])->get();
    }


    public function getCategoriesList($where=array()){
        $model = $this->categoryModel;
        if ($where) {
            $model = $model->where($where);
        }
        $result = $model->get();
        return $result;
    }


    /**
     * 获取事件分类 key=>value
     * @return mixed
     */
    public function getCategoriesPluck(){
        return $this->categoryModel->get()->pluck("name","id")->toArray();
    }

    public function getMultiList($request,$sortColumn = "id", $sort = "desc") {
        $search = $request->input("search");
        $state = $request->input("state");
        $source = $request->input("source");
        $eventId = $request->input("eventId");
        $where = [];

        if(!is_null($state)) {
            $where[] = ["workflow_multievents.state","=", $state];
        }
        if(!is_null($source)) {
            $where[] = ["source","=", $source];
        }

        $where[] = ["event_id", "=", $eventId];
        $model = $this->multiEventModel->with('category',"user","assigner","asset")->where($where);
        $tbl = "workflow_multievents";

        if(!empty($search)) {
            //搜索资产名称，IP，资产编号，事件处理人
            $model->leftJoin("users as B","$tbl.user_id","=", "B.id")
                ->join("assets_device as A","$tbl.asset_id","=","A.id")
                ->where(function ($query) use ($search) {
                    if (!empty($search)) {
                        $query->orWhere("A.number", "like", "%" . $search . "%");
//                        $query->orWhere("A.name", "like", "%" . $search . "%");
//                        $query->orWhere("A.ip", "like", "%" . $search . "%");
                        $query->orWhere("B.username", "like", "%" . $search . "%");
                        $query->orWhere("workflow_multievents.id", "like", "%" . $search . "%");
                    }
                });
        }

        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("$tbl.updated_at", $between);
        }

        $userId = $this->userRepository->getUser()->id;
        if ($this->userRepository->isEngineer()) { //工程师只能看自己的以及未分配的
            $model->whereIn("user_id", [0, $userId]);
        }
        else if ($this->userRepository->isNormal()) {//用户只能看自己上报的
            $model->where(["report_id" => $userId]);
        }
        $model->select(["$tbl.*"]);

        return $this->usePage($model,$sortColumn,$sort);
    }

    public function getProcessEventByUser($userId) {
        return $this->model->whereIn("state","=",[Event::STATE_ACCESS, Event::STATE_ING])
            ->where("user_id", "=", $userId)->get();
    }

    public function getList($request,$sortColumn = "id", $sort = "desc") {

        $search = $request->input("search");
        $state = $request->input("state");
        $source = $request->input("source");
        $location = $request->input('location');
        $officeBuilding = $request->input('officeBuilding');
        $enginerooms = $request->input('enginerooms');
        $department = $request->input('department');
        $number = $request->input('number');
        $assetType = $request->input('assettype');


        $where = [];
        $eventStates = [];


        if(!is_null($assetType)){
            //资产列表的事件入口
            if(!is_null($number)) {
                $where[] = ['A.number', '=', $number];
            }
            $eventStates = [Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING];
        }
        if(!$eventStates){
            if (!is_null($state)) {
                $where[] = ["workflow_events.state", "=", $state];
            }
        }
        if (!is_null($source)) {
            $where[] = ["workflow_events.source", "=", $source];
        }
        if(!is_null($location)){
            $where[] = ["A.location",'=',$location];
        }
        if(!is_null($officeBuilding)){
            $where[] = ['A.officeBuilding','=',$officeBuilding];
        }
        if(!is_null($enginerooms)){
            $where[] = ['A.area','=',$enginerooms];
        }
        if(!is_null($department)){
            $where[] = ['A.department','=',$department];
        }

        $model = $this->model->with('category',"user","assigner","asset")->where($where);
        $tbl = "workflow_events";
        $userId = $this->userRepository->getUser()->id;

        if($eventStates) {
            $model->whereIn("$tbl.state", $eventStates);
        }

        //搜索资产名称，IP，资产编号，事件处理人,事件id
        $model->leftJoin("users as B","$tbl.user_id","=", "B.id")
            ->leftJoin("assets_device as A","$tbl.asset_id","=","A.id")
            ->where('A.deleted_at','=',null)
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    $query->orWhere("A.number", "like", "%" . $search . "%");
//                    $query->orWhere("A.name", "like", "%" . $search . "%");
//                    $query->orWhere("A.ip", "like", "%" . $search . "%");
                    $query->orWhere("B.username", "like", "%" . $search . "%");
                    $query->orWhere("workflow_events.id", "like", "%" . $search . "%");
                }
            });


        if (false !== ($between = $this->searchTime($request))) {
            $model->whereBetween("$tbl.updated_at", $between);
        }

        $limitCategories = $this->userRepository->getCategories();
        if ($this->userRepository->isEngineer()) { //工程师只能看自己的以及未分配的
            $model->whereIn("$tbl.user_id", [0, $userId]);
            $model->where(function($query) use($limitCategories) {
                $query->orWhereNull("A.sub_category_id");
                $query->orWhereIn("A.sub_category_id", $limitCategories);
            });
        }
        else if ($this->userRepository->isNormal()) {//用户只能看自己上报的
            $model->where(["$tbl.report_id" => $userId]);
        }
        else if ($this->userRepository->isManager()) { //限制工种
            $model->where(function($query) use($limitCategories) {
                $query->orWhereNull("A.sub_category_id");
                $query->orWhereIn("A.sub_category_id", $limitCategories);
            });
        }
        $model->select(["$tbl.*"]);

        //批量导入添加数据时不在添加子记录，查询时数据不需要子查询（2019-02-27 15:40）
        /*
         if(!empty($search)) {
            $model2 = DB::table($tbl)->leftJoin("users as B","$tbl.user_id","=", "B.id")
                ->leftJoin("workflow_multievents as C", "$tbl.id","=","C.event_id")
                ->leftJoin("assets_device as D","C.asset_id","=","D.id")
                ->whereNotNull("C.id")
                ->where(function ($query) use ($search) {
                    if (!empty($search)) {
                        $query->orWhere("B.username", "like", "%" . $search . "%");
                        $query->orWhere("D.number", "like", "%" . $search . "%");
//                        $query->orWhere("D.name", "like", "%" . $search . "%");
//                        $query->orWhere("D.ip", "like", "%" . $search . "%");
                        $query->orWhere("workflow_events.id", "like", "%" . $search . "%");
                    }
                })
                ->select("$tbl.*");

            if(!is_null($state)) {
                $model2->where(["C.state" => $state]);
            }

            if(!is_null($source)) {
                $model2->where(["C.source" => $source]);
            }

            if (false !== ($between = $this->searchTime($request))) {
                $model2->whereBetween("$tbl.updated_at", $between);
            }

            if ($this->userRepository->isEngineer()) { //工程师只能看自己的以及未分配的
                $model2->whereIn("$tbl.user_id", [0, $userId]);
                $model2->where(function($query) use($limitCategories) {
                    $query->orWhereNull("D.sub_category_id");
                    $query->orWhereIn("D.sub_category_id", $limitCategories);
                });
            }
            else if ($this->userRepository->isNormal()) {//用户只能看自己上报的
                $model2->where(["$tbl.report_id" => $userId]);
            }
            else if ($this->userRepository->isManager()) { //限制工种
                $model2->where(function($query) use($limitCategories) {
                    $query->orWhereNull("D.sub_category_id");
                    $query->orWhereIn("D.sub_category_id", $limitCategories);
                });
            }
            $model2->select(["$tbl.*"])->distinct("id");

            $model2->union($model);

            $modelunion = $this->model->with('category',"user","assigner","asset")
                ->from(DB::raw("({$model2->toSql()}) as $tbl"))
                ->mergeBindings($model2)->select(["$tbl.*"]);

            return $this->usePage($modelunion,$sortColumn,$sort);

        }*/

        return $this->usePage($model,$sortColumn,$sort);
    }

    public function wxGetList($request,$sortColumn = "id", $sort = "desc") {
        $search = $request->input("s");
        $state = $request->input("state");
        $source = $request->input("source");
        $where = [];

        if(!is_null($state)) {
            $where[] = ["workflow_events.state","=", $state];
        }
        if(!is_null($source)) {
            $where[] = ["workflow_events.source","=", $source];
        }

        $model = $this->model->with('category',"user","assigner","asset")->where($where);
        $tbl = "workflow_events";
        $userId = $this->userRepository->getUser()->id;

        $model->leftJoin("assets_device as A","$tbl.asset_id","=","A.id");

        if(!empty($search)) {
            //搜索资产名称，IP，资产编号，事件处理人
            $model->where(function ($query) use ($search, $tbl) {
                if (!empty($search)) {
                    $query->orWhere("$tbl.id", "=", $search );
                    $query->orWhere("$tbl.description", "like","%" . $search . "%");
                    $query->orWhere("A.number", "like","%" . $search . "%");
                }
            });
        }

        $limitCategories = $this->userRepository->getCategories();
        if ($this->userRepository->isEngineer()) { //工程师只能看自己的以及未分配的
            $model->whereIn("$tbl.user_id", [0, $userId]);
            $model->where(function($query) use($limitCategories) {
                $query->orWhereNull("A.sub_category_id");
                $query->orWhereIn("A.sub_category_id", $limitCategories);
            });
        }
        else if ($this->userRepository->isNormal()) {//用户只能看自己上报的
            $model->where(["$tbl.report_id" => $userId]);
        }elseif($this->userRepository->isUserLeader()){//用户主管能看到所有微信端上报的事件
            $where[] = ["$tbl.report_id",'>','0'];
            $model->where($where);
        }
        else if ($this->userRepository->isManager()) { //限制工种
            $model->where(function($query) use($limitCategories) {
                $query->orWhereNull("A.sub_category_id");
                $query->orWhereIn("A.sub_category_id", $limitCategories);
            });
        }
        $model->select(["$tbl.*"]);


        return $this->usePage($model,$sortColumn,$sort);
    }

    /**
     * 创建事件页面参数
     * @param null $assetId
     * @return array
     */
    public function getAdd($assetId = null,$isReporter=false) {

        $data = [];
        $state = null;

        $user = Auth::user();
        // 是否为用户主管
        $isUserDirector = user::USER_LEADER == $user->identity_id ? TRUE : FALSE;

        if(!empty($assetId)) {
            $device = Device::findOrFail($assetId);
            // 资产编号
            $data['assetNumber'] = $device->number;
            // 资产状态
            $state = $device->state;
        }

        // 根据状态返回可操作的事件
        $categories = $this->categoryModel->getCategoriesByState($state,$isReporter);

        // 取用户的工种(用户主管没有工种)
        $limitCategories = $this->userRepository->getCategories();

        if($categories){
            foreach($categories as $k=>$v){
                if(!$limitCategories && !$isUserDirector){
                    $categories[$k]['enable'] = false;
                }
            }
        }

        $data['categories_state'] = $limitCategories ? true : false;

        // 用户主管直接跳过验证
        if($isUserDirector){
            $data['categories_state'] = true;
        }

        $data['categories'] = $categories;
        if($this->userRepository->isManager() || $this->userRepository->isAdmin()) {
            $types = [
                ["id" => Event::SRC_SELF, "name" => "自建"],
                ["id" => Event::SRC_ASSIGN, "name" => "分派"],
            ];
            $result = $this->userRepository->getEngineers();
            $users = [];
            foreach($result as $v) {
                $users[] = [
                    "id" => $v->id,
                    "name" => $v->name,
                    "username" => $v->username,
                ];
            }
            $data["types"] = $types;
            $data["users"] = $users;
        }
        else if($this->userRepository->isUserLeader() ) {
            $types = [
                ["id" => Event::SRC_ASSIGN, "name" => "分派"],
            ];
            $result = $this->userRepository->getEngineers();
            $users = [];
            foreach($result as $v) {
                $users[] = [
                    "id" => $v->id,
                    "name" => $v->name,
                    "username" => $v->username,
                ];
            }
            $data["types"] = $types;
            $data["users"] = $users;
        }
        else {
            $types = [
                ["id" => Event::SRC_SELF, "name" => "自建"],
            ];
            $users = [];
            $data["types"] = $types;
            $data["users"] = $users;
        }
        return $data;

    }

    public function checkAssetInEvent($assetId,$eventID=0) {
        if(empty($assetId)) return false;
        $where[] = ["asset_id","=", $assetId];
        if($eventID){
            $where[] = ["id" ,"!=", $eventID];
        }
        $cnt = $this->model->where($where)->whereIn("state",[0,1,2])->count();
        return $cnt > 0;
    }

    public function addMulti($number2Id, $categoryId, $data) {
        //todo 判断事件状态是否可操作
        $userId = $this->userRepository->getUser()->id;

        /**/$inserts = [];
        foreach($number2Id as $assetId) {
            $inserts[] = [
                "category_id" => $categoryId - 6,
                "asset_id" => $assetId,
                "user_id" => $userId,
                "assigner_id" => $userId,
                "state" => Event::STATE_END,
                "source" => Event::SRC_SELF,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            ];
        }
        if($inserts) {
            $this->model->insert($inserts);
        }

        $idLogs = join(",",array_values($number2Id));
        if(strlen($idLogs) > 100) {
            $idLogs = substr($idLogs,0,100). "...";
        }
        userlog("创建了批量事件，事件id: 事件分类：".Category::getMsg($categoryId)." 资产id：". $idLogs." 处理人id：".$userId);
        /**/

        //old code
        /*$insert = [
            "category_id" => $categoryId,
            //"asset_id" => join(",",$assetIds),
            "user_id" => $userId,
            "assigner_id" => $userId,
            "state" => Event::STATE_END,
            "source" => Event::SRC_SELF,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        ];
        $insertId = $this->model->insertGetId($insert);

        $inserts = [];
        foreach($number2Id as $assetId) {
            $inserts[] = [
                "category_id" => $categoryId - 6,
                "event_id" => $insertId,
                "asset_id" => $assetId,
                "user_id" => $userId,
                "assigner_id" => $userId,
                "state" => Event::STATE_END,
                "source" => Event::SRC_SELF,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            ];
        }
        $this->multiEventModel->insert($inserts);

        //取出事件id，
        $eventIds = $this->multiEventModel->where("event_id","=",$insertId)->get(["id","asset_id"])->pluck("id","asset_id")->toArray();

        if(isset($data['insert']) && !empty($data['insert'])) {
            foreach ($data['insert'] as $categoryId => $values) {
                foreach($values as &$value) {
                    $assetId = $number2Id[$value['number']];
                    unset($value['id']);
                    $value['event_id'] = $eventIds[$assetId];
                    $value['created_at'] = date("Y-m-d H:i:s");
                    $value['updated_at'] = date("Y-m-d H:i:s");
                }
                $this->multiDeviceModel->insert($values);
            }
        }
        if(isset($data['update']) && !empty($data['update'])) {
            foreach ($data['update'] as &$value) {
                $assetId = $number2Id[$value['number']];
                unset($value['id']);
                $value['event_id'] = $eventIds[$assetId];
                $value['created_at'] = date("Y-m-d H:i:s");
                $value['updated_at'] = date("Y-m-d H:i:s");
            }
            $this->multiDeviceModel->insert($data['update']);
        }

        $idLogs = join(",",array_values($number2Id));
        if(strlen($idLogs) > 100) {
            $idLogs = substr($idLogs,0,100). "...";
        }
        userlog("创建了批量事件，事件id：".$insertId." 事件分类：".Category::getMsg($categoryId)." 资产id：". $idLogs." 处理人id：".$userId);
        */
    }



    // 只有批量入库走这个方法
    public function addMultiStorage($number2Id, $categoryId, $data) {
        //todo 判断事件状态是否可操作
        $userId = $this->userRepository->getUser()->id;

        /**/$inserts = [];
        foreach($number2Id as $assetId) {
            $inserts[] = [
                "category_id" => $categoryId - 6,
                "asset_id" => $assetId,
                "user_id" => $userId,
                "assigner_id" => $userId,
                "state" => Event::STATE_END,
                "source" => Event::SRC_SELF,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
            ];
        }

        if($inserts) {
            $this->model->insert($inserts);
        }

        $eventIds = $this->model->whereIn("asset_id",$number2Id)->get()->pluck('id','asset_id')->toArray();

        if(isset($data['inserts']) && !empty($data['inserts'])) {
            foreach ($data['inserts'] as $categoryId => $values) {
                foreach($values as &$value) {
                    $assetId = $number2Id[$value['number']];
                    unset($value['id']);
                    $value['event_id'] = $eventIds[$assetId];
                    $value['created_at'] = date("Y-m-d H:i:s");
                    $value['updated_at'] = date("Y-m-d H:i:s");
                }
                $this->eventDeviceModel->insert($values);
            }
        }

        $updates = isset($data['updates']) ? $data['updates'] : [];
        if($updates) {
            foreach ($updates as &$value) {
                $assetId = $number2Id[$value['number']];
                unset($value['id']);
                $value['event_id'] = $eventIds[$assetId];
                $value['created_at'] = date("Y-m-d H:i:s");
                $value['updated_at'] = date("Y-m-d H:i:s");
            }
            $this->eventDeviceModel->insert($updates);
        }

        $idLogs = join(",",array_values($number2Id));
        if(strlen($idLogs) > 100) {
            $idLogs = substr($idLogs,0,100). "...";
        }
        userlog("创建了批量事件，事件id: 事件分类：".Category::getMsg($categoryId)." 资产id：". $idLogs." 处理人id：".$userId);

    }













    /**
     * 添加事件
     * @param $assetId
     * @param $categoryId
     * @param $typeId
     * @param $userId
     */
    public function add($input=array()) { //添加事件默认即处理中
        //参数检查
        $state = null;

        $assetId = isset($input["assetId"]) ? $input["assetId"] : '';
        $categoryId = isset($input["categoryId"]) ? $input["categoryId"] : '';
        $typeId = isset($input["typeId"]) ? $input["typeId"] : '';
        $userId = isset($input["userId"]) ? $input["userId"] : '';
        $eState = isset($input["eventState"]) ? $input["eventState"]:'';
        $description = isset($input["description"]) ? $input["description"]:'';
        $eventState = $eState ? $eState : Event::STATE_ING;

        $report_at = isset($input["report_at"]) ? $input["report_at"] : '';
        $reportAtTime = $report_at ? strtotime($report_at) : '';
        $reached_at = isset($input["reached_at"]) ? $input["reached_at"] : '';
        $reachedAtTime = $reached_at ? strtotime($reached_at) : '';
        $accept_at = isset($input["accept_at"]) ? $input["accept_at"] : '';
        $acceptAtTime = $accept_at ? strtotime($accept_at) : '';
        $finished_at = isset($input["finished_at"]) ? $input["finished_at"] : '';
        $finished_at = $finished_at ? strtotime($finished_at) : '';


        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
        }

        if(!empty($assetId)) {
            $device = Device::findOrFail($assetId);
            $state = $device->state;

            if(isset($limitCategories) && !in_array($device->sub_category_id, $limitCategories)) {
                Code::setCode(Code::ERR_USER_CATEGORY);
                return;
            }
        }

        //检查是否有事件进行中
        if ($this->checkAssetInEvent($assetId)) {
            Code::setCode(Code::ERR_ASSETS_IN_EVENT);
            return;
        }

        $categories = $this->categoryModel->getCategoriesByState($state);
        $flag = false;
        foreach($categories as $category) {
            if($categoryId == $category['id']) {
                if(false === $category['enable']) {
                    Code::setCode(Code::ERR_EVENT_CATE);
                    return;
                }
                else {
                    $flag = true;
                    break;
                }
            }
        }
        if(false === $flag) {
            Code::setCode(Code::ERR_EVENT_CATE);
            return;
        }

        if (Event::SRC_ASSIGN == $typeId) { //如果是分派的，需要检查userId和当前用户身份
            if (!$this->userRepository->isManager() && !$this->userRepository->isAdmin() && !$this->userRepository->isUserLeader()) {
                Code::setCode(Code::ERR_EVENT_ASSIGN);
                return;
            }

            $userInfo = $this->userRepository->getById($userId);
            if (!$this->userRepository->isEngineer($userId) && !$this->userRepository->isManager($userId)) {
                Code::setCode(Code::ERR_EVENT_USER);
                return;
            }

            if(!empty($assetId)) {
                $limitCategories = $this->userRepository->getCategories($userId);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    Code::setCode(Code::ERR_ASSIGN_USER_CATEGORY);
                    return;
                }
            }

            if($this->userRepository->getUser()->id == $userId) {
                $typeId = Event::SRC_SELF;
            }
        }
        else {
            if($this->userRepository->isUserLeader()) { //工程师主管不能新建
                Code::setCode(Code::ERR_EVENT_ADD);
                return;
            }
            $userId = $this->userRepository->getUser()->id;
        }

        BaseEventsRepository::init($categoryId)->add($assetId);

        $current = date("Y-m-d H:i:s");
        $currentTime = strtotime($current);

        $data = [
            "category_id" => $categoryId,
            "asset_id" => $assetId,
            "user_id" => $userId,
            "assigner_id" => $this->userRepository->getUser()->id,
            "state" => $eventState,
            "source" => $typeId,
            "description" => $description
        ];

        $data['report_at'] = $report_at ? $report_at : $current;
        $data['accept_at'] = $accept_at ? $accept_at : $current;
        $data['reached_at'] = $reached_at ? $reached_at : $current;

        if($report_at) {
            $response_time = $currentTime>$reportAtTime ? $currentTime - $reportAtTime : 0;
            $data['response_time'] = $response_time;
        }

        /*
        if($reached_at){
            $data['reached_at'] = $reached_at ? $reached_at : $current;
        }
        if($finished_at){
            $data['finished_at'] = $finished_at ? $finished_at : $current;
        }*/



        $model = $this->store($data);

        userlog("创建了事件，事件id：".$model->id." 事件分类：".Category::getMsg($categoryId)." 资产id：". $assetId." 处理人id：".$userId);
        return $model->id;
    }

    public function process($eventId, $multi = 0) {
        if($multi) {
            $event = $this->multiEventModel->findOrFail($eventId);
        }
        else {
            $event = $this->getById($eventId);
        }

        $data['eventId'] = $event->id;
        $data['assetId'] = $event->asset_id;
        $data['assetNumber'] = $event->asset->number;
        $data['assetName'] = $event->asset->name;
        $data['mobile'] = $event->mobile;
        $data['description'] = $event->description;
        $data['remark'] = $event->remark;
        $data['createTime'] = $event->created_at->format('Y-m-d H:i:s');
        $data['state'] = $event->state;
        $data['source'] = $event->source;
        $data['categoryId'] = $event->category_id;
        $data['category'] = $event->category->name;
        $data['reportId'] = $event->report_id;
        $data['reportName'] = $event->report_name;
        $data['userId'] = $event->user_id;
        $data['user'] = $event->user->username;
        $data['assignerId'] = $event->assigner_id;
        $data['assigner'] = !empty($event->assigner_id)?$event->assigner->username:null;
        $data['suspend_status'] = $event->suspend_status;
        $data['distance_time'] = $event->distance_time;
        $data['report_at'] = $event->report_at;
        $data['accept_at'] = $event->accept_at;
        $data['reached_at'] = $event->reached_at;
        $data['finished_at'] = $event->finished_at;

        $categoryId = $event->category_id;

        $data['comment'] = array();
        if(Event::STATE_END == $event->state) {
            $whereEid[] = ["event_id", "=", $eventId];
            $whereEid[] = ["etype", "=", \ConstInc::WX_ETYPE];
            $comment = $this->eventsComment->getOne($whereEid);
            $comment = $comment ? $comment->toArray() : array();
            $data['comment'] = array(
                'content' => isset($comment['content']) ? $comment['content'] : '',
                'feedback' => isset($comment['feedback']) ? $comment['feedback'] : '',
                'star_level' => isset($comment['star_level']) ? $comment['star_level'] : 0,
            );
        }

        $data['images'] = array();
        if ($event) {
            $whereEP = array("event_id" => $eventId,'etype'=>\ConstInc::WX_ETYPE);
            $imgs = $this->eventsPic->getList($whereEP);
            $data['images'] = $imgs ? $imgs : array();
        }


        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }
        $moreData = BaseEventsRepository::init($categoryId)->process($event);
        return array_merge($data, $moreData);
    }

    public function multiProcess($eventId) {
        $event = $this->multiEventModel->findOrFail($eventId);
        $data['eventId'] = $event->id;
        $data['assetId'] = $event->asset_id;
        $data['assetNumber'] = $event->asset->number;
        $data['remark'] = $event->remark;
        $data['createTime'] = $event->created_at->format('Y-m-d H:i:s');
        $data['state'] = $event->state;
        $data['categoryId'] = $event->category_id;
        $data['category'] = $event->category->name;
        $data['userId'] = $event->user_id;
        $data['user'] = $event->user->username;
        $data['assignerId'] = $event->assigner_id;
        $data['assigner'] = !empty($event->assigner_id)?$event->assigner->username:null;
        $categoryId = $event->category_id;

        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }

        $moreData = BaseEventsRepository::init($categoryId)->process($event);
        return array_merge($data, $moreData);
    }


    public function getFields($deviceCategoryId) {
        //根据设备分类获取字段
        $fieldsList = $this->categoryRepository->getFieldsList($deviceCategoryId);
        return $fieldsList;
    }

    protected function canSave($event) {
        if($event->user_id != 0) {
            return $event->user_id == $this->userRepository->getUser()->id;
        }
        return true;
    }

    protected function __canClose($event) {
        return $event->assigner_id == $this->userRepository->getUser()->id;
    }

    protected function canClose($event=array()){
        $identity_id = $this->userRepository->getUser()->identity_id;
        $uid = $this->userRepository->getUser()->id;
        $result = false;
//        var_dump($event->report_id,$uid,$event->state);exit;
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


    /**
     * 保存草稿
     * @param $data
     * @return bool
     */
    public function saveDraft($data,$event) {
        if(!$event) {
            $event = $this->getById($data['eventId']);
        }
        $remark = isset($data['remark'])?$data['remark'] : "";
        if(!$this->canSave($event)) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }

        if(in_array($event->state,[Event::STATE_END,Event::STATE_CLOSE])) {
            //事件已完成，不用后续操作
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }

        $device = $this->eventDeviceModel->getByEventId($data['eventId']);
        $categoryId = $event->category_id; //事件类别

        $insert = BaseEventsRepository::init($categoryId)->prepareSaveDraft($data);

        $date = date("Y-m-d H:i:s");
        $datetime = strtotime($date);
        if(!empty($insert)) {
            if(!empty($device)) {
                $device->fill($insert)->save();
            }
            else {
                $insert['event_id'] = $data['eventId'];
                $insert["created_at"] = $date;
                $this->eventDeviceModel->insert($insert);
            }
        }

        $update = [
            "user_id" => $this->userRepository->getUser()->id,
            "state" => Event::STATE_ING,
            "remark" => $remark,
            "updated_at" => $date
        ];

        $report_at = isset($data["report_at"]) ? $data["report_at"] : '';
        $reportAtTime = $report_at ? strtotime($report_at) : '';
        $reached_at = isset($data["reached_at"]) ? $data["reached_at"] : '';
        $reachedAtTime = $reached_at ? strtotime($reached_at) : '';
        $accept_at = isset($data["accept_at"]) ? $data["accept_at"] : '';
        $acceptAtTime = $accept_at ? strtotime($accept_at) : '';
        $finished_at = isset($data["finished_at"]) ? $data["finished_at"] : '';
        $finishedAtTime = $finished_at ? strtotime($finished_at) : '';
        $suspendUsetime = isset($event['suspend_usetime']) ? intval($event['suspend_usetime']) : 0;

        $update['report_at'] = $report_at ? $report_at : null;
        $update['reached_at'] = $reached_at ? $reached_at : null;
        $update['accept_at'] = $accept_at ? $accept_at : null;
        $update['finished_at'] = $finished_at ? $finished_at : null;


        //响应时间=接单-上报
        $response_time = $reportAtTime && $acceptAtTime > $reportAtTime ? $acceptAtTime - $reportAtTime : 0;
        $update['response_time'] = $response_time;
        //路程时间=处理-接单
        $distance_time = $acceptAtTime && $reachedAtTime > $acceptAtTime ? ($reachedAtTime - $acceptAtTime) : 0;
        $update['distance_time'] = $distance_time;
        //处理时间=完成-处理-挂起
        $process_time = $reachedAtTime && $finishedAtTime>$reachedAtTime ? $finishedAtTime - $reachedAtTime - $suspendUsetime : 0;
        $update['process_time'] = $process_time;



        //更新事件状态
        if(empty($event->assigner_id)) {
            $update['assigner_id'] = $this->userRepository->getUser()->id;
        }

        $this->update($data['eventId'], $update);
        userlog("保存了草稿，事件id：".$data['eventId']." 事件分类：".Category::getMsg($categoryId));
        return true;
    }


    /**
     * 提交事件
     * @param $data
     * @return bool
     * @throws ApiException
     */
    public function save($data,$event=array()) {
        $eventId = isset($data['eventId']) ? $data['eventId'] : 0;
        if(!$event) {
            $event = $this->getById($eventId);
        }
        $remark = isset($data['remark'])?$data['remark'] : "";
        if(!$this->canSave($event)) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }
        if(in_array($event->state,[Event::STATE_END,Event::STATE_CLOSE])) {
            //事件已完成，不用后续操作
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        if($event->suspend_status>0){
            //挂起事件不能做完成操作
            Code::setCode(Code::ERR_SUSPEND_NOT_END);
            return false;
        }

        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }
        $categoryId = $event->category_id; //事件类别
        

        //保存草稿
        $device = $this->eventDeviceModel->getByEventId($eventId);
        $cls = BaseEventsRepository::init($categoryId);
        $insert = $cls->prepareSaveDraft($data);

        if(!empty($insert)) {
            if(!empty($device)) {
                $cls->check($device->sub_category_id, $insert);
                $device->fill($insert)->save();
            }
            else {
                $cls->check($insert['sub_category_id'], $insert);

                $insert['event_id'] = $eventId;
                $insert["created_at"] = date("Y-m-d H:i:s");
                $this->eventDeviceModel->insert($insert);
            }
        }

        $cls->save($data);

        //清除字段
        $this->clear($event);

        $source = isset($event['source']) ? $event['source'] : '';
        $date = date("Y-m-d H:i:s");
        $datetime = strtotime($date);
        //用户（终端）上报
        if(Event::SRC_TERMINAL == $source) {
            $where = array('event_id' => $eventId, 'state' => Event::STATE_ING,'etype' => \ConstInc::WX_ETYPE);
            $etRes = $this->eventsTrackRepository->getOne($where);
            $created_at = $etRes->created_at->format('Y-m-d H:i:s');
            $etCreate = $created_at ? strtotime($created_at) : 0;
        }else{
            //其他
            $created_at = $event->created_at->format('Y-m-d H:i:s');
            $etCreate = $created_at ? strtotime($created_at) : 0;
        }
        $reportAt = isset($event['report_at']) ? $event['report_at'] : '';
        $acceptAt = isset($event['accept_at']) ? $event['accept_at'] : '';
        $reachedAt = isset($event['reached_at']) ? $event['reached_at'] : '';
        $suspendUsetime = isset($event['suspend_usetime']) ? intval($event['suspend_usetime']) : 0;
//            var_dump(strtotime($date),$etCreate,$suspendUsetime);exit;
//        $process_time = $etCreate && $datetime>$etCreate ? $datetime - $etCreate - $suspendUsetime : 0;

        $update = [
            "user_id" => $this->userRepository->getUser()->id,
            "state" => Event::STATE_END,
            "remark" => $remark,
            "updated_at" => $date,
//            "process_time" => $process_time,
        ];
        $report_at = isset($data["report_at"]) ? $data["report_at"] : $reportAt;
        $reportAtTime = $report_at ? strtotime($report_at) : '';
        $reached_at = isset($data["reached_at"]) ? $data["reached_at"] : $reachedAt;
        $reachedAtTime = $reached_at ? strtotime($reached_at) : '';
        $accept_at = isset($data["accept_at"]) ? $data["accept_at"] : $acceptAt;
        $acceptAtTime = $accept_at ? strtotime($accept_at) : '';
        $finished_at = isset($data["finished_at"]) ? $data["finished_at"] : $date;
        $finishedAtTime = $finished_at ? strtotime($finished_at) : $datetime;

        $update['report_at'] = $report_at ? $report_at : $created_at;
        $update['reached_at'] = $reached_at ? $reached_at : $created_at;
        $update['accept_at'] = $accept_at ? $accept_at : $created_at;
        $update['finished_at'] = $finished_at ? $finished_at : $date;


        //响应时间=接单-上报
        $response_time = $reportAtTime && $acceptAtTime > $reportAtTime ? $acceptAtTime - $reportAtTime : 0;
        $update['response_time'] = $response_time;
        //路程时间=处理-接单
        $distance_time = $acceptAtTime && $reachedAtTime > $acceptAtTime ? ($reachedAtTime - $acceptAtTime) : 0;
        $update['distance_time'] = $distance_time;
        //处理时间=完成-处理-挂起
        $process_time = $reachedAtTime && $finishedAtTime>$reachedAtTime ? $finishedAtTime - $reachedAtTime - $suspendUsetime : 0;
        $update['process_time'] = $process_time;
        //更新事件状态
        if(empty($event->assigner_id)) {
            $update['assigner_id'] = $this->userRepository->getUser()->id;
        }

        //更新事件状态
        $this->update($eventId, $update);

        userlog("完成事件，事件id：".$eventId." 事件分类：".Category::getMsg($categoryId));
        return true;
    }

    /**
     * 获取最近24小时报警数
     */
    public function getAlertCnt($request) {
        $model = $this->model->where("source","=",2);
        if (false !== ($between = $this->searchTime($request,date("Y-m-d H:i:s",strtotime("-1 day"))))) {
            $model->whereBetween("created_at", $between);
        }
        return $model->count();
    }

    public function clear($event) {
        if(empty($event->asset_id)) {
            return;
        }

        $device = $this->deviceRepository->getById($event->asset_id);
        if(0 !== $event->cagetory_id) {
            $categoryRequire = $this->categoryRepository->getCategoryRequire($device->sub_category_id, $event->category_id);
        }

        $update = [];
        foreach($categoryRequire as $sname => $v){
            if($v['require'] === categoryFields::REQUIRE_CLEAR) {
                $update[$sname] = null;
            }
        }

        if(empty($update)) {
            return;
        }
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->deviceModel->where(["id" => $device->id])->update($update);
    }

    /**
     * 关闭事件
     * @param $request
     */
    public function close($data) {
        $event = $this->getById($data['eventId']);
        $remark = isset($data['remark'])?$data['remark'] : "";
        if(!$this->canClose($event)) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }
        if($event->state === Event::STATE_END) {
            //事件已完成，不用后续操作
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }

        $current = date("Y-m-d H:i:s");

        $upParam = array(
            "state" => Event::STATE_CLOSE,
            "remark" => $remark,
            "updated_at" => $current,
            "close_uid"=>$this->userRepository->getUser()->id,
            "finished_at" => $current
        );
        //更新事件状态
        $this->update($data['eventId'], $upParam);
        userlog("关闭事件，事件id：".$data['eventId']);

        //假如是维护类或变更类事件关闭事件,将主机状态还原
        if ($event->category_id == Category::MAINTAIN || $event->category_id == Category::MODIFY){
            BaseEventsRepository::init($event->category_id)->close($event);
        }

        return true;

    }

    /**
     * 取今日事件
     */
    public function getTodayEvents() {
        $today = date("Y-m-d H:i:s", mktime(0, 0, 0, date("m")  , date("d"), date("Y")));
        $tomorrow = date("Y-m-d H:i:s",mktime(0, 0, 0, date("m")  , date("d") + 1, date("Y")));
        $result = $this->model->select(DB::raw('count(id) as cnt,state'))->where("updated_at",">=", $today)->where("updated_at","<",$tomorrow)->groupBy("state")->get();
        $data = [
            "state_0" => 0,
            "state_1" => 0,
            "state_2" => 0,
            "state_3" => 0,
            "state_4" => 0
        ];
        foreach($result as $v) {
            $data["state_".$v['state']] = $v['cnt'];
        }
        return $data;
    }

    /**
     * 添加到事件记录
     * 监控 & 环控
     * @param $assetId
     * @param $description
     * @param $alertEventId
     * @return bool
     */

    public function addByMonitor($assetId, $description, $alertEventId, $sortkey){
        //监控事件不检查是否其他有事件进行
        //检查id，如果有重复的，则不再添加
        $ret = $this->model->where($sortkey,"=",$alertEventId)->count();
        if($ret > 0) {
            Log::warning("event dup $alertEventId , skip");
            return false;
        }

        $report_name = [
            'alert_event_id' => '监控上报',
            'em_alert_event_id' => '环境监控上报'
        ];

        $data = [
            "category_id" => 4, //默认给维护事件
            "asset_id" => $assetId,
            "report_id" => User::SYSADMIN_ID,
            "user_id" => 0,//处理人
            "assigner_id" => 0,
            "state" => Event::STATE_WAIT,
            "description" => $description,
            "mobile" => "",
            $sortkey => $alertEventId,
            "report_name" => $report_name[$sortkey], // 监控上报或者环境监控上报
            "source" => 2, //0:自建 1：分派 2：监控 3：终端
            "report_at" => date("Y-m-d H:i:s") // 加上上报时间
        ];
        $result = Event::create($data);
        return $result;
    }


    /**
     * 通过环境监控关闭到事件记录（监控 & 环控）
     * @param $assetId
     * @param $description
     * @param $alertEventId
     * @return bool
     */

    public function closeByMonitor($alertEventId, $description, $sortkey) {
        $upParam = array(
            "state" => Event::STATE_CLOSE,
            "remark" => $description,
            "updated_at" => date("Y-m-d H:i:s"),
            "finished_at" => date("Y-m-d H:i:s"), // 事件处理完成时间
            "close_uid"=>User::SYSADMIN_ID
        );

        $report = [
            'alert_event_id' => '监控',
            'em_alert_event_id' => '环境监控'
        ];

        # start 3/4
        $event_info = $this->model->where("source","=",2)->whereIn("state",[Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING])->where($sortkey,"=", $alertEventId)->first();
        if($event_info){
            $event_id = $event_info->id;
            $maintain_event_id = $this->maintainModel->where('event_id',$event_id)->first();
            if(!$maintain_event_id) {
                $this->maintainModel->insert([
                    'event_id' => $event_id,
                    'wrong_id' => 1,   # 系统故障
                    'solution_id' => \ConstInc::$mRecoveryId, # 监控、环控关闭后自动恢复
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s")
                ]);
            }
        }
        # end

        $events = $this->model->where("source","=",2)
            ->whereIn("state",[Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING])
            ->where($sortkey,"=", $alertEventId)->get()->toArray();

        if(!empty($events)) {
            foreach($events as &$e) {
                if(isset($e['id'])) {
                    $this->update($e['id'], $upParam);
                    $e = $upParam + $e;
                    //更新事件状态
                    userlog("{$report[$sortkey]}关闭事件，事件id：".$e['id']);
                }
            }
        }
        
        return $events;
    }


    /**
     * 上报事件新增(前端)
     * @param array $param
     *
     *
     * @return mixed
     */
    public function addByReport($param=array()){
        $categoryId = isset($param['category_id'])?$param['category_id']:0;
        $assetId = isset($param['asset_id'])?$param['asset_id']:null;
        $userid = isset($param['uid'])?$param['uid']:0;
        $handler_id = isset($param['handler_id'])?$param['handler_id']:0;
        $description = isset($param['description'])?$param['description']:'';
        $moblie = isset($param['mobile'])?$param['mobile']:'';
        $reportName = isset($param['report_name'])?$param['report_name']:'';
        $assigner_id = isset($param['assigner_id'])?$param['assigner_id']:0;
        $source = isset($param['source'])?$param['source']:3;
        $reportAt = isset($param['report_at'])?$param['report_at']:'';

        //检查是否有事件进行中
        if ($assetId != 0 && $this->checkAssetInEvent($assetId)) {
            throw new ApiException(Code::ERR_ASSETS_IN_EVENT);
        }

        if(!empty($assetId)) {
            $device = Device::findOrFail($assetId);
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
            if(!empty($handler_id)) {
                if($this->userRepository->isEngineer($handler_id) || $this->userRepository->isManager($handler_id)) {
                    $limitCategories = $this->userRepository->getCategories();
                    if(!in_array($device->sub_category_id, $limitCategories)) {
                        throw new ApiException(Code::ERR_USER_CATEGORY);
                    }
                }
            }
        }

        $identity_id = isset($this->userInfo['identity_id'])?$this->userInfo['identity_id']:0;//1;//
        if(in_array($identity_id,array(User::USER_ADMIN,User::USER_MANAGER))){
            $assigner_id = $userid;
        }

        $current = date("Y-m-d H:i:s");

        $data = [
            "category_id" => $categoryId,
            "asset_id" => $assetId,
            "report_id" => $userid,
            "user_id" => $handler_id,//处理人
            "assigner_id" => $assigner_id,
            "state" => Event::STATE_WAIT,
            "description" => $description,
            "mobile" => $moblie,
            "report_name" => $reportName,
            "source" => $source,//0:自建 1：分派 2：监控 3：终端
            "report_at" => $reportAt ? $reportAt : $current,
        ];
//        dd($data);exit;
        $result = Event::create($data);
//        dd($model['id']);exit;
        return $result;
    }


    /**
     * 上报事件列表数据
     * @param array $where
     * @param int $number
     * @param string $sort
     * @param string $sortColumn
     * @return mixed
     */
    public function getListReport($where=array(),$number = 10, $sort = 'desc', $sortColumn = 'created_at'){
        $res['result'] = array();
        if($where){
            $res['result'] = $this->model->where($where)->orderBy($sortColumn, $sort)->paginate($number);
        }else {
            $res['result'] = $this->model->orderBy($sortColumn, $sort)->paginate($number);
        }

        return $res;
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
     * 接单或分派,需要eventId,processUserId
     * @param $request
     */
    public function wxAccept($request,$eventId=0) {
        $event = $this->getById($eventId);

        //存在被分派人，判断是否为主管
        if(!empty($request->input("processUserId"))) {
            if (!$this->userRepository->isManager() && !$this->userRepository->isAdmin() && !$this->userRepository->isUserLeader()) {
                Code::setCode(Code::ERR_EVENT_ASSIGN);
                return false;
            }
            $processUserId = $request->input("processUserId");
            $this->userRepository->getById($processUserId);
            if (!$this->userRepository->isEngineer($processUserId) && !$this->userRepository->isManager($processUserId)) {
                Code::setCode(Code::ERR_EVENT_USER);
                return false;
            }

            //判断工种
            if(!empty($event->asset_id)) {
                $device = Device::findOrFail($event->asset_id);
                $limitCategories = $this->userRepository->getCategories($processUserId);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    Code::setCode(Code::ERR_USER_CATEGORY);
                    return false;
                }
            }
            $user_id = $processUserId;
        }
        else {
            if (!$this->userRepository->isEngineer() && !$this->userRepository->isManager()) {
                Code::setCode(Code::ERR_EVENT_USER);
                return false;
            }

            if(!empty($event->asset_id)) {
                $device = Device::findOrFail($event->asset_id);
                $limitCategories = $this->userRepository->getCategories();
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    Code::setCode(Code::ERR_USER_CATEGORY);
                    return false;
                }
            }

            $user_id = $this->userRepository->getUser()->id;
        }
        $date = date("Y-m-d H:i:s");
        $datetime = strtotime($date);
//        $source = $event->source;

        //用户（终端）上报
        /*if(Event::SRC_TERMINAL == $source) {
            $where = array('event_id' => $eventId, 'state' => Event::STATE_ACCESS,'etype' => \ConstInc::WX_ETYPE);
            $etRes = $this->eventsTrackRepository->getOne($where);
            $etCreate = isset($etRes['created_at']) ? strtotime($etRes['created_at']) : 0;
        }else{
            //其他
            $etCreate = isset($event['created_at']) ? strtotime($event['created_at']) : 0;
        }*/
        $report_at = $event->report_at ? strtotime($event->report_at) : 0;

        //响应时间=接单-上报
        $response_time = $report_at && $datetime > $report_at ? $datetime - $report_at : 0;

        $update = [
            "assigner_id" => $this->userRepository->getUser()->id,
            "user_id" => $user_id,
            "state" => Event::STATE_ACCESS,
            "response_time" => $response_time,
            "accept_at" => $date
        ];

        if($eventId) {
            $this->update($eventId, $update);
        }else{
            $update = false;
        }
        return $update;
    }

    /**
     * 确定事件
     * @param $request
     */
    public function wxSelectCategorySave($request) {
        $assetId = $request->input("assetId");
        $categoryId = $request->input("categoryId");
        $this->deviceRepository->getById($assetId);
        $eventID = $request->input("eventId");

        //检查是否有事件进行中
        /*if ($this->checkAssetInEvent($assetId,$eventID)) {
            Code::setCode(Code::ERR_ASSETS_IN_EVENT);
            return false;
        }*/

        $event = $this->getById($eventID);
        if(!$this->canSave($event)) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }

        if(in_array($event->state,[Event::STATE_END,Event::STATE_CLOSE])) {
            //事件已完成，不用后续操作
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }

        $device = Device::findOrFail($assetId);
        $state = $device->state;

        $categories = $this->categoryModel->getCategoriesByState($state);
        $flag = false;
        foreach($categories as $category) {
            if($categoryId == $category['id']) {
                if(false === $category['enable']) {
                    Code::setCode(Code::ERR_EVENT_CATE);
                    return false;
                }
                else {
                    $flag = true;
                    break;
                }
            }
        }
        if(false === $flag) {
            Code::setCode(Code::ERR_EVENT_CATE);
            return false;
        }

        if(!empty($assetId)) {
            if ($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $device = Device::findOrFail($assetId);
                $limitCategories = $this->userRepository->getCategories();
                if (!in_array($device->sub_category_id, $limitCategories)) {
                    Code::setCode(Code::ERR_USER_CATEGORY);
                    return false;
                }
            }
        }

        BaseEventsRepository::init($categoryId)->add($assetId);
        $source = $event->source;
        $date = date("Y-m-d H:i:s");
        $datetime = strtotime($date);

        /*//用户（终端）上报
        if(Event::SRC_TERMINAL == $source) {
            $where = array('event_id' => $eventID, 'state' => Event::STATE_ACCESS,'etype' => \ConstInc::WX_ETYPE);
            $etRes = $this->eventsTrackRepository->getOne($where);
            $etCreate = isset($etRes['created_at']) ? strtotime($etRes['created_at']) : 0;
        }else{
            //其他
            $etCreate = isset($event['created_at']) ? strtotime($event['created_at']) : 0;
        }*/
        $accept_at = $event->accept_at;
        $acceptAtTime = $accept_at ? strtotime($accept_at) : '';
        $distance_time = $acceptAtTime && $datetime > $acceptAtTime ? $datetime - $acceptAtTime : 0;

        $update = [
            "category_id" => $categoryId,
            "asset_id" => $assetId,
            "state" => Event::STATE_ING,
            "updated_at" => $date,
            "distance_time" => $distance_time,
            "reached_at" => $date
        ];
        $this->update($eventID, $update);
        return true;
    }

    /**
     * 确定事件页面获取资产可以选择的事件类别
     * @param $assetId
     */
    public function wxSelectCategory($assetId) {
        $data = [];
        $state = null;

        if(!empty($assetId)) {
            $device = Device::findOrFail($assetId);
            $data['assetNumber'] = $device->number;
            $state = $device->state;

            if ($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                if (!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }
        $categories = $this->categoryModel->getCategoriesByState($state, true);
        if(false === $categories) {
            throw new ApiException(Code::ERR_DEVICE_NOT_INUSE);
        }
        $data['categories'] = $categories;

        return $data;
    }

    /**
     * 工程师自建，直接为处理中状态
     * @param $assetId
     * @param $categoryId
     * @param $typeId
     * @param $userId
     */
    public function wxAdd($input) {

        $input['eventState'] = Event::STATE_ING;

        return $this->add($input);
    }

    /**
     * 事件处理页面
     * @param $request
     */
    public function wxProcess($event) {
//        $event = $this->getById($request->input("eventId"));
        $data['eventId'] = $event->id;
        $data['assetId'] = $event->asset_id;
        $data['assetNumber'] = is_null($event->asset) ? '' : $event->asset->number;
        $data['remark'] = $event->remark;
        $data['createTime'] = $event->created_at->format('Y-m-d H:i:s');
        $data['state'] = $event->state;
        $data['categoryId'] = $event->category_id;
        $data['category'] = is_null($event->category) ? '' : $event->category->name;
        $data['userId'] = $event->user_id;
        $data['user'] = is_null($event->user) ? '' : $event->user->username;
        $data['assignerId'] = $event->assigner_id;
        $data['assigner'] = is_null($event->assigner)?'':$event->assigner->username;
        $data['reportId'] = $event->report_id;
        $data['reporter'] = is_null($event->reporter)?'':$event->reporter->username;
        $data['reportName'] = $event->report_name;
        $data['mobile'] = $event->mobile;
        $data['description'] = $event->description;
        $data['source'] = $event->source;
        $data['suspend_status'] = $event->suspend_status;
        $categoryId = $event->category_id;

        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
            }
        }

        $moreData = array();
        if(2 ==  $event->state) {
            if (!$categoryId) {
                throw new ApiException(Code::ERR_PARAMS, ["事件类型不存在！"]);
            }

            $moreData = BaseEventsRepository::init($categoryId)->process($event);
        }
        return array_merge($data, $moreData);
    }

    /**
     * 事件提交
     * @param $request
     */
    public function wxSave($request,$event=array()) {
//        $event = $this->getById($request->input("eventId"));
        $remark = $request->input("remark");
        $eventId = $request->input("eventId");
        if(!$this->canSave($event)) {
            Code::setCode(Code::ERR_EVENT_PERM);
            return false;
        }
        if(in_array($event->state,[Event::STATE_END,Event::STATE_CLOSE])) {
            //事件已完成，不用后续操作
            Code::setCode(Code::ERR_EVENT_STATE);
            return false;
        }
        if($event->suspend_status>0){
            //挂起事件不能做完成操作
            Code::setCode(Code::ERR_SUSPEND_NOT_END);
            return false;
        }

        if(!empty($event->asset_id)) {
            if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
                $limitCategories = $this->userRepository->getCategories();
                $device = Device::findOrFail($event->asset_id);
                if(!in_array($device->sub_category_id, $limitCategories)) {
                    Code::setCode(Code::ERR_USER_CATEGORY);
                    return false;
                }
            }
        }

        $categoryId = $event->category_id; //事件类别

        BaseEventsRepository::init($categoryId)->save($request->input());
        $source = $event->source;
        $date = date("Y-m-d H:i:s");
        $datetime = strtotime($date);
        /*//用户（终端）上报
        if(Event::SRC_TERMINAL == $source) {
            $where = array('event_id' => $eventId, 'state' => Event::STATE_ING,'etype' => \ConstInc::WX_ETYPE);
            $etRes = $this->eventsTrackRepository->getOne($where);
            $etCreate = isset($etRes['created_at']) ? strtotime($etRes['created_at']) : 0;
        }else{
            //其他
            $etCreate = isset($event['created_at']) ? strtotime($event['created_at']) : 0;
        }*/

        $reached_at = $event->reached_at;
        $reachedAtTime = $reached_at ? strtotime($reached_at) : '';
        $suspendUsetime = isset($event['suspend_usetime']) ? intval($event['suspend_usetime']) : 0;
//            var_dump(strtotime($date),$etCreate,$suspendUsetime);exit;
        $process_time = $reachedAtTime && $datetime>$reachedAtTime ? $datetime - $reachedAtTime - $suspendUsetime : 0;



        //更新事件状态
        $update = [
            "state" => Event::STATE_END,
            "remark" => $remark,
            "updated_at" => $date,
            "process_time" => $process_time,
            "finished_at" => $date
        ];
        $this->update($eventId, $update);
        return $update;
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
     * 验证工程师和工程师主管对资产是否有权限操作
     * @param $event
     * @param array $uids
     * @return bool
     */
    public function checkAssetOperationAccess($event,$uids=array(),$eManager=false){
        $result = false;
        $eManager = $eManager ? $eManager : false;
        if($uids) {
            $asset_id = isset($event['asset_id']) ? $event['asset_id'] :'';
            if ($asset_id) {
                $device = Device::findOrFail($asset_id);
                $userRes = $this->userRepository->getEngineersByCategory([$device->sub_category_id]);
                if($userRes) {
                    $key = 0;
                    foreach ($userRes as $k => $user) {
                        $flag = false;
                        if($eManager){
                            $flag = $user->identity_id == 2 ? true : false;
                        }
                        if(!is_null($user->id) && $flag == $eManager) {
                            $result[$key] = $user->id;
                            $key++;
                        }
                    }
                }
            }else{
                $result = $uids;
            }
        }
        return $result;
    }


    public function eventsImportAdd($data=array(),$param=array()){
        $result = 0;
        if($data && is_array($data)) {
            $assets_wrong = array('5'=>'终端运维');
            $assetsWrongKey = array_flip($assets_wrong);
            $sourceArr = array(
                '自建' => Event::SRC_SELF,
                '分派' => Event::SRC_ASSIGN,
                '监控' => Event::SRC_MONITOR,
                '终端' => Event::SRC_TERMINAL,
            );
            $aSolutionArr = array();
            $aSolutionKeys = array();
            $cid = isset($param['categoryId']) ? $param['categoryId'] : 0;
            $sourceParam = isset($param['source']) ? $param['source'] : 0;
            $wrongId = isset($param['wrongId']) ? $param['wrongId'] : 0;
            $aSolutionId = isset($param['solutionId']) ? $param['solutionId'] : 0;
            $wrongId = $wrongId ? $wrongId : $assetsWrongKey['终端运维'];
            $asWhere = array('wrong_id'=>$wrongId);
            $aSolutionRes = $this->solutionModel->getListByWhere($asWhere);
//            $aSolutionRes = $aSolutionRes->toArray();
            if($aSolutionRes){
                foreach($aSolutionRes as $asv){
                    $asId = isset($asv['id']) ? $asv['id'] : 0;
                    $asName = isset($asv['name']) ? $asv['name'] : 0;
                    $aSolutionArr[$asId] = $asName;
                }
                if($aSolutionArr){
                    $aSolutionKeys = array_flip($aSolutionArr);
                }
            }
//            var_dump($aSolutionArr,$aSolutionKeys);exit;
            $stateMsgKey = array_flip(Event::$stateMsg);


            $insertIdArr = array();
            $date = date("Y-m-d H:i:s");
//            var_dump($data);exit;
            DB::beginTransaction();
            try{
            foreach($data as $k=>$v) {
                $strSource = isset($v['0']) ? $v['0'] : '';
                $strSource = isset($sourceArr[$strSource]) ? $sourceArr[$strSource] : '';
                $source = $sourceParam ? $sourceParam : $strSource;
                $assets_solution = isset($v['1']) ? $v['1'] : '';
                $assets_solution = isset($aSolutionKeys[$assets_solution]) ? $aSolutionKeys[$assets_solution] : '';
                $solutionId = $aSolutionId ? $aSolutionId : $assets_solution;
                $stateInput = isset($v['7']) ? $v['7'] : '';
                $state = isset($stateMsgKey[$stateInput]) ? $stateMsgKey[$stateInput] : Event::STATE_END;

                $description = isset($v['10']) ? trim($v['10']) : '';
                if($k>0 && $description && $assets_solution) {
                    $kArr[] = $k;
                    $sdate = isset($v['2']) ? $v['2'] : '';
                    $stime = isset($v['3']) ? $v['3'] : '';
                    $edate = isset($v['4']) ? $v['4'] : '';
                    $etime = isset($v['5']) ? $v['5'] : '';
                    $created = $sdate . ' ' . $stime;
                    if($sdate){
                        $created = strtotime($created);
                        $created = date('Y-m-d H:i:s',$created);
                    }
                    $created_end = $edate . ' ' . $etime;
                    if($edate){
                        $created_end = strtotime($created_end);
                        $created_end = date('Y-m-d H:i:s',$created_end);
                    }
                    $userName = isset($v['6']) ? $v['6'] : '';
                    $uWhere = array('username' => $userName);
                    $userRes = $this->userRepository->getOne($uWhere);
                    $userId = is_null($userRes->id) ? 0 : $userRes->id;
                    $report_name = isset($v['8']) ? $v['8'] : '';
                    $mobile = isset($v['9']) ? $v['9'] : '';

                    $insert = [
                        "category_id" => $cid,
                        "user_id" => $userId,
                        "assigner_id" => $userId,
                        "state" => $state,
                        "source" => $source,
                        "mobile" => $mobile,
                        "report_name" => $report_name,
                        "description" => $description,
                        "is_comment" => 1,
                        "created_at" => $created,
                        "updated_at" => $created_end,
                    ];
//                    var_dump($insert);exit;
                    $insertId = $this->model->insertGetId($insert);

                    //添加事件评论
                    $paramEC = array(
                        'star_level'=>5,
                        'content'=>'此用户没有填写评价。',
                        'feedback'=>'此用户没有填写反馈。',
                        'etype' => \ConstInc::WX_ETYPE,
                        'created_at' => $created_end,
                        'updated_at' => $created_end,
                    );
                    $ecommentID = $this->eventsComment->addBatch(array($insertId),$paramEC);

                    $insertIdArr[] = $insertId;
                    $event_id = $insertId ? $insertId : 0;

                    $trackParam = array(
                        array(
                            "event_id" => $event_id,
//                            "description" => $description,
                            "step" => Event::STATE_ACCESS,
                            "state" => Event::STATE_ACCESS,
                            "etype" => \ConstInc::WX_ETYPE,
                            "created_at" => $created,
                            "updated_at" => $created,
                        ),
                        array(
                            "event_id" => $event_id,
//                            "description" => $description,
                            "step" => $state,
                            "state" => $state,
                            "etype" => \ConstInc::WX_ETYPE,
                            "created_at" => $created_end,
                            "updated_at" => $created_end,
                        )
                    );
//                    var_dump($trackParam);exit;
                    //事件跟踪
                    $trackRes = $this->eventsTrackRepository->addBatch($trackParam);

                    //事件资产维护信息
                    $maintainInsert = [
                        "event_id" => $event_id,
                        "wrong_id" => $wrongId,
                        "solution_id" => $solutionId,
                        "created_at" => $created,
                        "updated_at" => $created_end
                    ];
                    $maintainRes = $this->maintainModel->insert($maintainInsert);
                    Log::info('[number:'.$k.']'.$insertId.'_import_event_maintain:'.$maintainRes.',event_track:'.$trackRes);
                }
            }

            Log::info('import_event_insertids:'.json_encode($insertIdArr));
            $result = count($insertIdArr);
            DB::commit();
            } catch (\Exception $e){
                DB::rollback();//事务回滚
                Log::info('number:'.json_encode($kArr).',import_event_error:'.json_encode($e->getMessage()) .',code:'. json_encode($e->getCode()));
            }
        }
        return $result;
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
     * 事件报表
     * @param $request
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     */
    public function getReportForm($request, $export = false, $sortColumn = "id", $sort = "desc") {
        $state = $request->input("state");
        $source = $request->input("source");
        $assetIds = $request->input("assetIds");
        $actCategory = $request->input("actCategory");
        $users = $request->input("users");
        $responseTime = $request->input("responseTime");
        $processTime = $request->input("processTime");
        $distanceTime = $request->input("distanceTime");
        $where = [];
        $tbl = "workflow_events";

        if(!is_null($state)) {
            $where[] = [$tbl.".state","=", $state];
        }
        if(!is_null($source)) {
            $where[] = [$tbl.".source","=", $source];
        }

        $model = $this->model->with('category',"user","assigner","asset")->where($where);
        $actCategoryArr = array_filter(array_unique(explode(',',$actCategory)),'arrayFilterCallbak');
        if($actCategoryArr){
            $model = $model->whereIn($tbl.".category_id",$actCategoryArr);
        }
        $assetIdArr = array_filter(array_unique(explode(',',$assetIds)),'arrayFilterCallbak');
        if($assetIdArr){
            $model = $model->whereIn($tbl.".asset_id",$assetIdArr);
        }
        $usersArr = array_filter(array_unique(explode(',',$users)),'arrayFilterCallbak');
        if($usersArr){
            $model = $model->whereIn($tbl.".user_id",$usersArr);
        }

        //搜索资产名称，IP，资产编号，事件处理人
        $model->leftJoin("users as B","$tbl.user_id","=", "B.id")
            ->leftJoin("assets_device as A","$tbl.asset_id","=","A.id");
        $model->whereNull('A.deleted_at');

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
        //处理时间
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
            $distance = Event::$timeArr[$distanceTime];
            if(Event::TIME_5 == $distanceTime){
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
        $total = $model2->select(
            DB::raw("sum($tbl.process_time) as total_process_time"),
            DB::raw("sum($tbl.response_time) as total_response_time"),
            DB::raw("sum($tbl.distance_time) as total_distance_time")
        )->first();
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
            $asset = $v['asset'];
            unset($v['category']);
            unset($v['assigner']);
            unset($v['user']);
            unset($v['asset']);

            $list[$k] = $v;
            $source = isset($v['source'])?$v['source']:'';
            $created_at = !empty($v['updated_at']) ? $v['updated_at'] : $v['updated_at'];
            $list[$k]['created_date'] = $created_at ? date('Y-m-d',strtotime($created_at)):'';
            $list[$k]['created_time'] = $created_at ? date('H:i',strtotime($created_at)):'';
            $list[$k]['source_name'] = isset(Event::$sourceMsg[$source]) ? Event::$sourceMsg[$source]:'';
            $list[$k]['category'] = isset($category['name'])?$category['name']:'';
            $list[$k]['assigner'] = isset($assigner['username'])?$assigner['username']:'';
            $list[$k]['user'] = isset($user['username'])?$user['username']:'';
            $list[$k]['asset_number'] = isset($asset['number'])?$asset['number']:'';
            $list[$k]['asset_name'] = isset($asset['name'])?$asset['name']:'';
            $list[$k]['state_name'] = isset(Event::$stateMsg[$v['state']]) ? Event::$stateMsg[$v['state']]:'';
            $list[$k]['process_time_human'] = sec2Time($v['process_time'],1);
            $list[$k]['response_time_human'] = sec2Time($v['response_time'],1);
            $list[$k]['distance_time_human'] = sec2Time($v['distance_time'],1);
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
            }else {
                $ct = count($list);
                $exportLimit = \ConstInc::EXPORT_LIMIT;
                //导出数据不能大于限制条数
                if($ct > $exportLimit){
                    Code::setCode(Code::ERR_EXPORT_LIMIT,null,[$exportLimit]);
                    return false;
                }
                $result['meta'] = [
                    [
                        'total' => $ct,
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
        foreach(Event::$sourceMsg as $k=>$v){
            $result[] = array(
                'id' => $k,
                'name' => $v,
            );
        }
        return $result;
    }

    public function getState(){
        foreach(Event::$stateMsg as $k=>$v){
            $result[] = array(
                'id' => $k,
                'name' => $v,
            );
        }
        return $result;
    }


    /**
     * 事件和OA事件报表
     * @param $request
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     */
    public function getReportOAForm($request, $export = false, $sortColumn = "updated_at", $sort = "desc") {
        $users = $request->input("users");
        $where = [];
        $tbl = "workflow_events";
        $tblOa = 'workflow_oa';

        $fields = array(
            $tbl.'.id',
            $tbl.'.user_id',
            $tbl.'.category_id',
            $tbl.'.asset_id',
            'A.name AS asset_name',
            'A.number AS asset_number',
            DB::raw('IF('.$tbl.'.id,0,NULL) AS etype'),
            $tbl.'.report_id',
            $tbl.'.report_name',
            DB::raw('NULL AS company'),
            DB::raw('NULL AS location'),
            DB::raw('NULL AS problem'),
            DB::raw('NULL AS object'),
            $tbl.'.state',
            $tbl.'.source',
            $tbl.'.mobile',
            $tbl.'.remark',
            $tbl.'.description AS des',
            $tbl.'.response_time',
            $tbl.'.distance_time',
            $tbl.'.process_time',
            $tbl.'.created_at',
            $tbl.'.updated_at',
            $tbl.'.deleted_at',
        );


        $eventSql = $this->model->select($fields)
            ->leftJoin("assets_device as A","$tbl.asset_id","=","A.id")
            ->whereNull('A.deleted_at');

        $oaFileds = array(
            $tblOa.'.id',
            $tblOa.'.user_id',
            $tblOa.'.category_id',
            DB::raw('NULL AS asset_id'),
            DB::raw('NULL AS asset_name'),
            DB::raw('NULL AS asset_number'),
            DB::raw('if('.$tblOa.'.id,1,null) as etype'),
            $tblOa.'.report_id',
            $tblOa.'.report_name',
            $tblOa.'.company',
            $tblOa.'.location',
            $tblOa.'.problem',
            $tblOa.'.object',
            $tblOa.'.state',
            $tblOa.'.source',
            $tblOa.'.mobile',
            $tblOa.'.remark',
            $tblOa.'.description as des',
            $tblOa.'.response_time',
            $tblOa.'.distance_time',
            $tblOa.'.process_time',
            $tblOa.'.created_at',
            $tblOa.'.updated_at',
            $tblOa.'.deleted_at',
        );
        $oaSql = DB::table($tblOa)->select($oaFileds);

        $usersArr = array_filter(array_unique(explode(',',$users)),'arrayFilterCallbak');
        if($usersArr){
            $eventSql =$eventSql->whereIn($tbl.".user_id",$usersArr);
            $oaSql =$oaSql->whereIn($tblOa.".user_id",$usersArr);
        }


        if (false !== ($between = $this->searchTime($request))) {
            $begin = isset($between[0]) ? $between[0] : '';
            $end = isset($between[1]) ? $between[1] : '';
            if($begin && $end) {
                $eventSql =$eventSql->whereBetween("$tbl.updated_at", $between);
                $oaSql =$oaSql->whereBetween("$tblOa.updated_at", $between);
            }else{
                $beTime = array();
                $beTimeOa = array();
                if($begin){
                    $beTime[] = ["$tbl.updated_at",">=" ,$begin];
                    $beTimeOa[] = ["$tblOa.updated_at",">=" ,$begin];
                }elseif($end){
                    $beTime[] = ["$tbl.updated_at","<=" ,$end];
                    $beTimeOa[] = ["$tblOa.updated_at","<=" ,$end];
                }
                if($beTime) {
                    $eventSql =$eventSql->where($beTime);
                    $oaSql =$oaSql->where($beTimeOa);
                }
            }
        }
        $oaSql->union($eventSql);
        $sql = $this->model->with('category',"user","suspend")
            ->from(DB::raw("({$oaSql->toSql()}) as $tbl"))
            ->mergeBindings($oaSql);
        $sqlTotal = clone $sql;
        $total = $sqlTotal->select(
            DB::raw("sum(process_time) as total_process_time"),
            DB::raw("sum(response_time) as total_response_time"),
            DB::raw("sum(distance_time) as total_distance_time")
        )->first();
        unset($sqlTotal);

        if(false === $export) {
            //页面显示
            $resObj = $this->usePage($sql, $sortColumn, $sort);
        }else{
            //导出
            $resObj = $sql->orderBy($sortColumn, $sort)->get();
        }
        $res = $resObj ? $resObj->toArray() : array();
        $result = array('result'=>array());
        $list = array();
        if(isset($resObj['data'])) {
            $data = $resObj['data'];
        }
        else {
            $data = $resObj;
        }
        foreach($data as $k=>$v){
//            $v = (array)$v;
            $category = $v['category'];
            $assigner = $v['assigner'];
            $user = $v['user'];
            $suspend = $v['suspend'];
            unset($v['category']);
            unset($v['assigner']);
            unset($v['user']);
            unset($v['suspend']);
            $list[$k] = $v;
            $etype = isset($v['etype'])?$v['etype']:'';
            $source = isset($v['source'])?$v['source']:0;
            $object = isset($v['object']) ? $v['object'] : '';
            $remark = isset($v['remark']) ? $v['remark'] : '';
            $event_type = '资产';
            $object_name = '';
            $remark_txt = '';
            if($etype == 1) {
                $event_type = 'OA';
                $source_name = isset(Oa::$sourceMsg[$source]) ? Oa::$sourceMsg[$source] : '';
                $state_name = isset(Oa::$stateMsg[$v['state']]) ? Oa::$stateMsg[$v['state']]:'';
                $object_name = isset(Oa::$objectMsg[$object])?Oa::$objectMsg[$object]:'';
            }else {
                $remark_txt = $remark;
                $remark = '';
                $list[$k]['problem'] = isset($v['des']) ? $v['des'] : '';
                $list[$k]['des'] = '';
                $source_name = isset(Event::$sourceMsg[$source]) ? Event::$sourceMsg[$source] : '';
                $state_name = isset(Event::$stateMsg[$v['state']]) ? Event::$stateMsg[$v['state']]:'';

            }
            $created_at = isset($v['updated_at']) ? $v['updated_at'] : '';
            $list[$k]['created_date'] = $created_at ? date('Y-m-d',strtotime($created_at)):'';
            $list[$k]['created_time'] = $created_at ? date('H:i',strtotime($created_at)):'';
            $list[$k]['source_name'] = $source_name;
            $list[$k]['asset_number'] = isset($v['asset_number'])?$v['asset_number']:'';
            $list[$k]['asset_name'] = isset($v['asset_name'])?$v['asset_name']:'';
            $list[$k]['state_name'] = $state_name;
            $list[$k]['object_name'] = $object_name;
            $list[$k]['user'] = isset($user['username'])?$user['username']:'';
            $list[$k]['category'] = isset($category['name'])?$category['name']:'';
            $list[$k]['event_type'] = $event_type;
            $list[$k]['remark_txt'] = $remark_txt;
            $list[$k]['remark'] = $remark;
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
//        var_dump(count($list));exit;

        if($list) {
            $result['result'] = $list;
            if(false === $export) {
                //页面显示
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
            } else {
                //导出
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


    /**
     * 处理中的事件图表（待处理,已结单,处理中）
     * @param array $input
     * @return array
     */
    public function getEventsProcess($input=array()){
        $result = array();
        $list = getKey($input,'list');
        $model = $this->model;
        $event_cnt = 'event_cnt';
        if(!$list) {
            $event_cnt = 'value';
        }
        $state_msg = 'state_msg';

        $fieldArrKeys = ['workflow_events.state as '.$state_msg,
            DB::raw('count(workflow_events.id) as '.$event_cnt)
        ];
        $model = $model->select($fieldArrKeys)
            ->join('assets_device as A','A.id','=','workflow_events.asset_id')
            ->whereIn('workflow_events.state',[0,1,2])
            ->whereNull('A.deleted_at')
            ->groupBy($state_msg)
            ->orderBy("$event_cnt","desc");
        $res = $model->get();
        $res = $res ? $res->toArray() : array();
//        var_dump($res);exit;
        foreach($res as $k=>&$v){
            $name = getKey($v,'state_msg');
            $v['name'] = isset(Event::$stateMsg[$name]) ? Event::$stateMsg[$name] : '';
        }
        if(empty($res)){
            $res = [
                ['name'=>'处理中','value'=>0],
                ['name'=>'待处理','value'=>0],
                ['name'=>'已接单','value'=>0],
            ];
        }else{
            $diff_arr = array_diff(['处理中','待处理','已接单'],array_column($res,'name'));
            if(!empty($diff_arr)){
                foreach($diff_arr as $val){
                    $res[] = ['name'=>$val,'value'=>0];
                }
            }
        }
        $result['result'] = $res;
        return $result;
//        return $this->usePage($model);
    }


    /**
     * 处理中的事件列表（待处理,已结单,处理中）
     * @return mixed
     */
    public function getEventsProcessList($input = array()){
        $model = $this->deviceModel;
        $state = $input['state']??'';
        $search = $input['search']??'';

        $fieldArrKeys = [
            "assets_device.id",
            "assets_device.name",
            "assets_device.category_id",
            "assets_device.sub_category_id",
            "assets_device.number",
            "location",
            "officeBuilding",
            "department",
            "area",
            "A.state as event_state",
            "A.description as description",
            "B.username as username"
        ];
        $model = $model->select($fieldArrKeys)->with('sub_category','events')
            ->join('workflow_events as A','assets_device.id','=','A.asset_id')
            ->leftjoin('users as B','B.id','=','A.user_id')
            ->whereNull('A.deleted_at');
//            ->whereIn('A.state',[0,1,2])
//            ->orderBy("A.id","desc");

        if($state || array_key_exists($state,$this->getEventsProcessState())){
            $model->where('A.state','=',$state);
        }else{
            $model->whereIn('A.state',array_keys($this->getEventsProcessState()));
        }


        $model->where(function($query) use($search){
           if(!empty($search)){
               $query->orWhere("assets_device.name","like","%".$search."%");
               $query->orWhere("assets_device.number","like","%".$search."%");
           }
        });

        return $this->usePage($model,'A.id','desc');
    }

    public function getEventsProcessState(){
        $stateMsg = $this->model::$stateMsg;
        $stateMsg= array_slice($stateMsg,0,3);
        return $stateMsg;
    }

    /**
     * 获取事件统计，用于报表
     */
    public function getWeekEvents() {
        //周：周一到周日
        $thisFrom = date("Y-m-d H:i:s",strtotime("Monday last week")); //取上周数据
        $thisTo = date("Y-m-d H:i:s",strtotime("Monday this week"));
        $lastFrom = date("Y-m-d H:i:s",strtotime("Monday last week") - 86400 * 7);
        $lastTo = $thisFrom;
        $lastAllCnt = $this->model->where("updated_at",">=", $lastFrom)->where("updated_at","<",$lastTo)->count(); //上上周运维事件数
        $thisAllCnt = $this->model->where("updated_at",">=", $thisFrom)->where("updated_at","<",$thisTo)->count(); //上周运维事件数
        if($lastAllCnt > 0) {
            $diffAll = $thisAllCnt - $lastAllCnt;
        }
        else {
            $diffAll = 0;
        }

        $lastTerCnt = $this->model->where("updated_at",">=", $lastFrom)->where("updated_at","<",$lastTo)->where("source","=",Event::SRC_TERMINAL)->count();
        $thisTerCnt = $this->model->where("updated_at",">=", $thisFrom)->where("updated_at","<",$thisTo)->where("source","=",Event::SRC_TERMINAL)->count();
        if($lastTerCnt > 0) {
            $diffTer = $thisTerCnt - $lastTerCnt;
        }
        else {
            $diffTer = 0;
        }

        //用户报修次数
        $data = $this->maintainModel->select(DB::raw("count(workflow_maintain.id) as cnt, wrong_id"))
            ->join("workflow_events","event_id","=","workflow_events.id")
            ->where("workflow_events.updated_at",">=", $thisFrom)
            ->where("workflow_events.updated_at","<",$thisTo)
            ->where("category_id","=", Category::MAINTAIN)
            ->groupBy("wrong_id")
            ->get()->pluck("cnt","wrong_id")->toArray();

        $wrong = $this->wrongModel->get()->pluck("name","id");
        $result = [];
        foreach($wrong as $k => $v) {
            $result[] = [
                "name" => $v,
                "value"  => isset($data[$k]) ? $data[$k] : 0
            ];
        }

        $ret = [
            "all" => $thisAllCnt,
            "diffAll" => $diffAll,
            "terminal"  => $thisTerCnt,
            "diffTerminal" => $diffTer,
            "terminalCategory" => $result
        ];

        return $ret;
    }

    // 统计每个事件的数据总量 上周的
    public function getEveryEvent(){
        $model = $this->model;
        // 入库
//        $in_storage = $model->where('category_id','=',1)->whereRaw('YEARWEEK(date_format(created_at,\'%Y-%m-%d\')) = YEARWEEK(now())-1')->count();
        // 上架
        $shelves = $model->where('category_id','=',2)->whereRaw('YEARWEEK(date_format(created_at,\'%Y-%m-%d\')) = YEARWEEK(now())-1')->count();
        // 变更
        $change = $model->where('category_id','=',3)->whereRaw('YEARWEEK(date_format(created_at,\'%Y-%m-%d\')) = YEARWEEK(now())-1')->count();
        // 维护
        $maintenance = $model->where('category_id','=',4)->whereRaw('YEARWEEK(date_format(created_at,\'%Y-%m-%d\')) = YEARWEEK(now())-1')->count();
        // 下架
        $downshelves = $model->where('category_id','=',5)->whereRaw('YEARWEEK(date_format(created_at,\'%Y-%m-%d\')) = YEARWEEK(now())-1')->count();
        // 报废
//        $scrap = $model->where('category_id','=',6)->whereRaw('YEARWEEK(date_format(created_at,\'%Y-%m-%d\')) = YEARWEEK(now())-1')->count();
        // 批量入库
//        $batch_storage = $model->where('category_id','=',7)->get()->count();
        // 批量下架
//        $batch_downshelves = $model->where('category_id','=',8)->get()->count();
        $data['result'] = [
//            ['name'=>'入库','value'=>$in_storage],
            ['name'=>'上架','value'=>$shelves],
            ['name'=>'变更','value'=>$change],
            ['name'=>'维护','value'=>$maintenance],
            ['name'=>'下架','value'=>$downshelves],
//            ['name'=>'报废','value'=>$scrap],
//            ['name'=>'批量入库','value'=>$batch_storage],
//            ['name'=>'批量下架','value'=>$batch_downshelves],
        ];
        return $data;

    }






    /**
     * 获取终端事件趋势，用于报表
     */
    public function getTerminalTrend() {
        $currentFrom = strtotime("Monday last week"); //从上周开始
        $weekNum = 10;
        $weeks = [];
        for($i = 0;$i < $weekNum;$i++) {
            //$w = date("W", $currentFrom);  //改用具体日期
            $d = date("Y-m-d",$currentFrom);
            $weeks[$d] = [date("Y-m-d H:i:s",$currentFrom), date("Y-m-d H:i:s",$currentFrom + 86400 * 7)];
            $currentFrom -= 86400 * 7;
        }
        $weeks = array_reverse($weeks);

        $caseSql = "(CASE";
        foreach($weeks as $k => $v) {
            $caseSql .= " WHEN workflow_events.updated_at >= '{$v[0]}' and workflow_events.updated_at < '{$v[1]}' THEN '$k'";
        }
        $caseSql .= "END ) week";

        //变更
        $modifyResult = $this->model->select(DB::raw("count(id) as cnt, $caseSql"))
            ->whereIn("category_id", [Category::MODIFY, Category::MULTI_MODIFY])
            ->groupBy("week")->get()->pluck("cnt","week");

        //上架
        $upResult = $this->model->select(DB::raw("count(id) as cnt, $caseSql"))
            ->whereIn("category_id", [Category::UP, Category::MULTI_UP])
            ->groupBy("week")->get()->pluck("cnt","week");

        $wrongId = 5;//5是wrong表的巡检id

        //故障处理
        $maintainResult = $this->model->select(DB::raw("count(workflow_events.id) as cnt, $caseSql"))
            ->join("workflow_maintain","workflow_events.id","=","event_id")
            ->where("wrong_id","!=",$wrongId ) //不包含巡检的故障处理
            ->whereIn("category_id", [Category::MAINTAIN])
            ->groupBy("week")->get()->pluck("cnt","week");

        //巡检
        $xunjianResult = $this->model->select(DB::raw("count(workflow_events.id) as cnt, $caseSql"))
            ->join("workflow_maintain","workflow_events.id","=","event_id")
            ->where("wrong_id","=",$wrongId ) //巡检
            ->whereIn("category_id", [Category::MAINTAIN])
            ->groupBy("week")->get()->pluck("cnt","week");

        $upRs = [];
        $modifyRs = [];
        $maintainRs = [];
        $xunjianRs = [];

        foreach($weeks as $k => $v) {
            $upRs[] = isset($upResult[$k]) ? $upResult[$k] : 0;
            $modifyRs[] = isset($modifyResult[$k]) ? $modifyResult[$k] : 0;
            $maintainRs[] = isset($maintainResult[$k]) ? $maintainResult[$k] : 0;
            $xunjianRs[] = isset($xunjianResult[$k]) ? $xunjianResult[$k] : 0;
        }

        $result = [
            "data" => [
                [ "name" => "变更", "data" => $modifyRs],
                [ "name" => "上架", "data" => $upRs],
                [ "name" => "故障处理", "data" => $maintainRs],
                [ "name" => "巡检", "data" => $xunjianRs],
            ],
            "Xdata" => array_keys($weeks)
        ];
        return $result;

    }

    /**
     * 设备故障类型
     * @param array $input
     * @return mixed
     */
    public function getSolutionType($input=array(),$request){

        $list = getKey($input,'list');
        $search = getKey($input,'search');
        $solution_id = 25;
        $where = null;
        $where[] = ['B.id','!=',$solution_id];

        if($list) {
            $model = $this->model;

            $fieldArrKeys = [
                "workflow_events.id",
                "workflow_events.report_id",
                "workflow_events.user_id",
                "workflow_events.process_time",
                "workflow_events.response_time",
                "workflow_events.distance_time",
                "B.id as solution_id",
                "B.name as solution_name",
            ];
            if($where){
                $model = $model->where($where);
            }
            $model = $model->select($fieldArrKeys)
                ->join('workflow_maintain as A','workflow_events.id','=','A.event_id')
                ->leftjoin('assets_solution as B','A.solution_id','=','B.id');

            $between = $this->searchTime($request);
            if(false !== $between){
                $model->whereBetween("A.updated_at", $between);
            }
            if($search){
                $model->where('B.name','like',"%{$search}%");
            }

            $result = $this->usePage($model);

            if($result){
                foreach($result as &$v){
                    $process_time = isset($v['process_time'])?$v['process_time']:'';
                    $response_time = isset($v['response_time'])?$v['response_time']:'';
                    $distance_time = isset($v['distance_time'])?$v['distance_time']:'';
                    $v['process_time_msg'] = sec2Time($process_time);
                    $v['response_time_msg'] = sec2Time($response_time);
                    $v['distance_time_msg'] = sec2Time($distance_time);
                }
            }
            return $result;
        }else{
            $model = $this->maintainModel;
            $cnt = 'value';
            $fieldArrKeys = [
                DB::raw('count(workflow_maintain.id) as '.$cnt),
                "workflow_maintain.id",
                "B.name"
            ];
            if($where){
                $model = $model->where($where);
            }
            $model = $model->select($fieldArrKeys)
                ->rightjoin('assets_solution as B','workflow_maintain.solution_id','=','B.id')
                ->groupBy("B.id")
                ->orderBy($cnt,"desc");
            return $this->usePage($model);
        }
    }

    public function getWrongTypeNum(){
        $model = $this->maintainModel;
        $sql = $model->select(DB::raw('count(*) as value'),'wrong_id')->groupBy('wrong_id');
        $data = $this->wrongModel->select('assets_wrong.name as name',DB::raw('coalesce(A.value,0) as value'))->leftjoin(DB::raw("({$sql->toSql()}) as A"),'A.wrong_id','assets_wrong.id')->get()->toArray();
//        $data = $model->leftjoin('assets_wrong as A','workflow_maintain.wrong_id','=','A.id')->select(DB::raw('count(*) as value'),'A.name as name')->groupBy('wrong_id')->get()->toArray();
        return ['result' => $data];
    }



}