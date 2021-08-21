<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class BuildingRequest extends Request
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
                    Rule::unique('assets_building')->where(function ($query) {
                        $query->where("zone_id","=", $this->input("zoneId",0));
                        return $query->whereNull('deleted_at');
                    })
                ],
                "zoneId" => "required|exists:assets_zone,id,deleted_at,NULL",
            ],
            "postDel" => [
                "id"  => "required|exists:assets_building,id,deleted_at,NULL"
            ],
            "getEdit" => [
                "id"  => "required|exists:assets_building,id,deleted_at,NULL"
            ],
            "postEdit" => [
                "id"  => "required|exists:assets_building,id,deleted_at,NULL",
                "zoneId" => "required|exists:assets_zone,id,deleted_at,NULL",
                "name" =>  [
                    "required",
                    Rule::unique('assets_building')->ignore($this->input("id"))->where(function ($query) {
                        $query->where("zone_id","=", $this->input("zoneId",0));
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
