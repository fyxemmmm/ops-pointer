<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/8
 * Time: 21:52
 */

namespace App\Http\Controllers\Report;

use App\Repositories\Report\DeviceRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Http\Requests\Report\DeviceRequest;
use Illuminate\Http\Request;
use App\Transformers\Report\DeviceReportTransformer;
use App\Http\Controllers\Controller;
use App\Repositories\Assets\ExportRepository;
use Response;
use App\Models\Code;
use DB;


class DeviceController extends Controller {

    protected $deviceRepository;
    protected $exportRepository;

    function __construct(DeviceRepository $deviceRepository,
                         EventsRepository $eventsRepository,
                         ExportRepository $exportRepository) {
        $this->deviceRepository = $deviceRepository;
        $this->eventsRepository = $eventsRepository;
        $this->exportRepository = $exportRepository;
    }


    /**
     * 质保
     * @return mixed
     */
    public function getWarranty() {
        $data = $this->deviceRepository->getWarrantyDetail();
        return $this->response->send($data);
    }

    /**
     * 品牌统计
     */
    public function getBrand() {
        $data = $this->deviceRepository->getBrand();
        return $this->response->send($data);
    }

    /**
     * 供应商统计
     * @return mixed
     */
    public function getProvider() {
        $data = $this->deviceRepository->getProvider();
        return $this->response->send($data);
    }

    /**
     * 机房统计
     * @return mixed
     */
    public function getArea() {
        $data = $this->deviceRepository->getArea();
        return $this->response->send($data);
    }

    /**
     * 使用年限
     * @return mixed
     */
    public function getYears() {
        $data = $this->deviceRepository->getYears();
        return $this->response->send($data);
    }

    /**
     * 获取资产报表的字段
     */
    public function getFieldsSelect(DeviceRequest $request) {
        $data = $this->deviceRepository->getFieldSelect($request);
        return $this->response->send($data);
    }

    public function postFieldsSelect(DeviceRequest $request) {
        //{"title":"aaa", "desc" :"miaoshu", "config": [{"categoryId":8,"content":[{"field":"state","op":[["=",1]]}]}]}
        $this->deviceRepository->setFieldSelect($request);
        return $this->response->send();
    }

    /**
     * 显示模板list
     */
    public function getTemplateList(DeviceRequest $request) {
        $data = $this->deviceRepository->getTemplateList($request);
        return $this->response->send($data);
    }

    public function postTemplateDel(DeviceRequest $request) {
        $this->deviceRepository->delTemplate($request);
        return $this->response->send();
    }

    public function postTemplateDefault(DeviceRequest $request) {
        $this->deviceRepository->setDefaultTemplate($request);
        return $this->response->send();
    }

    /**
     * 资产分析报告
     */
    public function getReport(DeviceRequest $request) {
        $data = $this->deviceRepository->getReport($request, $header);
        $obj = new DeviceReportTransformer;
        $this->response->setTransformer($obj);
        $this->response->addMeta(["fields" => $header]);
        return $this->response->send($data);
    }

    /**
     * 巡检报告报表 EXCEL 导出
     *
     * @param DeviceRequest $request 接收时间区间值、模板id
     * @return bool|\Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \App\Exceptions\ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getDeviceReport(DeviceRequest $request){
        // 报表中所需内容（二维数组）
        $data = $this->deviceRepository->getDeviceReport($request);
//        dump($data);exit;
        if(empty($data)){
            Code::setCode(Code::ERR_MODEL);
            return false;
        }

        foreach ($data as $key => $val){
            // 取出报表中列所需数据（取出来的值排序会被打乱）
            $keyArr = array_keys($val[0]);
        }

        // 删除键值 name、item
        unset($keyArr[0],$keyArr[1]);
        // 对时间正序排列
        asort($keyArr);
        // 将键值 name、item 重新添加数组中去
        $new = ["name","item"];
        $keyArr = array_merge($new,$keyArr);

        $titles = [];
        // 处理 excel 表中第一行（一维数组）
        foreach($keyArr as $k => $v){
            switch ($v){
                case 'name':
                    $titles['设备名称'] = $v;
                    break;
                case 'item':
                    $titles['巡检项'] = $v;
                    break;
                default:
                    $timeStr = strval($v);
                    $y = substr($timeStr,0,4);
                    $m = substr($timeStr,4,2);
                    $d = substr($timeStr,6,2);
                    $t = substr($timeStr,8,2);
                    $timeStrNew = $y . '/' . $m . '/' . $d . ' ' . $t . '点';
                    $titles[$timeStrNew] = $v;
            }
        }

        $filename = "巡检报告报表-".date("Y-m-d H:i:s").".xlsx";

        $filepath = $this->exportRepository->saveReportByMerge($filename, $data, $titles);
//        dd($filepath);
        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );
        userlog("导出了巡检报告报表，文件名: $filename");
        return Response::download($filepath, $filename, $headers);

    }


    /**
     *
     * 数据库中所有数据表详细信息导出
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws \App\Exceptions\ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getTables(){
        // 查询出所有的数据表
        $tables = DB::select("show tables");

        if(empty($tables)){
            Code::setCode(Code::ERR_MODEL);
            return false;
        }

        $tables_in_bases = (array)$tables[0];
        // 数据表所对应的数据库
        $tableIn = array_keys($tables_in_bases)[0];

        $array = [];
        $new = [];

        foreach($tables as $k => $v){
            // 所有的表名称
            $tableName = $v->$tableIn;

            // 查看表中注释
            $comments = Db::select("show full columns from $tableName");

            // 强制将对象转换成数组
            foreach ($comments as &$value){
                $value = (array)$value;
            }

            // excel 表处理默认值
            $new[0] = [
                "Field" => $tableName . "表",
                "Type" => '',
                "Collation" => '',
                "Null" => '',
                "Key" => '',
                "Default" => '',
                "Extra" => '',
                "Privileges" => '',
                "Comment" => '',
            ];

            $new[1] = [
                "Field" => '字段',
                "Type" => '属性',
                "Collation" => '字符集',
                "Null" => '是否为空',
                "Key" => '自增主键',
                "Default" => '默认值',
                "Extra" => '自动递增',
                "Privileges" => '权限',
                "Comment" => '备注',
            ];

            $comments = array_merge($new,$comments);
            $array[$tableName] = $comments;
        }

        // 三层数组转换成二层数组
        $data = array_reduce($array,"array_merge",array());
        $titles = [
            '字段' => 'Field',
            '属性' => 'Type',
            '字符集' => 'Collation',
            '是否为空' => 'Null',
            '自增主键' => 'Key',
            '默认值' => 'Default',
            '自动递增' => 'Extra',
            '权限' => 'Privileges',
            '备注' => 'Comment',
        ];

        $filename = $tableIn . "数据表-" . date("Y-m-d H:i:s").".xlsx";

        $filepath = $this->exportRepository->saveReportForTwoArray($filename, $data, $titles, false);

        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );

        return Response::download($filepath, $filename, $headers);


    }

    /**
     * 获取设备出保时间统计
     * @return mixed
     */
    public function getAssetsWarrantyTimeRate()
    {
        $data = $this->deviceRepository->getAssetsWarrantyTimeRate();
        return $this->response->send($data);
    }

}