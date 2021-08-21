<?php

namespace App\Http\Requests\Setting;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;


class LayoutRequest extends Request
{

    public function messages()
    {
        return [
            "required" => "缺少字段 [:attribute]",
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
            'postDel' => [
                "id" => "required|integer|min:1",
            ],
            'postAdd' => [
                "name" => "required|string",
                "content" => "required",
            ],
            'postEdit' => [
                "id" => "required|integer|min:1",
                "name" => "required|string",
                "content" => "required",
            ],
            'postSetDefault' => [
                "id" => "required|integer|min:1",
            ],
            'getDetail' => [
//                "id" => "required|integer|min:1",
            ],
            'getList' => [

            ]
        ];
        return $this->useRule($rules);
    }

}
