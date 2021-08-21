<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/8
 * Time: 21:52
 */

namespace App\Http\Controllers\Report;

use App\Repositories\Report\KpiRepository;
use App\Repositories\Workflow\EventsRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class KpiController extends Controller {

    function __construct(KpiRepository $kpiRepository) {
        $this->kpiRepository = $kpiRepository;
    }

    /**
     * Kpi
     * @return mixed
     */
    public function getStatistics(Request $request) {
        $data = $this->kpiRepository->calc($request);
        return $this->response->send($data);
    }


}