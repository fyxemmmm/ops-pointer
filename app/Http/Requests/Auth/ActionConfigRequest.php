<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ActionConfigRequest extends Request
{

    public function messages()
    {
        return [
            "required" => "缺少字段 [:attribute]",
            "integer"   => "参数格式错误，必须为整数 [:attribute]",
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
            "postEdit" => [
                "id" => "required|int",
                "status" => "required",
            ],
            "postAdd" => [
                "msg" => "required",
                "name" => "required",
                "desc" => "required",
                "type" => "required",
                "key" => "required",
            ],
        ];
        return $this->useRule($rules);
    }
}
