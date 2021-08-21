<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\Category;
use App\Exceptions\ApiException;
use App\Models\Code;
use App\Models\Assets\Device;
use App\Models\Assets\FieldsType;
use App\Models\Assets\CategoryFields;
use App\Models\Assets\Fields;
use App\Models\Workflow\Category as EventCategory;
use App\Models\Menu;
use App\Repositories\Auth\UserRepository;
use DB;
use Log;


class CategoryRepository extends BaseRepository
{

    protected $validFieldsCache = []; //有效字段缓存
    protected $categoryCache = [] ;
    protected $categoryIdCache = [] ;
    protected $fieldsTypeModel;

    public function __construct(Category $categoryModel,
                                FieldsType $fieldsTypeModel,
                                CategoryFields $categoryFieldsModel,
                                Device $deviceModel,
                                Fields $fieldsModel,
                                UserRepository $userRepository,
                                DictRepository $dictRepository,
                                Menu $menuModel
    )
    {
        $this->model = $categoryModel;
        $this->fieldsTypeModel = $fieldsTypeModel;
        $this->categoryFieldsModel = $categoryFieldsModel;
        $this->deviceModel = $deviceModel;
        $this->fieldsModel = $fieldsModel;
        $this->userRepository = $userRepository;
        $this->menuModel = $menuModel;
        $this->dictRepository = $dictRepository;
    }

    public function getList($all = 0,$cid=0) {
        $categories = [];
        if($all == 0 && ($this->userRepository->isEngineer() || $this->userRepository->isManager())) {
            $categories = $this->userRepository->getCategories();
        }
        $data = $this->model->getList($categories,$cid)->groupBy("id")->all();
        $result = [];
        foreach($data as $key => $value) {
            $sub = [];
            foreach($value as $v){
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
        return ["result" => $result];
    }

    /**
     * 根据分类ID取分类信息与字段
     * @param string $categoryId
     * @param string $categoryName
     * @param array $fields
     * @return mixed
     */
    public function getValidCategoryFields($categoryId = "", $categoryName = "", array $fields = []) {
        if(!empty($categoryId)) {
            $k = crc32($categoryId."__".join("__", $fields));
        }
        else if(!empty($categoryName)){
            $k = crc32($categoryName."__".join("__", $fields));
        }
        else {
            throw new ApiException(Code::ERR_CONTENT_CATE);
        }

        if(isset($this->validFieldsCache[$k])) {
            return $this->validFieldsCache[$k];
        }

        if(!empty($categoryId)) {
            $categoryInfo = $this->model->findOrFail($categoryId);
        }
        else {
            $categoryInfo = $this->model->where(["name" => $categoryName])->first();
            if(empty($categoryInfo)) {
                throw new ApiException(Code::ERR_CONTENT_CATE);
            }
        }

        if(!empty($fields)) {
            $this->validFieldsCache[$k] = $categoryInfo->fields()->whereIn("field_sname", $fields)->get();
        }
        else {
            $this->validFieldsCache[$k] = $categoryInfo->fields()->get();
        }

        return $this->validFieldsCache[$k];
    }

    /**
     * 取指定类别的ID和PID
     * @param $category
     * @return \Illuminate\Database\Eloquent\Model|mixed|null|static
     */
    public function getIdPidByName($category) {
        if(isset($this->categoryCache[$category])) {
            return $this->categoryCache[$category];
        }
        $ret = $this->model->getIdPidByName($category);
        if(empty($ret)) {
            return $ret;
        }
        $this->categoryCache[$category] = $ret;
        return $this->categoryCache[$category];
    }

    public function getIdPidById($category) {
        if(isset($this->categoryIdCache[$category])) {
            return $this->categoryIdCache[$category];
        }
        $ret = $this->model->getIdPidById($category);
        if(empty($ret)) {
            return $ret;
        }
        $this->categoryIdCache[$category] = $ret;
        return $this->categoryIdCache[$category];
    }

    /**
     * 获取字段列表
     * @param $categoryId
     * @return mixed
     */
    public function getFields($categoryId) {
        return $this->model->findOrFail($categoryId)
            ->fields()->get();
    }

    public function getDefaultFieldsList($fields)
    {
        $fieldsList = $this->fieldsModel->whereIn("field_sname",$fields)->orderBy("id","asc")->get()->groupBy("field_type_id")->toArray();
        $fieldsType = $this->fieldsTypeModel->get()->pluck("name", "id")->all();
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
                    "checked" => 1

                ];

                if(!empty($vv['field_dict']) || !empty($vv['dict_table'])) {
                    $child['tname'] = $vv['field_sname']."_msg";
                }
                if(!empty($vv['url'])) {
                    $child['url'] = $vv['url'];
                }
                if ($vv['field_sname'] === "state") {
                    $child['options'] = [];
                    foreach(Device::$stateMsg as $value => $text) {
                        $child['options'][] = [
                            "text" => $text,
                            "value" => $value
                        ];
                    }
                }
                if(!empty($vv['dict_table'])) {
                    $child['options'] = $this->dictRepository->getOptions($vv['dict_table'], $vv['field_sname']);
                }

                $children[] = $child;
            }
            $cur['children'] = $children;
            $data[] = $cur;
        }
        return $data;
    }


    /**
     * 获取字段列表,分组
     * @param $category
     * @param $fields []
     * @return array
     */
    public function getFieldsList($categoryId, $fields = null)
    {
        $model = $this->model->findOrFail($categoryId);
        $fieldsList = $model->fields()->get()->groupBy("field_type_id")->toArray();

        $fieldsType = $this->fieldsTypeModel->get()->pluck("name", "id")->all();
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
                }

                $children[] = $child;
            }
            $cur['children'] = $children;
            $data[] = $cur;
        }
        return $data;
    }


    /**
     * 获取入库和上架的必要字段
     * @param $categoryId
     * @param int $eventCategoryId
     * @return array
     */
    public function getCategoryRequire($categoryId, $eventCategoryId = 0){
        $ret = [];
        $requireId = null;
        switch($eventCategoryId) {
            case EventCategory::INSTORAGE:
                $requireId = "require1";
                break;
            case EventCategory::UP:
                $requireId = "require2";
                break;
            case EventCategory::MODIFY:
                $requireId = "require3";
                break;
            case EventCategory::DOWN:
                $requireId = "require5";
                break;
            default:
                return $ret;
        }

        return $this->categoryFieldsModel->getCategoryRequire($categoryId,$requireId);
    }

    /**
     * 添加资产分类
     * @param $request
     */
    public function addCategory($request) {
        if($request->input("pid") != 0) {
            $menuResponse = $this->menuModel->where(['category_id' => $request->input("pid")])->first();
            if(!$menuResponse){
                Code::setCode(Code::ERR_NUMBER_NOT_EXISTS);
                return;
            }
            // $categoryResponse = $this->getById($request->input("pid"));
        }else{
            $menuResponse['id'] = 2;
        }

        DB::beginTransaction();
        $categoryResult = $this->store($request->input());
        $menuResult = $this->menuModel->create([
          'pid' => $menuResponse['id'],
          'name' => $request->input("name"),
          'path' => $request->input("path"),
          'icon' => $request->input("icon"),
          'category_id' => $categoryResult['id'],
        ]);
        DB::commit();
    }

    /**
     * 删除资产分类，只能删除该资产类别下没有资产的分类
     * @param $request
     * @return mixed|void
     */
    public function delCategory($request) {
        $id = $request->input("id");
        $category = $this->getById($id);
        if($category->pid === 0) {
            $children = $this->model->getChildren($id);
            if($children->isEmpty()) {
                userlog("删除了资产分类：".$category->name);
                DB::beginTransaction();
                $this->del($id);
                $this->menuModel->where(['category_id' => $id])->delete();
                DB::commit();
            }
            else{
                Code::setCode(Code::ERR_SUBCATE_EXIST);
                return;
            }
        }
        else {
            //检查该分类是否还有资产
            $cnt = $this->deviceModel->where(["sub_category_id" => $id])->count();
            if($cnt === 0) {
                userlog("删除了资产分类：".$category->name);
                DB::beginTransaction();
                $this->del($id);
                $this->menuModel->where(['category_id' => $id])->delete();
                DB::commit();
            }
            else {
                Code::setCode(Code::ERR_SUBCATE_DEVICE_EXIST);
                return;
            }
        }
    }

    /**
     * 修改资产分类名称
     * @param $request
     */
    public function editCategory($request) {
        $id = $request->input("id");
        $org = $this->getById($id);
        $pid = $request->input("pid");
        //不允许变更PID
        // var_dump($pid ,$org->pid);exit;
        if($pid != $org->pid) {
            Code::setCode(Code::ERR_SUBCATE_PID);
            return;
        }

        userlog("修改了资产分类：".$org->name);
        $where = array('category_id' => $id);
        $param = [
            'name' => $request->input("name"),
            'icon' => $request->input("icon")
        ];
        // var_dump($param);exit;
        DB::beginTransaction();
        $this->update($request->input("id"), $request->input());
        $this->menuModel->where($where)->update($param);
        DB::commit();
    }

    public function viewCategory($id) {
        $data = $this->getById($id);
        return $data;
    }

    /**
     * 更新某资产类别下的字段
     * @param $request
     */
    public function modifyFields($request) {
        $edits = $request->input("edits");
        $inserts = $request->input("inserts");
        $dels = $request->input("dels");
        $name = null;

        //检查同一分类字段重复
        $checks = [];
        $fieldIds = [];
        foreach($inserts as $v) {
            $key = $v['category_id']."_".$v['field_id'];
            if(in_array($key, $checks)) {
                Code::setCode(Code::ERR_DUP_FIELD);
                return;
            }

            if(!isset($fieldIds[$v['category_id']])) {
                $fieldIds[$v['category_id']] = [];
            }
            $fieldIds[$v['category_id']][] = $v['field_id'];

            $checks[] = $key;
        }

        //检查是否已有数据
        foreach($fieldIds as $categoryId => $v) {
            $ret = $this->categoryFieldsModel->where("category_id", "=", $categoryId)->whereIn("field_id", $v)->get();
            if($ret->count() > 0) {
                Code::setCode(Code::ERR_DUP_FIELD);
                return;
            }
        }

        //$delId = $this->fieldsModel->whereIn("field_sname",$this->categoryFieldsModel->nodelFields)->get(["id"])->pluck("id")->toArray();
        //todo 限制不可删除字段
        DB::beginTransaction();
        foreach($dels as $del) {
            if(empty($name)) {
                $result = $this->categoryFieldsModel->where("id","=",$del)->first();
                $category = $this->getById($result->category_id);
                $name = $category->name;
            }
            $this->categoryFieldsModel->where("id","=",$del)->delete();
        }

        foreach($edits as $edit) {
            $id = $edit["id"];
            if(empty($name)) {
                $result = $this->categoryFieldsModel->where("id","=",$id)->first();
                $category = $this->getById($result->category_id);
                $name = $category->name;
            }
            $this->categoryFieldsModel->where("id", "=", $id)->update($edit) ;
        }

        foreach($inserts as $insert) {
            if(empty($insert["field_id"])) continue;
            if(empty($name)) {
                $category = $this->getById($insert['category_id']);
                $name = $category->name;
            }

            $this->categoryFieldsModel->insert($insert);
        }

        DB::commit();
        userlog("对资产类别 $name 下的字段进行了编辑");
        return;
    }

    public function getCategoryFields($request) {
        $id = $this->fieldsModel->whereIn("field_sname",$this->categoryFieldsModel->hiddenFields)->get(["id"])->pluck("id")->toArray();
        return $this->categoryFieldsModel->with('fields')->where("category_id","=",$request->categoryId)->get()
            ->filter(function ($value, $key) use($id) {
                return !in_array($value->field_id, $id);
        });

    }


    /**
     * 判断分类属于机房或者科室
     * @param $categoryId
     * @return string  er|dt|null
     */
    public function getRoomType($categoryId) {
        $data = $this->categoryFieldsModel->join("assets_fields","assets_category_fields.field_id","=","assets_fields.id")
            ->where("category_id","=",$categoryId)->select("field_sname")->get()->pluck("field_sname")->toArray();
        if(in_array(\ConstInc::ENGINEROOM, $data)) {
            return "er";
        }
        if(in_array(\ConstInc::DEPARTMENT, $data)) {
            return "dt";
        }
        return null;
    }

}