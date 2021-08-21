<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Exceptions\ApiException;
use App\Models\Assets\Engineroom;
use App\Models\Assets\RackPos;
use App\Repositories\Auth\UsersPreferencesRepository;
use App\Repositories\Auth\UserRepository;
use App\Models\Workflow\Event;
use App\Repositories\BaseRepository;
use App\Models\Assets\Category;
use App\Models\Workflow\Category as WorkflowCategory;
use App\Models\Assets\Device;
use App\Models\Assets\FieldsType;
use App\Models\Assets\Fields;
use App\Models\Assets\CategoryFields;
use App\Models\Code;
use App\Models\Info;
use App\Models\Assets\Zone;
use QrCode;
use Log;
use Cache;
use DB;
use App;
use App\Models\Workflow\Multievent;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Fractal;

class DeviceRepository extends BaseRepository
{

    public $defaultFields = ["number", "state", "name", "brand", "ip",  "intime"];
    public $mDefaultFields = ["number", "state", "assets_device.name", "ip"];

    protected $fieldsType;
    protected $device;
    protected $category;
    protected $userRepository;
    protected $userPreferences;
    protected $categoryfieldsModel;
    protected $zone;
    protected $multieventModel;
    protected $workflowCategoryModel;
    protected $model;

    public function __construct(Category $categoryModel,
                                Device $deviceModel,
                                FieldsType $fieldsTypeModel,
                                Fields $fieldsModel,
                                UsersPreferencesRepository $userPreferencesRepository,
                                UserRepository $userRepository,
                                Engineroom $engineroomModel,
                                RackPos $rackPosModel,
                                Event   $eventModel,
                                Info $infoModel,
                                CategoryFields $categoryfieldsModel,
                                DictRepository $dictRepository,
                                Zone $zone,
                                Multievent $multieventModel,
                                WorkflowCategory $workflowCategoryModel)
    {
        $this->category = $categoryModel;
        $this->model  = $deviceModel;
        $this->device = $deviceModel;
        $this->fieldsType = $fieldsTypeModel;
        $this->fields = $fieldsModel;
        $this->userPreferences = $userPreferencesRepository;
        $this->dictRepository = $dictRepository;
        $this->engineroom = $engineroomModel;
        $this->info = $infoModel;
        $this->rackPos = $rackPosModel;
        $this->event = $eventModel;
        $this->userRepository = $userRepository;
        $this->categoryfieldsModel = $categoryfieldsModel;
        $this->zone = $zone;
        $this->multieventModel = $multieventModel;
        $this->workflowCategoryModel = $workflowCategoryModel;
    }

    /**
     * 根据类别和field返回field和type
     * @param $categoryId
     * @param $fields
     * @return mixed
     */
    protected function getFields($categoryId, $fields)
    {
        if($categoryId) {
            return $this->category->findOrFail($categoryId)
                ->fields()->whereIn("field_sname", $fields)->get()->pluck("field_type","field_sname")->all();
        }
        else {
            return $this->fields->whereIn("field_sname", $fields)->get()->pluck("field_type","field_sname")->all();
        }
    }

    public function getCategory($category)
    {
        $data = $this->category->where(["shortname" => $category])->first();
        if (empty($data)) {
            throw new ApiException(Code::ERR_PARAMS, ["category不存在"]);
        }
        return $data;
    }

    public function getRackList() {
        $data = $this->model->getRackList();
        return $data;
    }

    /**
     * 获取机柜详情
     * @param $assetId
     * @return mixed
     * @throws ApiException
     */
    public function getRackInfo($assetId) {
        $rack = $this->model->getRackList($assetId);
        if(empty($rack)) {
            throw new ApiException(Code::ERR_PARAMS,["资产不存在 $assetId"]);
        }

        $rackPos = $this->rackInfoById($assetId);
        $data['number'] = $rack->number;
        $data['name'] = $rack->name;
        $data['rackSize'] = $rack->rack_size;
        $data['info'] = $rackPos;
        return $data;

    }


    /**
     * 根据机柜id获取详情
     * @param $assetId
     * @return array
     */
    public function rackInfoById($assetId){
        $rackPos = [];
        if($assetId) {
            //取位置信息
            $rackPos = $this->rackPos->where(["rack_asset_id" => $assetId])->get()->toArray();
            foreach ($rackPos as $k => &$v) {
                $device = $this->model->find($v['asset_id']);
                if (empty($device)) {
                    Log::error("机柜上的设备没找到 " . $v['asset_id']);
                    unset($rackPos[$k]);
                    continue;
                }
                $v['number'] = $device->number;
                $v['name'] = $device->name;
                $v['ip'] = $device->ip;
                $v['unit'] = $device->unit;
                $v['model'] = DeviceRepository::transform('model',$device->model,$tkey);
                $v['brand'] = DeviceRepository::transform('brand',$device->brand,$tkey);
                $v['category'] = $this->category->where(["id" => $device->category_id])->first()->name;
                $v['subCategory'] = $this->category->where(["id" => $device->sub_category_id])->first()->name;
            }
        }
        return $rackPos;
    }

    public function isRack($assetId) {
        return $this->model->isRack($assetId);
    }

    /**
     * 上架
     * @param $input
     * @throws ApiException
     * @throws \Exception
     */
    public function rackUp($input) {
        $rack = $this->model->getRackList($input['rackId']);
        if(empty($rack)) {
            throw new ApiException(Code::ERR_PARAMS,["资产不存在 ".$input['rackId']]);
        }

        $rackSize = intval($rack->rack_size);
        if(empty($rackSize)) {
            throw new ApiException(Code::ERR_ASSETS_RACK_SIZE);
        }

        $device = $this->getById($input['assetId']);
        if(empty($device)) {
            throw new ApiException(Code::ERR_PARAMS,["资产不存在 ".$input['assetId']]);
        }

        $unit = intval($device->unit) || intval($input['unit']); //设备U数
        if($unit === 0) {
            throw new ApiException(Code::ERR_ASSETS_UNIT);
        }

        $position = isset($input['position']) ? intval($input['position']) : 0;

        $insert = [
            "rack_asset_id" => $input['rackId'],
            "asset_id" => $input['assetId'],
            "pos_start" => $position,
            "pos_end" => $position + $unit - 1,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        ];

        if($rackSize < $insert['pos_end']) {
            throw new ApiException(Code::ERR_ASSETS_RACK_POS);
        }

        DB::beginTransaction();
        //移走原来的位置
        $this->rackPos->where(["asset_id" => $insert['asset_id']])->delete();

        if (!$this->rackPos->checkPosition($insert['rack_asset_id'], $insert['pos_start'], $insert['pos_end'])) {
            throw new ApiException(Code::ERR_ASSETS_RACK_POS);
        }

        $this->rackPos->insert($insert);
        DB::commit();

        userlog("机柜上架了设备。机柜id：".$input['rackId']." 资产id：".$input['assetId']." 位置：".$insert['pos_start']." ".$insert['pos_end']);

    }

    /**
     * 下架
     * @param $assetId
     * @throws ApiException
     */
    public function rackDown($assetId) {
        $device = $this->getById($assetId);
        if(empty($device)) {
            throw new ApiException(Code::ERR_PARAMS,["资产不存在 ".$assetId]);
        }

        $this->rackPos->where(["asset_id" => $assetId])->delete();

        //userlog("机柜下架了设备。资产id：".$assetId);
    }



    /**
     * 获取详情
     * @param $id
     * @return array
     * @throws ApiException
     */
    public function getItem($id)
    {
        $device = $this->model->withTrashed()->findOrFail($id);

        if(empty($device)) {
            throw new ApiException(Code::ERR_PARAMS,["资产不存在 $id"]);
        }
        $categoryId = $device->sub_category_id;
        // 查询出资产所属机房级别
        $engineroomId = isset($device->area) ? $device->area : 0;
        if($engineroomId > 0){
            $engineroomItem = Engineroom::find($engineroomId);
            if(empty($engineroomItem)) { //机房删除了
                $engineroomType = '';
                $engineroomAddress = '';
            }
            else {
                $engineroom_type = $engineroomItem->type;
                $engineroomAddress = $engineroomItem->address;
                $engineroomType = !empty($engineroom_type) && in_array($engineroom_type,array_flip(Engineroom::ENGINEROOM_TYPES)) ? Engineroom::ENGINEROOM_TYPES[$engineroom_type] : $engineroom_type;
            }
        }else{
            $engineroomType = '';
            $engineroomAddress = '';
        }

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
            if(!in_array($categoryId,$limitCategories)) {
                throw new ApiException(Code::ERR_USER_CATEGORY);
            }
        }
        $fieldArrKeys[] = "assets_fields.*";
        $fieldArrKeys[] = "assets_category_fields.is_show";

        $fieldsList = $this->category->findOrFail($categoryId)
            ->fields()->select($fieldArrKeys)->get()->groupBy("field_type_id")->toArray();
        $fieldsType = $this->fieldsType->get()->pluck("name", "id")->all();

        $info = [];
        $number = "";

        foreach ($fieldsList as $k => $v) {
            $cur = [
                "id" => $k,
                "cname" => $fieldsType[$k],
            ];
            $children = [];
            foreach ($v as $vv) {
                $child = [
                    "sname" => $vv['field_sname'],
                    "cname" => $vv['field_cname'],
                    "value" => self::transform($vv['field_sname'], $device->{$vv['field_sname']}, $tkey),
                    "is_show" => isset($vv['is_show']) ? $vv['is_show'] : 1,
                ];
                if($vv['field_sname'] == "number") {
                    $number = $device->{$vv['field_sname']};
                }
                $children[] = $child;
            }
            $cur['children'] = $children;
            $info[] = $cur;
        }

        // 位置信息中强加机房级别
        foreach ($info as $key => &$value){
            if('位置信息' == $value['cname']){
                $new = [];
                if(is_array($value['children'])){
                    foreach ($value['children'] as $vvv){
                        switch ($vvv['sname']){
                            case 'location':
                                $new[0] = $vvv;
                                break;
                            case 'officeBuilding':
                                $new[1] = $vvv;
                                break;
                            case 'area':
                                $new[2] = $vvv;
                                // 强制加机房级别和机房地址
                                $isShow = isset($vvv['is_show']) ? $vvv['is_show'] : 0;
                                $new[3] = [
                                    "sname" => "engineroom_type",
                                    "cname" => "机房级别",
                                    "value" => $engineroomType,
                                    "is_show" => $isShow,
                                ];
                                $new[4] = [
                                    "sname" => "engineroom_address",
                                    "cname" => "机房地址",
                                    "value" => $engineroomAddress,
                                    "is_show" => $isShow,
                                ];
                                break;
                            case 'rack_no':
                                $new[5] = $vvv;
                                break;
                            case 'rack_pos':
                                $new[6] = $vvv;
                                break;
                            default:
                                $new[] = $vvv;
                        }
                    }
                    ksort($new);
                    $value['children'] = $new;
                }
            }
        }

        //当前处理的事件
        $event = $this->event->where("asset_id","=", $id)->whereIn("state",[0,1,2])->select("id")->first();
        $eventId = 0;
        if(!empty($event)) $eventId = $event->id;

        $data = [
            "id" => $id,
            "category" => $this->category->where(["id" => $device->category_id])->first()->name,
            "subCategory" => $this->category->where(["id" => $device->sub_category_id])->first()->name,
            "isDel" => is_null($device->deleted_at) ? 1: 0,
            "number" => $number,
            "eventId" => $eventId,
            "state" => $device->state,
            "emType" => in_array($device->category_id, \ConstInc::$em_category_id) ? 1: 0,
            "info" => $info,
            //"qrCode" => QrCode::size(self::QRSIZE)->generate($qrdetail)
            "qrCode" => $this->genQrcode($id)
        ];

        return $data;
    }

    public function genQrcode($assetId, $overwrite = 0) {
        $device = $this->model->withTrashed()->findOrFail($assetId); //2172 asset_id
        $number = $device->number;
        // D:\www\operation\storage\app/qr/
        $path = storage_path(\ConstInc::QR_PATH);
        if (!checkCreateDir($path)) {
            Log::error("can not create dir $path");
            throw new ApiException(Code::ERR_QRCODE);
        }

        $filename = $assetId.".png";
//        var_dump($filename);exit;
        if($overwrite != 0 || !file_exists($path.$filename) || filesize($path.$filename) == 0) {
            //$qrdetail = config("app.wx_url")."/commdetail?assetId=$assetId";
//            "http://localhost/commdetail?number=MLSW-DN-00333L"
            $qrdetail = config("app.wx_url")."/commdetail?number=$number";
            QrCode::errorCorrection('H');
//            ->merge(public_path()."/logo.png", .2, true)
            QrCode::format('png')->size(\ConstInc::QRSIZE)->margin(0)->generate($qrdetail, $path.$filename);
        }
        $blob = file_get_contents($path.$filename);
        unlink($path.$filename);
        return base64_encode($blob);
    }



    public function printQrcode($assetId, $overwrite = 0) {
        $device = $this->model->withTrashed()->findOrFail($assetId); //2172 asset_id
        $number = $device->number;
        // D:\www\operation\storage\app/qr/
        $path = storage_path(\ConstInc::QR_PATH);
        if (!checkCreateDir($path)) {
            Log::error("can not create dir $path");
            throw new ApiException(Code::ERR_QRCODE);
        }

        $filename = $assetId.".png";
        if($overwrite != 0 || !file_exists($path.$filename) || filesize($path.$filename) == 0) {
            $qrdetail = config("app.wx_url")."/commdetail?number=$number";
            QrCode::errorCorrection('H');
//            ->merge(public_path()."/logo.png", .2, true)
            QrCode::format('png')->size(150)->margin(2)->generate($qrdetail, $path.$filename);

        }

        // 二维码数据流
        $image = imagecreatefromstring(file_get_contents($path.$filename));

        $font_path = storage_path('heiti.ttf');  // 黑体字体
        $black = imagecolorallocatealpha($image, 0, 0, 0, 0); //创建黑色字体

        imagettftext($image, 9, 0, 17, 144, $black, $font_path, $number); // 创建文字
        header("Content-Type:image/png");
        imagepng($image, $path.'number'.$filename);
//        exit;
        $blob = file_get_contents($path.'number'.$filename);
//        $blob = file_get_contents($path.$filename);
        unlink($path.$filename); // 应该删除
        unlink($path.'number'.$filename); // 应该删除
        return base64_encode($blob);
    }

    /**
     * 检查是否有资产编号，没有则自动补全
     * @param $data
     */
    protected function genNumberMulti(&$data) {
        $current = 0;
        foreach($data as &$v) {
            if(!isset($v['number']) || empty($v['number'])) {
                $prefix = $this->getNumberPrefix($v['category_id']);
                if(0 === $current) {
                    $current = intval($this->device->select(DB::raw("max(id) as MAXID"))->first()->MAXID);
                }
                $current += 1;
                $v['number'] = sprintf("%s-%05s", $prefix, $current);
            }
        }
    }


    /**
     * 生成序列号
     * @param $categoryId
     * @return array [number,prefix,current]
     */
    public function genNumber($categoryId) {
        $prefix = $this->getNumberPrefix($categoryId);
        $current = intval($this->device->withTrashed()->select(DB::raw("max(id) as MAXID"))->first()->MAXID) + 1;
        return [
            "number" => sprintf("%s-%05s", $prefix, $current),
            "prefix" => $prefix,
            "current" => $current
        ];
    }

    protected function getNumberPrefix($categoryId) {
        $companyShortname = $this->info->getByName("company_shortname");
        $categoryInfo = $this->category->findOrFail($categoryId);
        return $companyShortname."-".$categoryInfo->shortname;
    }


    /**
     * 特殊字段转换
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function transform($key, $value, &$tkey,$type=false)
    {
        $tkey = "";
        if(is_null($value)) {
            return $value;
        }

        $fieldsArr = Cache::remember('fields',1, function () {
            return DB::table("assets_fields")->get()->keyBy("field_sname");
        });

        switch ($key) {
            case "warranty_time":
                return !is_null($value)?$value."天":$value;
            case "warranty_begin":
            case "warranty_end":
                return !is_null($value)?date("Y-m-d", strtotime($value)):null;
            case "created_at":
            case "updated_at":
                return $value->format('Y-m-d H:i:s');
            case "userpwd":
                if($type){
                    $value = endecode($value,$type);
                }else{
                    $value = $value ? '******' : null;
                }
                return $value;
            default:
                if (!empty($fieldsArr[$key]->field_dict)) {
                    $tkey = $key."_msg";
                    $dict = json_decode($fieldsArr[$key]->field_dict , true);
                    if(!$dict) {
                        return $value;
                    }
                    if(isset($dict[$value])) {
                        return $dict[$value];
                    }
                }
                else if(!empty($fieldsArr[$key]->dict_table)) {
                    $dictTable = $fieldsArr[$key]->dict_table;
                    $tkey = $key."_msg";

                    return Cache::remember("field_{$key}_{$value}",0.2, function () use($dictTable, $value, $key) {
                        if(strpos($dictTable, "|") > 0){
                            list($dictTable, $p_sname) = explode("|", $dictTable);
                            $className = "\App\Models\\".$dictTable;
                        }
                        else {
                            $className = "\App\Models\\".$dictTable;
                        }
                        $cls = App::make($className);

                        $fieldName = "name";
                        if(property_exists($cls, "fieldTrans")){
                            if(array_key_exists($key, $cls->fieldTrans)) {
                                $fieldName = $cls->fieldTrans[$key];
                            }
                        }

                        return call_user_func_array([$cls, "getFieldById"],[$value, $fieldName]);
                    });
                }
                else {
                    return $value;
                }
        }
    }

    /**
     * @param $engineroomId
     */
    public function summary($engineroomId) {
        $summary = [
            'state_0' => 0,
            'state_1' => 0,
            'state_2' => 0,
            'state_3' => 0,
        ];

        $model = $this->model->select(DB::raw('count(id) as cnt,state'));
        if(!is_null($engineroomId)) {
            $model = $model->where(["area" => $engineroomId]);
        }
        $data = $model->groupBy("state")->get();
        $total = 0;
        foreach($data as $value) {
            $summary["state_".$value->state] = $value->cnt;
            $total += $value->cnt;
        }
        $summary['total'] = $total;
        $summary['trashed'] = $this->model->where(["area" => $engineroomId])->onlyTrashed()->count();

        return $summary;
    }

    public function del($assetIds) {

        //权限判断
        if (!$this->userRepository->isAdmin() && !$this->userRepository->isManager()){
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
            $ret = $this->device->whereIn("id", $assetIds)->get();
            foreach($ret as $v){
                if(!in_array($v->sub_category_id, $limitCategories)) {
                    $numbers[] = $v->number;
                }
            }
            if(!empty($numbers)) {
                Code::setCode(Code::ERR_USER_CATEGORY);
                return false;
            }
        }


        // STATE_WAIT => 0待处理, STATE_ACCESS => 1已接单, STATE_ING => 2处理中
        $workflowStateArr = [Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING];
        // UP => 2上架， MULTI_UP => 8批量上架
        $workflowCategoryArr = [WorkflowCategory::UP, WorkflowCategory::MULTI_UP];

        // 判断当前数据是否有通过批量导入的资产
        $multieventsRes = $this->multieventModel->with("asset")
                                                ->whereIn("asset_id", $assetIds)
                                                ->get()
                                                ->unique("asset_id");

        $stateNumbers = [];
        $categoryNumbers = [];
        if(!empty($multieventsRes)){
            foreach ($multieventsRes as $vv){
                if(in_array($vv->state,$workflowStateArr)){
                    $stateNumbers[] = $vv->asset->number;
                }
                if(in_array($vv->category_id,$workflowCategoryArr)){
                    $categoryNumbers[] = $vv->asset->number;
                }
            }
        }


//        $eventRes = $this->event->with("asset")
//                                ->whereIn("asset_id", $assetIds)
//                                ->get()
//                                ->unique("asset_id");
//        $eventStateNumbers = [];
//        $eventCategoryNumbers = [];
//        if(!empty($eventRes)){
//            foreach ($eventRes as $vvv){
//                if(in_array($vvv->state,$workflowStateArr)){
//                    $eventStateNumbers[] = $vvv->asset->number;
//                }
//                if(in_array($vvv->category_id,$workflowCategoryArr) && $vvv->state == Event::STATE_END){
//                    $eventCategoryNumbers[] = $vvv->asset->number;
//                }
//            }
//        }


        //允许删除从未有过上架记录的资产
        $result = $this->event->with("asset")
            // 过滤掉状态为“已完成”的资产
            ->where("state",3)
            ->whereIn("asset_id", $assetIds)
            ->whereIn("category_id",$workflowCategoryArr)
            ->get()
            ->unique("asset_id");

        $resultNumbers = [];
        if($result->count() > 0) {
            foreach($result as $v){
                $resultNumbers[] = $v->asset->number;
            }
        }

        // 单独事件或批量事件任一有过上架记录的资产不允许删除
        if(!empty($categoryNumbers) || !empty($resultNumbers)){
            $categoryErrNumber = array_merge($categoryNumbers,$resultNumbers);
            Code::setCode(Code::ERR_ASSETS_USING,null, [join(",",$categoryErrNumber)]);
            return false;
        }

        //如果有事件处理中，不可删除
        $res = $this->event->with("asset")
                            ->whereIn("asset_id", $assetIds)
                            ->whereIn("state",$workflowStateArr)
                            ->get()
                            ->unique("asset_id");

        $resNumbers = [];
        if($res->count() > 0) {
            foreach($res as $v){
                $resNumbers[] = $v->asset->number;
            }
        }

        // 单独事件或批量事件任一有事件处理中，不可删除
        if(!empty($stateNumbers) || !empty($resNumbers)){
            $stateErrNumber = array_merge($stateNumbers,$resNumbers);
            Code::setCode(Code::ERR_EVENT_ASSET_DEL,null, [join(",",$stateErrNumber)]);
            return false;
        }


        $this->model->whereIn("id", $assetIds)->delete();
        userlog("删除了资产: ".join(",",$assetIds));
        return true;
    }

    public function search($search) {
        $model = $this->device;

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
            $model = $model->whereIn("sub_category_id", $limitCategories);
        }

        //优先精确查找
        $model = $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("number", "=", $search );
                $query->orWhere("name", "=", $search);
                $query->orWhere("ip", "=", $search );
            }
        });

        $fieldArrKeys = $this->defaultFields;
        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "created_at";
        $fieldArrKeys[] = "updated_at";
        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";
        $result = $model->select($fieldArrKeys)->limit(10)->get();
        if($result->count() === 0 ) {
            $model = $this->device;
            if(isset($limitCategories)) {
                $model = $model->whereIn("sub_category_id", $limitCategories);
            }
            $model = $model->where(function ($query) use ($search) {
                if (!empty($search)) {
                    $query->orWhere("number", "like", "%" . $search . "%");
                    $query->orWhere("name", "like", "%" . $search . "%");
                    $query->orWhere("ip", "like", "%" . $search . "%");
                }
            });
            $result = $model->select($fieldArrKeys)->with("monitor","category","sub_category")->limit(10)->get();
        }
        return $result;
    }

    protected function getFieldSearch($categoryId, $fieldSearch) {
        if($categoryId === 0) {
            $fieldSearchKeys = array_intersect($this->defaultFields, array_keys($fieldSearch));
            $fieldSearchKeys = $this->getFields(0, $fieldSearchKeys);
        }
        else {
            $fieldSearchKeys = $this->getFields($categoryId, array_keys($fieldSearch));
        }

        $where = [];
        if (!empty($fieldSearchKeys)) {
            foreach ($fieldSearch as $k => $v) {
                if (!is_null($v) && isset($fieldSearchKeys[$k])) {
                    switch($fieldSearchKeys[$k]) {
                        case Fields::TYPE_DATETIME:
                        case Fields::TYPE_DATE:
                            if(false !== strpos($v, ",")) {
                                list($start, $end) = explode(",", $v);
                                if(!empty($start)) {
                                    $where[] = ["assets_device.".$k , ">=", $start];
                                }
                                if(!empty($end)) {
                                    $where[] = ["assets_device.".$k , "<=", $end];
                                }
                            }
                            break;
                        case Fields::TYPE_INT:
                        case Fields::TYPE_DICT:
                        case Fields::TYPE_FLOAT:
                            $where[] = ["assets_device.".$k, "=", $v];
                            break;
                        default:
                            $where[] = ["assets_device.".$k, 'like', "%" . $v . "%"];
                    }
                }
            }
        }
        return $where;
    }

    public function getListAll($categoryId, $search, array $fieldSearch = []) {
        $where = [];

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
        }

        if($categoryId) {
            $where[] = ["category_id", "=", $categoryId];
        }

        if(!empty($fieldSearch['location'])){
            $where[] = ["location",'=',$fieldSearch['location']];
        }

        if(!empty($fieldSearch['officeBuilding'])){
            $where[] = ["officeBuilding","=",$fieldSearch['officeBuilding']];
        }

        if(!empty($fieldSearch['enginerooms'])){
            $where[] = ["area","=",$fieldSearch['enginerooms']];
        }

        if(!empty($fieldSearch['department'])){
            $where[] = ["department",'=',$fieldSearch['department']];
        }

        if (!empty($fieldSearch)) {
            $searchWhere = $this->getFieldSearch(0, $fieldSearch);
            $where = array_merge($where, $searchWhere);
        }

        $model = $this->device;

        if(isset($limitCategories)) {
            $model = $model->whereIn("assets_device.sub_category_id",$limitCategories);
        }

        $model = $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("number", "like", "%" . $search . "%");
                $query->orWhere("name", "like", "%" . $search . "%");
                $query->orWhere("ip", "like", "%" . $search . "%");
            }
        })->where($where);

        $fieldArrKeys = $this->defaultFields;
        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "created_at";
        $fieldArrKeys[] = "updated_at";
        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";

       /* $model->join("workflow_events as B",function($join) {
            $join->on("assets_device.id", "=", "B.asset_id")
                ->where("B.source","!=",2)
                ->whereIn("B.state", [Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING]);
        },null,null,'left');

        $model->join("workflow_events as C",function($join) {
            $join->on("assets_device.id", "=", "C.asset_id")
                ->where("C.source","=",2)
                ->whereIn("C.state", [Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING]);
        },null,null,'left');*/

        foreach($fieldArrKeys as &$v) {
            $v = "assets_device.$v";
        }
        //$fieldArrKeys[] = "B.id as event_id";
        //$fieldArrKeys[] = "C.id as alert_id";
        $model = $model->with("monitor","category","sub_category", "events", "emdevice")->select($fieldArrKeys);
        return $this->usePage($model);
    }

    protected function getEventId() {

    }

    /**
     * @param $categoryId
     * @param $fields
     * @param $search
     * @param array $fieldSearch
     * @return mixed
     * @throws ApiException
     */
    public function getList($categoryId, $fields, $search, array $fieldSearch = [], $reset = 0)
    {
        unset($fieldSearch["debug"]);
        unset($fieldSearch["raw"]);
        unset($fieldSearch["search"]);
        unset($fieldSearch["categoryId"]);

        //$categoryId = $this->getCategoryId($category);
        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
            if(!in_array($categoryId,$limitCategories)) {
                Code::setCode(Code::ERR_USER_CATEGORY);
                return false;
            }
        }

        $where[] = ["sub_category_id", "=", $categoryId];

        if (!empty($fieldSearch)) {
            $searchWhere = $this->getFieldSearch($categoryId, $fieldSearch);
            $where = array_merge($where, $searchWhere);
        }

        $model = $this->device->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("number", "like", "%" . $search . "%");
                $query->orWhere("name", "like", "%" . $search . "%");
                $query->orWhere("ip", "like", "%" . $search . "%");
            }
        })->where($where);

        //重置
        if($reset == 1){
            $this->setPreferences($categoryId);
        }

        //返回选中的显示列
        if ($reset != 1 && !empty($fields)) {
            $fieldsArr = explode(",", $fields);
            $fieldArrKeys = $this->getFields($categoryId, $fieldsArr);
            if (empty($fieldArrKeys)) {
                throw new ApiException(Code::ERR_LISTFIELDS);
            }
            $fieldArrKeys = array_keys($fieldArrKeys);
            //写入用户自定义列
            $this->setPreferences($categoryId, $fieldArrKeys);
        } else {
            $fieldArrKeys = $this->getPreferences($categoryId);
        }

        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "created_at";
        $fieldArrKeys[] = "updated_at";
        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";

        foreach($fieldArrKeys as &$v) {
            $v = "assets_device.$v";
        }
        $model = $model->with("monitor","category","sub_category","events","emdevice")->select($fieldArrKeys);
        return $this->usePage($model);
    }

    /**
     * 回收站
     * @param $search
     * @return mixed
     */
    public function getTrashList($search) {
        //权限判断
        if (!$this->userRepository->isAdmin() && !$this->userRepository->isManager()){
            Code::setCode(Code::ERR_PERM);
            return false;
        }

        $model = $this->device;
        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
            $model = $model->whereIn("sub_category_id", $limitCategories);
        }

        $model = $model->onlyTrashed()->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("number", "like", "%" . $search . "%");
                $query->orWhere("name", "like", "%" . $search . "%");
                $query->orWhere("ip", "like", "%" . $search . "%");
            }
        });

        $fieldArrKeys = $this->defaultFields;
        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "created_at";
        $fieldArrKeys[] = "updated_at";
        $fieldArrKeys[] = "deleted_at";
        $model->orderBy("deleted_at","desc");
        $model->select($fieldArrKeys);

        return $this->usePage($model);

    }

    /**
     * 回收站还原
     * @param $id
     */
    public function trashRestore($id) {
        //权限判断
        if (!$this->userRepository->isAdmin() && !$this->userRepository->isManager()){
            Code::setCode(Code::ERR_PERM);
            return false;
        }
        $ids = explode(",", $id );
        $this->device->onlyTrashed()->whereIn("id", $ids)->restore();
    }

    /**
     * 回收站删除
     * @param $id
     */
    public function trashDel($id, $type) {
        //权限判断
        if (!$this->userRepository->isAdmin() && !$this->userRepository->isManager()){
            Code::setCode(Code::ERR_PERM);
            return false;
        }
        if($type == 1) {
            $this->device->onlyTrashed()->forceDelete();
        }
        else {
            $ids = explode(",", $id );
            $this->device->onlyTrashed()->whereIn("id", $ids)->forceDelete();
        }
    }



    /**
     * 取显示字段配置
     * @param $categoryId
     * @return null
     */
    public function getPreferences($categoryId)
    {
        $preferences = $this->userPreferences->getPreferences("assets_fields");
        if (!empty($preferences)) {
            if (isset($preferences[$categoryId])) {
                return $preferences[$categoryId];
            }
        }
        return $this->defaultFields;
    }

    /**
     * @param $categoryId
     * @param $fields
     */
    public function setPreferences($categoryId, $fields = null)
    {
        $preference = $this->userPreferences->getPreferences("assets_fields");
        if($preference) {
            if(empty($fields)) {
                unset($preference[$categoryId]);
            }
            else {
                $preference[$categoryId] = $fields;
            }
        }
        else {
            if(!empty($fields)) {
                $preference = [$categoryId => $fields];
            }
            else {
                $preference = [];
            }
        }

        $this->userPreferences->setPreferences("assets_fields", $preference);
    }

    /**
     * @param $state
     */
    public function getHealthy() {
        $use = $this->device->getCntByState(Device::STATE_USE);
        $maintain = $this->device->getCntByState(Device::STATE_MAINTAIN);

        if(($use + $maintain) === 0) {
            return 0;
        }

        return round($use/($use + $maintain) * 100 , 2);
    }

    public function getAssetsNum() {
        $ret = $this->device->groupBy("state")->selectRaw("state, count(*) as cnt")->pluck("cnt","state")->toArray();
        $data = [
            "free" => isset($ret[Device::STATE_FREE])?$ret[Device::STATE_FREE]:0,
            "use" => isset($ret[Device::STATE_USE])?$ret[Device::STATE_USE]:0,
            "maintain" => isset($ret[Device::STATE_MAINTAIN])?$ret[Device::STATE_MAINTAIN]:0,
            "down" => isset($ret[Device::STATE_DOWN])?$ret[Device::STATE_DOWN]:0,
        ];

        $all = 0;
        foreach($data as $v) {
            $all += $v;
        }
        $data['all'] = $all;
        return $data;
    }

    public function getAssetsYears(){
        //<1
        $lastYear = date("Y-m-d H:i:s", mktime(0,0,0,date('m'), date('d'), date('Y') - 1));
        $year["y1"] = $this->device->where(["state" => Device::STATE_USE])->where("intime" ,">=" ,$lastYear)->count();
        //1~3
        $last3Year = date("Y-m-d H:i:s", mktime(0,0,0,date('m'), date('d'), date('Y') - 3));
        $year["y1_3"] = $this->device->where(["state" => Device::STATE_USE])->where("intime" ,"<" ,$lastYear)->where("intime" ,">=" ,$last3Year)->count();
        //3~5
        $last5Year = date("Y-m-d H:i:s", mktime(0,0,0,date('m'), date('d'), date('Y') - 5));
        $year["y3_5"] = $this->device->where(["state" => Device::STATE_USE])->where("intime" ,"<" ,$last3Year)->where("intime" ,">=" ,$last5Year)->count();
        //>5
        $year["y5"] = $this->device->where(["state" => Device::STATE_USE])->where("intime" ,"<" ,$last5Year)->count();

        return $year;
    }

    public function getWarranty() {
        $today = date("Y-m-d H:i:s");
        $month6 = date("Y-m-d H:i:s", mktime(0,0,0,date("m")+6, date("d"), date("Y")));

        //在保
        $warranty["in"] = $this->device->where(["state" => Device::STATE_USE])->where("warranty_begin","<=",$today)->where("warranty_end",">=",$today)->count();

        //过保
        $warranty["out"] = $this->device->where(["state" => Device::STATE_USE])->where("warranty_end","<",$today)->count();

        //即将过保
        $warranty["willout"] = $this->device->where(["state" => Device::STATE_USE])->where("warranty_end",">=",$today)->where("warranty_end","<",$month6)->count();
        return $warranty;
    }


    public function getDeviceById($input=array()){

        $id = isset($input['assetId']) ? $input['assetId'] : 0;
        if(!$id) {
            throw new ApiException(Code::ERR_PARAMS, ["资产或环控设备不能为空"]);
        }
        $device = $this->model->find($id);
        $device = $device ? $device : array();
        return $device;

    }

    /**
     * 按机房或科室获取分类
     * @param Request $request
     */
    public function getErDtCategory($input=array()){
        $type = isset($input['type']) ? $input['type'] : '';

        $tableArr = array('er'=>'assets_enginerooms','dt'=>'assets_department');
        $table = isset($tableArr[$type]) ? $tableArr[$type] : '';
        if(!$table || !$type){
            Code::setCode(Code::ERR_PARAMS,'参数不正确');
            return false;
        }
        $result = array();
        $fieldArrKeys[] = 'assets_zone.id';
        $fieldArrKeys[] = 'assets_zone.name';
        $fieldArrKeys[] = 'A.id as ab_id';
        $fieldArrKeys[] = 'A.name as ab_name';
        $fieldArrKeys[] = 'A.zone_id as ab_zone_id';
        $fieldArrKeys[] = 'B.id as ed_id';
        $fieldArrKeys[] = 'B.name as ed_name';
        $fieldArrKeys[] = 'B.zone_id as ed_zone_id';
        $fieldArrKeys[] = 'B.building_id as ed_building_id';
        $where[] = ['B.zone_id','>','0'];
        $rs = $this->zone->select($fieldArrKeys)
            ->leftJoin('assets_building as A','A.zone_id','=','assets_zone.id')
            ->leftJoin($table.' as B','B.zone_id','=','assets_zone.id')
            ->where($where)
            ->whereNull('A.deleted_at')
            ->whereNull('B.deleted_at')
            ->get();
        if($rs){
//            return $rs->toArray();
            $build = array();
            $child = array();
            $tmpArr = array();
            foreach($rs as $k=>$v) {
                $id = isset($v['id']) ? $v['id'] : 0;
                $bid = isset($v['ab_id']) ? $v['ab_id'] : 0;
                $edid = isset($v['ed_id']) ? $v['ed_id'] : 0;
                $edbid = isset($v['ed_building_id']) ? $v['ed_building_id'] : 0;
                $tmpArr[$id]['id'] = $id;
                $tmpArr[$id]['name'] = $v['name'];
                if (!isset($build[$id][$bid])) {
                    $ed_name = isset($v['ab_name']) ? $v['ab_name'] : '';
                    if(!$bid) {
                        $ed_name = '其它';
                    }
                    $build[$id][$bid]['id'] = $bid;
                    $build[$id][$bid]['name'] = $ed_name;
                    $build[$id][$bid]['pid'] = $id;
                } else {
                    $ed_name = isset($v['ab_name']) ? $v['ab_name'] : '';
                    if(!$bid) {
                        $ed_name = '其它';
                    }
                    $build[$id][$bid]['id'] = $bid;
                    $build[$id][$bid]['name'] = $ed_name;
                    $build[$id][$bid]['pid'] = $id;
                }


                if ($edbid) {
                    if (!isset($child[$id][$edbid][$edid])) {
                        $child[$id][$edbid][$edid]['id'] = $edid;
                        $child[$id][$edbid][$edid]['name'] = $v['ed_name'];
                        $child[$id][$edbid][$edid]['pid'] = $edbid;
                        $child[$id][$edbid][$edid]['ppid'] = $id;
                    } else {
                        $child[$id][$edbid][$edid]['id'] = $edid;
                        $child[$id][$edbid][$edid]['name'] = $v['ed_name'];
                        $child[$id][$edbid][$edid]['pid'] = $edbid;
                        $child[$id][$edbid][$edid]['ppid'] = $id;
                    }
                }else{
                    if (!isset($child[$id][0][$edid])) {
                        $child[$id][0][$edid]['id'] = $edid;
                        $child[$id][0][$edid]['name'] = $v['ed_name'];
                        $child[$id][0][$edid]['pid'] = $edbid;
                        $child[$id][0][$edid]['ppid'] = $id;
                    }else{
                        $child[$id][0][$edid]['id'] = $edid;
                        $child[$id][0][$edid]['name'] = $v['ed_name'];
                        $child[$id][0][$edid]['pid'] = $edbid;
                        $child[$id][0][$edid]['ppid'] = $id;
                    }
                }
            }

//            return $build;
//            return $child;
//            var_dump($child);exit;

            foreach($tmpArr as $k=>$v){
                $tmpArr[$k]['children'] = isset($build[$k]) ? $build[$k] : array();
            }

//            return $tmpArr;
            foreach($tmpArr as $k=>$v){
                foreach($v['children'] as $kk=>$vv){
                    if($kk != '') {
                        $tmpArr[$k]['children'][$kk]['children'] = isset($child[$k][$kk]) ? $child[$k][$kk] : array();
                    }else{
                        $tmpArr[$k]['children'][0]['children'] = isset($child[$k][0]) ? $child[$k][0] : array();
                    }
                }
                $other = isset($child[$k][0]) ? $child[$k][0] : array();

                if($other) {
                    $ppid = '';
                    foreach ($other as $v) {
                        $ppid = isset($v['ppid']) && $v['ppid'] ? $v['ppid'] : 0;
                    }

                    if ($ppid == $k) {
                        $tmpArr[$k]['children'][0]['id'] = 0;
                        $tmpArr[$k]['children'][0]['name'] = '其它';
                        $tmpArr[$k]['children'][0]['pid'] = $k;
                        $tmpArr[$k]['children'][0]['children'] = isset($child[$k][0]) ? $child[$k][0] : array();
                    }
                }
            }

            $result = array_values($tmpArr);
            foreach($result as $k=>$v){
                if(isset($v['children'])){
                    $childArr = $v['children'];
//                    $childArr = array_values($v['children']);
//                    $result[$k]['children'] = $childArr;
                    foreach ($childArr as $kk => $vv) {
                        $children = isset($result[$k]['children'][$kk]['children']) ? $result[$k]['children'][$kk]['children'] : '';
                        if ($children) {
                            $result[$k]['children'][$kk]['children'] = array_values($result[$k]['children'][$kk]['children']);
                        } else {
                            unset($result[$k]['children'][$kk]);
                        }
                    }
                    //unset后重新获取数据把key从0开始
                    $result[$k]['children'] = array_values($result[$k]['children']);
                }

            }
        }
//        var_dump($rs->toArray());
        return $result;

    }


    public function _getAssetsForm($input=array(),$fieldCateoryIds=array()){
        $type = isset($input['type']) ? strtolower($input['type']):'';
        $category = isset($input['category']) ? $input['category']:'';
        $category = $category ? array_filter(array_unique(explode(',',$category))) : array();
        $fieldArr = array('er'=>'area','dt'=>'department');
        $field = isset($fieldArr[$type]) ? $fieldArr[$type] : '';

        if(!$field || !$type){
            Code::setCode(Code::ERR_PARAMS,'参数不正确');
            return false;
        }

        if(!$fieldCateoryIds) {
            $fieldCateoryIds = $this->getCategoryByField($field);
        }
        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "location";
        $fieldArrKeys[] = "officeBuilding";
        $fieldArrKeys[] = "area";
        $fieldArrKeys[] = "department";
        $where[] = [$field,'>',0];
        $where[] = ['location','>',0];
        $model = $this->model->select($fieldArrKeys)
            ->where($where)
            ->groupBy($field);
        if($fieldCateoryIds){
            $model->whereIn('sub_category_id',$fieldCateoryIds);
        }
        if($category){
            $model->whereIn($field,$category);
        }
        $pageRes = $this->usePage($model,['location','officeBuilding'],['asc','asc']);
//        $pageRes = $pageRes ? $pageRes->toArray() : array();
        $areaArr = array();
        if($pageRes) {
            foreach ($pageRes as $v) {
                $adVal = isset($v[$field]) ? $v[$field] : 0;
                if ($adVal) {
                    $areaArr[] = $adVal;
                }
            }
        }
//        var_dump($fieldCateoryIds);exit;
//        return $fieldCateoryIds;
//        var_dump($fieldCateoryIds->toArray());exit;


//        var_dump($areaArr);//exit;
        $areaArr = $areaArr ? array_filter(array_unique($areaArr)) : '';
        if($areaArr) {
            $fieldArrKeys[] = "category_id";
            $fieldArrKeys[] = "sub_category_id";
            $fieldArrKeys[] = "name";
            $fieldArrKeys[] = "state";
            $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=0 THEN 1 ELSE 0 END) AS state0");//"state as state0";//
            $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=1 THEN 1 ELSE 0 END) AS state1");//"state as state1";//
            $sub = $this->device->select($fieldArrKeys)
                ->where($where)
                ->whereIn($field, $areaArr)
                ->groupBy([$field, 'sub_category_id'])
                ->orderBy('location','desc')
                ->orderBy('officeBuilding','desc')
//                ->orderBy($field,'desc')
                ->get();
//            var_dump($sub);exit;
            $subRes = $sub ? $sub->toArray() : array();

            $deviceArr = array();
            $categoryArr = $this->getCategoryIdName();//exit;
            $categoryList = array();
//            var_dump($categoryArr);exit;
            if ($subRes) {
                foreach ($subRes as $v) {
                    $adVal = isset($v[$field]) ? $v[$field] : 0;
                    $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
                    $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
                    $state0 = isset($v['state0']) ? $v['state0'] : 0;
                    $state1 = isset($v['state1']) ? $v['state1'] : 0;
                    $sub_category_name = $categoryArr[$sub_category_id] ? $categoryArr[$sub_category_id] : '';
                    if(!isset($categoryList[$category_id])){
                        $categoryList[$category_id]['category_id'] = $category_id;
                        $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                            'sub_category_id' => $sub_category_id,
                            'sub_category_name' => $sub_category_name,
                            'state0' => '',
                            'state1' => '');
                    }else{
                        $categoryList[$category_id]['category_id'] = $category_id;
                        $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                            'sub_category_id' => $sub_category_id,
                            'sub_category_name' => $sub_category_name,
                            'state0' => '',
                            'state1' => '');
                    }


                    if (!isset($deviceArr[$adVal])) {
                        if (!isset($deviceArr[$adVal][$category_id])) {
                            if (!isset($deviceArr[$adVal][$category_id][$sub_category_id])) {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        } else {
                            if (!isset($deviceArr[$adVal][$category_id][$sub_category_id])) {
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        }
                    } else {
                        if (!isset($deviceArr[$adVal][$category_id])) {
                            if (!isset($deviceArr[$adVal][$category_id][$sub_category_id])) {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        } else {
                            if (!isset($deviceArr[$adVal][$category_id][$sub_category_id])) {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$adVal][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$adVal][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        }
                    }
                }
            }

//            return $categoryList;

//        var_dump($deviceArr);exit;
//        return $deviceArr;
            $tmpDevice = array();


            if ($deviceArr) {
                foreach ($deviceArr as $k => $val) {
//                    $keys = array_keys($val);
                    if($categoryList) {
//                        var_dump($val);exit;
                        $cateArr = array_diff_key($categoryList, $val);
                        $val = array_merge($val, $cateArr);
                    }
                    $i = 0;
//                    var_dump($val);exit;
                    foreach ($val as $kk => $vv) {
                        $kkcid = isset($vv['category_id'])?$vv['category_id'] : '';
//                var_dump($vv);exit;
                        if($categoryList && $kkcid) {
                            $cateArr = array_diff_key($categoryList[$kkcid], $vv);
                            $vv = array_merge($vv, $cateArr);
                        }
                        $category_id = isset($vv['category_id']) ? $vv['category_id'] : 0;
                        $tmpDevice[$k][$i]['id'] = $category_id;
                        $tmpDevice[$k][$i]['name'] = isset($categoryArr[$category_id]) ? $categoryArr[$category_id] : 0;

                        $j = 0;
                        foreach ($vv as $kj => $vj) {
                            if (is_array($vj)) {
//                        var_dump($kj,$vj);exit;
                                $tmpDevice[$k][$i]['sub_category'][$j] = $vj;
                                $j++;
                            }
                        }

                        $i++;
                    }

                }
            }

//        return $tmpDevice;
            foreach ($pageRes as $k => $v) {
                $adVal = isset($v[$field]) ? $v[$field] : 0;
                if (isset($tmpDevice[$adVal])) {
                    $pageRes[$k]['categorys'] = $tmpDevice[$adVal];
                }
            }
        }
//        var_dump($pageRes);exit;
//        var_dump($sub->toArray());exit;
        return $pageRes;
//        return $this->usePage($model);

    }


    /**
     * 资产报表
     * @param array $input
     * @param array $fieldCateoryIds
     * @return mixed
     */
    /*public function _getAssetsFormNew($input=array(),$fieldCateoryIds=array()){
        $type = isset($input['type']) ? strtolower($input['type']):'';
        $category = isset($input['category']) ? $input['category']:'';
        $bid = isset($input['building']) ? $input['building']:'';
        $lid = isset($input['zone']) ? $input['zone']:'';
        $category = $category ? array_filter(array_unique(explode(',',$category))) : array();
        $bids = $bid ? array_filter(array_unique(explode(',',$bid))) : array();
        $lids = $lid ? array_filter(array_unique(explode(',',$lid))) : array();
        $fieldArr = array('er'=>'area','dt'=>'department');
        $field = isset($fieldArr[$type]) ? $fieldArr[$type] : '';
        $tbl = 'assets_device';
        $dateRes = array();

        if(!$field || !$type){
            Code::setCode(Code::ERR_PARAMS,'参数不正确');
            return false;
        }

        if(!$fieldCateoryIds) {
            $fieldCateoryIds = $this->getCategoryByField($field);
        }
//        var_dump($fieldCateoryIds);exit;
        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "location";
        $where[] = ['location','>',0];


        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";
        $fieldArrKeys[] = "name";
        $fieldArrKeys[] = "state";
        $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=0 THEN 1 ELSE 0 END) AS state0");//"state as state0";//
        $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=1 THEN 1 ELSE 0 END) AS state1");//"state as state1";//
        $fieldArrKeys[] = "deleted_at";

        $models = array();
        $fieldKey = DB::raw("'0' as `$field`");
        if($lids) {
            $lFieldArrKeys = $fieldArrKeys;
            $lFieldArrKeys[] = DB::raw("'0' as `officeBuilding`");
            $lFieldArrKeys[] = $fieldKey;
            $lsql = DB::table($tbl)->select($lFieldArrKeys)->where($where)
                ->whereIn('location',$lids)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->groupBy( ['location','sub_category_id']);
//                    ->whereIn('sub_category_id',$fieldCateoryIds);
//                return $lsql;
            $models[] = $lsql;
        }
        if($bids){
            $BFieldArrKeys = $fieldArrKeys;
            $BFieldArrKeys[] = 'officeBuilding';
            $BFieldArrKeys[] = $fieldKey;
            $bsql = DB::table($tbl)->select($BFieldArrKeys)->where($where)
                ->whereIn('officeBuilding',$bids)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->groupBy( ['officeBuilding','sub_category_id']);

            $models[] = $bsql;
        }

        $fieldArrKeys[] = 'officeBuilding';
        $fieldArrKeys[] = $field;


        if($category){
            $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereIn($field,$category)
                ->groupBy( [$field,'sub_category_id']);
//            $csql->whereIn($field,$category);
            $models[] = $csql;
        }
        if(!$category && !$lids && !$bids){
            $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->groupBy( [$field,'sub_category_id']);
//            $csql->whereIn($field,$category);
            $models[] = $csql;
        }



        if($models) {
            foreach ($models as $k => &$model) {
                if ($k > 0) {
                    $models[0]->union($model);
                }
            }


            $modelunion = $this->model->with("sub_category", "category", "monitor", "events", "emdevice")
                ->from(DB::raw("({$models[0]->toSql()}) as assets_device"))
                ->mergeBindings($models[0])->select(["assets_device.*"]);


            $dateRes = $this->usePage($modelunion, ['location', 'officeBuilding'], ['asc', 'desc']);
        }

        $deviceArr = array();
        $categoryArr = $this->getCategoryIdName();//exit;
        $categoryList = array();
//            var_dump($categoryArr);exit;
        if ($dateRes) {
            $zbcKeyArr = array();
            foreach ($dateRes as $v) {
                $adVal = isset($v[$field]) ? $v[$field] : 0;
                $zone = isset($v['location']) ? $v['location'] : 0;
                $building = isset($v['officeBuilding']) ? $v['officeBuilding'] : 0;
                $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
                $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
                $zbcKey = $zone.'_'.$building.'_'.$adVal;
                $zbcccKey = $zbcKey.'_'.$category_id.'_'.$sub_category_id;
                $zbcKeyArr[] = $zbcKey;
                $state0 = isset($v['state0']) ? $v['state0'] : 0;
                $state1 = isset($v['state1']) ? $v['state1'] : 0;
                $sub_category_name = $categoryArr[$sub_category_id] ? $categoryArr[$sub_category_id] : '';
                if(!isset($categoryList[$category_id])){
                    $categoryList[$category_id]['category_id'] = $category_id;
                    $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                        'sub_category_id' => $sub_category_id,
                        'sub_category_name' => $sub_category_name,
                        'state0' => '',
                        'state1' => '');
                }else{
                    $categoryList[$category_id]['category_id'] = $category_id;
                    $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                        'sub_category_id' => $sub_category_id,
                        'sub_category_name' => $sub_category_name,
                        'state0' => '',
                        'state1' => '');
                }


                if (!isset($deviceArr[$zbcccKey])) {
                    if (!isset($deviceArr[$zbcccKey][$category_id])) {
                        if (!isset($deviceArr[$zbcccKey][$category_id][$sub_category_id])) {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        } else {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        }
                    } else {
                        if (!isset($deviceArr[$zbcccKey][$category_id][$sub_category_id])) {
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        } else {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        }
                    }
                } else {
                    if (!isset($deviceArr[$zbcccKey][$category_id])) {
                        if (!isset($deviceArr[$zbcccKey][$category_id][$sub_category_id])) {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        } else {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        }
                    } else {
                        if (!isset($deviceArr[$zbcccKey][$category_id][$sub_category_id])) {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        } else {
                            $deviceArr[$zbcccKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                            $deviceArr[$zbcccKey][$category_id][$sub_category_id] = array(
                                'category_id' => $category_id,
                                'sub_category_id' => $sub_category_id,
                                'sub_category_name' => $sub_category_name,
                                'state0' => $state0,
                                'state1' => $state1,
                            );
                        }
                    }
                }
            }
        }

//            return $categoryList;

//        var_dump($deviceArr);exit;
//        return $deviceArr;
        $tmpDevice = array();


        if ($deviceArr) {
            foreach ($deviceArr as $k => $val) {
//                    $keys = array_keys($val);
                if($categoryList) {
//                        var_dump($val);exit;
                    $cateArr = array_diff_key($categoryList, $val);
                    $val = array_merge($val, $cateArr);
                }
                $i = 0;
//                    var_dump($val);exit;
                foreach ($val as $kk => $vv) {
                    $kkcid = isset($vv['category_id'])?$vv['category_id'] : '';
//                var_dump($vv);exit;
                    if($categoryList && $kkcid) {
                        $cateArr = array_diff_key($categoryList[$kkcid], $vv);
                        $vv = array_merge($vv, $cateArr);
                    }
                    $category_id = isset($vv['category_id']) ? $vv['category_id'] : 0;
                    $tmpDevice[$k][$i]['id'] = $category_id;
                    $tmpDevice[$k][$i]['name'] = isset($categoryArr[$category_id]) ? $categoryArr[$category_id] : 0;

                    $j = 0;
                    foreach ($vv as $kj => $vj) {
                        if (is_array($vj)) {
//                        var_dump($kj,$vj);exit;
                            $tmpDevice[$k][$i]['sub_category'][$j] = $vj;
                            $j++;
                        }
                    }

                    $i++;
                }

            }
        }

        foreach ($dateRes as $k => $v) {
            $adVal = isset($v[$field]) ? intval($v[$field]) : 0;
            $zone = isset($v['location']) ? intval($v['location']) : 0;
            $building = isset($v['officeBuilding']) ? intval($v['officeBuilding']) : 0;
            $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
            $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
            $zbcKey = $zone.'_'.$building.'_'.$adVal.'_'.$category_id.'_'.$sub_category_id;
            if (isset($tmpDevice[$zbcKey])) {
                $dateRes[$k]['categorys'] = $tmpDevice[$zbcKey];
            }
        }

        return $dateRes;

    }*/



    /**
     * 资产报表
     * @param array $input
     * @param array $fieldCateoryIds
     * @return mixed
     */
    public function getAssetsFormBak($input=array(),$fieldCateoryIds=array()){
        $type = isset($input['type']) ? strtolower($input['type']):'';
        $category = isset($input['category']) ? $input['category']:'';
        $bid = isset($input['building']) ? $input['building']:'';
        $lid = isset($input['zone']) ? $input['zone']:'';
        $category = $category ? array_filter(array_unique(explode(',',$category))) : array();
        $bids = $bid ? array_filter(array_unique(explode(',',$bid))) : array();
        $lids = $lid ? array_filter(array_unique(explode(',',$lid))) : array();
        $fieldArr = array('er'=>'area','dt'=>'department');
        $field = isset($fieldArr[$type]) ? $fieldArr[$type] : '';
        $tbl = 'assets_device';
        $dateRes = array();

        if(!$field || !$type){
            Code::setCode(Code::ERR_PARAMS,'参数不正确');
            return false;
        }

        if(!$fieldCateoryIds) {
            $fieldCateoryIds = $this->getCategoryByField($field);
        }
//        var_dump($fieldCateoryIds);exit;
        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "location";
        $where[] = ['location','>',0];


        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";
        $fieldArrKeys[] = "name";
        $fieldArrKeys[] = "state";
        $fieldArrKeys[] = "deleted_at";

        $models = array();
        $fieldKey = DB::raw("'0' as `$field`");
        if($lids) {
            $lFieldArrKeys = $fieldArrKeys;
            $lFieldArrKeys[] = $fieldKey;
            $lFieldArrKeys[] = DB::raw("'0' as `officeBuilding`");
            $lsql = DB::table($tbl)->select($lFieldArrKeys)->where($where)
                ->whereIn('location',$lids)
                ->whereIn('sub_category_id',$fieldCateoryIds)->groupBy( ['location']);
//                ->groupBy( ['location','sub_category_id']);
//                    ->whereIn('sub_category_id',$fieldCateoryIds);
//                return $lsql;
            $models[] = $lsql;
        }
        if($bids){
            $BFieldArrKeys = $fieldArrKeys;
            $BFieldArrKeys[] = 'officeBuilding';
            $BFieldArrKeys[] = $fieldKey;
            $bsql = DB::table($tbl)->select($BFieldArrKeys)->where($where)
                ->whereIn('officeBuilding',$bids)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->groupBy( ['officeBuilding']);
//                ->groupBy( ['officeBuilding','sub_category_id']);

            $models[] = $bsql;
        }

        $fieldArrKeys[] = 'officeBuilding';
        $fieldArrKeys[] = $field;


        if($category){
            $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereIn($field,$category)
                ->groupBy( [$field]);
//                ->groupBy( [$field,'sub_category_id']);
//            $csql->whereIn($field,$category);
            $models[] = $csql;
        }
        if(!$category && !$lids && !$bids){
            $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                ->whereIn('sub_category_id',$fieldCateoryIds)->groupBy( [$field]);
//                ->groupBy( [$field,'sub_category_id']);
//            $csql->whereIn($field,$category);
            $models[] = $csql;
        }



        if($models) {
            foreach ($models as $k => &$model) {
                if ($k > 0) {
                    $models[0]->union($model);
                }
            }


            $modelunion = $this->model->with("sub_category", "category", "monitor", "events", "emdevice")
                ->from(DB::raw("({$models[0]->toSql()}) as assets_device"))
                ->mergeBindings($models[0])->select(["assets_device.*"]);


            $dateRes = $this->usePage($modelunion, ['location', 'officeBuilding'], ['asc', 'desc']);
        }

//        return $dateRes;

        $deviceArr = array();
        $categoryArr = $this->getCategoryIdName();//exit;
        $categoryList = array();
//            var_dump($categoryArr);exit;
        if ($dateRes) {
            $zbcKeyArr = array();
            foreach ($dateRes as $v) {
                $adVal = isset($v[$field]) ? $v[$field] : 0;
                $zone = isset($v['location']) ? $v['location'] : 0;
                $building = isset($v['officeBuilding']) ? $v['officeBuilding'] : 0;
                $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
                $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
                $zbcKey = $zone.'_'.$building.'_'.$adVal;
                $zbcccKey = $zbcKey.'_'.$category_id.'_'.$sub_category_id;
                $zbcKeyArr[] = $zbcKey;

            }
//            return $dateRes;

//            return $zbcKeyArr;


            $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=0 THEN 1 ELSE 0 END) AS state0");//"state as state0";//
            $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=1 THEN 1 ELSE 0 END) AS state1");//"state as state1";//
            unset($models);
            $models = array();
//            var_dump($BFieldArrKeys);exit;
//            $fieldKey = DB::raw("'0' as `$field`");
            if($lids) {
                $lfieldArrKeys = $fieldArrKeys;
                $lfieldArrKeys[] = DB::raw("CONCAT_WS('_',IFNULL(location,0),0,0) as loc");
                $lsql = DB::table($tbl)->select($lfieldArrKeys)->where($where)
                    ->whereIn('location',$lids)
                    ->whereIn('sub_category_id',$fieldCateoryIds)
                    ->whereIn(DB::raw("CONCAT_WS('_',IFNULL(location,0),0,0)"),$zbcKeyArr)
                    ->groupBy( ['location','sub_category_id']);
//                    ->whereIn('sub_category_id',$fieldCateoryIds);
//                return $lsql;
                $models[] = $lsql;
            }
            if($bids){
                $bfieldArrKeys = $fieldArrKeys;
                $bfieldArrKeys[] = DB::raw("CONCAT_WS('_',IFNULL(location,0),IFNULL(officeBuilding,0),0) as loc");
                $bsql = DB::table($tbl)->select($bfieldArrKeys)->where($where)
                    ->whereIn('officeBuilding',$bids)
                    ->whereIn('sub_category_id',$fieldCateoryIds)
                    ->whereIn(DB::raw("CONCAT_WS('_',location,officeBuilding,0)"),$zbcKeyArr)
                    ->groupBy( ['officeBuilding','sub_category_id']);

                $models[] = $bsql;
            }

//            $fieldArrKeys[] = 'officeBuilding';
//            $fieldArrKeys[] = $field;



            $fieldArrKeys[] = DB::raw("CONCAT_WS('_',IFNULL(location,0),IFNULL(officeBuilding,0),IFNULL(".$field.",0)) as loc");
            if($category){
                $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                    ->whereIn('sub_category_id',$fieldCateoryIds)
                    ->whereIn($field,$category)
                    ->whereIn(DB::raw("CONCAT_WS('_',location,officeBuilding,".$field.")"),$zbcKeyArr)
                    ->groupBy( [$field,'sub_category_id']);
//            $csql->whereIn($field,$category);
                $models[] = $csql;
            }
            if(!$category && !$lids && !$bids){
                $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                    ->whereIn('sub_category_id',$fieldCateoryIds)
                    ->whereIn(DB::raw("CONCAT_WS('_',location,officeBuilding,".$field.")"),$zbcKeyArr)
                    ->groupBy( [$field,'sub_category_id']);
//            $csql->whereIn($field,$category);
                $models[] = $csql;
            }



//            var_dump($models);exit;
            if($models) {
                foreach ($models as $k => &$model) {
                    if ($k > 0) {
                        $models[0]->union($model);
                    }
                }
//                return $models[0]->toSql();


                $cdata = $this->device->with("sub_category", "category", "monitor", "events", "emdevice")
                    ->from(DB::raw("({$models[0]->toSql()}) as assets_device"))
                    ->mergeBindings($models[0])->select(["assets_device.*"])
                    ->orderBy('location','asc')
                    ->orderBy('officeBuilding','desc')
                    ->get();
            }
//            return $cdata;

            if($cdata){
                $cArray = array();
                foreach($cdata as $k=>$v){
                    $zbcKey = isset($v['loc']) ? $v['loc'] : '';

                    $zone = isset($v['location']) ? $v['location'] : 0;
                    $building = isset($v['officeBuilding']) ? $v['officeBuilding'] : 0;
                    $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
                    $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
                    $state0 = isset($v['state0']) ? $v['state0'] : 0;
                    $state1 = isset($v['state1']) ? $v['state1'] : 0;



                    $sub_category_name = $categoryArr[$sub_category_id] ? $categoryArr[$sub_category_id] : '';
                    if(!isset($categoryList[$category_id])){
                        $categoryList[$category_id]['category_id'] = $category_id;
                        $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                            'sub_category_id' => $sub_category_id,
                            'sub_category_name' => $sub_category_name,
                            'state0' => '',
                            'state1' => '');
                    }else{
                        $categoryList[$category_id]['category_id'] = $category_id;
                        $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                            'sub_category_id' => $sub_category_id,
                            'sub_category_name' => $sub_category_name,
                            'state0' => '',
                            'state1' => '');
                    }


                    if (!isset($deviceArr[$zbcKey])) {
                        if (!isset($deviceArr[$zbcKey][$category_id])) {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        } else {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        }
                    } else {
                        if (!isset($deviceArr[$zbcKey][$category_id])) {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        } else {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        }
                    }




//                    unset($cdata[$k]);
                }
            }
//            var_dump($cArray);exit;
//            return $cArray;

        }

//            return $categoryList;

//        var_dump($deviceArr);exit;
//        return $deviceArr;
        $tmpDevice = array();


        if ($deviceArr) {
            foreach ($deviceArr as $k => $val) {
//                    $keys = array_keys($val);
                if($categoryList) {
//                        var_dump($val);exit;
                    $cateArr = array_diff_key($categoryList, $val);
                    $val = array_merge($val, $cateArr);
                }
                $i = 0;
//                    var_dump($val);exit;
                foreach ($val as $kk => $vv) {
                    $kkcid = isset($vv['category_id'])?$vv['category_id'] : '';
//                var_dump($vv);exit;
                    if($categoryList && $kkcid) {
                        $cateArr = array_diff_key($categoryList[$kkcid], $vv);
                        $vv = array_merge($vv, $cateArr);
                    }
                    $category_id = isset($vv['category_id']) ? $vv['category_id'] : 0;
                    $tmpDevice[$k][$i]['id'] = $category_id;
                    $tmpDevice[$k][$i]['name'] = isset($categoryArr[$category_id]) ? $categoryArr[$category_id] : 0;

                    $j = 0;
                    foreach ($vv as $kj => $vj) {
                        if (is_array($vj)) {
//                        var_dump($kj,$vj);exit;
                            $tmpDevice[$k][$i]['sub_category'][$j] = $vj;
                            $j++;
                        }
                    }

                    $i++;
                }

            }
        }

//        return $tmpDevice;
//        var_dump($zbcKeyArr,array_keys($tmpDevice));exit;
        $categoryDevice = $tmpDevice;
        $categorys = array_shift($tmpDevice);
        if($categorys){
            foreach($categorys as $k=>$v){
                $categorys[$k]['sub_category'][0]['state0'] = '';
                $categorys[$k]['sub_category'][0]['state1'] = '';
            }
        }
//        return $categoryDevice;

        foreach ($dateRes as $k => $v) {
            $adVal = isset($v[$field]) ? intval($v[$field]) : 0;
            $zone = isset($v['location']) ? intval($v['location']) : 0;
            $building = isset($v['officeBuilding']) ? intval($v['officeBuilding']) : 0;
//            $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
//            $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
            $zbcKey = $zone.'_'.$building.'_'.$adVal;
//            var_dump($zbcKey);
            if (isset($categoryDevice[$zbcKey])) {
                $dateRes[$k]['categorys'] = $categoryDevice[$zbcKey];
            }else{
                $dateRes[$k]['categorys'] = $categorys;
            }
        }
//exit;
        return $dateRes;

    }


    /**
     * 资产报表
     * @param array $input
     * @param array $fieldCateoryIds
     * @return mixed
     */
    public function getAssetsForm($input=array(),$fieldCateoryIds=array()){
        $type = isset($input['type']) ? strtolower($input['type']):'';
        $category = isset($input['category']) ? $input['category']:'';
        $bid = isset($input['building']) ? $input['building']:'';
        $lid = isset($input['zone']) ? $input['zone']:'';
        $category = $category ? array_filter(array_unique(explode(',',$category))) : array();
        $bids = $bid ? array_filter(array_unique(explode(',',$bid))) : array();
        $lids = $lid ? array_filter(array_unique(explode(',',$lid))) : array();
        $fieldArr = array('er'=>'area','dt'=>'department');
        $field = isset($fieldArr[$type]) ? $fieldArr[$type] : '';
        $tbl = 'assets_device';
        $dataRes = array();

        if(!$field || !$type){
            Code::setCode(Code::ERR_PARAMS,'参数不正确');
            return false;
        }

        if(!$fieldCateoryIds) {
            $fieldCateoryIds = $this->getCategoryByField($field);
        }
        //分页列表数据,不包含分类
        $dataRes = $this->getCategoryDevice($input,$fieldCateoryIds);

//        return $dataRes;

        $deviceArr = array();
        $categoryArr = $this->getCategoryIdName();//exit;
        $categoryList = array();

//            var_dump($categoryArr);exit;
        if ($dataRes) {
            $zbcKeyArr = array();
            foreach ($dataRes as $v) {
                $adVal = isset($v[$field]) ? $v[$field] : 0;
                $zone = isset($v['location']) ? $v['location'] : 0;
                $building = isset($v['officeBuilding']) ? $v['officeBuilding'] : 0;
                $zbcKey = $zone.'_'.$building.'_'.$adVal;
                $zbcKeyArr[] = $zbcKey;

            }


            //根据使用地，楼，科室获取分类数据(计算数据)
//            dd($input,$fieldCateoryIds,$zbcKeyArr);
            $cdata = $this->getCategoryDevice($input,$fieldCateoryIds,$zbcKeyArr,false);
//            return $cdata;

            if($cdata){
                //排列数组
                foreach($cdata as $k=>$v){
                    $zbcKey = isset($v['loc']) ? $v['loc'] : '';
                    /*if(!isset($cArray[$zbcKey])) {
                        $cArray[$zbcKey][] = $v;
                    }else{
                        $cArray[$zbcKey][] = $v;
                    }*/

                    $zone = isset($v['location']) ? $v['location'] : 0;
                    $building = isset($v['officeBuilding']) ? $v['officeBuilding'] : 0;
                    $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
                    $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
                    $state0 = isset($v['state0']) ? $v['state0'] : 0;
                    $state1 = isset($v['state1']) ? $v['state1'] : 0;



                    $sub_category_name = $categoryArr[$sub_category_id] ? $categoryArr[$sub_category_id] : '';
                    if(!isset($categoryList[$category_id])){
                        $categoryList[$category_id]['category_id'] = $category_id;
                        $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                            'sub_category_id' => $sub_category_id,
                            'sub_category_name' => $sub_category_name,
                            'state0' => '',
                            'state1' => '');
                    }else{
                        $categoryList[$category_id]['category_id'] = $category_id;
                        $categoryList[$category_id][$sub_category_id] = array('category_id'=> $category_id,
                            'sub_category_id' => $sub_category_id,
                            'sub_category_name' => $sub_category_name,
                            'state0' => '',
                            'state1' => '');
                    }


                    if (!isset($deviceArr[$zbcKey])) {
                        if (!isset($deviceArr[$zbcKey][$category_id])) {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        } else {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        }
                    } else {
                        if (!isset($deviceArr[$zbcKey][$category_id])) {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        } else {
                            if (!isset($deviceArr[$zbcKey][$category_id][$sub_category_id])) {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            } else {
                                $deviceArr[$zbcKey][$category_id]['category_id'] = $category_id;
//                                $deviceArr[$adVal][$category_id]['category_name'] = $category_id;
                                $deviceArr[$zbcKey][$category_id][$sub_category_id] = array(
                                    'category_id' => $category_id,
                                    'sub_category_id' => $sub_category_id,
                                    'sub_category_name' => $sub_category_name,
                                    'state0' => $state0,
                                    'state1' => $state1,
                                );
                            }
                        }
                    }
                }
            }

        }


        $tmpDevice = array();


        if ($deviceArr) {
            foreach ($deviceArr as $k => $val) {
                if($categoryList) {
                    $cateArr = array_diff_key($categoryList, $val);
                    $val = array_merge($val, $cateArr);
                }
                $i = 0;
                foreach ($val as $kk => $vv) {
                    $kkcid = isset($vv['category_id']) ? $vv['category_id'] : '';
                    if ($categoryList && $kkcid) {
                        $cateArr = array_diff_key($categoryList[$kkcid], $vv);
                        $vv = array_merge($vv, $cateArr);
                    }
                    $category_id = isset($vv['category_id']) ? $vv['category_id'] : 0;
                    $tmpDevice[$k][$i]['id'] = $category_id;
                    $tmpDevice[$k][$i]['name'] = isset($categoryArr[$category_id]) ? $categoryArr[$category_id] : 0;

                    $j = 0;
                    foreach ($vv as $kj => $vj) {
                        if (is_array($vj)) {
                            $tmpDevice[$k][$i]['sub_category'][$j] = $vj;
                            $j++;
                        }
                    }

                    $i++;
                }
            }
        }

//        var_dump($zbcKeyArr,array_keys($tmpDevice));exit;
        //默认配置
        $categoryDevice = $tmpDevice;
        $categorys = array_shift($tmpDevice);
        if($categorys){
            foreach($categorys as $k=>$v){
                $categorys[$k]['sub_category'][0]['state0'] = '';
                $categorys[$k]['sub_category'][0]['state1'] = '';
            }
        }
//        return $categoryDevice;

//        dd($dataRes);

        foreach ($dataRes as $k => $v) {
            $adVal = isset($v[$field]) ? intval($v[$field]) : 0;
            $zone = isset($v['location']) ? intval($v['location']) : 0;
            $building = isset($v['officeBuilding']) ? intval($v['officeBuilding']) : 0;
//            $category_id = isset($v['category_id']) ? $v['category_id'] : 0;
//            $sub_category_id = isset($v['sub_category_id']) ? $v['sub_category_id'] : 0;
            $zbcKey = $zone.'_'.$building.'_'.$adVal;
//            var_dump($zbcKey);
            if (isset($categoryDevice[$zbcKey])) {
                $dataRes[$k]['categorys'] = $categoryDevice[$zbcKey];
            }else{
                $dataRes[$k]['categorys'] = $categorys;
            }
        }

        return $dataRes;

    }

    /**
     * 获取资产根据站点的统计列表
     * @return mixed
     */
    public function getAssetsForLocation(){
        $model = $this->device->select('location')
            ->where('location','>',0)->groupBy('location');
        $data = $this->usePage($model,'location','asc');
        $template = [];
        $tmp_list = [];
        if($data){
            $fields = [
                'location',
                'sub_category_id',
                Db::raw("SUM(IF(state=0,1,0)) AS state0"),
                Db::raw("SUM(IF(state=1,1,0)) AS state1"),
                DB::raw("SUM(IF(state in (0,1),1,0)) AS total")
            ];
            $category_data = $this->device->select($fields)
                ->where('location','>',0)->groupBy(['location','sub_category_id'])->orderBy('location')->get();
            if($category_data){
                foreach($category_data as $key => $value){
                    $location = isset($value['location']) ? $value['location'] :0;
                    $sub_category_id = isset($value['sub_category_id']) ? $value['sub_category_id'] : 0;
                    $state0 = isset($value['state0']) ? $value['state0'] : 0;
                    $state1 = isset($value['state1']) ? $value['state1'] : 0;
                    $total = isset($value['total']) ? $value['total'] : 0;
                    if(!isset($template[$sub_category_id])){
                        $template[$sub_category_id] = [
                            'sub_category_id' => $value['sub_category_id'],
                            'sub_category_name' => $value['sub_category']['name'],
                            'state0' => '',
                            'state1' => '',
                            'total' => ''
                        ];
                    }

                    if(!isset($tmp_list[$location])){
                        $tmp_list[$location] = [];
                    }
                    $tmp_list[$location][$sub_category_id] = [
                        'sub_category_id' => $value['sub_category_id'],
                        'sub_category_name' => $value['sub_category']['name'],
                        'state0' => $state0,
                        'state1' => $state1,
                        'total' => $total
                    ];
                }
            }
        }
        $total_data = ['state0'=>0,'state1'=>0,'total'=>0];
        if($tmp_list){
            foreach($tmp_list as $k => &$v){
                $tmp_total = [
                    'sub_category_id' => 0,
                    'sub_category_name' => '设备统计',
                    'state0' => 0,
                    'state1' => 0,
                    'total' => 0,
                ];
                foreach($v as $kk => $vv){
                    $tmp_total['state0'] += intval($vv['state0']);
                    $tmp_total['state1'] += intval($vv['state1']);
                    $tmp_total['total'] += intval($vv['total']);
                    $total_data['state0'] += intval($vv['state0']);
                    $total_data['state1'] += intval($vv['state1']);
                    $total_data['total'] += intval($vv['total']);
                }
                if($template) {
                    $cateArr = array_diff_key($template, $v);
                    $v = array_merge($v, $cateArr);
                }
                array_unshift($v,$tmp_total);
            }
        }

        foreach($data as $key => $item){
            if(isset($tmp_list[$item['location']])){
                $data[$key]['categorys'] = $tmp_list[$item['location']];
            }else{
                $data[$key]['categorys'] = array_values($template);
            }
        }
        $GLOBALS['total'] = $total_data;
        return $data;
    }

    /**
     * 根据使用地，楼，科室获取分类设备数据
     * @param array $input
     * @param array $fieldCateoryIds
     * @param array $zbcKeyArr
     * @param bool $isList
     * @return array|mixed
     */
    public function getCategoryDevice($input=array(),$fieldCateoryIds=array(),$zbcKeyArr=array(),$isList=true){
        $dataRes = array();
        $type = isset($input['type']) ? strtolower($input['type']):'';
        $category = isset($input['category']) ? $input['category']:'';
        $bid = isset($input['building']) ? $input['building']:'';
        $lid = isset($input['zone']) ? $input['zone']:'';
        $category = $category ? array_filter(array_unique(explode(',',$category))) : array();
        $bids = $bid ? array_filter(array_unique(explode(',',$bid))) : array();
        $lids = $lid ? array_filter(array_unique(explode(',',$lid))) : array();
        $fieldArr = array('er'=>'area','dt'=>'department');
        $field = isset($fieldArr[$type]) ? $fieldArr[$type] : '';
        $tbl = 'assets_device';

        if(!$fieldCateoryIds) {
            $fieldCateoryIds = $this->getCategoryByField($field);
        }

        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "location";
        $where[] = ['location','>',0];


        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";
        $fieldArrKeys[] = "name";
        $fieldArrKeys[] = "state";
        $fieldArrKeys[] = "deleted_at";

        $fieldKey = DB::raw("'0' as `$field`");


        if(!$isList) {
            $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=0 THEN 1 ELSE 0 END) AS state0");//"state as state0";//
            $fieldArrKeys[] = DB::raw("SUM(CASE WHEN state=1 THEN 1 ELSE 0 END) AS state1");//"state as state1";//
        }
//        unset($models);
        $models = array();
        if($lids) {
            $lfieldArrKeys = $fieldArrKeys;
            if($isList){
                $lfieldArrKeys[] = $fieldKey;
                $lfieldArrKeys[] = DB::raw("'0' as `officeBuilding`");
            }else {
                $lfieldArrKeys[] = $field;
                $lfieldArrKeys[] = 'officeBuilding';
                $lfieldArrKeys[] = DB::raw("CONCAT_WS('_',IFNULL(location,0),0,0) as loc");
            }
            $lsql = DB::table($tbl)->select($lfieldArrKeys)->where($where)
                ->whereIn('location',$lids)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereNull('deleted_at');

            if($isList){
                $lsql->groupBy( ['location']);
            }else{
                $lsql->whereIn(DB::raw("CONCAT_WS('_',IFNULL(location,0),0,0)"),$zbcKeyArr)
                    ->groupBy( ['location','sub_category_id']);
            }

            $models[] = $lsql;
        }
//            var_dump($BFieldArrKeys);exit;
//            $fieldKey = DB::raw("'0' as `$field`");
        if($bids){
            $bfieldArrKeys = $fieldArrKeys;

            if($isList){
                $bfieldArrKeys[] = $fieldKey;
                $bfieldArrKeys[] = 'officeBuilding';
            }else {
                $bfieldArrKeys[] = $field;
                $bfieldArrKeys[] = 'officeBuilding';
                $bfieldArrKeys[] = DB::raw("CONCAT_WS('_',IFNULL(location,0),IFNULL(officeBuilding,0),0) as loc");
            }
            $bsql = DB::table($tbl)->select($bfieldArrKeys)->where($where)
                ->whereIn('officeBuilding',$bids)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereNull('deleted_at');


            if($isList){
                $bsql->groupBy( ['officeBuilding']);
            }else{
                $bsql->whereIn(DB::raw("CONCAT_WS('_',IFNULL(location,0),IFNULL(officeBuilding,0),0)"),$zbcKeyArr)
                    ->groupBy( ['officeBuilding','sub_category_id']);
            }

            $models[] = $bsql;
        }

        $fieldArrKeys[] = $field;
        $fieldArrKeys[] = 'officeBuilding';
        if((!$category && !$lids && !$bids) || $category){
            if(!$isList){
                $fieldArrKeys[] = DB::raw("CONCAT_WS('_',IFNULL(location,0),IFNULL(officeBuilding,0),IFNULL(".$field.",0)) as loc");
            }
            $csql = DB::table($tbl)->select($fieldArrKeys)->where($where)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereIn('sub_category_id',$fieldCateoryIds)
                ->whereNull('deleted_at');

            if($category){
                $csql->whereIn($field,$category);
            }
            if($isList){
                $csql->groupBy( [$field]);
            }else{
                $csql->whereIn(DB::raw("CONCAT_WS('_',IFNULL(location,0),IFNULL(officeBuilding,0),IFNULL(".$field.",0))"),$zbcKeyArr)
                    ->groupBy( [$field,'sub_category_id']);
            }
            $models[] = $csql;
        }



//            var_dump($models);exit;
        if($models) {
            foreach ($models as $k => &$model) {
                if ($k > 0) {
                    $models[0]->union($model);
                }
            }
//                return $models[0]->toSql();

            $modelunion = $this->device->with("sub_category", "category", "monitor", "events", "emdevice")
                ->from(DB::raw("({$models[0]->toSql()}) as assets_device"))
                ->mergeBindings($models[0])->select(["assets_device.*"]);

            if($isList){
                $dataRes = $this->usePage($modelunion, ['location', 'officeBuilding'], ['asc', 'desc']);
            }else{
                $dataRes = $modelunion->orderBy('location','asc')
                    ->orderBy('officeBuilding','desc')->get();
            }
        }
        return $dataRes;
    }


    /**
     * 获取所有的key=>val(id=>name)
     * @return mixed
     */
    public function getCategoryIdName(){
        $res = $this->category->get()->pluck('name','id')->all();
        return $res;
    }


    public function getCategoryByField($field=''){
        $result = array();
        if($field) {
            $where = array('A.field_sname'=>$field);
            $result = $this->categoryfieldsModel
                ->leftJoin('assets_fields as A', 'A.id', '=', 'assets_category_fields.field_id')
                ->where($where)
                ->get()
                ->pluck('category_id');
        }
        return $result;
    }


    /**
     * 机房或科室资产大小分类
     * @param array $categories
     * @return array
     */
    public function getCategoryList($categories=array()){
        $data = $this->category->getList($categories)->groupBy("id")->all();
        $result = [];
        if($data) {
            foreach ($data as $key => $value) {
                $sub = [];
                foreach ($value as $v) {
                    $sub[] = [
                        "category" => $v->cname,
                        "categoryId" => $v->cid,
                        "shortname" => $v->cshortname,
                        "icon" => $v->cicon,
                    ];
                }
                $result[] = [
                    "category" => $value[0]->name,
                    "categoryId" => $key,
                    "shortname" => $value[0]->shortname,
                    "icon" => $value[0]->icon,
                    "subCategory" => $sub,
                ];
            }
        }

        return $result;
    }


    /**
     * 首页机房配置中机房所在设备
     *
     * @param $request
     * @return mixed
     * @throws ApiException
     */
    public function getListByArea($request){
        $roomid = NULL !== $request->input("roomid") ? intval($request->input("roomid")) : 0;

        if($roomid <= 0){
            throw new ApiException(Code::ERR_PARAMS,["参数错误"]);
        }

        $device = $this->engineroom->findOrFail($roomid);

        if(empty($device)) {
            throw new ApiException(Code::ERR_PARAMS,["机房不存在 $roomid"]);
        }

        $data = $this->model->select("id AS assetid","number","name","area AS roomid")->where("area",$roomid)->orderBy("id","DESC")->get();

        return $data;

    }


    /**
     * 绑定已经有监控设备时搜索资产
     * @param array $input
     * @return mixed
     */
    public function searchMonitor($input=array(),$monitor=array()) {
        $model = $this->device;
        $limitCategories = '';

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
            if($limitCategories) {
                $model = $model->whereIn("sub_category_id", $limitCategories);
            }
        }

        $search = getKey($input,'s');//资产名称/资产编号/ip
        $p = getKey($input,'p');//位置/部门/(机房/科室)
        $zIds = $bIds = $eIds = $dIds = '';
        /*if($p){
            $where[] = ['name','like','%'.$p.'%'];
            $zone = Zone::where($where)->get();
            $building = Building::where($where)->get();
            $enginerroom = Engineroom::where($where)->get();
            $department = Department::where($where)->get();
            $zIds = $this->getIds($zone);
            $bIds = $this->getIds($building);
            $eIds = $this->getIds($enginerroom);
            $dIds = $this->getIds($department);
        }*/
//        var_dump($zIds,$bIds,$eIds,$dIds);exit;
        $limit = 10;
        $mAssetIds = array();

        if($monitor){
            foreach($monitor as $m){
                $mAssetId = isset($m['asset_id']) ? $m['asset_id'] : '';
                if($mAssetId){
                    $mAssetIds[] = $mAssetId;
                }
            }
        }
//        $mAssetIds = '';
        if($mAssetIds) {
            $model = $model->WhereNotIn("assets_device.id", $mAssetIds);
        }

//        $search = '';
        //优先精确查找
        $model = $model->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("assets_device.number", "=", $search );
                $query->orWhere("assets_device.name", "=", $search);
                $query->orWhere("assets_device.ip", "=", $search );
            }
        });
//        $zIds = '';
//        $bIds = '';
//        $eIds = '';
//        $dIds = '';

        /*if($zIds){
            if(($mAssetIds || $limitCategories) && !$search){
                $model = $model->WhereIn("location",$zIds );
            }else {
                $model = $model->orWhereIn("location", $zIds);
            }
        }
        if($bIds){
            if(!$zIds && ($mAssetIds || $limitCategories) && !$search){
                $model = $model->WhereIn("officeBuilding",$bIds );
            }else {
                $model = $model->orWhereIn("officeBuilding", $bIds);
            }
        }
        if($eIds){
            if(!$bIds && !$zIds && ($mAssetIds || $limitCategories) && !$search){
                $model = $model->WhereIn("area", $eIds);
            }else {
                $model = $model->orWhereIn("area", $eIds);
            }
        }
        if($dIds){
            if(!$bIds && !$zIds && ($mAssetIds || $limitCategories) && !$search){
                $model = $model->WhereIn("department",$dIds );
            }else{
                $model = $model->orWhereIn("department",$dIds );
            }
        }*/
//exit;

        $fieldArrKeys = $this->mDefaultFields;
        $fieldArrKeys[] = "assets_device.id";
        $fieldArrKeys[] = "assets_device.created_at";
        $fieldArrKeys[] = "assets_device.updated_at";
        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";
        $fieldArrKeys[] = "location";
        $fieldArrKeys[] = "officeBuilding";
        $fieldArrKeys[] = "department";
        $fieldArrKeys[] = "area";

        $model = $model->select($fieldArrKeys)
//            ->with("sub_category");
            ->with("sub_category","zone","office_building","engineroom","department");
        //优先精确查找
        $model = $model->leftjoin('assets_zone as A','A.id','=','assets_device.location')
            ->leftjoin('assets_building as B','B.id','=','assets_device.officeBuilding')
            ->leftjoin('assets_enginerooms as C','C.id','=','assets_device.area')
            ->leftjoin('assets_department as D','D.id','=','assets_device.department');
        $model = $model->where(function ($query) use ($p) {
            if (!empty($p)) {
                $query->orWhere("A.name", "=", $p );
                $query->orWhere("B.name", "=", $p);
                $query->orWhere("C.name", "=", $p );
                $query->orWhere("D.name", "=", $p );
            }
        });

        $result = $model->limit($limit)->get();
        if($result->count() === 0 ) {
            $model = $this->device;
            if($limitCategories) {
                $model = $model->whereIn("sub_category_id", $limitCategories);
            }

            if($mAssetIds) {
                $model = $model->WhereNotIn("assets_device.id", $mAssetIds);
            }

            $model = $model->where(function ($query) use ($search) {
                if (!empty($search)) {
                    $query->orWhere("assets_device.number", "like", "%" . $search . "%");
                    $query->orWhere("assets_device.name", "like", "%" . $search . "%");
                    $query->orWhere("assets_device.ip", "like", "%" . $search . "%");
                }
            });

            /*if($zIds){
                if(($mAssetIds || $limitCategories) && !$search){
                    $model = $model->WhereIn("location",$zIds );
                }else {
                    $model = $model->orWhereIn("location", $zIds);
                }
            }
            if($bIds){
                if(!$zIds && ($mAssetIds || $limitCategories) && !$search){
                    $model = $model->WhereIn("officeBuilding",$bIds );
                }else {
                    $model = $model->orWhereIn("officeBuilding", $bIds);
                }
            }
            if($eIds){
            if(!$bIds && !$zIds && ($mAssetIds || $limitCategories) && !$search){
                    $model = $model->WhereIn("area", $eIds);
                }else {
                    $model = $model->orWhereIn("area", $eIds);
                }
            }
            if($dIds){
                if(!$bIds && !$zIds && ($mAssetIds || $limitCategories) && !$search){
                    $model = $model->WhereIn("department",$dIds );
                }else{
                    $model = $model->orWhereIn("department",$dIds );
                }
            }*/


            $model = $model->select($fieldArrKeys)
//                ->with("sub_category");
                ->with("sub_category","zone","office_building","engineroom","department");
            $model = $model->leftjoin('assets_zone as A','A.id','=','assets_device.location')
                ->leftjoin('assets_building as B','B.id','=','assets_device.officeBuilding')
                ->leftjoin('assets_enginerooms as C','C.id','=','assets_device.area')
                ->leftjoin('assets_department as D','D.id','=','assets_device.department');
            $model = $model->where(function ($query) use ($p) {
                if (!empty($p)) {
                    $query->orWhere("A.name", "like", "%".$p."%" );
                    $query->orWhere("B.name", "like", "%".$p."%");
                    $query->orWhere("C.name", "like", "%".$p."%" );
                    $query->orWhere("D.name", "like", "%".$p."%" );
                }
            });

            $result = $model->limit($limit)->get();
        }
        return $result;
    }


    /**
     * 获取数据id
     * @param array $data
     * @return array
     */
    public function getIds($data=array()){
        $result = array();
        $data = is_object($data) ? $data->toArray() : $data;
//        var_dump($data);
        if($data){
            foreach($data as $v){
                $id = getKey($v,'id');
                $result[$id] = $id;
            }
        }
        return $result;
    }


    /**
     * 资产维修次数(频率)
     * @param string $input
     * @return mixed
     */
    public function getAssetRepairFrequency($input=''){
        $list = getKey($input,'list');
        $model = $this->device;
        $event_cnt = 'event_cnt';
        if($list) {
            $fieldArrKeys = [
                "assets_device.state",
                "assets_device.name",
                "assets_device.brand",
                "assets_device.ip",
                "assets_device.intime",
                "assets_device.category_id",
                "assets_device.sub_category_id",
                "warranty_state",
                "warranty_begin",
                "warranty_end",
                "location",
                "officeBuilding",
                "department",
                "area",
            ];
        }else{
            $event_cnt = 'value';
        }
        $fieldArrKeys[] = "assets_device.id";
        $fieldArrKeys[] = 'assets_device.name';
        $fieldArrKeys[] = 'assets_device.number';
        $fieldArrKeys[] = DB::raw('count(A.id) as '.$event_cnt);

//        $model->orderBy("deleted_at","desc");
        $model = $model->select($fieldArrKeys)->with('sub_category','events')
            ->join('workflow_events as A','assets_device.id','=','A.asset_id')
            ->join('workflow_maintain as B','A.id','=','B.event_id')
            //排除日常巡检事件
            ->where('B.solution_id','!=',24)
            ->whereNull('A.deleted_at')
//            ->whereIn('B.solution_id',[1,3,6,8,9,10])
            ->groupBy('assets_device.id')
            ->orderBy("$event_cnt","desc");

        $result = $this->usePage($model);
        if(isset($result)){
            foreach($result as &$v){
                $status = isset($v['warranty_state']) ? $v['warranty_state'] : '';
                $v['status'] = 1 == $status ? 1 : 0;
            }
        }
        return $result;
    }

    // 导出所有的二维码
    public function getAllQrCode($data,$path,$switch=false){
        $info = scandir($path);
        // 先删除文件夹里的内容
        foreach($info as $k=>$v){
            if($v !="." && $v != ".." && $v != 'allEpc.xlsx'){
                unlink($path."/".$v);
            }
        }

        foreach($data as $k=>$v){
            $number = $v['number'];    // number是资产编号
            $id = $v['id'];
            if(file_exists($path.'/'.$number.'.png')){
                continue;
            }
            $margin = $switch == true ? 3 : 0;
            QrCode::errorCorrection('H');
            $qrdetail = config("app.wx_url")."/commdetail?number=$number";
            // ->merge(public_path()."/logo.png", .2, true)
            QrCode::format('png')->size(150)->margin($margin)->generate($qrdetail, $path.'/'.$number."__{$id}".'.png');
            if($switch == false) continue;
            $image = imagecreatefromstring(file_get_contents($path.'/'.$number."__{$id}".'.png'));
            $font_path = storage_path('heiti.ttf');  // 黑体
            $black = imagecolorallocatealpha($image, 0, 0, 0, 0); //创建黑色字体
            imagettftext($image, 9, 0, 17, 144, $black, $font_path, $number); // 创建文字
//            header("Content-Type:image/png");
            imagepng($image, $path.'/'.$number."__{$id}".'.png');
        }
        return true;
    }




    /**
     * 各位置资产总数
     * @return mixed
     */
    public function getAssetsLocationNum(){
        $model = $this->device;
        $asset_cnt = 'value';
        $fieldArrKeys = ['A.name as name',
            DB::raw('count(assets_device.id) as '.$asset_cnt)
        ];
        $model = $model->select($fieldArrKeys)
            ->join('assets_zone as A','assets_device.location','=','A.id')
            ->groupBy('location')
            ->orderBy("$asset_cnt","desc");
        $res = $model->get();
        $res = $res ? $res->toArray() : array();
        $result['result'] = $res;
        return $result;
    }


    /**
     * 各位置资产使用率
     * @param array $input
     * @return mixed
     */
    public function getAssetsLocationRate($input=array(),$type = ''){
        $list = getKey($input,'list');
        $model = $this->device;
        $asset_cnt = 'asset_cnt';
        $defaultField = [
            DB::raw("SUM(IF(assets_device.state IN (1,2),1,0)) AS used"),//使用率
        ];
        if($type === 'free'){
            $defaultField[] = DB::raw("round(((SUM(IF(assets_device.state = 0,1,0))/SUM(IF(assets_device.state IN (0,1,2),1,0)))*100),2) as use_rate");
        }else{
            $defaultField[] = DB::raw("round(((SUM(IF(assets_device.state IN (1,2),1,0))/SUM(IF(assets_device.state IN (0,1,2),1,0)))*100),2) as use_rate");
        }

        if($list) {
            $fieldArrKeys = $defaultField;
            $fieldArrKeys[] = "assets_device.id";
            $fieldArrKeys[] = "location";
            $fieldArrKeys[] = DB::raw("SUM(IF(assets_device.state = 0,1,0)) AS state_free_cnt");
            $fieldArrKeys[] = DB::raw("SUM(IF(assets_device.state = 3,1,0)) AS state_down_cnt");
        }else{
            $asset_cnt = "total";
            $fieldArrKeys = $defaultField;
            $fieldArrKeys[] = "assets_device.id";
            $fieldArrKeys[] = "A.name as name";
            $fieldArrKeys[] = DB::raw("SUM(IF(assets_device.state = 0,1,0)) AS free");
        }

        $fieldArrKeys[] = DB::raw('count(assets_device.id) as '.$asset_cnt);
        $model = $model->select($fieldArrKeys)
            ->join('assets_zone as A','assets_device.location','=','A.id')
            ->groupBy('location')
            ->orderBy('use_rate',"desc")->orderBy($asset_cnt,'desc');    // $asset_cnt
        $result = $this->usePage($model);
        if(isset($result)){
            foreach($result as &$v){
                if(!is_null($v->use_rate) && $v->use_rate > 0.01) {
                    $v->use_rate = $v->use_rate . '%';
                }else{
                    $v->use_rate = 0;   // ''
                }

            }
        }
//        $res = $res ? $res->toArray() : array();
        return $result;
    }


    /**
     * 核心监控资产使用情况
     * @param array $input
     * @return mixed
     */
    public function getMonitorCore($input=array()){
        $list = getKey($input,'list');
        $number = getKey($input,'number');
        $category_id = getKey($input,'category_id',0);
        $model = $this->device;
        $asset_cnt = 'asset_cnt';
        $fieldArrKeys = [
            "assets_device.id",
            "assets_device.number",
            "assets_device.name",
            "A.cpu_percent",
            "A.mem_percent",
            "A.disk_percent",
        ];
        $where = array('assets_device.coreSwitch'=>'是');
        if($list) {
            $fieldArrKeys[] = "assets_device.category_id";
            $fieldArrKeys[] = "assets_device.sub_category_id";
            $fieldArrKeys[] = "assets_device.state";
            $fieldArrKeys[] = "A.device_status";//设备的状态
            $fieldArrKeys[] = "assets_device.usage";
            $fieldArrKeys[] = "assets_device.brand";
            $fieldArrKeys[] = "assets_device.os";
            $fieldArrKeys[] = "assets_device.antivirus";
            $fieldArrKeys[] = "assets_device.warranty_state";
//            $fieldArrKeys[] = "assets_device.warranty_time";
            $fieldArrKeys[] = "location";
            $fieldArrKeys[] = "A.ip";
            $fieldArrKeys[] = "A.work_days"; //使用天数
//            $fieldArrKeys[] = "assets_device.intime";
            //计算除日常巡检的维修次数
            $fieldArrKeys[] = Db::raw("count(C.id) as count_service");
            if(!empty($category_id)){
                $where[] = ['assets_device.category_id','=',$category_id];
            }
        }else{
            if($number){
                $where[] = ['assets_device.number','=',$number];
            }else{
                Code::setCode(Code::ERR_PARAMS,'参数不正确');
                return false;
            }
        }



        $model = $model->select($fieldArrKeys)->join('assets_monitor as A','assets_device.id','=','A.asset_id')->where($where)->whereNull('A.deleted_at');
        if($list) {
            $model->leftjoin('workflow_events as B','assets_device.id','=','B.asset_id')
                ->leftjoin('workflow_maintain as C',function($join){
                    $join->on('B.id','=','C.event_id')->where('C.solution_id','!=',\ConstInc::$mInspectionId);
                })
                ->groupBy('assets_device.id');
        }

        $result = $this->usePage($model);
//        dd($result->toArray());
        if(isset($result)){
            foreach($result as &$v){
                if(!is_null($v->cpu_percent) && $v->cpu_percent > 0.01) {
                    $v->cpu_percent = $v->cpu_percent . '%';
                }else{
                    $v->cpu_percent = 0;
                }
                if(!is_null($v->mem_percent) && $v->mem_percent > 0.01) {
                    $v->mem_percent = $v->mem_percent . '%';
                }else{
                    $v->mem_percent = 0;
                }
                if(!is_null($v->disk_percent) && $v->disk_percent > 0.01) {
                    $v->disk_percent = $v->disk_percent . '%';
                }else{
                    $v->disk_percent = 0;
                }
                if(!$list) {
                    $v->echartData = array(
                        array(
                            'name' => '处理器使用率',
                            'value' => $v->cpu_percent,
                            'description' => [],
                        ),
                        array(
                            'name' => '内存使用率',
                            'value' => $v->mem_percent,
                            'description' => [],
                        ),
                        array(
                            'name' => '磁盘使用率',
                            'value' => $v->disk_percent,
                            'description' => [],
                        ),
                    );
                }else{
                    $day_time = 24*3600;
                    $times = ceil($v->work_days/$day_time);
                    $v->work_days = $times;
                }
            }
        }
        return $result;
    }


    /**
     * 资产过保情况
     * @param array $input
     * @return mixed
     */
    public function getAssetsWarranty($input=array()){
        $list = getKey($input,'list');
        $model = $this->device;
        if($list) {
            $fieldArrKeys = ["assets_device.id",
                "assets_device.name",
                "assets_device.number",
                "assets_device.state",
                "assets_device.name",
                "assets_device.brand",
                "assets_device.ip",
                "assets_device.intime",
                "assets_device.category_id",
                "assets_device.sub_category_id",
                "warranty_state",
                "warranty_begin",
                "warranty_end",
                "location",
                "officeBuilding",
                "department",
                "area",
                "master",
            ];
            $warranty_state = array(0,1);
            $model = $model->select($fieldArrKeys)->whereIn('warranty_state',$warranty_state);
            $result = $this->usePage($model);
            if(isset($result)){
                foreach($result as &$v){
                    $status = isset($v['warranty_state']) ? $v['warranty_state'] : '';
                    $v['status'] = 1 == $status ? 1 : 0;
                }
            }
            return $result;
        }else{

            $fieldArrKeys = array(
                DB::raw('SUM(IF(warranty_state=1,1,0)) AS cnt1'),
                DB::raw('SUM(IF(warranty_state=0,1,0)) AS cnt0')
            );
            $res = $model->select($fieldArrKeys)->first();
            //未过保
            $inWarranty = isset($res['cnt1']) ? $res['cnt1'] : 0;
            //已过保
            $outWarranty = isset($res['cnt0']) ? $res['cnt0'] : 0;
            if($res){
                $result['result'] = array(
                    array('name'=>'未过保','value'=>$inWarranty),
                    array('name'=>'已过保','value'=>$outWarranty)
                );
            }

            return $result;
        }
    }



    /**
     * 核心监控资产状态
     * @param array $input
     * @return mixed
     */
    public function getAssetsCoreStatus($input=array()){
        $list = getKey($input,'list');
        $model = $this->device;

        $fieldArrKeys = [
            "assets_device.id",
            "assets_device.number",
            "assets_device.name",
            "A.device_status",
            "location"
        ];
        $where = array('assets_device.coreSwitch'=>'是');
        if($list) {
            $fieldArrKeys[] = "assets_device.category_id";
            $fieldArrKeys[] = "assets_device.sub_category_id";
        }



        $model = $model->select($fieldArrKeys)
            ->join('assets_monitor as A','assets_device.id','=','A.asset_id')
            ->where($where)
            ->whereNull('A.deleted_at');
        $result = $this->usePage($model);
//        dd($result->toArray());
        if(isset($result)){
            foreach($result as &$v){
                $device_status = isset($v['device_status']) ? $v['device_status'] : '';
                $v['device_status_msg'] = 1 == $device_status ? '在线' : '离线';
                $v['status'] = 1 == $device_status ? 1 : 0;
            }
        }
        return $result;
    }


    /**
     * 各站点报修情况
     * @param array $input
     * @return mixed
     */
    public function getLocationUserEventNum($request,$input=array()){
        $list = getKey($input,'list');
        $search = getKey($input,'search');
        $model = $this->device;
        $cnt = 'cnt';

        $fieldArrKeys = [
            "assets_device.id",
            "assets_device.location"
        ];

        $where[] = ['A.id','>','0'];
        $between = '';

        if($list) {
            $between = $this->searchTime($request);
            $fieldArrKeys[] = DB::raw('count(A.id) AS '.$cnt);
            $fieldArrKeys[] = DB::raw('SUM(IF(A.state=0,1,0)) AS state0');
            $fieldArrKeys[] = DB::raw('SUM(IF(A.state=1,1,0)) AS state1');
            $fieldArrKeys[] = DB::raw('SUM(IF(A.state=2,1,0)) AS state2');
            $fieldArrKeys[] = DB::raw('SUM(IF(A.state=3,1,0)) AS state3');
            $fieldArrKeys[] = DB::raw('SUM(IF(A.state=4,1,0)) AS state4');
        }else{
            $cnt = 'value';
            $fieldArrKeys[] = DB::raw('count(A.id) AS '.$cnt);
        }


        $model = $model->select($fieldArrKeys)
            ->leftjoin('workflow_events as A','assets_device.id','=','A.asset_id')
            ->where($where)
            ->whereNull('A.deleted_at')
            ->groupBy("assets_device.location")
            ->orderBy($cnt,"desc");

        if($between){
            $model = $model->whereBetween("A.updated_at", $between);
        }

        if($search){
            $zone_model = $this->zone;
            $zone_ids = $zone_model->select('id')->where('name','like',"%{$search}%")->get()->pluck('id')->toArray();
            $model = $model->whereIn('assets_device.location',$zone_ids)->whereNotNull('assets_device.location');
        }
        $result = $this->usePage($model);
        return $result;
    }

    // 得到不同的使用年限时间
    public function getRangeTime(){
        $year = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 1));
        $year3 = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 3));
        $year5 = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 5));
        $year_1_lower = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])->where("intime",">",$year)->count();

        $year_1_3 = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])->where("intime","<=",$year)->where("intime",">",$year3)->count();

        $year_3_5 = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])->where("intime","<=",$year3)->where("intime",">",$year5)->count();

        $year_5_upper = $this->device->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])->where("intime","<",$year5)->count();

        $data['result'] = [
            ['name'=>'<1年','value'=>$year_1_lower],
            ['name'=>'1-3年','value'=>$year_1_3],
            ['name'=>'3-5年','value'=>$year_3_5],
            ['name'=>'>5年','value'=>$year_5_upper],
        ];
        return $data;
    }


    public function getAssetsTimeList(){
        $year = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 1));
        $year3 = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 3));
        $year5 = date("Y-m-d H:i:s", mktime(0,0,0,date("m"), date("d"), date("Y") - 5));
        $model = $this->device->select(
            'assets_category.name',
            DB::raw('SUM( IF ( assets_device.intime > "'.$year.'", 1, 0 ) ) as year'),
            DB::raw('SUM( IF (assets_device.intime <= "'.$year.'" AND assets_device.intime  > "'.$year3.'", 1, 0 ) ) as year1_3'),
            DB::raw('SUM( IF (assets_device.intime <= "'.$year3.'" AND assets_device.intime  > "'.$year5.'", 1, 0 ) ) as year3_5'),
            DB::raw('SUM( IF ( assets_device.intime  < "'.$year5.'", 1, 0 ) ) as year5'),
            'assets_device.category_id','assets_device.sub_category_id'
        )
            ->whereIn("state",[Device::STATE_FREE ,Device::STATE_USE, Device::STATE_MAINTAIN])
            ->whereNotNull('assets_device.intime')
            ->leftjoin('assets_category','assets_device.sub_category_id','=','assets_category.id')
            ->groupBy('assets_device.sub_category_id');
        $model = $this->usePage($model,'assets_category.id','asc');

        $result = $model;
        return $result;

    }


    /**
     * 获取使用地的分类 type不存在时获取所有
     * @param array $input
     * @return array
     */
    public function getAssetsZoneCategory($input = array()){
        $type = isset($input['type']) ? $input['type'] : '';

        $tableArr = array('er'=>'enginerooms','dt'=>'department');
        $table = isset($tableArr[$type]) ? $tableArr[$type] : '';

        $data = array();
        if($table){
            $data = $this->getZoneCategoryForTable($table);
        }else{
            $data = array_merge($this->getZoneCategoryForTable($tableArr['er']),$this->getZoneCategoryForTable($tableArr['dt']));
        }


        $array = [];


        if($data){
            foreach($data as $value){
                $id = $value['id']??0;
                $aid = $value['ab_id']??0;
                $eid = $value['ed_id']??0;
                $edbid = $value['edb_id']??0;
                $tmp_arr = [];
                if($id){
                    if(!isset($array[$id])){
                        $array[$id] = [
                            'id' => $id,
                            'label' => $value['name'],
                            'key' => 'location',
                            'value' => 'location'.':'.$id,
                            'children' => []
                        ];
                    }

                    if($aid && ($aid === $edbid)){
                        $aid_key = 'officeBuilding'.':'.$aid;
                        //添加a为了排序
                        if(!isset($array[$id]['children']['a'.$aid_key])){
                            $array[$id]['children']['a'.$aid_key] = [
                                'id' => $aid,
                                'label' => $value['ab_name'],
                                'key' => 'officeBuilding',
                                'value' => $aid_key,
                                'children' => []
                            ];
                        }

                        if($eid){
                            $eid_key = $value['table'].':'.$eid;
                            if(!isset($array[$id]['children']['a'.$aid_key]['children'][$eid_key])) {
                                $array[$id]['children']['a'.$aid_key]['children'][$eid_key] = [
                                    'id' => $eid,
                                    'label' => $value['ed_name'],
                                    'key' => $value['table'],
                                    'value' => $eid_key,
                                ];
                            }
                        }
                    }else if($edbid === 0){
                        if($eid){
                            $eid_key = $value['table'].':'.$eid;
                            if(!isset($array[$id]['children'][$eid_key])) {
                                $array[$id]['children'][$eid_key] = [
                                    'id' => $eid,
                                    'label' => $value['ed_name'],
                                    'key' => $value['table'],
                                    'value' => $eid_key,
                                ];
                            }
                        }
                    }
                }
            }
        }

        foreach($array as $key=>&$value){
            if($value['children']){
                foreach($value['children'] as &$val){
                    if(isset($val['children'])){
                        $val['children'] = array_values($val['children']);
                    }
                }
                ksort($value['children'],SORT_NATURAL);
                $value['children'] = array_values($value['children']);
            }else{
                unset($array[$key]['children']);
            }
        }
        $array = array_values($array);

        return $array;
    }

    /**
     * 根据类型查询表分类信息
     * @param $table
     * @return array
     */
    protected function getZoneCategoryForTable($table){
        if(!$table){
            return [];
        }
        $fields = [
            'assets_zone.id','assets_zone.name','A.id as ab_id','A.name as ab_name','B.id as ed_id','B.id as ed_id','B.name as ed_name','B.building_id as edb_id'
        ];
        $where[] = ['B.zone_id','>','0'];
        $data = $this->zone->select($fields)
            ->leftJoin('assets_building as A','A.zone_id','=','assets_zone.id')
            ->leftJoin('assets_'.$table.' as B','B.zone_id','=','assets_zone.id')
            ->where($where)
            ->whereNull('A.deleted_at')
            ->whereNull('B.deleted_at')
            ->get()->toArray();

        foreach($data as $k=>$v){
            $data[$k]['table'] = $table;
        }
        return $data;
    }


    public function getAssetsTotalByCategory($input = array())
    {
        $fieldArrKeys[]= 'assets_category.id';
        $fieldArrKeys[]= 'assets_category.name';
        $fieldArrKeys[] = DB::raw('count(A.id) AS value');

        $model = $this->category->select($fieldArrKeys)
            ->join('menus as B','B.category_id','=','assets_category.id')
            ->leftjoin('assets_device as A','assets_category.id','=','A.category_id')
            ->where('assets_category.pid','=',0)
            ->where('B.status','=',1)
            ->whereNull('A.deleted_at')
            ->groupBy("assets_category.id")
            ->orderBy('value',"desc")->get()->toArray();

        $data['result'] = $model;


        return $data;
    }


    /**
     * 资产用户名密码管理
     * @param $search
     * @return bool|mixed
     */
    public function getAssetsAccount($input=array()){
        $result['result'] = '';
        $search = getKey($input,'search');

        if(!$this->userRepository->isAdmin() && !$this->userRepository->isManager()) {
            Code::setCode(Code::ERR_USER_CATEGORY);
            return false;

        }
        $where[] = ['username','!=',''];
        $model = $this->device->where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->orWhere("number", "like", "%" . $search . "%");
                $query->orWhere("name", "like", "%" . $search . "%");
                $query->orWhere("ip", "like", "%" . $search . "%");
                $query->orWhere("username", "like", "%" . $search . "%");
            }
        })->where($where);

        $fieldArrKeys[] = "id";
        $fieldArrKeys[] = "created_at";
        $fieldArrKeys[] = "updated_at";
        $fieldArrKeys[] = "category_id";
        $fieldArrKeys[] = "sub_category_id";
        $fieldArrKeys[] = "ip";
        $fieldArrKeys[] = "number";
        $fieldArrKeys[] = "name";
        $fieldArrKeys[] = "username";
        $fieldArrKeys[] = "userpwd";

        $model = $model->select($fieldArrKeys);
        return $this->usePage($model);
    }


    /**
     * 批量报废资产
     * @param $assetIds
     * @return bool
     */
    public function breakdown($assetIds) {

        //权限判断
        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $freeArr = [];
            $limitCategories = $this->userRepository->getCategories();
            $ret = $this->device->whereIn("id", $assetIds)->get();
            foreach ($ret as $v) {
                if (!in_array($v->sub_category_id, $limitCategories)) {
                    $numbers[] = $v->number;
                }
                if (Device::STATE_FREE != $v->state) {
                    $freeArr[] = $v->id;
                }
            }
            if (!empty($numbers)) {
                Code::setCode(Code::ERR_USER_CATEGORY);
                return false;
            }
            if ($freeArr) {
                Code::setCode(Code::ERR_NOT_FREE);
                return false;
            }
        }

        $update['state'] = $this->workflowCategoryModel->getStateByCategory(WorkflowCategory::BREAKDOWN);
        $update["updated_at"] = date("Y-m-d H:i:s");
        $this->model->whereIn("id", $assetIds)->update($update);
        userlog("报废了资产: ".join(",",$assetIds));
        return true;
    }


    /**
     * 获取字段列表,分组
     * @param $category
     * @param $fields []
     * @return array
     */
    public function getFieldsList($categoryId, $fields = null)
    {
        $model = $this->category->findOrFail($categoryId);
        $fieldsList = $model->fields()->get()->groupBy("field_type_id")->toArray();

        $fieldsType = $this->fieldsType->get()->pluck("name", "id")->all();
        $data = [];
        foreach ($fieldsList as $k => $v) {
            $cur = [
                "id" => $k,
                "cname" => $fieldsType[$k],
            ];
            $children = [];
            foreach ($v as $vv) {
                $child = [
                    "sname" => $vv['field_sname'],
                    "cname" => $vv['field_cname'],
                    "type" => $vv['field_type'],

                ];
                if($fields) {
                    $child["checked"] = in_array($vv['field_sname'], $fields) ? 1 : 0;
                }
                if(!empty($vv['field_dict']) || !empty($vv['dict_table'])) {
                    $child['tname'] = $vv['field_sname']."_msg";
                }
                if(!empty($vv['url'])) {
                    $child['url'] = $vv['url'];
                }
                if (!empty($vv['field_dict'])) {
                    $child['options'] = [];
                    $arr = json_decode($vv['field_dict'], true);
                    if($arr) {
                        foreach($arr as $value => $text) {
                            $child['options'][] = [
                                "text" => $text,
                                "value" => $value
                            ];
                        }
                    }
                }
                if(!empty($vv['dict_table'])) {
                    $child['options'] = $this->dictRepository->getOptions($vv['dict_table'], $vv['field_sname']);
                    $child['parentField'] = $this->dictRepository->getParentField($vv['dict_table']);
                }

                $children[] = $child;
            }
            $cur['children'] = $children;
            $data[] = $cur;
        }
        return $data;
    }


    /**
     * 获取资产字段对象
     * @param $subDeviceCategoryId
     * @return array
     */
    public function getItemInfo($subDeviceCategoryId){
        $fieldsList = $this->getFieldsList($subDeviceCategoryId);
        $categoryRequire = $this->categoryfieldsModel->getCategoryRequire($subDeviceCategoryId, '1');

        foreach ($fieldsList as $k => &$v) {
            foreach ($v['children'] as $kk => &$vv) {
                if(in_array($vv['sname'], $this->categoryfieldsModel->hiddenFields)) { //状态过滤
                    unset($v['children'][$kk]);
                    continue;
                }

                if(!empty($categoryRequire) && isset($categoryRequire[$vv['sname']])) {
                    if($categoryRequire[$vv['sname']]['require'] === 0 || $categoryRequire[$vv['sname']]['require'] === 4) { //删除
                        unset($v['children'][$kk]);
                    }
                    $vv['require'] = $categoryRequire[$vv['sname']]['require'];
                }
                /*$tvalue = DeviceRepository::transform($vv['sname'], $device->{$vv['sname']}, $tkey);
                $sname = isset($vv['sname'])?$vv['sname']:'';
                $vv['value'] = encryptStr($sname,$device->{$sname},true);
                if(!empty($tkey)) {
                    $vv['tvalue'] = $tvalue;
                }*/
            }
            $v['children'] = array_values($v['children']);
            if(empty($v['children'])) { //去除没有子项的内容
                unset($fieldsList[$k]);
            }
        }

        $fieldsList = array_values($fieldsList);

        return $fieldsList;
    }

    /**
     * 获取机房或者科室
     * @param $sub_category_id
     * @param $data
     */
    public function getErDtInfo($sub_category_id,&$data) {
        $roomType = $this->categoryfieldsModel->getRoomType($sub_category_id);
        if(!empty($roomType)) {
            $data['room'] = $this->getErDtCategory(["type" => $roomType]);
            $data['roomType'] = $roomType;
        }
    }

    /**
     * 入库显示详情
     * @param array $input
     * @return array
     */
    public function instorageInfo($input=array()){
        $sub_category_id = getKey($input,'category_id');
        $categories = [];
        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $categories = $this->userRepository->getCategories();
        }
        $data = [];
        if($sub_category_id) {
            $this->getErDtInfo($sub_category_id,$data);
            $data['info'] = $this->getItemInfo($sub_category_id);
        }else{
            $categories = $this->category->getCategoryList($categories);
            $data = isset($categories['result']) ? $categories['result'] : [];
        }

        return $data;
    }





    public function instorageSave($data=array()){
        if(!isset($data['subDeviceCategoryId'])) {
            throw new ApiException(Code::ERR_EVENT_DEVICECATE);
        }
        $insert = [];
        $subCategoryId = $data['subDeviceCategoryId'];
        $categoryId = $data['deviceCategoryId'];

        //检查number
        if(isset($data['number']) && !empty($data['number'])) {
            $ret = $this->model->getByNumber($data['number']);
            if(!$ret->isEmpty()) {
                $numbers = $ret->pluck("number")->all();
                throw new ApiException(Code::ERR_NUMBER_EXISTS, $numbers);
            }
        }

        //取该类别字段
        $fields = $this->category->getFields($subCategoryId);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            if(isset($data[$key])) {
                $insert[$key] = encryptStr($key,$data[$key]);
            }
        }
//        return $insert;
        if(!$insert){
            throw new ApiException(Code::ERR_ASSETS_PARAMS);
        }

        $this->category->check($data['subDeviceCategoryId'], $insert);

        if(!isset($insert['number'])) {
            $numberInfo = $this->genNumber($data['subDeviceCategoryId']);
            $insert['number'] = $numberInfo['number'];
        }

        //检查该类别的内容
        $categoryRequire = $this->categoryfieldsModel->getCategoryRequire($data['subDeviceCategoryId'], '1');
        foreach($categoryRequire as $sname => $v) {
            if($v['require'] === 1 && $insert[$sname] === "") {
                throw new ApiException(Code::ERR_EVENT_FIELD_REQUIRE, [$v['cname']]);
            }
        }

        $insert['intime'] = date("Y-m-d H:i:s"); //入库时间
        $insert['state'] = Device::STATE_FREE;
        $insert['sub_category_id'] = $subCategoryId;
        $insert['category_id'] = $categoryId;
        $insert["created_at"] = date("Y-m-d H:i:s");
        $insert["updated_at"] = date("Y-m-d H:i:s");

        $id = $this->model->insertGetId($insert);
        return $id ? true : false;
    }



    public function getAstAnaly($input=[],$type=''){
        $end_time = '';
        if(!empty($input['endTime'])){
            $end_time = $input['endTime'].'23:59:59';
        }
        $category_id = [];
        if(!empty($input['categoryId'])){
            $category_id = explode(',',$input['categoryId']);
        }

        # category_id 如果为0 或者是没传,则查询的是全部的分类的信息
        if(!$end_time) {
            throw new ApiException(Code::ERR_PARAMS, ["时间参数不存在"]);
        }

        if(count($category_id) < 2){
            throw new ApiException(Code::ERR_PARAMS,["分类最少不得少于2个"]);
        }
        $model = $this->model;
        $ast = 'assets_device';
        $field = [
            $ast.'.number',
            $ast.'.name',
            $ast.'.state',
            $ast.'.id',
            $ast.'.warranty_end',
            $ast.'.user',
            $ast.'.brand',
            $ast.'.ip',
            $ast.'.intime',
            'Z.name as location'
        ];
        $where = [];
//        $where[] = [$ast.'.intime','<=', $end_time];
        $model = $model->leftjoin('assets_zone as Z',"$ast.location",'Z.id')
            ->select($field)
//            ->whereNotNull($ast.'.warranty_end')
//            ->whereNotNull($ast.'.intime')
            ->where($where)->with('events','dict');

        $model = $model->where(function ($query) use ($end_time) {
            $query->whereNull('assets_device.intime')->orWhere('assets_device.intime', '<=', $end_time);
        });
        if(!empty($category_id)){
            $model->whereIn($ast.'.category_id',$category_id);
        }

        if(empty($type)){
            return $this->usePage($model);
        }else{
            $data = $model->get();
            $transformer = new App\Transformers\Assets\AnalyTransformer($input['endTime']);
            return Fractal::collection($data, $transformer)->getArray()['data'];
        }

    }


    public function getAstCategory(){
        $category = $this->category->select('id','name')->where('pid','=',0)->get()->pluck('name','id')->toArray();
        $list = [];
        foreach($category as $k=>$v){
            $arr = [];
            $arr['id'] = $k;
            $arr['name'] = $v;
            $list[] = $arr;
        }
        return ['result' => $list];
    }


    public function getAstInfo($input=[],$type=''){
        $end_time = '';
        if(!empty($input['endTime'])){
            $end_time = $input['endTime'].'23:59:59';
        }
        $category_id = [];
        if(!empty($input['categoryId'])){
            $category_id = explode(',',$input['categoryId']);
        }
        $e_time = strtotime($end_time);

        # category_id 如果为0 或者是没传,则查询的是全部的分类的信息
        if(!$end_time) {
            throw new ApiException(Code::ERR_PARAMS, ["时间参数不存在"]);
        }

        if(count($category_id) < 2){
            throw new ApiException(Code::ERR_PARAMS,["分类最少不得少于2个"]);
        }

        if(!empty($category_id)){
            $category_arr = $this->category->where('pid','=',0)->whereIn('id',$category_id)->get()->pluck('name','id')->toArray();
        }else{
            $category_arr = $this->category->where('pid','=',0)->get()->pluck('name','id')->toArray();
        }
        # 最顶级分类数组
        $cate_arr = array_keys($category_arr);

        $model = $this->model
                    ->select('intime','assets_device.category_id','assets_device.id as asset_id','warranty_end')
                    ->whereIn('assets_device.category_id',$cate_arr);


        $model = $model->where(function ($query) use ($end_time) {
            $query->whereNull('assets_device.intime')->orWhere('assets_device.intime', '<=', $end_time);
        });
        $ast_data = $model->get()
                         ->groupBy('category_id')
                         ->toArray();
//        dump($ast_data);exit;
        if(empty($ast_data) && $category_id){
            return ['result'=>[]];
        }

        foreach($ast_data as $k=>$v){
            $list[$k] = count($v);
        }

        $list_arr = [];
        foreach($category_arr as $k=>$v){
            $list_clean = [];
            $list_clean['id'] = $k;
            $list_clean['name'] = $v;
            foreach($list as $kk=>$vv){

                if($kk == $k){
                    $list_clean['count'] = $vv;
                }
            }
            $list_arr[] = $list_clean;
        }

        $list135 = [];
        foreach($ast_data as $k=>$v){
            $list_1_3_5 = [];  # 1 3 5 年的数组
            $list_1_3_5['zero'] = 0;
            $list_1_3_5['three_less'] = 0;  # 小于3年
            $list_1_3_5['three_five'] = 0;  # 3到5年
            $list_1_3_5['five_more'] = 0;   # 大于5年
            $intime_arr = array_column($v,'intime');
            $list_1_3_5['id'] = $k;
            foreach($intime_arr as $vv){
                if(is_null($vv)){
                    $list_1_3_5['zero'] ++;
                }elseif ($e_time - strtotime($vv) < 3*365*86400){
                    $list_1_3_5['three_less'] ++;
                }elseif ($e_time - strtotime($vv) < 5*365*86400){
                    $list_1_3_5['three_five'] ++;
                }else{
                    $list_1_3_5['five_more'] ++;
                }
            }
            $list135[] = $list_1_3_5;
        }

        foreach($list_arr as $k=>&$v){
            foreach($list135 as $kk=>&$vv){
                if($v['id'] == $vv['id']){
                    $v = array_merge($v,$vv);
                }
            }
        }
        unset($v,$vv);

        # 0-3年的,计算出建议更换的
        $ast_cate_arr = [];
        foreach($ast_data as $k=>$v){
            # 资产
            $list_info = [];
            foreach($v as $kk=>$vv){
                if(!is_null($vv['intime']) && ($e_time - strtotime($vv['intime']) < 5*365*86400) && ($e_time - strtotime($vv['intime']) >= 3*365*86400)){
                    $list_info[] = $vv['asset_id'];
                }
            }
            $ast_cate_arr[$k] = $list_info;
        }

        # asset_id => cnt
        $event_data = $this->event->select('asset_id',DB::raw('count(*) as cnt'))->where('state','=',3)->whereNotNull('asset_id')->groupBy('asset_id')->get()->pluck('cnt','asset_id')->toArray();

        # 统计3-5年中，资产维修次数超过3次的,认为是需要更换
        $suggest_change = [];
        foreach($ast_cate_arr as $k=>$v){
            $suggest_change[$k] = 0;
            foreach($v as $kk=>$vv){
                if(isset($event_data[$vv]) && ($event_data[$vv] > 5) && !is_null($vv['warranty_end']) && ($e_time > $vv['warranty_end'])){
                    $suggest_change[$k]++;
                }
            }
        }

        # 设备总数
        $ast_cnt = 0;
        foreach ($list_arr as $k=>$v){
            if(isset($v['count'])){
                $ast_cnt += $v['count'];
            }
        }


        foreach($list_arr as $k=>&$v){
            if(count($v) !== 2){
                $v['suggest_update'] = $v['five_more'] + $suggest_change[$v['id']];
                if($v['count'] != 0){
                     $v['type_rate'] = (int)round(($v['suggest_update'] / $v['count'])*100); # 类型占比
                }else{
                    $v['type_rate'] = 0;
                }
                if(!empty($type)){
                    $v['type_rate'] .= '%';
                }
                if($ast_cnt != 0){
                    $v['type_all_rate'] = (int)round(($v['suggest_update'] / $ast_cnt)*100); # 类型占比
                }else{
                    $v['type_all_rate'] = 0;
                }
                if(!empty($type)){
                    $v['type_all_rate'] .= '%';
                }
            }else{
                $v['count'] = 0;
                $v['zero'] = 0;
                $v['three_less'] = 0;
                $v['three_five'] = 0;
                $v['five_more'] = 0;
                $v['type_rate'] = 0;
                $v['type_all_rate'] = 0;
                $v['suggest_update'] = 0;
            }
        }
        unset($v);
        if(count($category_id) > 1) {
            $total_arr = [];
            $total_arr['count'] = 0;
            $total_arr['suggest_update'] = 0;
            $total_arr['zero'] = 0;
            $total_arr['three_less'] = 0;
            $total_arr['three_five'] = 0;
            $total_arr['five_more'] = 0;
            foreach ($list_arr as $k => $v) {
                $total_arr['name'] = '全部';
                $total_arr['count'] += $v['count'];
                $total_arr['suggest_update'] += $v['suggest_update'];
                $total_arr['zero'] += $v['zero'];
                $total_arr['three_less'] += $v['three_less'];
                $total_arr['three_five'] += $v['three_five'];
                $total_arr['five_more'] += $v['five_more'];
            }

            if ($total_arr['count'] != 0) {
                $total_arr['type_rate'] = (int)round(($total_arr['suggest_update'] / $total_arr['count']) * 100); # 类型占比
                $total_arr['type_all_rate'] = $total_arr['type_rate'];
                if(!empty($type)){
                    $total_arr['type_rate'] .= '%';
                    $total_arr['type_all_rate'] .= '%';
                }
            }
            $list_arr = array_merge($list_arr, [$total_arr]);
        }
        return ['result' => $list_arr];
    }


    public function getAstDetail($input,$type = ''){
        $end_time = '';
        if(!empty($input['endTime'])){
            $end_time = $input['endTime'].'23:59:59';
        }
        $category_id = [];
        if(!empty($input['categoryId'])){
            $category_id = explode(',',$input['categoryId']);
        }
        $e_time = strtotime($end_time);
        # category_id 如果为0 或者是没传,则查询的是全部的分类的信息
        if(!$end_time) {
            throw new ApiException(Code::ERR_PARAMS, ["时间参数不存在"]);
        }

        if(count($category_id) < 2){
            throw new ApiException(Code::ERR_PARAMS,["分类最少不得少于2个"]);
        }

        if(!empty($category_id)){
            $category_arr = $this->category->where('pid','=',0)->whereIn('id',$category_id)->get()->pluck('name','id')->toArray();
        }else{
            $category_arr = $this->category->where('pid','=',0)->get()->pluck('name','id')->toArray();
        }
        # 最顶级分类数组
        $cate_arr = array_keys($category_arr);
        $cate_arr_str = implode($cate_arr,',');

        $sql = "SELECT `intime`,`warranty_end`,`assets_device`.`category_id`,`assets_device`.`id` AS `asset_id`,( '".$e_time."' - UNIX_TIMESTAMP( `assets_device`.`intime` ) ) / ( 86400 * 365 ) AS use_time,COALESCE( `query`.`cnt`, 0 ) AS times,
IF ( '".$e_time."' > UNIX_TIMESTAMP( `assets_device`.`warranty_end` ), 1, 0 ) AS is_warranty FROM `assets_device` LEFT JOIN ( SELECT asset_id, count( * ) AS cnt FROM `workflow_events` WHERE `state` = 3 AND `asset_id` IS NOT NULL AND `deleted_at` IS NULL GROUP BY `asset_id` ) AS `query` ON `query`.`asset_id` = `assets_device`.`id` WHERE`assets_device`.`category_id` IN ( ".$cate_arr_str." ) AND (`assets_device`.`intime` is null or `assets_device`.`intime` <= '".$end_time."') AND `assets_device`.`deleted_at` IS NULL";

        $asset_data = DB::select($sql);
        $asset_total = 0;
        if(!empty($type)){
            $asset_total = count($asset_data);
        }
        $asset_list = [];
//        dd($category_arr,$asset_data);
        foreach($asset_data as $v){
            if(!isset($asset_list[$v->category_id])){
                $asset_list[$v->category_id] = [];
            }
            $asset_list[$v->category_id][] = [
                'asset_id' => $v->asset_id,
                'intime' => $v->intime,
                'warranty_end' => $v->warranty_end,
                'use_time' => round($v->use_time,2),
                'times' => $v->times,
                'is_warranty' => $v->is_warranty
            ];
        }

        if(!empty($asset_list)){
            foreach($asset_list as $category => &$ast){
                foreach($ast as $k => &$v){
                    $this->getAssetSuggest($v);
                }
            }
        }
        ksort($asset_list);


        //统计分类数目
        $category_number = [];
        $category_number_list = [];
        if(!empty($category_arr)){
            foreach($category_arr as $id => $name){
                $category_num = isset($asset_list[$id])?count($asset_list[$id]):0;
                $category_number[] = [
                    'name' => $name,
                    'value' => $category_num
                ];
                if(!empty($type)){
                    $category_number_list[$id] = [
                        'all'=>$category_num,
                        'all_rate'=> $asset_total?(round($category_num/$asset_total,4)*100).'%':'0%',
                    ];
                }
            }
        }

        //统计分类建议数目
        $category_suggest_number = [];
        $category_suggest_list =[];
        if(!empty($category_arr)){
            foreach($category_arr as $id => $name){
                $no_suggest_number = 0;
                $yes_suggest_number = 0;
                $force_suggest_number = 0;
                if(!empty($asset_list[$id])){
                    foreach($asset_list[$id] as $key => $val){
                        if($val['is_suggest'] == 0){
                            $no_suggest_number++;
                        }else if($val['is_suggest'] == 1){
                            $yes_suggest_number++;
                        }else{
                            $force_suggest_number++;
                        }
                    }
                }
                $category_suggest_number[] = [
                    'name' => '不建议更换',
                    'value' => $no_suggest_number,
                ];
                $category_suggest_number[] = [
                    'name' => '建议更换',
                    'value' => $yes_suggest_number,
                ];
                $category_suggest_number[] = [
                    'name' => '强烈建议更换',
                    'value' => $force_suggest_number,
                ];
                if(!empty($type)){
                    $category_suggest_list[$id] = [
                        'name'=> $name,
                        'all'=> $category_number_list[$id]['all'],
                        'all_rate' => $category_number_list[$id]['all_rate'],
                        'no'=>$no_suggest_number,
                        'no_rate'=>$asset_total?(round($no_suggest_number/$asset_total,4)*100).'%':'0%',
                        'yes'=>$yes_suggest_number,
                        'yes_rate'=>$asset_total?(round($yes_suggest_number/$asset_total,4)*100).'%':'0%',
                        'force'=>$force_suggest_number,
                        'force_rate'=>$force_suggest_number?(round($force_suggest_number/$asset_total,4)*100).'%':'0%'
                    ];
                }
            }
        }




        //统计分类年限数目
        $category_year_number = [];
        $category_year_list = [];
        if(!empty($category_arr)){
            foreach($category_arr as $id => $name){
                $zero_number = 0;
                $one_three_number = 0;
                $three_five_number = 0;
                $five_number = 0;
                if(!empty($asset_list[$id])){
                    foreach($asset_list[$id] as $key => $val){
                        if(is_null($val['intime'])){
                            $zero_number++;
                        }else if($val['use_time'] < 3){
                            $one_three_number++;
                        }else if($val['use_time'] < 5){
                            $three_five_number++;
                        }else{
                            $five_number++;
                        }
                    }
                }
                $category_year_number[] = [
                    'name' => '无入库时间',
                    'value' => $zero_number,
                ];
                $category_year_number[] = [
                    'name' => '0-3年',
                    'value' => $one_three_number,
                ];
                $category_year_number[] = [
                    'name' => '3-5年',
                    'value' => $three_five_number,
                ];
                $category_year_number[] = [
                    'name' => '5年以上',
                    'value' => $five_number,
                ];

                if(!empty($type)){
                    $category_year_list[$id] = [
                        'name'=> $name,
                        'all'=> $category_number_list[$id]['all'],
                        'all_rate' => $category_number_list[$id]['all_rate'],
                        'zero'=>$zero_number,
                        'zero_rate'=>$asset_total?(round($zero_number/$asset_total,4)*100).'%':'0%',
                        'one_three'=>$one_three_number,
                        'one_three_rate'=>$asset_total?(round($one_three_number/$asset_total,4)*100).'%':'0%',
                        'three_five'=>$three_five_number,
                        'three_five_rate'=>$asset_total?(round($three_five_number/$asset_total,4)*100).'%':'0%',
                        'five'=>$five_number,
                        'five_rate'=>$asset_total?(round($five_number/$asset_total,4)*100).'%':'0%'
                    ];
                }
            }
        }



        //统计在保数目
        //1过保 0在保
        $warranty_number = [];
        $warranty_list = [];
        if(!empty($asset_list)){
            $tmp_no_warranty_number = 0;
            $tmp_yes_warranty_number = 0;
            $tmp_null_warranty_number = 0;
            foreach ($asset_list as $category => $asset){
                foreach($asset as $key => $val){
                    if(is_null($val['warranty_end'])){
                        $tmp_null_warranty_number++;
                    }elseif ($val['is_warranty'] == 0){
                        $tmp_no_warranty_number++;
                    }else{
                        $tmp_yes_warranty_number++;
                    }
                }
            }
            $warranty_number[] = [
                'name' => '在保',
                'value' => $tmp_no_warranty_number
            ];
            $warranty_number[] = [
                'name' => '已过保',
                'value' => $tmp_yes_warranty_number
            ];
            $warranty_number[] = [
                'name' => '无质保时间',
                'value' => $tmp_null_warranty_number
            ];
            if(!empty($type)){
                $warranty_list[] = [
                    'no'=>$tmp_no_warranty_number,
                    'no_rate'=>(round($tmp_no_warranty_number/$asset_total,4)*100).'%',
                    'yes'=>$tmp_yes_warranty_number,
                    'yes_rate'=>(round($tmp_yes_warranty_number/$asset_total,4)*100).'%',
                    'null'=>$tmp_null_warranty_number,
                    'null_rate'=>(round($tmp_null_warranty_number/$asset_total,4)*100).'%',
                ];
            }
        }


        //维修次数明细
        $repair_number = [];
        $repair_list = [];
        if(!empty($asset_list)){
            $two_number = 0;
            $two_five_number = 0;
            $five_number = 0;
            foreach ($asset_list as $category => $asset){
                foreach($asset as $key => $val){
                    if($val['times'] < 2){
                        $two_number++;
                    }else if($val['times'] < 5){
                        $two_five_number++;
                    }else{
                        $five_number++;
                    }
                }
            }
            $repair_number[] = [
                'name' => '不超过2次',
                'value' => $two_number
            ];
            $repair_number[] = [
                'name' => '2-5次',
                'value' => $two_five_number
            ];
            $repair_number[] = [
                'name' => '超过5次',
                'value' => $five_number
            ];
            if(!empty($type)){
                $repair_list[] = [
                    'two'=>$two_number,
                    'two_rate'=>(round($two_number/$asset_total,4)*100).'%',
                    'two_five'=>$two_five_number,
                    'two_five_rate'=>(round($two_five_number/$asset_total,4)*100).'%',
                    'five'=>$five_number,
                    'five_rate'=>(round($five_number/$asset_total,4)*100).'%',
                ];
            }
        }

        if(!empty($type)){
            $result = [
                array_values($category_suggest_list),
                array_values($category_year_list),
                array_values($warranty_list),
                array_values($repair_list)
            ];
        }else{
            $result = [
                [$category_number,$category_suggest_number],
                [$category_number,$category_year_number],
                $warranty_number,
                $repair_number
            ];
        }

        return $result;
    }


    public function getAssetSuggest(&$asset){
        if(empty($asset)){
            return [];
        }
        // 0 不建议 1 建议 2 强烈
        if(($asset['use_time'] < 3) || is_null($asset['intime'])){
            $asset['is_suggest'] = 0;
        }else if($asset['use_time'] <= 5){
            $asset['is_suggest'] = 0;
            if(($asset['times'] > 5) && !is_null($asset['warranty_end']) && $asset['is_warranty']){
                $asset['is_suggest'] = 1;
            }
        }else{
            $asset['is_suggest'] = 1;
            if(($asset['times'] > 5) && !is_null($asset['warranty_end']) && $asset['is_warranty']){
                $asset['is_suggest'] = 2;
            }
        }
    }

    public function getAssetExcel($input){
        try {
            $spreadsheet = new Spreadsheet();

            $worksheet = $spreadsheet->getActiveSheet();

            $asset_total = $this->getAstInfo($input,'excel')['result'];
            $next_number = $this->getExcelData($worksheet,$asset_total,['name'=>['name'=>'资产类型','value'=>'B'],'suggest_update'=>['name'=>'建议更新数','value'=>'C'],'count'=>['name'=>'总数','value'=>'D'],'type_rate'=>['name'=>'类型总占比','value'=>'E'],'type_all_rate'=>['name'=>'类型总占比','value'=>'F'],'zero'=>['name'=>'未设置使用年限','value'=>'G'],'three_less'=>['name'=>'使用0-3年','value'=>'H'],'three_five'=>['name'=>'使用3-5年','value'=>'I'],'five_more'=>['name'=>'使用5年以上','value'=>'J']],2,'资产分析');

            $asset_report = $this->getAstDetail($input,'excel');

            $next_number = $this->getExcelData($worksheet,$asset_report[0],['name'=>['name'=>'资产类型','value'=>'B'],'all'=>['name'=>'总数','value'=>'C'],'all_rate'=>['name'=>'总占比','value'=>'D'],'no'=>['name'=>'不建议更换','value'=>'E'],'no_rate'=>['name'=>'不建议更换占比','value'=>'F'],'yes'=>['name'=>'建议更换','value'=>'G'],'yes_rate'=>['name'=>'建议更换占比','value'=>'H'],'force'=>['name'=>'强烈建议更换','value'=>'I'],'force_rate'=>['name'=>'不建议更换占比','value'=>'J']],$next_number,'资产类型更换明细');

            $next_number = $this->getExcelData($worksheet,$asset_report[1],['name'=>['name'=>'资产类型','value'=>'B'],'all'=>['name'=>'总数','value'=>'C'],'all_rate'=>['name'=>'总占比','value'=>'D'],'zero'=>['name'=>'无入库时间','value'=>'E'],'zero_rate'=>['name'=>'无入库时间占比','value'=>'F'],'one_three'=>['name'=>'0-3年','value'=>'G'],'one_three_rate'=>['name'=>'0-3年占比','value'=>'H'],'three_five'=>['name'=>'3-5年','value'=>'I'],'three_five_rate'=>['name'=>'3-5年占比','value'=>'J'],'five'=>['name'=>'5年以上','value'=>'K'],'five_rate'=>['name'=>'5年以上占比','value'=>'L']],$next_number,'资产年限使用明细');

            $next_number = $this->getExcelData($worksheet,$asset_report[2],['no'=>['name'=>'在保','value'=>'B'],'no_rate'=>['name'=>'在保占比','value'=>'C'],'yes'=>['name'=>'已过保','value'=>'D'],'yes_rate'=>['name'=>'已过保占比','value'=>'E'],'null'=>['name'=>'无质保时间','value'=>'F'],'null_rate'=>['name'=>'无质保时间占比','value'=>'G']],$next_number,'资产在保明细');

            $next_number = $this->getExcelData($worksheet,$asset_report[3],['two'=>['name'=>'不超过2次','value'=>'B'],'two_rate'=>['name'=>'不超过2次占比','value'=>'C'],'two_five'=>['name'=>'2-5次','value'=>'D'],'two_five_rate'=>['name'=>'2-5次占比','value'=>'E'],'five'=>['name'=>'超过5次','value'=>'F'],'five_rate'=>['name'=>'超过5次占比','value'=>'G']],$next_number,'资产维修次数明细');


            $asset_list = $this->getAstAnaly($input,'excel');

            $this->getExcelData($worksheet,$asset_list,['state'=>['name'=>'状态','value'=>'B'],'number'=>['name'=>'资产编号','value'=>'C'],'name'=>['name'=>'资产名称','value'=>'D'],'use_year'=>['name'=>'使用年限','value'=>'E'],'repair_time'=>['name'=>'维修次数','value'=>'F'],'warranty'=>['name'=>'在保情况','value'=>'G'],'user'=>['name'=>'使用人','value'=>'H'],'brand'=>['name'=>'品牌','value'=>'I'],'ip'=>['name'=>'管理地址','value'=>'J'],'location'=>['name'=>'位置','value'=>'K']],$next_number,'资产列表');

            $filename = '更换意见' . date( 'YmdHis' ) . ".xls";
            $writer   = IOFactory::createWriter( $spreadsheet, "Xls" );

            header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            $writer->save( "php://output" );

            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    public function getExcelData($worksheet,$data,$columns,$start = 2,$title = ''){

        $worksheet->getCell('B'.$start)->setValue($title);

        $start++;
        foreach($columns as $column){
            $worksheet->getCell( $column['value'].$start )->setValue( $column['name'] );
        }

        $i = $start + 1;
        foreach ( $data as $value ) {
            foreach($columns as $key => $column){
                $worksheet->getCell( $column['value'] . $i )->setValue( $value[$key] );
            }

            $i ++;
        }

        $i++;

        return $i;
    }



}
