<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Report;

use App\Repositories\BaseRepository;
use App\Models\Assets\Category;
use App\Models\Assets\Device;
use App\Models\Assets\Engineroom;
use App\Models\Assets\Fields;
use App\Models\Assets\FieldsType;
use App\Models\Assets\CategoryFields;
use App\Repositories\Assets\DictRepository;
use App\Models\Report\Device as ReportDevice;
use App;
use Log;
use DB;
use App\Models\Monitor\Inspection;
use App\Models\Code;
use App\Exceptions\ApiException;
use App\Models\Monitor\EmInspectionTemplate;
use App\Repositories\Assets\DeviceRepository as AssetsDeviceRepository;

class DeviceRepository extends BaseRepository
{

    protected $fieldsType;
    protected $device;
    protected $category;
    protected $engineroom;
    protected $fields;
    protected $reportDevice;
    protected $emiTemplateModel;


    const QRSIZE = 160;

    public function __construct(Category $categoryModel,
                                ReportDevice $reportDeviceModel,
                                Engineroom $engineroomModel,
                                Fields $fieldsModel,
                                FieldsType $fieldsTypeModel,
                                CategoryFields $categoryFieldsModel,
                                Device $deviceModel,
                                Inspection $inspectionModel,
                                DictRepository $dictRepository,
                                EmInspectionTemplate $emiTemplateModel)
    {
        $this->engineroom = $engineroomModel;
        $this->category = $categoryModel;
        $this->reportDevice = $reportDeviceModel;
        $this->model = $this->device = $deviceModel;
        $this->fields = $fieldsModel;
        $this->categoryFields = $categoryFieldsModel;
        $this->fieldsType = $fieldsTypeModel;
        $this->inspectionModel = $inspectionModel;
        $this->emiTemplateModel = $emiTemplateModel;
        $this->dictRepository = $dictRepository;

    }

    public function getWarrantyDetail() {
        $today = date("Y-m-d H:i:s");
        $month = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+1, date("d"), date("Y")));
        $month3 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+3, date("d"), date("Y")));
        $month6 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+6, date("d"), date("Y")));
        $month12 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+12, date("d"), date("Y")));

        //在保
        $warranty["normal"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_begin","<=",$today)->where("warranty_end",">=",$today)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        //过保
        $warranty["expire"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_end","<",$today)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        //即将过保
        $warranty["soon1"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_end",">=",$today)->where("warranty_end","<",$month)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        $warranty["soon3"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_end",">=",$month)->where("warranty_end","<",$month3)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        $warranty["soon6"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_end",">=",$month3)->where("warranty_end","<",$month6)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        $warranty["soon12"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_end",">=",$month6)->where("warranty_end","<",$month12)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        $warranty["soonYear"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("warranty_end",">=",$month12)->groupBy("category_id")
            ->selectRaw("category_id, count(*) as cnt")->pluck("cnt","category_id")->toArray();

        $data = [];
        foreach($warranty as $k => &$v) {
            foreach($v as $kk => $vv) {
                if(!isset($data[$kk])) {
                    $data[$kk] = [
                        "category" => "",
                        "value" => [
                            "normal" => 0,
                            "expire" => 0,
                            "soon1" => 0,
                            "soon3" => 0,
                            "soon6" => 0,
                            "soon12" => 0,
                            "soonYear" => 0,
                        ]
                    ];
                }
                $data[$kk]['value'][$k] = $vv;
            }
        }

        $categoryMap = $this->category->whereIn("id",array_keys($data))->get()->pluck("name","id")->toArray();

        $ret = [];
        $all = [
            "normal" => 0,
            "expire" => 0,
            "soon1" => 0,
            "soon3" => 0,
            "soon6" => 0,
            "soon12" => 0,
            "soonYear" => 0,
        ];
        foreach($data as $k => $v) {
            $v['category'] = $categoryMap[$k];
            $ret[] = $v;
            $vv = $v['value'];
            $all['normal'] += $vv['normal'];
            $all['expire'] += $vv['expire'];
            $all['soon1'] += $vv['soon1'];
            $all['soon3'] += $vv['soon3'];
            $all['soon6'] += $vv['soon6'];
            $all['soon12'] += $vv['soon12'];
            $all['soonYear'] += $vv['soonYear'];
        }
        $ret[] = [
            "category" => null,
            "value" => $all
        ];

        return $ret;
    }


    public function getBrand() {
        $ret = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->whereNotNull("brand")
            ->groupBy("brand","category_id")
            ->selectRaw("brand,category_id, count(*) as cnt")->get();

        $data = [];

        $category_ids = $ret->pluck("category_id")->unique()->toArray();

        $categoryMap = $this->category->whereIn("id",$category_ids)->get()->pluck("name","id")->toArray();
        foreach($ret as $v) {
            $brand = $v->brand;
            if(!isset($data[$brand])) {
                $data[$brand] = [
                    "brand" => AssetsDeviceRepository::transform('brand',$brand,$tkey),
                    "cnt"   => 0,
                    "info"  => []
                ];
            }
            $data[$brand]["info"][] = [
                "category_id" => $v->category_id,
                "category" => $categoryMap[$v->category_id],
                "cnt" => $v->cnt
            ];
            $data[$brand]["cnt"] += $v->cnt;
        }
        return array_values($data);
    }

    public function getProvider() {
        $ret = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->whereNotNull("provider")
            ->groupBy("provider","category_id")
            ->selectRaw("provider,category_id, count(*) as cnt")->get();

        $data = [];

        $category_ids = $ret->pluck("category_id")->unique()->toArray();
        $categoryMap = $this->category->whereIn("id",$category_ids)->get()->pluck("name","id")->toArray();
        foreach($ret as $v) {
            $provider = $v->provider;
            if(!isset($data[$provider])) {
                $data[$provider] = [
                    "provider" => $provider,
                    "cnt"   => 0,
                    "info"  => []
                ];
            }
            $data[$provider]["info"][] = [
                "category_id" => $v->category_id,
                "category" => $categoryMap[$v->category_id],
                "cnt" => $v->cnt
            ];
            $data[$provider]["cnt"] += $v->cnt;
        }
        return array_values($data);
    }

    public function getArea() {
        $ret = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->whereNotNull("area")
            ->groupBy("area","category_id")
            ->selectRaw("area,category_id, count(*) as cnt")->get();

        $data = [];

        $category_ids = $ret->pluck("category_id")->unique()->toArray();
        $area_ids = $ret->pluck("area")->unique()->toArray();
        $categoryMap = $this->category->whereIn("id",$category_ids)->get()->pluck("name","id")->toArray();
        $areaMap = $this->engineroom->whereIn("id",$area_ids)->get()->pluck("name","id")->toArray();
        foreach($ret as $v) {
            $area = $v->area;
            if(!isset($data[$area])) {
                $data[$area] = [
                    "areaId" => $area,
                    "area" => $areaMap[$area]??'',
                    "cnt"   => 0,
                    "info"  => []
                ];
            }
            $data[$area]["info"][] = [
                "category_id" => $v->category_id,
                "category" => $categoryMap[$v->category_id],
                "cnt" => $v->cnt
            ];
            $data[$area]["cnt"] += $v->cnt;
        }
        return array_values($data);
    }


    public function getYears() {
        $year = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 1));
        $year3 = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 3));
        $year5 = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 5));
        //少于1年
        $intime["year"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("intime",">",$year)->groupBy("sub_category_id")
            ->selectRaw("sub_category_id, count(*) as cnt")->pluck("cnt","sub_category_id")->toArray();

        //1~3年
        $intime["year3"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("intime","<=",$year)->where("intime",">",$year3)->groupBy("sub_category_id")
            ->selectRaw("sub_category_id, count(*) as cnt")->pluck("cnt","sub_category_id")->toArray();

        //3~5年
        $intime["year5"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("intime","<=",$year3)->where("intime",">",$year5)->groupBy("sub_category_id")
            ->selectRaw("sub_category_id, count(*) as cnt")->pluck("cnt","sub_category_id")->toArray();

        $intime["year5up"] = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->where("intime","<",$year5)->groupBy("sub_category_id")
            ->selectRaw("sub_category_id, count(*) as cnt")->pluck("cnt","sub_category_id")->toArray();


        $data = [];
        foreach($intime as $k => &$v) {
            foreach($v as $kk => $vv) {
                if(!isset($data[$kk])) {
                    $data[$kk] = [
                        "category" => "",
                        "value" => [
                            "year" => 0,
                            "year3" => 0,
                            "year5" => 0,
                            "year5up" => 0,
                        ]
                    ];
                }
                $data[$kk]['value'][$k] = $vv;
            }
        }

        $categoryMap = $this->category->whereIn("id",array_keys($data))->get()->pluck("name","id")->toArray();

        $ret = [];
        foreach($data as $k => $v) {
            $v['category'] = $categoryMap[$k];
            $ret[] = $v;
        }

        return $ret;

    }

    /**
     * 获取模板列表
     * @param $request
     * @param bool $all
     * @return mixed
     */
    public function getTemplateList($request) {
        $model = $this->reportDevice;
        $all = $request->input("all");
        if($all) {
            $data = $model->get();
        }
        else {
            $data = $this->usePage($model);
        }
        return $data;
    }

    public function delTemplate($request) {
        $model = $this->reportDevice->findOrFail($request->input("id"));
        $model->delete();
    }

    public function setDefaultTemplate($request) {
        $model = $this->reportDevice->findOrFail($request->input("id"));
        DB::update("update report_device set is_default =0");
        $model->is_default = 1;
        $model->save();
    }


    public function setFieldSelect($request) {
        $input = $request->input();
        $input['config'] = json_encode($input['config']);
        if($request->input("id")) {
            $model = $this->reportDevice->findOrFail($request->input("id"));
        }
        else {
            $model = $this->reportDevice;
        }
        $model->fill($input);
        $model->save();
    }


    public function getFieldSelect($request) {
        $id = $request->input("id");
        $configField = [];
        $title = null;
        $desc = null;
        if(!empty($id)) {
            $obj = $this->reportDevice->findOrFail($request->input("id"));
            $title = $obj->title;
            $desc = $obj->desc;
            $configRaw = json_decode($obj->config, true);
            $config = [];
            $configField = [];
            foreach($configRaw as $v) {
                $config[$v['categoryId']] = $v['content'];
                foreach($v['content'] as $vv) {
                    if(!isset($configField[$vv['field']])) {
                        $configField[$vv['field']] = [];
                    }
                    $configField[$vv['field']][$v['categoryId']] = $vv['op'];
                }
            }
        }

        $fieldsList = $this->fields->get()->groupBy("field_type_id")->toArray();
        $fieldsTypeList = $this->fieldsType->get()->pluck("name","id")->toArray();
        $categoryFieldsList = $this->categoryFields->select("category_id","field_id")->get()->groupBy("field_id");

        $categoryFieldsListArr = [];
        foreach($categoryFieldsList as $k => $v) {
            $categoryFieldsListArr[$k] = [];
            foreach($v as $vv) {
                $categoryFieldsListArr[$k][] = $vv->category_id;
            }
        }

        $data = [];
        foreach($fieldsList as $typeId => $fields){
            $cur = [
                "id" => $typeId,
                "cname" => $fieldsTypeList[$typeId],
            ];
            $children = [];
            foreach($fields as $field) {
                $t = isset($categoryFieldsListArr[$field['id']])?$categoryFieldsListArr[$field['id']]:[];
                $checked = false;
                $category = [];
                foreach($t as $cate) {
                    $current = [
                        "cid" => $cate,
                        "checked" => false
                    ];
                    if(!empty($configField)) {
                        if(isset($configField[$field['field_sname']][$cate])) {
                            $checked = true;
                            $current["checked"] = true;
                            $current["op"] = $configField[$field['field_sname']][$cate];
                        }
                    }
                    $category[] = $current;
                }
                $child = [
                    "sname" => $field['field_sname'],
                    "cname" => $field['field_cname'],
                    "type" => $field['field_type'],
                    "checked" => $checked,
                    "category" => $category
                ];

                if(!empty($field['field_dict'])) {
                    $child['options'] = [];
                    $dict = json_decode($field['field_dict'], true);
                    foreach($dict as $value => $text) {
                        $child['options'][] = [
                            "text" => $text,
                            "value" => $value
                        ];
                    }
                }

                if(!empty($field['dict_table'])) {
                    $child['options'] = $this->dictRepository->getOptions($field['dict_table'], $field['field_sname']);
                }

                $children[] = $child;
            }
            $cur['children'] = $children;
            $data[] = $cur;
        }

        $ret = [
            "title" => $title,
            "desc"  => $desc,
            "fields" => $data
        ];

        return $ret;
    }


    protected function reportOp($current, $field, array $op) {
        if(count($op) != 2) {
            Log::warning("$field op error:",["op" => $op]);
            return false;
        }
        list($ops, $value) = $op;
        if($ops === "in") {
            $current->whereIn($field, $value);
        }
        else if($ops === "like") {
            $value = "%".$value."%";
            $current->where($field, $ops, $value);
        }
        else {
            $current->where($field, $ops, $value);
        }
    }

    public function getReport($request, &$header = []) {
        $obj = $this->reportDevice->findOrFail($request->input("id"));
        $configRaw = json_decode($obj->config, true);
        $config = [];
        $tbl = "assets_device";

        $fields = [];
        foreach($configRaw as $v) {
            $where = [];
            $where["sub_category_id"] = $v['categoryId'];
            $config[$v['categoryId']] = $v['content'];
            $current = DB::table($tbl)->where($where);
            foreach($v['content'] as $vv) {
                $fields[] = $vv['field'];
                foreach($vv['op'] as $op) {
                    $this->reportOp($current, $vv['field'], $op);
                }
            }
            $models[] = $current;
        }

        if(empty($models)) {
            return [];
        }

        $fields = array_merge(["id","number","sub_category_id", "deleted_at"],$fields);
        $fields = array_unique($fields);

        $fieldsResult = $this->fields->whereIn("field_sname", $fields)->get();
        foreach($fieldsResult as $v) {
            $key = $v->field_sname;
            if(!empty($v->dict_table) || !empty($v->field_dict)) {
                $key = $key."_msg";
            }
            $header[$key] = $v->field_cname;
        }

        foreach($models as $k => &$model) {
            $model->select($fields);
            if($k > 0) {
                $models[0]->union($model);
            }
        }

        $modelunion = $this->device->with("sub_category")
            ->from(DB::raw("({$models[0]->toSql()}) as assets_device"))
            ->mergeBindings($models[0])->select(["assets_device.*"]);
        return $this->usePage($modelunion, ["sub_category_id","id"],["asc","desc"]);


//        $model = DB::table("assets_device")->where("sub_category_id","=","8")->where("state","=",2)->select("id","number","sub_category_id","state","layer","deleted_at");
//        $model2 = DB::table("assets_device")->where("sub_category_id","=","9")->where("layer","=",2)->select("id","number","sub_category_id","state","layer","deleted_at");
//        $model3 = DB::table("assets_device")->where("sub_category_id","=","10")->where("state","=",1)->select("id","number","sub_category_id","state","layer","deleted_at");
//        $model2->union($model);
//        $model2->union($model3);
//
//        $modelunion = $this->device->with('category')
//            ->from(DB::raw("({$model2->toSql()}) as assets_device"))
//            ->mergeBindings($model2)->select(["assets_device.*"]);
//
//        return $this->usePage($modelunion, "sub_category_id","asc");


    }

    /**
     * @param $request 所需参数值
     * @return array|bool
     */
    public function getDeviceReport($request){
        $templateID = $request->input('template_id');
        if($templateID){
            $this->inspectionModel->where('template_id','=',$templateID)->firstOrFail();
//            $res = $this->inspectionModel->where('template_id','=',$templateID)->first();
//            if(null == $res){
//                throw new ApiException(Code::ERR_EXPORT);
//            }
//            return $res;
        }else {
            $default = isset($request['default']) ? $request['default'] : 1;
            if ($default) {
                $where = array('is_default' => $default);
                $tData = $this->emiTemplateModel->select('id')->where($where)->first();
                $templateID = isset($tData['id']) ? $tData['id'] : '';
            }
        }
        if (!$templateID) {
            Code::setCode(Code::ERR_EM_NOT_TEMPLATE_ID);
            return false;
        }

        if(null !== $request->input('start_time')){
            $startTime = $request->input('start_time');
        }else{
            Code::setCode(Code::ERR_QUERY);
            return false;
        }

        if(null !== $request->input('end_time')){
            $endTime = $request->input('end_time');
            if($startTime > $endTime){
                Code::setCode(Code::ERR_QUERY);
                return false;
            }
        }else{
            $endTime = $startTime;
        }


        // 查询出指定时间全部符合条件的数据
        $inspectionData = $this->inspectionModel
            ->select('inspection.id','inspection.asset_id','inspection.report_date', 'inspection.content','B.number','B.name','B.category_id')
            ->join('assets_device as B','inspection.asset_id','=','B.id')
            ->where('report_date','>=',$startTime)
            ->where('report_date','<=',$endTime)
            ->where('template_id',$templateID)
            ->get();


        $inspectionDataNew = [];
        $contents = array();
        $reportDateArr = array();
        if($inspectionData) {
            foreach ($inspectionData as $k => $v) {
                $id = $v['id'];
                $assetId = isset($v['asset_id']) ? $v['asset_id'] : '';
                $categoryId = isset($v['category_id']) ? $v['category_id'] : '';
                $reportDate = isset($v['report_date']) ? $v['report_date'] : '';
                $contentArr = isset($v['content']) ? json_decode($v['content'], true) : '';
                $key = $assetId . '_' . $categoryId;
//                var_dump($contentArr);exit;

                // 记录所有出现的时间
                $reportDateArr[$reportDate] = $reportDate;

                if ($contentArr) {
                    $contentArr['id'] = $id;
//                    var_dump($contentArr);exit;
//                    foreach($contentArr as $vv){
                    if (isset($contents[$key][$reportDate])) {

                        $contents[$key][$reportDate] = $contentArr;
                    } else {
                        $contents[$key][$reportDate] = $contentArr;
                    }
//                    }
                }

                // 只放入不重复的值
                if(!isset($inspectionDataNew[$key])){
                    $inspectionDataNew[$key] = $v;
                }
//                $inspectionData[$k]['cArr'] = $contents;
            }


//            return $reportDateArr;
            foreach($inspectionDataNew as $k => $v){
                $assetId = isset($v['asset_id']) ? $v['asset_id'] : '';
                $categoryId = isset($v['category_id']) ? $v['category_id'] : '';
//                $reportDate = isset($v['report_date']) ? $v['report_date'] : '';
                $key = $assetId . '_' . $categoryId;
                foreach($reportDateArr as $rdk=>$rdv){
                    if(!isset($contents[$key][$rdk])){
                        $contents[$key][$rdk] = array(
                            "cpu_percent" => '',
                            "mem_percent" => '',
                            "disk_percent" => '',
                            "disk_total" => '',
                            "packet_loss_percent" => '',
                            "ip" => '',
                            "status" => '',
                            "cpuCount" => ''
                        );
                    }
                }
                $inspectionDataNew[$k]['cArrCount'] = isset($contents[$key]) ? count($contents[$key]) : 0;
                $inspectionDataNew[$k]['cArr'] = isset($contents[$key]) ? $contents[$key] : array();


            }
        }


        $formatItem = $this->formatItem($inspectionDataNew);

        return $formatItem;

    }





    public function formatItem($inspectionDataNew){
        $arr = [];

        foreach ($inspectionDataNew as $key => $item){

            // 拼接设备名称
            if(!empty($item['name'])){
                $name = $item['number'] . ':' . $item['name'];
            }else{
                if(!empty($item['number'])){
                    $name = $item['number'];
                }else{
                    $name = '设备名未知';
                }

            }

            $cArr = $item['cArr'];
            $category_id = $item['category_id'];
            $showField = $this->showField($cArr,$category_id,$name);

            $timeListValue = $this->timeListValue($showField);

            // 根据分类将所需显示字段总数拼接到 $itemKey 中
            if(Inspection::CNE == $category_id || Inspection::CSE == $category_id){
                $count = 4;
            }elseif (Inspection::CSED == $category_id){
                $count = 6;
            }else{
                $count = 3;
            }

            $itemKey = $key . '_' . $count;
            // 以拼接的值为键名，$timeListValue 重新拼接数组
            $arr[$itemKey] = $timeListValue;

        }
        return $arr;

    }

    /**
     * @param $cArr   完整的数据数组
     * @param $category_id  设备分类 id
     * @param $name 设备名称
     * @return mixed 筛选字段之后的数组（设备名称也被包含在数组中）
     */
    public function showField($cArr,$category_id,$name){

        foreach ($cArr as $time => &$item){
            $arr = [];

                /*格式化报表中所需字段值*/
                // 需要格式化的字段数组
                $fields = ["cpu_percent", "mem_percent", "packet_loss_percent", "disk_percent", "status", "disk_total", "cpuCount", "ip"];
                // 循环格式化
                foreach ($fields as $field){
                    switch ($field){
                        case "cpu_percent":
                        case "mem_percent":
                        case "disk_percent":
                            if(isset($item[$field])){
                                $$field = $item[$field] > 0 ? $item[$field] . '%' : $item[$field];
                            }
                            break;
                        case "status":
                            if(isset($item[$field])){
                                $$field = $item[$field] ? '正常' : '异常';
                            }
                            break;
                        case "packet_loss_percent":
                            if(isset($item[$field])){
                                $$field = $item[$field] > 0 ? $item[$field] . '个/分钟' : '0个/分钟';
                            }
                            break;
                        case "disk_total":
                            if(isset($item[$field])){

                                if($item[$field] > 0){
                                    if($item[$field] / (1024*1024) < 1000){
                                        $$field = number_format($item[$field] / (1024*1024),2) . 'GB';
                                    }else{
                                        $$field = number_format($item[$field] / (1024*1024*1024),2) . 'T';
                                    }
                                }else{
                                    $$field = $item[$field];
                                }

                            }
                            break;
                        default:
                            if(isset($item[$field])){
                                $$field = $item[$field];
                            }

                    }
                }

                // 根据分类 id (category_id) 判断需要出现哪些指定字段
                switch ($category_id){
                    // 网络设备
                    case Inspection::CNE:
                        // 安全设备
                    case Inspection::CSE:
                        // cpu 使用率
                        $arr["cpu_percent"] = $cpu_percent;
                        // 内存使用率
                        $arr["mem_percent"] = $mem_percent;
                        // 丢包率
                        $arr["packet_loss_percent"] = $packet_loss_percent;
                        break;
                    // 服务器
                    case Inspection::CSED:
                        // ip地址
                        $arr["ip"] = $ip;
                        // CPU计数
                        $arr["cpuCount"] = $cpuCount;
                        // cpu 使用率
                        $arr["cpu_percent"] = $cpu_percent;
                        // 内存使用率
                        $arr["mem_percent"] = $mem_percent;
                        // 磁盘使用率
                        $arr["disk_percent"] = $disk_percent;
                        break;
                    // 存储
                    case Inspection::CSD:
                        // 磁盘大小
                        $arr["disk_total"] = $disk_total;
                        // 磁盘使用率
                        $arr["disk_percent"] = $disk_percent;
                        break;
//                    default:
//                        $arr = $content;

                }

                // 状态
                $arr["status"] = $status;

                // 将设备名称放在数组最前端
                $newArr = [];
                $newArr["name"] = $name;
                // 两数组合并并保证键名不变
                $item = $newArr + $arr;
        }

        return $cArr;
    }

    /**
     * @param $arr 待处理的数组
     * @return array 以所需字段为 item ，时间为键 所对应字段数据为值，重组后的二维数组
     */
    public function timeListValue($arr){

        // 取出设备名称（补充过空值，需要将空值过滤）避免取出空值
        $names = array_column($arr,'name');
        foreach($names as $nameval){
            if(!empty($nameval)) $name = $nameval;
        }

        // 巡检项名称
        $itemNames = [
            'cpu_percent' => 'CPU使用率',
            'mem_percent' => '内存使用率',
            'packet_loss_percent' => '丢包率',
            'status' => '状态',
            'disk_total' => '磁盘大小',
            'disk_percent' => '磁盘使用率',
            'ip' => 'ip地址',
            'cpuCount' => 'CPU计数',
        ];


        foreach ($arr as $time => $value){

            // $items 为 name、cpu_percent、mem_percent……一系列值
            $items = array_keys($value);

            // 删除设备名称
            unset($items[0]);
            $newArray = [];

            foreach($items as $item){

                // 取出 $arr 二维数组中指定列($item 字段值)的所有值
                $$item = array_column($arr,$item);

                // 取出时间数组
                $report_date = array_keys($arr);

                // 时间作为键名，报表中所需内容值为键值，拼接成新数组
                $array = [];
                $array = array_combine($report_date,$$item);

                $new = [];
                // 设备名称
                $new["name"] = $name;
                // 一台设备所对应的巡检项
                $new["item"] = $itemNames[$item];

                // 将两数组合并，键名保持不变
                $new = $new +$array;
                $newArray[] = $new;

            }

        }

        return $newArray;

    }

    public function getAssetsWarrantyTimeRate()
    {
        $today = date("Y-m-d H:i:s");
        $month = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+1, date("d"), date("Y")));
        $month3 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+3, date("d"), date("Y")));
        $month6 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+6, date("d"), date("Y")));
        $month12 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+12, date("d"), date("Y")));

        //即将过保
        $soon1 = $this->device->where("warranty_end",">=",$today)->where("warranty_end","<",$month)->count();

        $soon3 = $this->device->where("warranty_end",">=",$month)->where("warranty_end","<",$month3)->count();

        $soon6 = $this->device->where("warranty_end",">=",$month3)->where("warranty_end","<",$month6)->count();

        $soon12 = $this->device->where("warranty_end",">=",$month6)->where("warranty_end","<",$month12)->count();

        $soonYear = $this->device->where("warranty_end",">=",$month12)->count();

        $all = $soon1+$soon3+$soon6+$soon12+$soonYear;

        $data['result'] = [
            ['name'=>'一个月内','value'=>round(($soon1/$all)*100)],
            ['name'=>'三个月内','value'=>round(($soon3/$all)*100)],
            ['name'=>'六个月内','value'=>round(($soon6/$all)*100)],
            ['name'=>'一年内','value'=>round(($soon12/$all)*100)],
            ['name'=>'一年以上','value'=>round(($soonYear/$all)*100)],
        ];

        return $data;
    }




}
