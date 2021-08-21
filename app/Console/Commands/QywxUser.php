<?php
/**
 * 环控-巡检报告
 */

namespace App\Console\Commands;


use App\Repositories\Weixin\CommonRepository;
use App\Repositories\Weixin\QyWxUserRepository;
use Illuminate\Console\Command;
use App\Support\GValue;
use App\Models\Auth\User;

use DB;
use Log;


class QywxUser extends Command
{
    protected $inspection;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'qywxuser:add {db?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加企业微信用户的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $common;
    protected $qywxuser;
    protected $userModel;

    function __construct(CommonRepository $common,
                         QyWxUserRepository $qywxuser,
                         User $userModel
){
        parent::__construct();
        $this->common = $common;
        $this->qywxuser = $qywxuser;
        $this->userModel = $userModel;

   }

    public function selectDb($db='') {
        if(!$db) {
            $db = $this->argument('db');
        }
        if(!empty($db)) {
            //分库
            DB::setDefaultConnection($db);
            GValue::$currentDB = $db;
        }else{
            //默认库
            $db = DB::getDatabaseName();
        }
        \ConstInc::monitorCurrentConf($db);
    }


    public function handle()
    {
        $db = $this->argument('db');
        $users = $this->common->getQyUserList();
        if(\ConstInc::MULTI_DB){
            $common = $this->addCommonUser($users,$db);
            Log::info('qy_common_user_add cmd:'.json_encode($common));
        }
        $this->selectDb($db);
        $res = $this->addQyuser($users);

        Log::info('qy_user_add cmd:'.json_encode($res));
    }


    public function addQyuser($users=array()){
        if($users) {
            $userAdd = array();
            $userUpdate = array();
            $pwd = \ConstInc::WW_USER_DEFAULT_PWD;
            foreach($users as $v) {
                $qyuserid = getKey($v,'userid','');
                $phone = getKey($v,'mobile','');
                $name = getKey($v,'name','');
                $email = getKey($v,'email','');
                $phoneLen = strlen($phone);
                if($phone && 11==$phoneLen) {
                    $orwhere = array('phone' => $phone, 'name' => $phone);
                    $uRes = $this->userModel->orWhere($orwhere)->first();
                    $userWxid = isset($uRes['wxid']) ? $uRes['wxid'] : 0;
                    $resID = isset($uRes['id']) ? $uRes['id'] : 0;
                    $flag = false;
                    if (!$uRes) {
                        $user = User::create([
                            'name' => $phone,
                            'username' => $name ? $name : $phone,
                            'email' => $email,
                            'phone' => $phone,
                            'identity_id' => User::USER_ENGINEER,
                            'password' => bcrypt($pwd),
                        ]);
                        $resID = isset($user['id']) ? $user['id'] : 0;
                        $flag = true;
                        $userAdd[] = $resID;
                    }
                    $where = array('user_id' => $resID);
                    $res = $this->qywxuser->getOne($where);
                    $qyWhere = array('userid' => $qyuserid);
                    $qyRes = $this->qywxuser->getOne($qyWhere);
                    $qyid = isset($qyRes['id']) ? $qyRes['id'] : 0;
                    if (!$qyRes && !$uRes) {
                        $wxUserinfo = $v;
                        $wxUserinfo['user_id'] = $resID;
                        $add = $this->qywxuser->add($wxUserinfo);
                        $qyid = isset($add['id']) ? $add['id'] : 0;
                    } else {
                        $set['user_id'] = $resID;
                        $qyup = $this->qywxuser->update($set, $qyWhere);
                    }

                    if (!$userWxid || $flag) {
                        $uWhere = array('id' => $resID);
                        $param = array('wxid' => $qyid);
                        $uup = $this->userModel->where($uWhere)->update($param);
                        $userUpdate[] = $resID;
                    }

                }
            }
        }
        return array('add' => $userAdd,'update'=>$userUpdate);
    }


    public function addCommonUser($users=array(),$db=''){
        $userAdd = array();
        $existed = array();
        if($users && $db) {
            $pwd = \ConstInc::WW_USER_DEFAULT_PWD;
            foreach($users as $v) {
                $phone = getKey($v, 'mobile', '');
                $name = getKey($v, 'name', '');
                $email = getKey($v, 'email', '');
                //判断多数据库存在，判断用户不存在
                $orwhere = array('phone' => $phone, 'name' => $phone);
                $uRes = $this->userModel->orWhere($orwhere)->first();
                if ($uRes) {
                    $existed[] = $phone;
                }else {
                    $user = User::create([
                        'name' => $phone,
                        'username' => $name ? $name : $phone,
                        'email' => $email,
                        'phone' => $phone,
                        'identity_id' => User::USER_ENGINEER,
                        'password' => bcrypt($pwd),
                        'db' => $db,
                    ]);
                    $resID = isset($user['id']) ? $user['id'] : 0;
                    $userAdd[] = $resID;
                }
            }
        }
        return array('add' => $userAdd,'existed'=>$existed);
    }






}



