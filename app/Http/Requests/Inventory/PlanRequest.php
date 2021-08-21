<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Request;
use Illuminate\Validation\Rule;


class PlanRequest extends Request
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
            'postDel' => [
                "id" => "required|integer|min:1",
            ],
            'getPointOrScrap' => [
                "id" => "required|integer|min:1",
                "status" => "required|integer",
            ],
            'postAdd' => [
                "name" => "required|string",
                "location_flag" => "required|array",
                "asset_id" => "required|array",
            ],
            'postEdit' => [
                "id" => "required|integer|min:1",
                "name" => "required|string",
                "location_flag" => "required|array",
                "asset_id" => "array",
            ],
            'getDetail' => [
                "id" => "required|integer|min:1",
            ],
            'getDetailsAssetList' => [
                "id" => "required|integer|min:1",
            ],
            'postEditResult' => [
                "inventory_id" => "required|integer|min:1",
                "asset_id" => "required|integer|min:1",
                "result" => "required|integer|boolean",
            ],
        ];
        return $this->useRule($rules);
    }



}
