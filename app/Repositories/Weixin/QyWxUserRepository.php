<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/9
 * Time: 9:55
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\QyWxUser;
use Illuminate\Http\Request;
//use App\Models\Assets\Engineroom;
//use Auth;

class QyWxUserRepository extends BaseRepository
{
    protected $userInfo = '';


    public function __construct(QyWxUser $qyWxUserModel)
    {
        $this->model = $qyWxUserModel;

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $data = [
            "user_id" => getkey($param,'user_id',0),
            "userid" => getkey($param,'userid',0),
            "name" => getkey($param,'name',''),
            "email" => getkey($param,'email',''),
            "mobile" => getkey($param,'mobile',''),
            "avatar" => getkey($param,'avatar',''),
            "gender" => getkey($param,'gender',''),
            "qr_code" => getkey($param,'qr_code',''),
        ];
//        dd($data);exit;
        $result = QyWxUser::create($data);
        return $result;
    }


    /**
     * 更新
     * @param array $param
     * @param array $where
     * @return mixed
     */
    public function update($param=array(),$where=array()){
        $result = array();
        $data = array();
        if(isset($param['user_id'])){
            $data['user_id'] = getkey($param,'user_id',0);
        }
        if(isset($param['userid'])){
            $data['userid'] = getkey($param,'userid',0);
        }
        if(isset($param['name'])){
            $data['name'] = getkey($param,'name','');
        }
        if(isset($param['email'])){
            $data['email'] = getkey($param,'email','');
        }
        if(isset($param['mobile'])){
            $data['mobile'] = getkey($param,'mobile','');
        }
        if(isset($param['avatar'])){
            $data['avatar'] = getkey($param,'avatar','');
        }
        if(isset($param['gender'])){
            $data['gender'] = getkey($param,'gender','');
        }
        if(isset($param['qr_code'])) {
            $data['qr_code'] = getkey($param,'qr_code','');
        }

//        dd($data);exit;
        if($param && $where) {
            $result = $this->model->where($where)->update($data);
        }
        return $result;
    }


    /**
     * 列表数据
     * @param array $where
     * @param int $number
     * @param string $sort
     * @param string $sortColumn
     * @return mixed
     */
    public function getList($where=array(),$number = 10, $sort = 'desc', $sortColumn = 'created_at'){
        $res = array();
        if($where){
            $res = $this->model->where($where)->orderBy($sortColumn, $sort)->paginate($number);
        }else {
            $res = $this->model->orderBy($sortColumn, $sort)->paginate($number);
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
            $res = $res? $res->toArray():array();
        }
        return $res;
    }


    public function getListByuid($uids=array()){
        $res = array();
        if($uids){
            $res = $this->model->whereIn('user_id',$uids)->get();
        }
        return $res;
    }








}