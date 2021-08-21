<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class CategoryRequest extends Request
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
            "postAdd" => [
                "pid" => "required|int",
                "name" => "required|unique:assets_category",
                "shortname" => "required|unique:assets_category",
            ],
            "postEdit" => [
                "id" => "required|int",
                'name' => [
                    "required",
                    Rule::unique('assets_category',"name")->ignore($this->input("id"))
                ],
                'shortname' => [
                    "required",
                    Rule::unique('assets_category',"shortname")->ignore($this->input("id"))
                ],
            ],
            "postDel" => [
                "id" => "required|int",
            ],
        ];
        return $this->useRule($rules);
    }
}
