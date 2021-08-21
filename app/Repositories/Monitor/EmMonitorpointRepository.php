<?php
/**
 * Created by PhpStorm.
 * 环境监控
 * User: wangwei
 * Date: 2018/3/28
 * Time: 16:05
 */

namespace App\Repositories\Monitor;

use App\Repositories\BaseRepository;
use App\Models\Monitor\EmMonitorpoint;
use App\Models\Monitor\EmDevice;
use App\Models\Code;
use App\Exceptions\ApiException;
use DB;

class EmMonitorpointRepository extends BaseRepository
{
    protected $emdeviceModel;


    public function __construct(EmMonitorpoint $emmonitorpointModel,EmDevice $emdeviceModel)
    {
        $this->model = $emmonitorpointModel;
        $this->emdeviceModel = $emdeviceModel;
    }



    public function monitorpointList($input=array()){
        $sortStr = isset($input['sort'])?$input['sort']:'';
        $sortColumn = '';
        $sort = '';
        if($sortStr) {
            list($sortColumn, $sort) = explode('|', $sortStr);
        }
//        var_dump($sortColumn,$sort);exit;
        $res = $this->usePage($this->model,$sortColumn,$sort);
        return $res;
    }





}