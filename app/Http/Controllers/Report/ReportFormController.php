<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/8/2
 * Time: 11:30
 */

namespace App\Http\Controllers\Report;
use App\Repositories\Assets\ExportRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Workflow\OaRepository;
use App\Repositories\Auth\UserRepository;
use App\Repositories\Report\SheetRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Assets\ZoneRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Response;

class ReportFormController extends Controller {
    protected $events;
    protected $user;
    protected $eventoa;
    protected $sheet;
    protected $exportRepository;
    protected $zone;
    function __construct(EventsRepository $eventsRepository,
                         UserRepository $userRepository,
                         SheetRepository $sheetRepository,
                         ExportRepository $exportRepository,
                         OaRepository $eventoa,
                         ZoneRepository $zone,
                         DeviceRepository $device) {
        $this->events = $eventsRepository;
        $this->user = $userRepository;
        $this->sheet = $sheetRepository;
        $this->exportRepository = $exportRepository;
        $this->eventoa = $eventoa;
        $this->device = $device;
        $this->zone = $zone;
    }

    /**
     * 事件列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEvents(Request $request) {
        $data = $this->events->getReportForm($request);
        return $this->response->send($data);
    }

    public function getEventsDownload(Request $request) {
        $data = $this->events->getReportForm($request, true);
        $filename = "事件报表-".date("Y-m-d H:i:s").".xlsx";
        $titles = [
            "result" => [
                "事件ID" => "id",
                "日期" => "created_date",
                "时间" => "created_time",
                "处理人" => "user",
                "资产名称" => "asset_name",
                "资产编号" => "asset_number",
                "事件类型" => "category",
                "事件来源" => "source_name",
                "事件状态" => "state_name",
                "响应时间" => "response_time_human",
                "路程时间" => "distance_time_human",
                "处理时间" => "process_time_human",
            ],
            "meta" => [
                "事件总数" => "total",
                "响应总时" => "total_response_time",
                "路程总时" => "total_distance_time",
                "处理总时" => "total_process_time",
            ]
        ];
        if(!$data){
            return $this->response->send();
        }

        $filepath = $this->exportRepository->saveReport($filename, $data, $titles);
        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );
        userlog("导出了事件报表，文件名: $filename");
        return Response::download($filepath, $filename, $headers);

    }

    public function getEventoaDownload(Request $request) {
        $data = $this->eventoa->getReportForm($request, true);
        $filename = "OA报表-".date("Y-m-d H:i:s").".xlsx";
        $titles = [
            "result" => [
                "事件ID" => "id",
                "日期" => "created_date",
                "时间" => "created_time",
                "事件单位" => "company",
                "处理人" => "user",
                "事件对象" => "object_name",
                "事件类型" => "category",
                "事件来源" => "source_name",
                "完成情况" => "state_name",
                "响应时间" => "response_time_human",
                "路程时间" => "distance_time_human",
                "处理时间" => "process_time_human",
                "处理详情" => "description",
                "关闭信息" => "remark",
                "事件描述" => "problem",
                "挂起原因" => "contentStr",
                "上报人" => "report_name",
                "联系方式" => "mobile",
                "位置信息" => "location",
            ],
            "meta" => [
                "事件总数" => "total",
                "响应总时" => "total_response_time",
                "路程总时" => "total_distance_time",
                "处理总时" => "total_process_time",
            ]
        ];

        $filepath = $this->exportRepository->saveReport($filename, $data, $titles);
        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );
        userlog("导出了OA报表，文件名: $filename");
        return Response::download($filepath, $filename, $headers);

    }

    public function getEventsChart(Request $request) {
        $data = $this->sheet->getReportChart($request);
        return $this->response->send($data);
    }

    public function postEventsChart(Request $request) {
        $this->sheet->postReportChart($request);
        return $this->response->send();
    }

    public function getGenChart(Request $request) {
        $data = $this->sheet->genChart($request);
        return $this->response->send($data);
    }

    /**
     * 获取行为分类
     * @param Request $request
     * @return mixed
     */
    public function getCategories(Request $request){
        $where[] = ['batch' ,'<', 1];
        $where[] = ['id','<','100'];
        $data = $this->events->getCategoriesList($where);
        $res = $data ? $data->toArray() : array();
        $result['result'] = array_merge([['id'=>0, 'name'=>'[无]','username'=>'[无]']], $res);
//        var_dump($result);exit;
        return $this->response->send($result);
    }


    /**
     * oa事件列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEventoa(Request $request){
        $data = $this->eventoa->getReportForm($request);
        return $this->response->send($data);
    }


    /**
     * 获取OA事件类型
     * @param Request $request
     * @return mixed
     */
    public function getCategoriesoa(){
        $where = array('batch' => 0);
        $where[] = ['id','>','100'];
        $where[] =['id','<','111'];
        $data = $this->events->getCategoriesList($where);
        $res = $data ? $data->toArray() : array();
        $result['result'] = array_merge([['id'=>0, 'name'=>'[无]','username'=>'[无]']], $res);
        return $this->response->send($result);
    }


    /**
     * 获取工程师和工程师主管(处理人)
     * @return mixed
     */
    public function getEngineerusers(){
        $data = $this->user->getEngineers();
        $res = $data ? $data->toArray() : array();
        $result['result'] = array_merge([['id'=>0, 'name'=>'[无]','username'=>'[无]']], $res);
        return $this->response->send($result);
    }


    /**
     * 按机房或科室获取分类
     * @param Request $request
     */
    public function getZoneCategory(Request $request){
        $input = $request->input();
        $result = $this->device->getErDtCategory($input);
        return $this->response->send($result);
    }


    /**
     * 资产报表
     * @param Request $request
     * @return mixed
     */
    public function getAssets(Request $request){
        $input = $request->input();
        $type = isset($input['type']) ? strtolower($input['type']):'';
        $fieldArr = array('er'=>'area','dt'=>'department');
        $field = isset($fieldArr[$type]) ? $fieldArr[$type] : '';

//        $fieldCateoryIds = $this->device->getCategoryByField($field);
//        $fieldsList = $this->device->getCategoryList($fieldCateoryIds);
        $fieldCateoryIds = '';

        $result = $this->device->getAssetsForm($input,$fieldCateoryIds);
//        $this->response->addMeta(["fields" => $fieldsList]);
        return $this->response->send($result);
    }

    /**
     * 全部站点下的资产统计报表
     * @return mixed
     */
    public function getAssetsForLocation(){
        $result = $this->device->getAssetsForLocation();
        $this->response->addMeta([ "total" => $GLOBALS['total']]);
        return $this->response->send($result);
    }


    /**
     * 资产事件和OA事件汇总报表
     * @param Request $request
     * @return mixed
     */
    public function getEventsOaAll(Request $request){
        $result = $this->events->getReportOAForm($request);
//        var_dump($result->toArray());exit;
        return $this->response->send($result);
    }


    /**
     * 导出资产事件和OA事件汇总报表
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function getEventsOaAllDownload(Request $request) {
        $data = $this->events->getReportOAForm($request, true);
        $filename = "汇总报表-".date("Y-m-d H:i:s").".xlsx";
        $titles = [
            "result" => [
                "类型" => "event_type",
                "事件ID" => "id",
                "日期" => "created_date",
                "时间" => "created_time",
                "事件单位" => "company",
                "处理人" => "user",
                "资产名称" => "asset_name",
                "资产编号" => "asset_number",
                "事件对象" => "object_name",
                "事件类型" => "category",
                "事件来源" => "source_name",
                "事件状态" => "state_name",
                "响应时间" => "response_time_human",
                "路程时间" => "distance_time_human",
                "处理时间" => "process_time_human",
                "处理详情" => "des",
                "关闭信息" => "remark",
                "备注" => "remark_txt",
                "事件描述" => "problem",
                "挂起原因" => "contentStr",
                "上报人" => "report_name",
                "联系方式" => "mobile",
                "位置信息" => "location",
            ],
            "meta" => [
                "事件总数" => "total",
                "响应总时" => "total_response_time",
                "路程总时" => "total_distance_time",
                "处理总时" => "total_process_time",
            ]
        ];

        $filepath = $this->exportRepository->saveReport($filename, $data, $titles);
        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );
        userlog("导出了汇总报表，文件名: $filename");
        return Response::download($filepath, $filename, $headers);

    }


}