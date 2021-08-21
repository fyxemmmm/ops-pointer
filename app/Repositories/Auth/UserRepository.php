<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Auth;

use App\Repositories\BaseRepository;
use App\Models\Auth\User;
use App\Models\Auth\Engineer;
use App\Models\Auth\EngineersWorktype;
use App\Models\Auth\UsersEngineers;
use App\Models\Assets\Category;
use Auth;
use App\Models\Workflow\Event;
use App\Models\Code;
use Hash;
use DB;

class UserRepository extends BaseRepository
{

    public $user;
    protected $engineersWorktypeModel;
    protected $categoryModel;
    protected $engineerModel;
    protected $usersEngineersModel;
    protected $eventModel;

    public function __construct(User $userModel,
                                EngineersWorktype $engineersWorktypeModel,
                                Category $categoryModel,
                                Engineer $engineerModel,
                                UsersEngineers $usersEngineersModel,
                                Event $eventModel
    )
    {
        $this->model = $userModel;
        $this->engineersWorktypeModel = $engineersWorktypeModel;
        $this->engineerModel = $engineerModel;
        $this->categoryModel = $categoryModel;
        $this->usersEngineersModel = $usersEngineersModel;
        $this->eventModel = $eventModel;
    }

    public function getUser() {
        if(empty($this->user)) {
            $this->user = Auth::user();
        }
        return $this->user;
    }

    public function getUserByName($name) {
        return $this->model->where("name", "=", $name)->first();
    }

    public function getUserByPhone($name) {
        return $this->model->where("phone", "=", $name)->first();
    }

    public function getEngineerList($request) {
        $search = $request->input("search");

        $where = null;
        if(!empty($search)) {
            $where[] = ['name', 'like', "%". $search . "%"];
        }

        $model = $this->engineerModel;
        if(!empty($where)) {
            $model = $model->where($where);
        }
        return $this->usePage($model);
    }

    public function addEngineer($request) {
        if(!$this->isAdmin()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        $input = [
            "name" => $request->input("name"),
            "desc" => $request->input("desc"),
        ];
        $this->engineerModel->fill($input)->save();

        userlog("添加了工程师：".$request->input("name"));
    }

    public function editEngineer($request) {
        if(!$this->isAdmin()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        $model = $this->engineerModel->findOrFail($request->input("id"));

        $model->name = $request->input("name");
        $model->desc = $request->input("desc");
        $model->save();

        userlog("编辑了工程师：".$request->input("id"));
    }

    public function delEngineer($id) {
        if(!$this->isAdmin()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        $model = $this->engineerModel->findOrFail($id);

        $name = $model->name;

        $model->delete();

        $this->usersEngineersModel->where("engineer_id","=",$id)->delete();

        userlog("删除了工程师：".$name);
    }

    public function getEngineer($id) {
        $model = $this->engineerModel->findOrFail($id);
        return $model;
    }

    /**
     * 取当前用户的缩略信息
     */
    public function getBasic() {
        $user = Auth::user();
        $category = $this->getCategories();
        return [
            "id" => $user->id,
            "name" => $user->name,
            "username" => $user->username,
            "phone" => $user->phone,
            "email" => $user->email,
            "telephone" => $user->telephone,
            "identity_id" => $user->identity_id,
            "identity" => $user->identity->identity,
            "category" => $category
        ];
    }

    /**
     * 取用户工种
     */
    public function getCategories($userId = 0) {

        if($userId === 0) {
            $user = Auth::user();
            $userId = $user->id;
        }

        $category = $this->usersEngineersModel
            ->join("engineers_worktype as B","users_engineers.engineer_id","=","B.engineer_id")
            ->where("user_id","=", $userId)->get()->unique("category_id")->pluck("category_id")->toArray();
        $subCategory = $this->categoryModel->whereIn("pid",$category)->get(["id"])->pluck("id")->toArray();
        return array_merge($category, $subCategory);
    }

    public function isAdmin($id = null) {
        if(!empty($id)) {
            $user = $this->getById($id);
        }
        else {
            $user = $this->getUser();
        }
        return $user->identity_id == User::USER_ADMIN;
    }

    public function isManager($id = null) {
        if(!empty($id)) {
            $user = $this->getById($id);
        }
        else {
            $user = $this->getUser();
        }
        return $user->identity_id == User::USER_MANAGER;
    }

    public function isEngineer($id = null) {
        if(!empty($id)) {
            $user = $this->getById($id);
        }
        else {
            $user = $this->getUser();
        }
        return $user->identity_id == User::USER_ENGINEER;
    }

    public function isNormal($id = null) {
        if(!empty($id)) {
            $user = $this->getById($id);
        }
        else {
            $user = $this->getUser();
        }
        return $user->identity_id == User::USER_NORMAL;
    }

    public function getEngineers($includeManager = true, $includeAdmin = false, $onlyEManager=false) {
        $includeManager = $includeManager ? $includeManager : true;
        $includeAdmin = $includeAdmin ? $includeAdmin : false;
        $onlyEManager = $onlyEManager ? $onlyEManager : false;
        if($onlyEManager){
            return $this->model->where(["identity_id" => User::USER_MANAGER])->get();
        }else {
            if ($includeManager || $includeAdmin) {
                $where[] = User::USER_ENGINEER;
                if ($includeManager) {
                    $where[] = User::USER_MANAGER;
                }
                if ($includeAdmin) {
                    $where[] = User::USER_ADMIN;
                }
                return $this->model->whereIn("identity_id", $where)->get();
            } else {
                return $this->model->where(["identity_id" => User::USER_ENGINEER])->get();
            }
        }
    }



    /**
     * 用户主管
     * @param null $id
     * @return bool
     */
    public function isUserLeader($id = null){
        if(!empty($id)) {
            $user = $this->getById($id);
        }
        else {
            $user = $this->getUser();
        }
        return $user->identity_id == User::USER_LEADER;
    }

    public function add($request) {
        if(!$this->isAdmin() && !$this->isManager()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        if(\ConstInc::MULTI_DB) {
            $currentDb = DB::getDefaultConnection();
            DB::setDefaultConnection("mysql");
            //判断多数据库存在，判断用户不存在
            $ret = $this->model->where("name","=",$request->input("name"))->orWhere("phone","=",$request->input("phone"))->get();
            if($ret->count() > 0) {
                Code::setCode(Code::ERR_PARAMS, ["该账号已存在"]);
                return false;
            }

            User::create([
                'name' => $request->input("name"),
                'username' => $request->input("username"),
                'email' => $request->input("email"),
                'phone' => $request->input("phone"),
                'identity_id' => $request->input("identity_id"),
                'password' => bcrypt($request->input("password")),
                'db' => $currentDb,
            ]);

            DB::setDefaultConnection($currentDb);
        }

        User::create([
            'name' => $request->input("name"),
            'username' => $request->input("username"),
            'email' => $request->input("email"),
            'phone' => $request->input("phone"),
            'identity_id' => $request->input("identity_id"),
            'password' => bcrypt($request->input("password")),
        ]);
    }

    public function edit($request) {
        if(!$this->isAdmin() && !$this->isManager()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }
        $update = [];
        if(!empty($request->input("username"))) {
            $update['username'] = $request->input("username");
        }
        if(!empty($request->input("email"))) {
            $update['email'] = $request->input("email");
        }
        if(!empty($request->input("phone"))) {
            $update['phone'] = $request->input("phone");
        }
        if(!empty($request->input("identity_id"))) {
            $update['identity_id'] = $request->input("identity_id");
        }
        if(!empty($request->input("password"))) {
            $update['password'] = bcrypt($request->input("password"));
        }

        if(!empty($update)) {
            if(\ConstInc::MULTI_DB) {
                $ret = $this->getById($request->input("id"));
                $name = $ret->name;
                $currentDb = DB::getDefaultConnection();
                DB::setDefaultConnection("mysql");
                //判断多数据库存在，判断用户不存在
                $ret = $this->model->where("name","=",$name)->first();
                if(!empty($ret)) {
                    $this->update($ret->id, $update);
                }

                DB::setDefaultConnection($currentDb);
                $this->model->setConnection($currentDb);
            }

            $this->update($request->input("id"), $update);
        }
    }

    public function getEdit($request) {
        $user = $this->getById($request->input("id"));
        return $user;
    }

    /**
     * 根据资产类别获取工程师
     * @param array $category
     * @param bool $includeManager
     * @return null
     */
    public function getEngineersByCategory(array $category, $includeManager = true) {
        $subCategory = $this->categoryModel->whereIn("pid",$category)->get(["id"])->pluck("id")->toArray();
        $category = array_merge($category, $subCategory);

        //取出所有分类
        $engineers = $this->engineersWorktypeModel->leftJoin("assets_category as A","category_id","=","A.pid")
            ->orWhereIn("A.id", $category)
            ->orWhereIn("category_id", $category)
            ->get()->unique("engineer_id")->pluck("engineer_id")->toArray();

        $users = $this->usersEngineersModel->whereIn("engineer_id", $engineers)->get()->unique("user_id")->pluck("user_id")->toArray();

        if(empty($users)) {
            return null;
        }

        if($includeManager) {
            return $this->model->whereIn("identity_id", [User::USER_ENGINEER, User::USER_MANAGER])
                ->whereIn("id", $users)
                ->get();
        }
        else {
            return $this->model->whereIn("identity_id", [User::USER_ENGINEER])
                ->whereIn("id", $users)
                ->get();
        }
    }

    public function getList($request) {
        $search = $request->input("s");
        $model = $this->model->where("identity_id","!=",User::USER_SYSADMIN)->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("name", "like", "%" . $search . "%");
                $query->orWhere("username", "like", "%" . $search . "%");
                $query->orWhere("phone", "like", "%" . $search . "%");
                $query->orWhere("email", "like", "%" . $search . "%");
            }
        });

        return $this->usePage($model);
    }


    public function getOne($where=array()){
        $ret = $this->model->where($where)->first();
        return $ret;
    }


    public function getByNamePhone($where=array()){
        $ret = $this->model->orWhere($where)->withTrashed()->first();
        return $ret;
    }

    /**
     * 通过name or phone 查询（包含软删除）
     * @param array $where
     * @return mixed
     */
    public function getByNamePhonew($where=array()){
        $ret = $this->model->orWhere($where)->withTrashed()->first();
        return $ret;
    }

    public function getEngineerCategory($request) {
        $engineerId = $request->input("id");
        return $this->engineersWorktypeModel->where("engineer_id","=", $engineerId)->get();
    }

    public function setEngineerCategory($request) {
        if(!$this->isAdmin()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }
        $engineerId = $request->input("id");
        $this->engineerModel->findOrFail($engineerId);
        $categoryId = explode(",", $request->input("categoryId"));
        $this->engineersWorktypeModel->where("engineer_id","=",$engineerId)->delete();
        $insert = [];
        foreach($categoryId as $v) {
            if(!empty($v)) {
                $insert[] = [
                    "engineer_id" => $engineerId,
                    "category_id" => $v
                ];
            }
        }

        if(!empty($insert)) {
            $this->engineersWorktypeModel->insert($insert);
        }
    }

    /**
     * 获取用户对应的工程师
     * @param $request
     * @return mixed
     */
    public function getUserEngineer($request) {
        $userId = $request->input("id");
        return $this->usersEngineersModel->where("user_id","=", $userId)->get();
    }


    /**
     * 设置用户对应的工程师
     * @param $request
     */
    public function setUserEngineer($request) {
        if(!$this->isManager() && !$this->isAdmin()) {
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        $userId = $request->input("id");
        $model = $this->getById($userId);
        if(!$this->isManager($userId) && !$this->isEngineer($userId)) {
            Code::setCode(Code::ERR_NOT_ENGINEER);
            return false;
        }
        $engineerId = explode(",", $request->input("engineerId"));

        $orgEngineerId = $this->usersEngineersModel->where("user_id","=",$userId)->get()->pluck("engineer_id")->toArray();
        $dels = array_diff($orgEngineerId, $engineerId);
        //检查待删除的工种是否有事件

        $ret = $this->eventModel->with("asset")
            ->whereIn("state",[Event::STATE_ACCESS, Event::STATE_ING])
            ->where("user_id", "=", $userId)->get();
        $events = [];
        foreach($ret as $v){
            $categoryId = $v->asset->sub_category_id;
            if(!empty($categoryId)) {
                $events[$v->id] = $categoryId;
            }
        }

        $engineers = $this->engineersWorktypeModel->leftJoin("assets_category as A","category_id","=","A.pid")
            ->orWhereIn("A.id", $events)
            ->orWhereIn("category_id", $events)
            ->get()->unique("engineer_id")->pluck("engineer_id")->toArray();

        $inUse = array_intersect($dels, $engineers);
        if(!empty($inUse)) {
            $ret = $this->engineerModel->whereIn("id", $inUse)->get()->pluck("name")->toArray();
            Code::setCode(Code::ERR_ENGINEER_EVENTUSE, null, [join(",",$ret)]);
            return false;
        }

        $this->usersEngineersModel->where("user_id","=",$userId)->delete();
        $insert = [];
        foreach($engineerId as $v) {
            if(!empty($v)) {
                $insert[] = [
                    "engineer_id" => $v,
                    "user_id" => $userId
                ];
            }
        }

        if(!empty($insert)) {
            $this->usersEngineersModel->insert($insert);
        }
    }


    /**
     * 验证密码是否超过限定时间未修改
     * @param string $uid user id
     * @return bool true:需要修改，false:不需要修改
     */
    public function checkPwdUpdated($uid=''){
        $result = false;
        if($uid){
            $time = time();
            $user = $this->getById($uid);
            $pwd_updated = is_null($user->pwd_updated)?'':strtotime($user->pwd_updated);
            if(($pwd_updated && $time-$pwd_updated > 300) || !$pwd_updated){
                $result = true;
            }
        }

        return $result;
    }

    public function editPasswd($request) {
        if(!Hash::check($request->input("oldPassword"), $this->getUser()->password)) {
            Code::setCode(Code::ERR_PASSWD);
            return false;
        }

        if($request->input("oldPassword") == $request->input("password")) {
            Code::setCode(Code::ERR_PASSWD);
            return false;
        }

        $userId = $this->getUser()->id;
        $update = [
            "password" => bcrypt($request->input("password"))
        ];
        $this->update($userId, $update);
    }


    /**
     * 获取工种对应的用户
     * @return mixed
     */
    public function getEngineersTypeUsers(){
        $users = $this->usersEngineersModel
            ->select("B.id as engineer_id","B.name as engineer_name","C.id as uid","C.name","C.username")
            ->join('engineers as B','users_engineers.engineer_id','=','B.id')
            ->leftjoin('users as C','users_engineers.user_id','=','C.id')
            ->get()->toArray();
        return $users;
    }

    /**
     * 获取工种对应（包含未分配工种）的工程师（主管）二级分类
     * @return mixed
     */
    public function getEngineersUsersTypework(){
//        $result = array();
        $EwtData = $this->getEngineersTypeUsers();
//        var_dump($userData);exit;
        //有工种的工程师(主管)
        $engineerWT = $this->formatEngineerWorkType($EwtData);//array();//

        //无工种的工程师(主管)
        $EnwtData = $this->model->select("B.*","users.name","users.id as uid","users.username","users.identity_id")
            ->leftjoin('users_engineers as B','B.user_id','=','users.id')
            ->whereNull('B.engineer_id')
            ->whereIn('users.identity_id',array(2,3))
            ->get()->toArray();
        $engineerNWT = $this->formatEngineerWorkType($EnwtData,true);
        return array_merge($engineerWT,$engineerNWT);
    }


    /**
     * 格式化工程师（主管）工种或未分配的工种分类
     * @param array $data
     * @param bool $noWork
     * @return array
     */
    private function formatEngineerWorkType($data=array(),$noWork=false){
        $result = array();
        $users = array();
        $engineers = array();
        if($data) {
            foreach ($data as $v) {
                $engineer_id = $noWork ? 0 : getKey($v, 'engineer_id', 0);
                $engineer_name = $noWork ? '其他工程师' : getkey($v, 'engineer_name');
                $uid = getKey($v, 'uid');
                $uname = getKey($v, 'username');
                $name = getKey($v, 'name');

                if (isset($engineers[$engineer_id]) && $engineer_id) {
                    $engineers[$engineer_id]['value'] = $engineer_id;
                    $engineers[$engineer_id]['text'] = getkey($v, 'engineer_name');
                    if($uid) {
                        $users[$engineer_id][] = array(
                            'value' => $uid,
                            'text' => $uname ? $uname : $name,
                        );
                    }
                } else {
                    $engineers[$engineer_id]['value'] = $engineer_id;
                    $engineers[$engineer_id]['text'] = $engineer_name;
                    if($uid) {
                        $users[$engineer_id][] = array(
                            'value' => $uid,
                            'text' => $uname ? $uname : $name,
                        );
                    }
                }
            }

            $k = 0;
            foreach ($engineers as $ek => $ev) {
                $result[$k] = $ev;
                $result[$k]['children'] = array();
                if (isset($users[$ek])) {
                    $result[$k]['children'] = $users[$ek];
                }
                $k++;
            }
        }
        return $result;
    }


    /**
     * 企业微信注册账号
     * @param array $param
     * @return bool
     */
    public function qyAdd($param=array()) {
        $name = getKey($param,'name');
        $phone = getKey($param,'phone');
        $username = getKey($param,'username');
        $email = getKey($param,'email');
        $identity_id = getKey($param,'identity_id',4);
        $password = getKey($param,'password');
        $telephone = getKey($param,'telephone');

        if(\ConstInc::MULTI_DB) {
//            $currentDb = DB::getDefaultConnection();
            $dbs = $this->getDbs();
            $currentDb = $dbs[0];
            DB::setDefaultConnection("mysql");
            //判断多数据库存在，判断用户不存在
            $ret = $this->model->where("name","=",$name)->orWhere("phone","=",$name)->get();
            if($ret->count() > 0) {
                Code::setCode(Code::ERR_PARAMS, ["该账号已存在"]);
                return false;
            }

            User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'identity_id' => $identity_id,
                'password' => bcrypt($password),
                'db' => $currentDb,
            ]);

            DB::setDefaultConnection($currentDb);
        }

        $orWhere = array('phone' => $phone, 'name' => $phone);
        $user = $this->getByNamePhone($orWhere);
        if(!$user && !empty($user->deleted_at)) {
            User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'identity_id' => $identity_id,
                'password' => bcrypt($password)
            ]);
        }
    }

    /**
     * 企业微信注册账号
     * @param array $param
     * @return bool
     */
    public function qyEdit($param=array()) {
        $id = getKey($param,'id');
        $name = getKey($param,'name');
        $telephone = getKey($param,'telephone');
        $username = getKey($param,'username');
        $email = getKey($param,'email');
        $identity_id = getKey($param,'identity_id',4);
        $password = getKey($param,'password');
        $wxid = getKey($param,'wxid');

        $update = [];
        if(!empty($username)) {
            $update['username'] = $username;
        }
        if(!empty($email)) {
            $update['email'] = $email;
        }
        if(!empty($telephone)) {
            $update['telephone'] = $telephone;
        }
        if(!empty($identity_id)) {
            $update['identity_id'] = $identity_id;
        }
        if(!empty($password)) {
            $update['password'] = bcrypt($password);
        }
        if(!empty($wxid)) {
            $update['wxid'] = $wxid;
        }

        if(!empty($update)) {
            if(\ConstInc::MULTI_DB) {
                $ret = $this->getById($id);
                $name = $ret->name;
                $currentDb = DB::getDefaultConnection();
                DB::setDefaultConnection("mysql");
                //判断多数据库存在，判断用户不存在
                $ret = $this->model->where("name","=",$name)->first();
                if(!empty($ret)) {
                    $this->update($ret->id, $update);
                }

                DB::setDefaultConnection($currentDb);
                $this->model->setConnection($currentDb);
            }

            $this->update($id, $update);
        }
    }


    public function getDbs(){
        $dbs = array();
        $connections = array_keys(\Config::get("database.connections",[]));
        foreach($connections as $conn) {
            if(strpos($conn, "opf_") === 0) {
                $dbs[] = $conn;
            }
        }
        return $dbs;
    }

}