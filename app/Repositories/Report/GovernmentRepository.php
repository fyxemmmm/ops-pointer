<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Report;
use App\Models\Report\GovernmentResource;
use App\Repositories\BaseRepository;
use App\Models\Report\Government;
use App\Models\Report\JdGovernment;
use App\Models\Report\AppGovernment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Schema;
use App\Exceptions\ApiException;
use App\Models\Code;
use DB;


class GovernmentRepository extends BaseRepository
{
    protected static $time;
    protected static $data;

    protected $model;
    protected $jdModel;
    protected $resourceModel;

    public function __construct(Government $governmentModel,JdGovernment $jdGovernmentModel,GovernmentResource $resourceModel,AppGovernment $appGovernment){
        $this->model = $governmentModel;
        $this->jdModel = $jdGovernmentModel;
        $this->appModel = $appGovernment;
        $this->resourceModel = $resourceModel;
    }


    // 将EPC标签号打印到Excel里
    public function getQrExcel($data){
        foreach($data as $k=>&$v){
            $v[0] = $v['number'];
            $v[1] = $v['number']."__".$v['id'];
            $v[2] = sprintf("%016s",$v['id']);
            unset($v['number']);
            unset($v['id']);
        }
        $spreadsheet = IOFactory::load("./storage/excel/inventory.xlsx");
        $worksheet = $spreadsheet->getActiveSheet();
//        $highestRow = $worksheet->getHighestRow(); // 总行数
        $total_num = count($data)+2;
        for($i=2;$i<$total_num;$i++){
            for($j=0;$j<=2;$j++){
                $worksheet->setCellValueByColumnAndRow($j+1, $i, $data[$i-2][$j]);
            }

        }
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('./storage/app/allqr/allEpc.xlsx');
    }


    # 获得默认模板的数据
    public function getWeekReport(){
        $model = $this->model;
        $data = $model->where('is_default','=',1)->first();
        if(!$data){
            throw new ApiException(Code::ERR_MODEL);
        }
        $data = $data->toArray();

        $compute_data = htmlspecialchars_decode($data['compute_data'],true);
        $compute_data = json_decode($compute_data,true);

        $time = $data['date'];
        $before_time = strtotime(date("Y-m-d",strtotime("-1 month",strtotime($time))));
        $all_data = $model->select('id','date')->get()->toArray();
        $list = []; // 存放上一周的id
        foreach($all_data as $k=>$v){
            if(strtotime($v['date']) == $before_time){
                $list[] = $v['id'];
            }
        }
        if($list == array()){
            // 如果只存在一条数据，则没办法对比上周数据，初始化数组
            $diff = [
                'fwt' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'gcs' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'hfl' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'zjjl' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'jinshan' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'jsfwq' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'jsip' =>[
                    "diff" => 0,
                    "status" => "same"
                ],

                'tgc' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'xzyy' =>[
                    "diff" => 0,
                    "status" => "same"
                ],

                'xj' =>[
                    "diff" => 0,
                    "status" => "same"
                ],

                'ljxj' =>[
                    "diff" => 0,
                    "status" => "same"
                ],

            ];

        }else{
            // 计算本周与上周的数据差值用于前台页面显示
            $lsdata = $model->where('id','=',$list[0])->firstOrFail()->toArray();
            $diff_fwt = intval($data['fwt_dhbx']) - intval($lsdata['fwt_dhbx']); // 服务台电话报修
            $this->getDiff('fwt',$diff_fwt,$diff);
            $diff_engineer = intval($data['fwt_engineer']) - intval($lsdata['fwt_engineer']); // 服务台派出终端工程师
            $this->getDiff('gcs',$diff_engineer,$diff);
            $diff_hfl = intval($data['fwt_hfl']) - intval($lsdata['fwt_hfl']); // 服务台电话报修
            $this->getDiff('hfl',$diff_hfl,$diff);
            $diff_zjjl = intval($data['fwt_zjjl']) - intval($lsdata['fwt_zjjl']); // 服务台派出终端工程师
            $this->getDiff('zjjl',$diff_zjjl,$diff);
            $diff_terminal = intval($data['jsdb_zd']) - intval($lsdata['jsdb_zd']); // 金山毒霸终端
            $this->getDiff('jinshan',$diff_terminal,$diff);
            $diff_jsfw = intval($data['jsdb_fwq']) - intval($lsdata['jsdb_fwq']); // 金山毒霸服务器端
            $this->getDiff('jsfwq',$diff_jsfw,$diff);
            $diff_jsip = intval($data['jsdb_ip']) - intval($lsdata['jsdb_ip']); // 金山毒霸IP解冻申请
            $this->getDiff('jsip',$diff_jsip,$diff);
        }

        // 拼接时间显示格式
        $be_time = strtotime($data['date']);
        $show_time = date('Y.m',$be_time);


        $info = [
            'time' => $show_time,
            // 服务台
            'service' =>  [
                [
                    'value' => $data['fwt_dhbx'],
                    'diff_number' => $diff['fwt']['diff'],
                    'status' => $diff['fwt']['status'],
                ],
                [
                    'value' => $data['fwt_engineer'],
                    'diff_number' => $diff['gcs']['diff'],
                    'status' => $diff['gcs']['status'],
                ],
                [
                    'value' => $data['fwt_hfl'],
                    'diff_number' => $diff['hfl']['diff'],
                    'status' => $diff['hfl']['status'],
                ],
                [
                    'value' => $data['fwt_zjjl'],
                    'diff_number' => $diff['zjjl']['diff'],
                    'status' => $diff['zjjl']['status'],
                ]

            ],
            // 应用
            'application' =>  [
                [
                    'yy_zhyh' => $data['yy_zhyh'],
                    'yy_zhxzyh' => $data['yy_zhxzyh'],
                ],
                [
                    'yy_zhyh' => $data['yy_jdyh'],
                    'yy_zhxzyh' => $data['yy_jdxzyh']
                ]
            ],
            // 网络
            'network' =>  [
                'wl_xyip' => $data['wl_xyip'],
                'wl_xkip' => $data['wl_xkip'],
                'wl_xgq' => $data['wl_xgq'],
                'ckdk' => [
                    [
                        'name'=>'电信100M',
                        'value'=>$data['wl_dx100'],
                    ],
                    [
                        'name'=>'电信500M',
                        'value'=>$data['wl_dx500'],
                    ],
                    [
                        'name'=>'联通400M',
                        'value'=>$data['wl_lt400'],
                    ],
                    [
                        'name'=>'移动300M',
                        'value'=>$data['wl_yd300'],
                    ],
                    [
                        'name'=>'移动500M',
                        'value'=>$data['wl_yd500'],
                    ],
                ],
            ],
            // 金山毒霸
            'jinshan' => [
                [
                    'value' => $data['jsdb_zd'],
                    'diff_number' => $diff['jinshan']['diff'],
                    'status' => $diff['jinshan']['status'],
                ],
                [
                    'value' => $data['jsdb_fwq'],
                    'diff_number' => $diff['jsfwq']['diff'],
                    'status' => $diff['jsfwq']['status'],
                ],

                [
                    'value' => $data['jsdb_ip'],
                    'diff_number' => $diff['jsip']['diff'],
                    'status' => $diff['jsip']['status'],
                ],
            ],
            'compute_data' => $compute_data
        ];
        return $info;
    }


    # 对比上周数据取差值
    public function getDiff($name,$number,&$diff=[]){
        if($number == 0){
            $diff[$name] = [
                'diff' => $number,
                'status' => 'same'
            ];
        }else if($number > 0){
            $diff[$name] = [
                'diff' => $number,
                'status' => 'up'
            ];
        }else{
            $number = abs($number);
            $diff[$name] = [
                'diff' => $number,
                'status' => 'down'
            ];
        }
    }


    # 设置默认模板 #
    public function setDefaultTemplate($input = array()){
        $type = isset($input['type'])?$input['type']:'';
        $id = isset($input['id'])?$input['id']:0;
        if($id && in_array($type,['app','resource','all'])){
            if($type == 'app'){
                $model = $this->appModel;
            }else if($type == 'resource'){
                $model = $this->resourceModel;
            }else{
                $model = $this->model;
            }
            $model->where('is_default','!=',0)->update(['is_default'=>0]);
            $model->where('id','=',$id)->update(['is_default'=>1]);
        }else{
            throw new ApiException(Code::ERR_PARAMS, ["请选择正确的类型"]);
        }
    }

    public function addMonthData($file)
    {

        // 获取表中的所有字段
        $columns = Schema::getColumnListing('report_government');

        $model = $this->model;

        $cell = $this->intToChr(range(1,20));
        array_shift($columns);
        array_splice($columns, -4);
        $cellArray = array_combine($cell,$columns);
        $start = 5;

        $data = $this->getExcelDateForFile($file,$cellArray,$start,0);

        $cost_data = $this->getExcelDateForFile($file,['B'=>'date','C'=>'name','D'=>'charging'],$start,1);
        $cost_res = [];
        if($cost_data){
            foreach($cost_data as $cost){
                $cost['date'] = date('Y-m-d H:i:s',strtotime($cost['date'].'/01'));
                if (strtotime($cost['date']) == false) {
                    throw new ApiException(Code::ERR_PARAMS, ["模拟计费中请输入正确的时间格式，年/月"]);
                }
                if (!$cost['name']) {
                    throw new ApiException(Code::ERR_PARAMS, ["模拟计费中请输入使用地"]);
                }
                $cost_res[$cost['date']][] = ['name'=>$cost['name'],'charging'=>$cost['charging']];
            }
        }

        if($data && $cost_res){
            $dateArray = $model->select('date','id')->get()->pluck('date','id')->toArray();
            $updateArray = [];
            foreach($data as $key=>&$value){
                $value['date'] = date('Y-m-d H:i:s',strtotime($value['date'].'/01'));
                if (strtotime($value['date']) == false) {
                    throw new ApiException(Code::ERR_PARAMS, ["基本文件中请输入正确的时间格式，年/月"]);
                }
                if(isset($cost_res[$value['date']])){
                    $value['compute_data'] = json_encode($cost_res[$value['date']]);
                }else{
                    throw new ApiException(Code::ERR_PARAMS, ["基本文件与模拟计费不一致"]);
                }
                if(in_array($value['date'],$dateArray)){
                    $updateArray[] = $value;
                    unset($data[$key]);
                    continue;
                }
                $value['is_default'] = 0;
            }
            if(!$model->first()){
                $data[count($data)-1]['is_default'] = 1;
            }
            if($data){
                $this->addAll($model,$data);
            }
            if($updateArray){
                $this->updateAll($model,'date',$updateArray);
            }
        }else{
            throw new ApiException(Code::ERR_PARAMS, ["基本文件与模拟计费中要同时有数据并保持月份相同"]);
        }

        return true;
    }


    public function addResourceData($file){
        // 获取表中的所有字段
        $columns = Schema::getColumnListing('report_government_resource');

        $model = $this->resourceModel;

        $cell = $this->intToChr(range(1,12));
        array_shift($columns);
        array_splice($columns, -8);
        $cellArray = array_combine($cell,$columns);
        $start = 5;
        $data = $this->getExcelDateForFile($file,$cellArray,$start,0);

        //处理数据并转为键值对
        $system_data = $this->getExcelDateForFile($file,['B'=>'date','C'=>'host_total','D'=>'host_use','E'=>'system_total','F'=>'system_use','G'=>'month'],$start,1);
        $system_res = [];
        if($system_data){
            foreach($system_data as $system){
                $system['date'] = date('Y-m-d H:i:s',strtotime($system['date'].'/01'));
                if (strtotime($system['date']) == false) {
                    throw new ApiException(Code::ERR_PARAMS, ["虚拟主机、托管系统列表中请输入正确的时间格式，年/月"]);
                }
                if (!$system['month'] || !in_array($system['month'],range(1,12))) {
                    throw new ApiException(Code::ERR_PARAMS, ["虚拟主机、托管系统列表中请输入正确的统计月份"]);
                }
                $system_res[$system['date']][] = ['host_total'=>$system['host_total'],'host_use'=>$system['host_use'],'system_total'=>$system['system_total'],'system_use'=>$system['system_use'],'month'=>$system['month']];
            }
        }
        $resource_data = $this->getExcelDateForFile($file,['B'=>'date','C'=>'name','D'=>'value'],$start,2);
        $resource_res = $this->transformResourceData($resource_data);
        $vcpu_data = $this->getExcelDateForFile($file,['F'=>'date','G'=>'name','H'=>'value'],$start,2);
        $vcpu_res = $this->transformResourceData($vcpu_data);
        $memory_data = $this->getExcelDateForFile($file,['J'=>'date','K'=>'name','L'=>'value'],$start,2);
        $memory_res = $this->transformResourceData($memory_data);
        $storage_data = $this->getExcelDateForFile($file,['N'=>'date','O'=>'name','P'=>'value'],$start,2);
        $storage_res = $this->transformResourceData($storage_data);

        //填入数据
        if($data && $system_res && $resource_res && $vcpu_res && $memory_res && $storage_res){
            $dateArray = $model->select('date','id')->get()->pluck('date','id')->toArray();
            $updateArray = [];
            foreach($data as $key=>&$value){
                $value['date'] = date('Y-m-d H:i:s',strtotime($value['date'].'/01'));
                if (strtotime($value['date']) == false) {
                    throw new ApiException(Code::ERR_PARAMS, ["云平台资源情况中请输入正确的时间格式，年/月"]);
                }
                if(isset($system_res[$value['date']])){
                    $value['system_data'] = json_encode($system_res[$value['date']]);
                }else{
                    throw new ApiException(Code::ERR_PARAMS, ["云平台资源情况与虚拟主机、托管系统列表日期不一致"]);
                }
                if(isset($resource_res[$value['date']])){
                    $value['resource_data'] = json_encode($resource_res[$value['date']]);
                }else{
                    throw new ApiException(Code::ERR_PARAMS, ["单位资源排行与虚拟主机、托管系统列表日期不一致"]);
                }
                if(isset($vcpu_res[$value['date']])){
                    $value['vcpu_data'] = json_encode($vcpu_res[$value['date']]);
                }else{
                    throw new ApiException(Code::ERR_PARAMS, ["单位资源排行与虚拟主机、托管系统列表日期不一致"]);
                }
                if(isset($memory_res[$value['date']])){
                    $value['memory_data'] = json_encode($memory_res[$value['date']]);
                }else{
                    throw new ApiException(Code::ERR_PARAMS, ["单位资源排行与虚拟主机、托管系统列表日期不一致"]);
                }
                if(isset($storage_res[$value['date']])){
                    $value['storage_data'] = json_encode($storage_res[$value['date']]);
                }else{
                    throw new ApiException(Code::ERR_PARAMS, ["单位资源排行与虚拟主机、托管系统列表日期不一致"]);
                }
                if(in_array($value['date'],$dateArray)){
                    $updateArray[] = $value;
                    unset($data[$key]);
                    continue;
                }
                $value['is_default'] = 0;
            }
            if(!$model->first()){
                $data[count($data)-1]['is_default'] = 1;
            }
            if($data){
                $this->addAll($model,$data);
            }
            if($updateArray){
                $this->updateAll($model,'date',$updateArray);
            }
        }else{
            throw new ApiException(Code::ERR_PARAMS, ["请将所有数据都填入表中,否则导入失败"]);
        }
        return true;
    }

    protected function transformResourceData($data){
        $res = [];
        if($data){
            foreach($data as $value){
                $value['date'] = date('Y-m-d H:i:s',strtotime($value['date'].'/01'));
                if (strtotime($value['date']) == false) {
                    throw new ApiException(Code::ERR_PARAMS, ["单位资源排行中请输入正确的时间格式，年/月"]);
                }
                if (!$value['name']) {
                    throw new ApiException(Code::ERR_PARAMS, ["单位资源排行中请输入使用地"]);
                }
                $res[$value['date']][] = ['name'=>$value['name'],'value'=>$value['value']];
            }
        }
        return $res;
    }

    /**
     * @param $arr
     * @param int $start
     * @return array
     */
    protected function intToChr($arr, $start = 65) {
        $res = [];
        foreach($arr as $value){
            if($value < 26){
                $res[] =  chr($value % 26 + $start);
            }else{
                $res[] =  'A'.chr($value % 26 + $start);
            }
        }
        return $res;
    }


    /**
     * @param $file 文件名
     * @param $cellArray execl与数据库键对应
     * @param $start 从哪一行开始读
     * @param int $sheet_index
     * @return array
     * @throws ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function getExcelDateForFile($file,$cellArray,$start,$sheet_index = 0){
        $realPath = $file->getRealPath();
        if(!$realPath){
            throw new ApiException(Code::ERR_PARAMS, ["请上传文件"]);
        }
        $spreadsheet = IOFactory::load($realPath);
        $spreadsheet->setActiveSheetIndex($sheet_index);
        $sheet = $spreadsheet->getActiveSheet();
        $data = [];
        if(empty($cellArray)){
            return $data;
        }
        $cell = array_keys($cellArray)[0];
        while(true){
            if(empty($sheet->getCell($cell.$start)->getFormattedValue())){
                return $data;
            }
            $tmp = [];
            foreach($cellArray as $key=>$value){
                $tmp[$value] = (string)$sheet->getCell($key.$start)->getFormattedValue();
            }
            $data[] = $tmp;
            $start++;
        }
        return $data;
    }



    public function insertAppExcel($file){
        $model = $this->appModel;
        $columns = Schema::getColumnListing('report_government_situation');
        $columns = array_splice($columns,1,-3);
        $cell = $this->intToChr(range(1,29));
        $cellArray = array_combine($cell,$columns);
        $start = 5;
        $data = $this->getExcelDateForFile($file,$cellArray,$start,0);
        $dateArray = $model->select('date','id')->get()->pluck('date','id')->toArray();
        $updateArray = [];
        foreach($data as $key=>&$value){
            $value['date'] = date('Y-m-d H:i:s',strtotime($value['date'].'/01'));
            if (strtotime($value['date']) == false) {
                throw new ApiException(Code::ERR_PARAMS, ["基本文件中请输入正确的时间格式，年/月"]);
            }
            if(in_array($value['date'],$dateArray)){
                $updateArray[] = $value;
                unset($data[$key]);
                continue;
            }
            $value['is_default'] = 0;
        }

        if(!$model->first()){
            $data[count($data)-1]['is_default'] = 1;
        }
        if($data){
            $this->addAll($model,$data);
        }
        if($updateArray){
            $this->updateAll($model,'date',$updateArray);
        }
        return true;

    }

    public function getResourceReport()
    {
        $model = $this->resourceModel;
        $data = $model->where('is_default','=',1)->first();
        if(!$data){
            throw new ApiException(Code::ERR_MODEL);
        }
        $data = $data->toArray();
        if($data['vcpu_total'] > 0){
            $data['vcpu_use_rate'] = sprintf("%.2f",round(($data['vcpu_use']/$data['vcpu_total'])*100,2));
        }else{
            $data['vcpu_use_rate'] = 0;
        }
        if($data['memory_total'] > 0){
            $data['memory_use_rate'] = sprintf("%.2f",round(($data['memory_use']/$data['memory_total'])*100,2));
        }else{
            $data['memory_use_rate'] = 0;
        }
        if($data['storage_total'] > 0){
            $data['storage_use_rate'] = sprintf("%.2f",round(($data['storage_use']/$data['storage_total'])*100,2));
        }else{
            $data['storage_use_rate'] = 0;
        }

        $data['system_data'] = json_decode(htmlspecialchars_decode($data['system_data'],true),true);
        $data['resource_data'] = json_decode(htmlspecialchars_decode($data['resource_data'],true),true);
        if($data['resource_data'] && is_array($data['resource_data'])){
            $data['resource_data'] = array_combine(array_column($data['resource_data'],'value'),$data['resource_data']);
            krsort($data['resource_data']);
            $data['resource_data'] = array_values($data['resource_data']);
        }
        $data['vcpu_data'] = json_decode(htmlspecialchars_decode($data['vcpu_data'],true),true);
        if($data['vcpu_data'] && is_array($data['vcpu_data'])){
            $data['vcpu_data'] = array_combine(array_column($data['vcpu_data'],'value'),$data['vcpu_data']);
            krsort($data['vcpu_data']);
            $data['vcpu_data'] = array_values($data['vcpu_data']);
        }
        $data['memory_data'] = json_decode(htmlspecialchars_decode($data['memory_data'],true),true);
        if($data['memory_data'] && is_array($data['memory_data'])){
            $data['memory_data'] = array_combine(array_column($data['memory_data'],'value'),$data['memory_data']);
            krsort($data['memory_data']);
            $data['memory_data'] = array_values($data['memory_data']);
        }
        $data['storage_data'] = json_decode(htmlspecialchars_decode($data['storage_data'],true),true);
        if($data['storage_data'] && is_array($data['storage_data'])){
            $data['storage_data'] = array_combine(array_column($data['storage_data'],'value'),$data['storage_data']);
            krsort($data['storage_data']);
            $data['storage_data'] = array_values($data['storage_data']);
        }

        // 拼接时间显示格式
        $be_time = strtotime($data['date']);
        $data['time'] = date('Y.m',$be_time);
        unset($data['date']);
        return $data;

    }

    public function getAppList(){
        $model = $this->appModel;
        $data = $model->where('is_default','=',1)->first();
        if(!$data){
            throw new ApiException(Code::ERR_MODEL);
        }
        $data = $data->toArray();
//        dump($data);exit;
        $time = $data['date'];
        $before_time = strtotime(date("Y-m-d",strtotime("-1 month",strtotime($time))));
        $all_data = $model->select('id','date')->get()->toArray();
        $list = []; // 存放上一周的id
        foreach($all_data as $k=>$v){
            if(strtotime($v['date']) == $before_time){
                $list[] = $v['id'];
            }
        }
        if($list == array()){
            // 如果只存在一条数据，则没办法对比上周数据，初始化数组
            $diff = [
                'record' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'gov_message' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'courier' =>[
                    "diff" => 0,
                    "status" => "same"
                ],
                'inform' =>[
                    "diff" => 0,
                    "status" => "same"
                ],


            ];

        }else{
            // 计算本周与上周的数据差值用于前台页面显示
            $lsdata = $model->where('id','=',$list[0])->firstOrFail()->toArray();
//            dump($lsdata);exit;
            $record_diff = intval($data['work_record']) - intval($lsdata['work_record']);
            $this->getDiffRate('record',$record_diff,$diff,$lsdata['work_record']);

            $record_diff = intval($data['gov_message']) - intval($lsdata['gov_message']);
            $this->getDiffRate('gov_message',$record_diff,$diff,$lsdata['gov_message']);

            $record_diff = intval($data['courier']) - intval($lsdata['courier']);
            $this->getDiffRate('courier',$record_diff,$diff,$lsdata['courier']);

            $record_diff = intval($data['inform']) - intval($lsdata['inform']);
            $this->getDiffRate('inform',$record_diff,$diff,$lsdata['inform']);

        }
//        dump($diff);exit;
//        echo 33;exit;

        // 拼接时间显示格式
        $be_time = strtotime($data['date']);
        $data['time'] = date('Y.m',$be_time);
        $total_unit = $data['ad_village'] + $data['neibourhood'] + $data['community'] + $data['town_enterprise'] + $data['other_unit']; # 涉及接入单元
        $info = [
            'time' => $data['time'],
            'net_situation' => [
                [
                    'name' => '涉及接入单元',
                    'value' => $total_unit
                ],
                [
                    'name' => '行政村',
                    'value' => $data['ad_village']
                ],
                [
                    'name' => '居委会',
                    'value' => $data['neibourhood']
                ],
                [
                    'name' => '社区',
                    'value' => $data['community']
                ],
                [
                    'name' => '镇企业',
                    'value' => $data['town_enterprise']
                ],
                [
                    'name' => '各委员办、职能部门及事业单位包括学校、医院、公安等',
                    'value' => $data['other_unit']
                ],
                [
                    'name' => '接入网络用户',
                    'value' => $data['network_users']
                ]
            ],
            'app_situation' => [
                [
                    'value' => $data['work_record'],
                    'daily' => $data['work_record_daily'],
                    'total' => $data['work_record_total'],
                    'diff_rate' => $diff['record']['diff'],
                    'status' => $diff['record']['status']
                ],

                [
                    'value' => $data['gov_message'],
                    'daily' => $data['gov_message_daily'],
                    'total' => $data['gov_message_total'],
                    'diff_rate' => $diff['gov_message']['diff'],
                    'status' => $diff['gov_message']['status']
                ],

                [
                    'value' => $data['courier'],
                    'daily' => $data['courier_daily'],
                    'total' => $data['courier_total'],
                    'diff_rate' => $diff['courier']['diff'],
                    'status' => $diff['courier']['status']
                ],

                [
                    'value' => $data['inform'],
                    'daily' => $data['inform_daily'],
                    'total' => $data['inform_total'],
                    'diff_rate' => $diff['inform']['diff'],
                    'status' => $diff['inform']['status']
                ],
                [
                    'value' => $data['gov_infor'],
                    'daily' => '',
                    'total' => $data['gov_infor_total'],
                    'diff_rate' => 0,
                    'status' => 'same'
                ]

            ],

            'move_situation' => [
                [
                    'name' => '移动政务开通用户',
                    'value' => $data['open_users']
                ],

                [
                    'name' => '本月日均访问量',
                    'value' => $data['daily_visit']
                ]
            ],

            'output_1' => [
                [
                    'name' => '互联网出口总流量',
                    'value' => $data['outlet_flow']
                ],
                [
                    'name' => '日均在线人数',
                    'value' => $data['online_daily_user']
                ],
                [
                    'name' => '总流速',
                    'value' => $data['flow_rate']
                ]
            ],

            'output_2' => [
                [
                    'name' => '月提供网站访问',
                    'value' => $data['outlet_flow']
                ],
                [
                    'name' => '日均网页访问流量',
                    'value' => $data['online_daily_user']
                ],
          ],

           'output_3' => [
                [
                    'name' => '日均非法拦截攻击',
                    'value' => $data['intercept_attack_daily']
                ]
          ]


        ];
    return $info;

    }
    # 对比上周数据取差值
    public function getDiffRate($name,$number,&$diff=[],$last_data){
        if($last_data){
            $diff[$name] = [
                'diff' => 0,
                'status' => 'same'
            ];
            return;
        }
        if($number == 0){
            $diff[$name] = [
                'diff' => $number,
                'status' => 'same'
            ];
        }else if($number > 0){
            $diff[$name] = [
                'diff' => round(($number/$last_data)*100,2),
                'status' => 'up'
            ];
        }else{
            $number = abs($number);
            $diff[$name] = [
                'diff' => round(($number/$last_data)*100,2),
                'status' => 'down'
            ];
        }
    }

    public function getDateList($input = array()){
        $type = isset($input['type'])?$input['type']:'';
        $year = isset($input['year'])?$input['year']:'';
        if(!in_array($type,['app','resource','all'])) {
            throw new ApiException(Code::ERR_PARAMS, ["请选择正确的类型"]);
        }
        if($type === 'app'){
            $model = $this->appModel;
        }else if($type === 'resource'){
            $model = $this->resourceModel;
        }else{
            $model = $this->model;
        }
        if(!$year){
            $model = $model->select('id','date')->where('id','>',0)->orderBy('is_default','desc')->orderBy('date','desc')->orderBy('id','asc');
        }else{
            $model = $model->select('date','id')->where('id','>',0)->where('date','like',"{$year}%")->orderBy('is_default','desc')->orderBy('id','asc');
        }
        $model = $this->usePage($model);
        return $model;
    }

    public function getDateId($type){
        if($type == 'app'){
            $model = $this->appModel;
        }else if($type == 'resource'){
            $model = $this->resourceModel;
        }else{
            $model = $this->model;
        }
        $id = $model->where('is_default','=',1)->value('id');
        return $id;
    }

}
