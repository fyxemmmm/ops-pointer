<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class MenuRequest extends Request
{

    public function messages()
    {
        return [
            "required" => "缺少字段 [:attribute]",
            "integer"   => "参数格式错误，必须为整数 [:attribute]",
            "string"   => "参数格式错误，必须为字符串 [:attribute]",
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
                "pid" => "required|integer",
                "name" => "required|string",
                "path" => "required|string",
            ],
            "postEdit" => [
                "id" => "required|int",
                "name" => "required|string",
                "path" => "required|string",
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
