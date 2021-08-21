<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/5/10
 * Time: 10:30
 */

namespace App\Http\Controllers\Report;

use App\Repositories\Report\EventsRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EventsController extends Controller {
    protected $eventsRepository;

    function __construct(EventsRepository $eventsRepository) {
        $this->eventsRepository = $eventsRepository;
    }

    /**
     * 每周运维事件时效统计报告，本周终端运维时效统计，本周最长响应（到场，处理）时间 TOP 3事件
     * @return mixed
     */
    public function getWeekPrescription(Request $request) {
        $data = $this->eventsRepository->getWeekPrescription($request);
//        var_dump($data);exit;
        return $this->response->send($data);
    }


    /**
     * 年度终端运维事件处理时效
     * @return mixed
     */
    public function getYearProcessPrescription(){
        $data = $this->eventsRepository->getYearProcessPrescription();
//        var_dump($data);exit;
        return $this->response->send($data);
    }

    /**
     * 获取保修
     * @return mixed
     */
    public function getMaintain(Request $request) {
        $data = $this->eventsRepository->getMaintain($request);
        return $this->response->send($data);
    }

    public function getMaintainDetail(Request $request) {
        $data = $this->eventsRepository->getMaintainDetail($request);
        return $this->response->send($data);
    }

    public function getEngineers(Request $request) {
        $data = $this->eventsRepository->getEngineers($request);
        return $this->response->send($data);
    }


    /**
     * 获取终端数据统计,终端事件分类统计
     * @param Request $request
     * @return mixed
     */
    public function getTerminalEvent(Request $request){
        $data = $this->eventsRepository->getTerminalEvent($request);
//        var_dump($data);exit;
        return $this->response->send($data);
    }

}