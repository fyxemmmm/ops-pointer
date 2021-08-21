<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class CategoryFieldsRequest extends Request
{
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
            ],
            "postDel" => [
                "id" => "required|int",
            ],
            'getView' => [
                "category"
            ],
            "getList" => [
                "categoryId" => "required|exists:assets_category,id"
            ]
        ];
        return $this->useRule($rules);
    }
}
