<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class FieldsRequest extends Request
{

    public function messages()
    {
        return [
            "required" => "缺少字段 [:attribute]",
            "string"   => "参数格式错误，必须为字符串 [:attribute]",
            "integer"   => "参数格式错误，必须为整数 [:attribute]",
            "unique"   => "字段名重复，请检查中文和英文字段名称",
            "exists"   => "记录不存在 [:attribute]",
            "max"      => "数据超过长度限制 [:attribute]",
            "min"      => "数据小于长度限制 [:attribute]",
            "mimes"    => "[:attribute] 文件类别不匹配，期望文件为：:values",
            "password.regex"    => "密码过于简单，要求8位以上，并且含有英文数字或符号",
            "regex"    => "数据格式不正确",
            "ip"       => "IP地址格式不正确",
            "size"    => "手机号长度不正确，请输入11位"
        ];

    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */

    public function rules()
    {
        $rules = [
            "postAdd" => [
                "sname" => "required|unique:assets_fields,field_sname",
                "cname" => "required|unique:assets_fields,field_cname",
                "type" => "required|in:0,1,2,3,4",
                "length" => "int",
                "typeId" => "required|exists:assets_fields_type,id",
                "system" => "in:0,1",
            ],
            "postEdit" => [
                "id" => "required|int",
                'cname' => [
                    "required",
                    Rule::unique('assets_fields',"field_cname")->ignore($this->input("id"))
                ],
            ],
            "postDel" => [
                "id" => "required|int",
            ],
            'getEdit' => [
                "id" => "required|int",
            ],

        ];
        return $this->useRule($rules);
    }
}
