<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/2
 * Time: 15:55
 */

namespace App\Repositories\Weixin;

use App\Repositories\BaseRepository;
use App\Models\Home\SmsInfo;
use Illuminate\Http\Request;
//use App\Models\Assets\Engineroom;
use Auth;

class SmsInfoRepository extends BaseRepository
{
    protected $model;


    public function __construct(SmsInfo $smsInfoModel)
    {
        $this->model = $smsInfoModel;

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








}