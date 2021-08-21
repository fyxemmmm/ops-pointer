<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 2019/3/19
 * Time: 10:16
 */

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Repositories\Report\GovernmentRepository;
use App\Http\Requests\Report\GovernmentRequest;
use App\Support\Response;

class GovernmentController extends Controller
{
    protected $government;
    protected $response;

    function __construct(GovernmentRepository $governmentRepository) {
        $this->government = $governmentRepository;
        $this->response = new Response();
    }

    // 获取智慧政务运行情况周汇报
    public function getWeekReport(GovernmentRequest $request){
        $data = $this->government->getWeekReport($request->input('time'));
        return $this->response->send($data);
    }

    # 获取智慧政务应用情况列表
    public function getAppList(){
        $data = $this->government->getAppList();
        return $this->response->send($data);
    }

    public function getResourceReport(){
        $data = $this->government->getResourceReport();
        return $this->response->send($data);
    }
}