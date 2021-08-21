<?php
/**
 * 巡检报告
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/13
 * Time: 16:20
 */

namespace App\Http\Controllers\Report;

use App\Repositories\Report\InspectionRepository;
use App\Repositories\Monitor\EnvironmentalRepository;
use App\Repositories\Monitor\AssetsMonitorRepository;
use App\Repositories\Monitor\CommonRepository as mCommon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class InspectionController extends Controller {
    protected $inspection;
    protected $environmental;
    protected $assetsmonitor;
    protected $emRepository;
    protected $mcommon;

    function __construct(InspectionRepository $inspection,
                         AssetsMonitorRepository $assetsmonitor,
                         EnvironmentalRepository $emRepository,
                         mCommon $mcommon
    ) {
        $this->inspection = $inspection;
        $this->assetsmonitor = $assetsmonitor;
        $this->emRepository = $emRepository;
        $this->mcommon = $mcommon;
    }


    /**
     * 根据设备获取监控点
     * @param Request $request
     * @return mixed
     */
    public function getMonitorpoint(Request $request){
        $input = $request->input();
        $deviceId = isset($input['deviceId']) ? $input['deviceId'] : '';
        $where = array();
        if($deviceId) {
            $where = array('device_id' => $deviceId);
        }
        $reportDate = $this->inspection->getReportDate($where);
        $data = $this->inspection->getMonitorpoint($where);
        $result = array('monitor_point'=>$data,'report_date'=>$reportDate);
        return $this->response->send($result);
    }

    /**
     * 获取监控报告时间
     * @return mixed
     */
    public function getReportDate(Request $request) {
        $input = $request->input();


        $data['result'] = $this->inspection->getReportDate($input);
//        var_dump($data);exit;
        return $this->response->send($data);
    }


    /**
     * 获取监控报告
     * @return mixed
     */
    public function getReport(Request $request){
        $input = $request->input();
        $data = $this->inspection->getReport($input);
//        var_dump($data);exit;
        return $this->response->send($data);
    }


    /**
     * 根据资产类型获取资产监控设备
     * @param Request $request
     */
    public function getAssetByCategory(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res['result'] = $this->inspection->getAssetByCategory($input);
        return $this->response->send($res);
    }


    public function postAddReport(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res['result'] = $this->inspection->addReport($input);
        return $this->response->send($res);
    }


    public function postAddEmReport(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res['result'] = $this->emRepository->addEmReport($input);
        return $this->response->send($res);
    }

    /**
     * 获取实时环控报告
     * @param Request $request
     * @return mixed
     */
    public function getRealTimeEmReport(Request $request){
        $input = $request->input() ? $request->input() : array();
        $data = $this->emRepository->getRealTimeEmReport($input);
        return $this->response->send($data);
    }


    /**
     * 获取环控报告
     * @return mixed
     */
    public function getEmReport(Request $request){
        $input = $request->input();
        $data = $this->emRepository->getEmReport($input);
        return $this->response->send($data);
    }


    /**
     * 根据模板获取分类
     * @param Request $request
     * @return mixed
     */
    public function getReportCategory(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res = $this->inspection->getReportCategory($input);
        return $this->response->send($res);
    }


    /**
     * 获取模板配置
     * @param Request $request
     * @return mixed
     */
    public function getReportTemplate(Request $request){
        $input = $request->input() ? $request->input() : array();
        $res = $this->inspection->getReportTemplate($input);
        return $this->response->send($res);
    }


    public function getTemplate(Request $request){
        $input = $request->input() ? $request->input() : array();
        $result = $this->mcommon->getTemplate($input);
        return $this->response->send($result);
    }

}