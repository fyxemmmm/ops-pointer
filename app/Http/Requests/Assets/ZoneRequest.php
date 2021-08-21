<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ZoneRequest extends Request
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
                    Rule::unique('assets_zone')->where(function ($query) {
                        return $query->whereNull('deleted_at');
                    })
                    ],
            ],
            "postDel" => [
                "id"  => "required|exists:assets_zone,id,deleted_at,NULL"
            ],
            "getEdit" => [
                "id"  => "required|exists:assets_zone,id,deleted_at,NULL"
            ],
            "postEdit" => [
                "id"  => "required|exists:assets_zone,id,deleted_at,NULL",
                "name" =>  [
                    "required",
                    Rule::unique('assets_zone')->ignore($this->input("id"))->where(function ($query) {
                        return $query->whereNull('deleted_at');
                    })
                ],
            ],
            "getList" => [
                "search"  => "max:32"
            ],
        ];
        return $this->useRule($rules);
    }
}
