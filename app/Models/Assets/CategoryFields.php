<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Log;
use App\Models\Code;
use App\Exceptions\ApiException;

class CategoryFields extends Model
{

    protected $table = "assets_category_fields";

    public $timestamps = false;

    const REQUIRE_HIDDEN = 0; //不显示
    const REQUIRE_NEED = 1; //必须
    const REQUIRE_SELECT = 2; //选填
    const REQUIRE_READ = 3; //只读
    const REQUIRE_CLEAR = 4; //清除

    protected $fillable = [
        "category_id",
        "field_id",
        "require1",
        "require2",
        "require3",
        "require5",
        "is_show",
    ];


    //不显示，且不可配置
    public $hiddenFields = [
       // "state",
       // "intime",
       // "warranty_state",
       // "warranty_time",
    ];

    //不可删除字段，但可配置
    public $nodelFields = [
        "number",
        "name",
        "ip"
    ];

    public function fields() {
        return $this->hasOne("App\Models\Assets\Fields","id", "field_id");
    }

    public function getCategoryRequire($categoryId=0,$requireId=''){
        $ret = [];
        if($categoryId && $requireId){
            $categoryRequire = $this->with("fields")->where(["category_id" => $categoryId])->get();
            foreach ($categoryRequire as $v) {
                $field = $v->fields;
                if (empty($field)) {
                    Log::error("getCategoryRequire field not exist.field_id:" . $v->field_id);
                    throw new ApiException(Code::ERR_SERVER_INTERNAL);
                }
                if (in_array($field->field_sname, $this->hiddenFields)) { //跳过不可编辑字段
                    continue;
                }
                $requireStr = "require".$requireId;
                $ret[$field->field_sname] = [
                    "require" => $v->$requireStr,
                    "cname" => $field->field_cname
                ];
            }
        }
        return $ret;
    }

    /**
     * 判断分类属于机房或者科室
     * @param $categoryId
     * @return string  er|dt|null
     */
    public function getRoomType($categoryId) {
        $data = $this->join("assets_fields","assets_category_fields.field_id","=","assets_fields.id")
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
