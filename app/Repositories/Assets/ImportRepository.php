<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Exceptions\ApiException;
use App\Models\Assets\CategoryFields;
use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Models\Assets\Device;
use App\Models\Assets\Fields;
use App\Models\Workflow\Category;
use App\Repositories\Workflow\EventsRepository;
use App\Models\Code;
use DB;
use App\Support\Singleton;
use Log;


class ImportRepository extends BaseRepository
{

    const CATEGORY = "类型明细";
    const NUMBER = "资产编号";
    const AUTOINSERT = true;

    protected $fieldsCache = [];
    protected $numberCache;

    protected $device;
    protected $fields;
    protected $categoryRepo;
    protected $deviceRepo;
    protected $dictRepo;
    protected $info;
    protected $userRepository;
    protected $dictRow = [];

    public function __construct(Device $deviceModel, Fields $fieldsModel,
                                CategoryRepository $categoryRepo,
                                DeviceRepository $deviceRepo,
                                EventsRepository $eventsRepo,
                                DictRepository $dictRepo,
                                CategoryFields $categoryFieldsModel,
                                UserRepository $userRepository
                                )
    {
        $this->device = $deviceModel; //资产模型
        $this->fields = $fieldsModel; //资产字段
        $this->categoryRepo = $categoryRepo;
        $this->deviceRepo = $deviceRepo;
        $this->eventsRepo = $eventsRepo;
        $this->dictRepo = $dictRepo;
        $this->categoryFields = $categoryFieldsModel;
        $this->userRepository = $userRepository;
    }

    protected function getByField($fields, $type = 1) {
        if($type == 1) {
            $field = "field_cname";
        }
        else {
            $field = "field_sname";
        }
        if(is_array($fields)) {
            return $this->fields->whereIn($field, $fields)->get(['id','field_cname', 'field_sname']);
        }
        else {
            return $this->fields->where([$field => $fields])->get(['id','field_cname', 'field_sname']);
        }
    }

    /**
     * 取分类ID
     * @param $category
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function getCategoryId($category) {
        $ret = $this->categoryRepo->getIdPidByName($category);
        if(empty($ret)) {
            throw new ApiException(Code::ERR_CONTENT_CATE);
        }
        return $ret;
    }


    /**
     * 转换粘贴文本字符串为数组
     * @param $content
     * @return array
     */
    public function transformContent($content) {
        $lines = explode("\n", $content);
        $ret = [];
        foreach($lines as $line) {
            $l = trim($line);
            $ret[] = explode("\t", $l);
        }
        return $ret;
    }

    /**
     * 应用粘贴或者上传
     * @param $content
     * @param $orderType
     * @return array
     * @throws ApiException
     */
    public function apply($content, $orderType) {
        $titles = array_shift($content);

        if($orderType == Category::INSTORAGE) {
            //入库判断是否存在类型明细
            if(!in_array(self::CATEGORY, $titles)) {
                throw new ApiException(Code::ERR_CONTENT_CATE);
            }
        }

        $hiddenFields = ["state", "warranty_state", "warranty_time"];

        //检查字段
        $fields = $this->getByField($titles);

        $fieldsKV = $fields->pluck('field_sname', 'field_cname')->all();
        
        Log::debug("fields all",["fieldsKV" => $fieldsKV]);
        $titlesEn = [];
        foreach($titles as $key => $value) { //转换中文字段到英文
            if(empty($value)) {
                $titlesEn[] = "";//需要跳过的空列
                continue;
            }
            if($value === self::CATEGORY) {
                $titlesEn[] = "category";
                continue;
            }
            //$value = strtoupper($value); //全部转为大写
            if (!isset($fieldsKV[$value])) {
                throw new ApiException(Code::ERR_CONTENT_FIELD, [$value]);
            }
            $title = $fieldsKV[$value];
            $titlesEn[] = $title;
        }

        //解析数据
        $data = [];
        foreach($content as $line) {
            $nulls = 0;
            $cur = [];
            foreach($titlesEn as $key => $title) {
                if(empty($title) || in_array($title, $hiddenFields)) {
                    unset($titlesEn[$key]);
                    continue; //空白列或者系统列跳过
                }
                $cur[$title] = isset($line[$key]) ? trim($line[$key]) : null;
                if($cur[$title] === "" || is_null($cur[$title])) { //过滤全空白行
                    $nulls++;
                }
                else {
                    $cur[$title] = strval($cur[$title]); //所有都转为字符串
                }
            }
            if($nulls === count($titlesEn)) {
                continue;
            }
            $data[] = $cur;
        }
        //检查具体数据
        switch($orderType) {
            case Category::MULTI_INSTORAGE:
                $this->checkInstorage($data);
                break;
            case Category::MULTI_UP:
                $this->checkUp($data);
                break;
            case Category::MULTI_MODIFY:
                $this->checkModify($data);
                break;
        }

        //元数据处理
        $fieldsArr = $fields->map(function($item, $key) {
            return [
                "cname" => $item["field_cname"],
                "sname" => $item["field_sname"],
            ];
        })->all();
        $fieldsArr[] = [
            "cname" => "类型明细",
            "sname" => "category"
        ];

        $meta = [
            "pagination" => [
                "total" => count($data),
            ],
            "fields" => $fieldsArr
        ];

        return ["result" => $data, "meta" => $meta];
    }

    protected function processDict(&$row) {
        if(!empty($this->dictRow)) {
            foreach($this->dictRow as $field => $v) {
                $row[$field] = $this->processEachDict($v['field'], $v['value']);
            }
            $this->dictRow = [];
        }
    }

    protected function processEachDict($field, $value) {
        $cls = $this->dictRepo->getCls($field['dict_table'], $p_sname);
        //$model = Singleton::getInstance($cls);
        $model = new $cls;
        if(method_exists($model, "setRelatedFields")) {
            $model->setRelatedFields($p_sname);
        }

        $rfields = $model->getRelatedFields();
        if(empty($rfields)) {
            $where = [];
            if($cls == "\\App\\Models\\Assets\\Dict") {
                $where["field_id"] = $field['id'];
            }
            $id = $model->getByName($value,$where,self::AUTOINSERT);
            if(empty($id)) {
                throw new ApiException(Code::ERR_ASSETS_RELATE_NOT_EXISTS, [$field['field_cname'], $value]);
            }
            return $id;
        }
        else {
            $where = [];
            if($cls == "\\App\\Models\\Assets\\Dict") {
                $where["field_id"] = $field['id'];
            }
            foreach($rfields as $k => $v) {
                if(isset($this->dictRow[$v])) {
                    $dpField = $this->dictRow[$v]['field'];
                    $dpFieldValue = $this->dictRow[$v]['value'];
                    $where[$k] = $this->processEachDict($dpField, $dpFieldValue);
                }
                else {
                    $where[$k] = 0;
                }
            }
            $id = $model->getByName($value, $where, self::AUTOINSERT);
            if(empty($id)) {
                throw new ApiException(Code::ERR_ASSETS_RELATE_NOT_EXISTS, [$field['field_cname'], $value]);
            }
            return $id;
        }
    }

    /**
     * 检查字段
     * @param $field
     * @param $value
     */
    protected function checkFieldsValue($field, &$value, $validFields) {
        if(isset($validFields[$field])) {
            if(is_null($value) || $value === "") {
                return true;
            }
            if(!empty($validFields[$field]['dict_table'])) { //检查关联model
                /*$cacheKey = $validFields[$field]['dict_table']."_".$value;
                if(isset($this->fieldsCache[$cacheKey])) {
                    $value =  $this->fieldsCache[$cacheKey];
                    return true;
                }*/

                $this->dictRow[$field] = ["field" => $validFields[$field], "value" => $value];
            }
            else {
                switch($validFields[$field]['field_type']) {
                    case 5: //类型检查，时间转换
                        $date = strtotime($value);
                        if(false === $date) {
                            throw new ApiException(Code::ERR_ASSETS_FIELD_TYPE, [$validFields[$field]['field_cname'], $value]);
                        }
                        $value = date("Y-m-d",$date);
                        break;
                    case 4: //类型检查，日期转换
                        $date = strtotime($value);
                        if(false === $date) {
                            throw new ApiException(Code::ERR_ASSETS_FIELD_TYPE, [$validFields[$field]['field_cname'], $value]);
                        }
                        $value = date("Y-m-d H:i:s",$date);
                        break;
                    case 0:
                        if(!preg_match("/^\d+$/",$value)) {
                            throw new ApiException(Code::ERR_ASSETS_FIELD_TYPE, [$validFields[$field]['field_cname'], $value]);
                        }
                        $value = intval($value);
                        break;
                    case 1:
                        if(mb_strlen($value, "utf-8") > $validFields[$field]['field_length']) {
                            throw new ApiException(Code::ERR_ASSETS_FIELD_TYPE, [$validFields[$field]['field_cname'], $value]);
                        }
                        break;
                }
            }
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * 检查入库数据
     * 1. 入库判断类型明细
     * 2. 只使用正确的字段
     * 3. 判断资产编号存在的话是否有冲突
     * 4. 判断是否有必要字段没提供
     * @param $data
     * @param $category
     * @return array
     * @throws ApiException
     */
    protected function checkInstorage($data) {
        $numbers = []; //资产编号
        $inserts = []; //待入库数据

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
        }

        foreach($data as $value) {
            $fields = array_keys($value);
            $insert = $this->_getInsertData($fields, $value, $numbers);
            if(isset($limitCategories) && !in_array($insert["sub_category_id"], $limitCategories)) {
                throw new ApiException(Code::ERR_USER_CATEGORY);
            }

            $insert['state'] = Device::STATE_FREE; //跳过入库直接上架
            if(!isset($inserts[$insert["sub_category_id"]])) {
                $inserts[$insert["sub_category_id"]] = [];
            }
            $inserts[$insert["sub_category_id"]][] = $insert;
        }

        //检查numbers是否有重复
        if(count(array_unique($numbers)) != count($numbers)) {
            throw new ApiException(Code::ERR_NUMBER_DUP);
        }
        $ret = $this->device->getByNumber($numbers);
        if(!$ret->isEmpty()) {
            $numbers = $ret->pluck("number")->all();
            throw new ApiException(Code::ERR_NUMBER_EXISTS, $numbers);
        }

        return $inserts;
    }

    /**
     * 构建插入数据
     * @param $fields
     * @param $value
     * @param array $numbers
     * @return array
     * @throws ApiException
     */
    private function _getInsertData($fields, $value, &$numbers = []) {
        if (!in_array("category", $fields) || empty($value['category'])) {
            throw new ApiException(Code::ERR_CONTENT_CATE);
        }

        $validFields = $this->categoryRepo->getValidCategoryFields(null, $value['category'], $fields)->keyBy("field_sname")->toArray(); //["cpu","os"]
        if(empty($validFields)) {
            throw new ApiException(Code::ERR_CONTENT_CATE);
        }
        $categoryIds = $this->getCategoryId($value['category']); //获取分类id和pid stdClass Object{id:xx , pid: xx}

        if(empty($categoryIds)) {
            throw new ApiException(Code::ERR_CONTENT_CATE);
        }

        $insert = [];
        foreach($value as $k => $v) {
            if($k === "number" && !empty($v)) {
                $numbers[] = $v;
            }

            if($this->checkFieldsValue($k, $v, $validFields)) {
                $insert[$k] = $v;
            }

        }

        //处理dict_table中的联动数据
        $this->processDict($insert);

        $insert["category_id"] = $categoryIds->pid;
        $insert["sub_category_id"] = $categoryIds->id;
        $insert["created_at"] = date("Y-m-d H:i:s");
        $insert["updated_at"] = date("Y-m-d H:i:s");
        return $insert;
    }

    /**
     * 检查上架数据,允许跳过入库直接上架，自动完成入库过程。
     * 1. 入库判断类型明细
     * 2. 只使用正确的字段
     * 3. 判断资产编号存在的话是否有冲突
     * 4. 判断是否有必要字段没提供
     * 5. 检查状态是否是闲置
     * @param $data
     * @param $category
     * @return array
     * @throws ApiException
     */
    protected function checkUp($data) {
        $numbers = []; //资产编号
        $inserts = []; //待入库数据
        $updates = [];
        $dataUpdate = []; //待更新数据

        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
        }

        foreach($data as $value) {
            $fields = array_keys($value);

            if (isset($value['number']) && !empty($value['number'])) {
                //判断资产编号是否有效
                $numbers[] = $value['number'];
                $dataUpdate[] = $value;
            }
            else {
                //如果不存在，那么必须有资产分类
                $insert = $this->_getInsertData($fields, $value);
                if(isset($limitCategories) && !in_array($insert["sub_category_id"], $limitCategories)) {
                    throw new ApiException(Code::ERR_USER_CATEGORY);
                }
                $insert['state'] = Device::STATE_USE; //跳过入库直接上架
                if(!isset($inserts[$insert["sub_category_id"]])) {
                    $inserts[$insert["sub_category_id"]] = [];
                }
                $inserts[$insert["sub_category_id"]][] = $insert;
            }
        }

        //检查不存在的资产编号,可以是更新或者新增
        if(!empty($numbers)) {
            $where = ["state" => Device::STATE_FREE];
            $ret = $this->device->getByNumber($numbers, $where); //取出入库状态的资产
            $numberExists = $ret->pluck("number")->toArray();
            $numberInserts = array_diff($numbers, $numberExists);
            $arr = $ret->keyBy("number")->all();

            //需要新增的机器，查看是否在上架等其他状态存在
            if(count(array_unique($numberInserts)) != count($numberInserts)) {
                throw new ApiException(Code::ERR_NUMBER_DUP);
            }

            $ret = $this->device->getByNumber($numberInserts);
            if(!$ret->isEmpty()) {
                $numbers = $ret->pluck("number")->all();
                throw new ApiException(Code::ERR_NUMBER_EXISTS_UP, $numbers);
            }

            foreach($dataUpdate as $value) {
                $fields = array_keys($value);
                if(in_array($value['number'], $numberInserts)) {
                    //需要insert的资产
                    $insert = $this->_getInsertData($fields, $value);
                    $insert['state'] = Device::STATE_USE; //跳过入库直接上架
                    if(!isset($inserts[$insert["sub_category_id"]])) {
                        $inserts[$insert["sub_category_id"]] = [];
                    }
                    $inserts[$insert["sub_category_id"]][] = $insert;
                }
                else {
                    $categoryId = $arr[$value['number']]['sub_category_id'];
                    if(isset($limitCategories) && !in_array($categoryId, $limitCategories)) {
                        throw new ApiException(Code::ERR_USER_CATEGORY);
                    }

                    $validFields = $this->categoryRepo->getValidCategoryFields($categoryId,null, $fields)->keyBy("field_sname")->toArray(); //["cpu","os"]
                    $update = [];
                    foreach ($value as $k => $v) {
                        if($this->checkFieldsValue($k, $v, $validFields)) {
                            $update[$k] = $v;
                        }
                        if($k === "category") {
                            $categoryIds = $this->getCategoryId($value['category']);
                            $update["category_id"] = $categoryIds->pid;
                            $update["sub_category_id"] = $categoryIds->id;
                        }
                    }
                    $update['id'] = $arr[$value['number']]['id'];
                    $update['state'] = Device::STATE_USE;
                    $this->processDict($update);
                    $updates[] = $update;
                }
            }
        }

        return ["inserts" => $inserts, "updates" => $updates];
    }

    /**
     * 检查资产变更
     * @param $data
     * @return array
     * @throws ApiException
     */
    protected function checkModify($data) {
        $numbers = []; //资产编号
        $updates = [];
        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $limitCategories = $this->userRepository->getCategories();
        }

        foreach($data as $value) {
            if (!isset($value['number']) || empty($value['number'])) {
                throw new ApiException(Code::ERR_CONTENT_NUMBER);
            } else {
                $numbers[] = $value['number'];
            }
        }
        $where = ["state" => Device::STATE_USE]; //使用中的资产
        $ret = $this->device->getByNumber($numbers, $where);
        $numberExists = $ret->pluck("number")->toArray();
        $diffs = array_diff($numbers, $numberExists);
        if(!empty($diffs)) {
            throw new ApiException(Code::ERR_NUMBER_NOT_EXISTS, array_values($diffs));
        }
        $arr = $ret->keyBy("number")->all();
        foreach($data as $value) {
            $fields = array_keys($value);
            $categoryId = $arr[$value['number']]['sub_category_id'];
            if(isset($limitCategories) && !in_array($categoryId, $limitCategories)) {
                throw new ApiException(Code::ERR_USER_CATEGORY);
            }
            $validFields = $this->categoryRepo->getValidCategoryFields($categoryId, null, $fields)->keyBy("field_sname")->toArray(); //["cpu","os"]

            $update = [];
            foreach ($value as $k => $v) {
                //if (in_array($k, $validFields)) { //判断字段是否正确
                if($k !== "state" && $this->checkFieldsValue($k, $v, $validFields)) {
                    $update[$k] = $v;
                }
                if($k === "category") {
                    $categoryIds = $this->getCategoryId($value['category']);
                    $update["category_id"] = $categoryIds->pid;
                    $update["sub_category_id"] = $categoryIds->id;
                }
            }

            $update['id'] = $arr[$value['number']]['id'];
            $this->processDict($update);
            $updates[] = $update;
        }

        return $updates;
    }


    /**
     * 检查是否有资产编号，没有则自动补全
     * @param $data
     */
    protected function genNumberMulti(&$data) {
        $id = 0;
        $cateList = [];
        foreach($data as &$v) {
            if(!isset($v['number']) || empty($v['number'])) {
                if(!isset($cateList[$v['sub_category_id']])) {
                    $numberInfo = $this->deviceRepo->genNumber($v['sub_category_id']);
                    $current = $numberInfo['current'];
                    $prefix = $numberInfo['prefix'];
                    $cateList[$v['sub_category_id']] = $prefix;
                }
                else {
                    $prefix = $cateList[$v['sub_category_id']];
                }

                if(0 === $id) {
                    $id = $current;
                }
                else {
                    $id++;
                }
                $v['number'] = sprintf("%s-%05s", $prefix, $id);
            }
        }
    }


    /**
     * 批量导入保存
     * @param $data
     * @param $orderType
     * @throws ApiException
     */
    public function saveData($data, $orderType) {
        switch($orderType) {
            case Category::MULTI_INSTORAGE:
                $data = $this->checkInstorage($data);
                $numbers = [];
                DB::beginTransaction();
                foreach ($data as $categoryId => &$values) {
                    $this->genNumberMulti($values);
                    $this->device->insert($values);
//                    foreach($values as $value) {
//                        $numbers[] = $value['number'];
//                    }
                }

//                $ids = $this->device->whereIn("number", $numbers)->get(["id","number"])->pluck("id","number")->toArray();
//                $data = ['inserts' => $data];
//                $this->eventsRepo->addMultiStorage($ids, $orderType, $data);//不记录事件

                DB::commit();
                break;
            case Category::MULTI_UP:
                //允许对于未入库的资产直接上架
                $data = $this->checkUp($data);
                $numbers = [];
                DB::beginTransaction();

                foreach($data['inserts'] as $categoryId => $values) {
                    $this->genNumberMulti($values);
                    $this->device->insert($values);
                    foreach($values as $value) {
                        $numbers[] = $value['number'];
                    }
                }
                foreach($data['updates'] as $value) {
                    $model = $this->device->findOrFail($value['id']);
                    $model->fill($value)->save();
                    $numbers[] = $model->number;
                }
                $ids = $this->device->whereIn("number", $numbers)->get(["id","number"])->pluck("id","number")->toArray();
                $this->eventsRepo->addMulti($ids, $orderType, $data);

                DB::commit();
                break;
            case Category::MULTI_MODIFY://此功能暂未启用
                $data = $this->checkModify($data);
                DB::beginTransaction();
                foreach($data as $value) {
                    $model = $this->device->findOrFail($value['id']);
                    $model->fill($value)->save();
                    $numbers[] = $model->number;
                }

                $ids = $this->device->whereIn("number", $numbers)->get(["id","number"])->pluck("id","number")->toArray();
                $data = [
                    "updates" => $data
                ];
                $this->eventsRepo->addMulti($ids, $orderType, $data);
                DB::commit();
                break;
        }
    }


    /**
     * 更新 RFID 盘点标签
     * @param $content EXCEL数据表中数据
     * @param $orderType
     * @return array
     * @throws ApiException
     */
    public function saveRfid($content, $orderType){
        // 去掉 excel title
        unset($content[0]);
        $error = [];
        $succ = [];
        $succTotal = [];
        $errorTotal = [];
        
        // 计算Excel表格中，是否有相同的标签号
        $rfid_column = array_column($content,1);
        $interst_column = array_unique($rfid_column);
        $diff = array_diff_assoc($rfid_column,$interst_column);
        $diff_info = implode($diff,',');
        if(!empty($diff_info)){
            throw new ApiException(Code::ERR_PARAMS, ["导入的数据中,有重复的标签号:{$diff_info}"]);
        }

        $all_rfid = $this->device->select('rfid')->get()->pluck('rfid')->toArray();
        foreach ($content as $key => $val){
            if(!is_array($val)) throw new ApiException(Code::ERR_FORMAT_DATA);
            if(isset($val[0])){
               if(empty($val[0])){
                   $errorTotal[] = ['line' => $key + 1, 'data' => $val];
               }else{
                  // 批量更新
                    if(in_array($val[1],$all_rfid)){
                        throw new ApiException(Code::ERR_PARAMS, ["不可重复添加已有标签号：{$val[1]}"]);
                    }

                   $res = $this->device->where('number',$val[0])->update(['rfid' => $val[1]]);
                   $succTotal[] = $res;
               }
            }else{
                $errorTotal[] = ['line' => $key + 1, 'data' => $val];
            }
        }

        $error['error_total'] = count($errorTotal);
        $error['error_msg'] = $errorTotal;
        $succ['success_total'] = count($succTotal);
        return array_merge($succ,$error);
    }


}