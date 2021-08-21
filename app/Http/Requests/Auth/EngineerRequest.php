<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class EngineerRequest extends Request
{

    public function messages()
    {
        return [
            "required" => "缺少字段 [:attribute]",
            "string"   => "参数格式错误，必须为字符串 [:attribute]",
            "integer"   => "参数格式错误，必须为整数 [:attribute]",
            "unique"   => "提交的工种已存在",
            "exists"   => "记录不存在 [:attribute]",
            "max"      => "数据超过长度限制 [:attribute]",
            "min"      => "数据小于长度限制 [:attribute]",
            "mimes"    => "[:attribute] 文件类别不匹配，期望文件为：:values",
            "password.regex"    => "密码过于简单，要求8位以上，并且含有英文数字或符号",
            "regex"    => "数据格式不正确",
            "ip"       => "IP地址格式不正确"
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
                'name' => 'required|string|max:64|unique:engineers',
            ],
            "postEdit" => [
                'name' => [
                    "string",
                    Rule::unique('engineers')->ignore($this->input("id"))
                ],
                'id' => 'required',
            ],
            "postDel" => [
                'id' => 'required',
            ],
            "postCategoryBind" => [
                'id' => 'required',
            ],
        ];
        return $this->useRule($rules);
    }
}
