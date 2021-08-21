<?php
/**
 * 资产字段通用字典表
 * User: yanxiang
 * Date: 2019/4/1
 * Time: 22:30
 */

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FieldsInterface;

class Dict extends AbstractDict implements FieldsInterface
{

    use SoftDeletes;

    protected $cache = [];

    protected $table = "assets_dict";

    protected $fillable = [
        "name",
        "field_id",
        "remark",
        "pid"
    ];

    public function fields() {
        return $this->hasOne("App\Models\Assets\Fields","id", "field_id");
    }

    public function parent() {
        return $this->hasOne("App\Models\Assets\Dict","id", "pid")->withDefault();
    }


    /**
     * 取字典数据，针对fields表中的dict_table
     */
    public function getDict($field = "") {
        $data = $this->select("assets_dict.name", "assets_dict.id")->join("assets_fields as A","A.id","=","assets_dict.field_id")
            ->where("A.field_sname","=",$field)
            ->get()->pluck("name", "id")->toArray();
        $options = [];
        foreach($data as $id => $name) {
            $options[] = [
                "text" => $name,
                "value" => $id
            ];
        }
        return $options;
    }

    /**
     * @param null $field 实际field表字段名
     * @return array
     */
    public function getRelatedFields() {
        //数据库依赖字段表哪个字段
        if(empty($this->sname)) {
            return [];
        }
        else {
            return ["pid" => $this->sname];
        }
    }

}
