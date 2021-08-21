<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/9
 * Time: 9:55
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\WeixinUser;
use Illuminate\Http\Request;
//use App\Models\Assets\Engineroom;
//use Auth;

class WeixinUserRepository extends BaseRepository
{
    protected $userInfo = '';


    public function __construct(WeixinUser $wxUserModel)
    {
        $this->model = $wxUserModel;

    }


    /**
     * 新增
     * @param array $param
     * @return mixed
     */
    public function add($param=array()){
        $subscribe = isset($param['subscribe'])?$param['subscribe']:0;
        $openid = isset($param['openid'])?$param['openid']:'';
        $unionid = isset($param['unionid'])?$param['unionid']:'';
        $nickname = isset($param['nickname'])?$param['nickname']:'';
        $sex = isset($param['sex'])?$param['sex']:0;
        $city = isset($param['city'])?$param['city']:'';
        $country = isset($param['country'])?$param['country']:'';
        $province = isset($param['province'])?$param['province']:'';
        $language = isset($param['language'])?$param['language']:'';
        $headimgurl = isset($param['headimgurl'])?$param['headimgurl']:'';
        $subscribe_time = isset($param['subscribe_time'])?$param['subscribe_time']:0;
        $remark = isset($param['remark'])?$param['remark']:'';
        $groupid = isset($param['groupid'])?$param['groupid']:0;
        $tagid_list = isset($param['tagid_list'])?json_encode($param['tagid_list']):'';
        $subscribe_scene = isset($param['subscribe_scene'])?$param['subscribe_scene']:'';
        $qr_scene = isset($param['qr_scene'])?$param['qr_scene']:'';
        $qr_scene_str = isset($param['qr_scene_str'])?$param['qr_scene_str']:'';
        $userid = isset($param['userid'])?$param['userid']:0;
        $data = [
            "subscribe" => $subscribe,
            "openid" => $openid,
            "unionid" => $unionid,
            "nickname" => $nickname,
            "sex" => $sex,
            "city" => $city,
            "country" => $country,
            "province" => $province,
            "language" => $language,
            "headimgurl" => $headimgurl,
            "subscribe_time" => $subscribe_time,
            "remark" => $remark,
            "groupid" => $groupid,
            "tagid_list" => $tagid_list,
            "subscribe_scene" => $subscribe_scene,
            "qr_scene" => $qr_scene,
            "qr_scene_str" => $qr_scene_str,
            "userid" => $userid,
        ];
//        dd($data);exit;
        $result = WeixinUser::create($data);
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
        $subscribe = isset($param['subscribe'])?$param['subscribe']:0;
        $openid = isset($param['openid'])?$param['openid']:'';
        $unionid = isset($param['unionid'])?$param['unionid']:'';
        $nickname = isset($param['nickname'])?$param['nickname']:'';
        $sex = isset($param['sex'])?$param['sex']:0;
        $city = isset($param['city'])?$param['city']:'';
        $country = isset($param['country'])?$param['country']:'';
        $province = isset($param['province'])?$param['province']:'';
        $language = isset($param['language'])?$param['language']:'';
        $headimgurl = isset($param['headimgurl'])?$param['headimgurl']:'';
        $subscribe_time = isset($param['subscribe_time'])?$param['subscribe_time']:0;
        $remark = isset($param['remark'])?$param['remark']:'';
        $groupid = isset($param['groupid'])?$param['groupid']:0;
        $tagid_list = isset($param['tagid_list'])?json_encode($param['tagid_list']):'';
        $subscribe_scene = isset($param['subscribe_scene'])?$param['subscribe_scene']:'';
        $qr_scene = isset($param['qr_scene'])?$param['qr_scene']:'';
        $qr_scene_str = isset($param['qr_scene_str'])?$param['qr_scene_str']:'';
        $userid = isset($param['userid'])?$param['userid']:0;
        $data = array();
        if(isset($param['subscribe'])){
            $data['subscribe'] = $subscribe;
        }
        if(isset($param['openid'])){
            $data['openid'] = $openid;
        }
        if(isset($param['unionid'])){
            $data['unionid'] = $unionid;
        }
        if(isset($param['nickname'])){
            $data['nickname'] = $nickname;
        }
        if(isset($param['sex'])){
            $data['sex'] = $sex;
        }
        if(isset($param['city'])){
            $data['city'] = $city;
        }
        if(isset($param['country'])){
            $data['country'] = $country;
        }
        if(isset($param['province'])){
            $data['province'] = $province;
        }
        if(isset($param['language'])){
            $data['language'] = $language;
        }
        if(isset($param['headimgurl'])){
            $data['headimgurl'] = $headimgurl;
        }
        if(isset($param['subscribe_time'])){
            $data['subscribe_time'] = $subscribe_time;
        }
        if(isset($param['remark'])){
            $data['remark'] = $remark;
        }
        if(isset($param['groupid'])){
            $data['groupid'] = $groupid;
        }
        if(isset($param['tagid_list'])){
            $data['tagid_list'] = $tagid_list;
        }
        if(isset($param['subscribe_scene'])){
            $data['subscribe_scene'] = $subscribe_scene;
        }
        if(isset($param['qr_scene'])){
            $data['qr_scene'] = $qr_scene;
        }
        if(isset($param['qr_scene_str'])){
            $data['qr_scene_str'] = $qr_scene_str;
        }
        if(isset($param['userid'])){
            $data['userid'] = $userid;
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
            $res = $this->model->whereIn('userid',$uids)->get();
        }
        return $res;
    }








}