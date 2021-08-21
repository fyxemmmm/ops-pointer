<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/22
 * Time: 10:35
 */

namespace App\Http\Requests;

use App\Models\Code;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Support\GValue;

class Request extends FormRequest {

    public function messages()
    {
        return [
            "required" => "缺少必要参数，请检查",
            "string"   => "参数格式错误，必须为字符串",
            "integer"   => "参数格式错误，必须为整数",
            "unique"   => "数据已存在，请检查",
            "phone.unique"   => "该手机号已存在",
            "exists"   => "记录不存在",
            "max"      => "参数超过长度限制",
            "min"      => "参数小于长度限制",
            //"mimes"    => "[:attribute] 文件类别不匹配，期望文件为：:values",
            "mimes"    => "文件类别不匹配，期望文件为：:values",
            "password.regex"    => "密码过于简单，要求8位以上，并且含有英文数字或符号",
            "password.min"    => "密码长度过短",
            "regex"    => "数据格式不正确",
            "ip"       => "IP地址格式不正确",
            "phone.size"    => "手机号长度不正确，请输入11位",
        ];

    }

    /**
     * 根据action自动选中使用的rule
     * @param $rules
     * @return array
     */
    protected function useRule($rules) {
        if(array_key_exists(GValue::$action, $rules)) {
            return $rules[GValue::$action];
        }
        else {
            return [];
        }
    }

}