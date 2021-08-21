<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

class Fields extends Model
{
    protected $table = "assets_fields";

    protected $fillable = [
        "field_sname",
        "field_cname",
        "field_type",
        "field_length",
        "field_desc",
        "field_default",
        "field_type_id",
        "field_dict",
        "dict_table",
        "url",
        "system",
    ];

    const TYPE_INT = 0;     //整型
    const TYPE_STR = 1;     //字符串
    const TYPE_DICT = 2;    //枚举
    const TYPE_FLOAT = 3;   //浮点
    const TYPE_DATETIME = 4;    //时间
    const TYPE_DATE = 5;        //日期
    const TYPE_PASSWORD = 6;    //密码

    public function category()
    {
        return $this->belongsToMany("App\Models\Assets\Category","assets_category_fields","category_id","field_id");

    }

    public function type() {
        return $this->hasOne("App\Models\Assets\FieldsType","id", "field_type_id");
    }


}
