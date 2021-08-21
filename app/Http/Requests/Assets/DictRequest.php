<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class DictRequest extends Request
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
                "name" => [
                    "required",
                    Rule::unique('assets_dict')->where(function ($query) {
                        $query->where("field_id","=", $this->input("fieldId"));
                        return $query->whereNull('deleted_at');
                    })
                ],
                "fieldId" => "required|int",
            ],
            "getAdd" => [
                "fieldId" => "required|int",
            ],
            "getChildren" => [
                "id" => "required|exists:assets_dict,id,deleted_at,NULL",
            ],
            "postDel" => [
                "id"  => "required|exists:assets_dict,id,deleted_at,NULL"
            ],
            "getEdit" => [
                "id"  => "required|exists:assets_dict,id,deleted_at,NULL"
            ],
            "postEdit" => [
                "id"  => "required|exists:assets_dict,id,deleted_at,NULL",
                "name" =>  [
                    "required",
                    Rule::unique('assets_dict')->ignore($this->input("id"))->where(function ($query) {
                        $query->where("pid","=", $this->input("pid",0));
                        return $query->whereNull('deleted_at');
                    })
                ],
            ],
            "getList" => [
                "search"  => "max:32",
                "fieldId" => "required|int",
            ],
        ];
        return $this->useRule($rules);
    }
}
