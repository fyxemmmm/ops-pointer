<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;
use App\Models\Code;
use App\Exceptions\ApiException;

class Category extends Model
{

    protected $table = "assets_category";

    public $timestamps = false;

    protected $fillable = [
        "pid",
        "name",
        "shortname",
        "icon"
    ];

    public function fields() {
        return $this->belongsToMany("App\Models\Assets\Fields","assets_category_fields","category_id","field_id");
    }

    public function parent() {
        return $this->hasOne("App\Models\Assets\Category","id","pid")->withDefault();
    }


    /**
     * 取id和pid
     * @param $category
     * @return Model|null|static
     */
    public function getIdPidByName($category) {
        return DB::table("assets_category as A")->
        join("assets_category as B","A.pid","=","B.id")
            ->where(["A.name" => $category])->select("A.id","A.pid")->first();
    }

    public function getIdPidById($categoryId) {
        return DB::table("assets_category as A")->
        join("assets_category as B","A.pid","=","B.id")
            ->where(["A.id" => $categoryId])->select("A.id","A.pid")->first();
    }

    public function getList($categoryIds = [],$cid=0) {
        $model = DB::table("assets_category as A")->
        leftJoin("assets_category as B","A.id","=","B.pid")
            ->select("A.id","A.name","A.shortname", "B.id as cid", "B.name as cname","B.shortname as cshortname","B.icon as cicon", "A.icon")
            ->where("A.pid","=",0);
        if($cid) {
            $model = $model->where("A.id","=",$cid);
        }
        if(!empty($categoryIds)) {
            $model = $model->whereIn("B.id",$categoryIds);
        }
        return $model->get();
    }

    public function getChildren($pid) {
        return $this->where(["pid" => $pid])->get();
    }


    /**
     * 获取大小分类
     * @param array $categories
     * @param int $cid
     * @return array
     */
    public function getCategoryList($categories = [],$cid=0) {
        $data = $this->getList($categories,$cid)->groupBy("id")->all();
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
     * 获取字段列表
     * @param $categoryId
     * @return mixed
     */
    public function getFields($categoryId) {
        return $this->findOrFail($categoryId)->fields()->get();
    }

    //检查
    public function check($sub_category_id, $data) {
        $fields = $this->getFields($sub_category_id);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            $cname = $field->field_cname;
            if(array_key_exists($key,$data)) {
                $length = $field->field_length;
                switch($field->field_type) {
                    case 0: //todo 增加枚举类型
                        if(empty($field->dict_table) && empty($field->field_dict) && !empty($length)) {
                            if($data[$key] > $length) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_MAX, [$cname, $length]);
                            }
                        }
                        break;
                    case 1:
                        if(!empty($length)) {
                            if(mb_strlen($data[$key]) > $length) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_LENGTH, [$cname, $length]);
                            }
                        }
                        break;
                    case 2:
                        $dict = $field->field_dict;
                        $dict = json_decode($dict, true);
                        if(!empty($dict)) {
                            if(!isset($dict[$data[$key]])) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_DICT, [$cname]);
                            }
                        }
                        break;
                    case 3:
                        if(!empty($length)) {
                            if($data[$key] > $length) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_MAX, [$cname, $length]);
                            }
                        }
                        break;
                    case 4:
                        if(!strtotime($data[$key])) {
                            throw new ApiException(Code::ERR_EVENT_FIELD_DATE, [$cname]);
                        }
                        break;
                }
            }
        }
    }

}
