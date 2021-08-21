<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class MapRequest extends Request
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
            "postAreaAdd" => [
                "name" => "required",
            ],
            "postRegionConfig" => [
                "enginerooms" => "exists:assets_enginerooms,id,deleted_at,NULL",
            ],
            "postLinkEdit" => [
                "enginerooms" => "exists:assets_enginerooms,id,deleted_at,NULL",
                "id" => "exists:map_region_config,id",
            ],
            "getLinkList" => [
                "id" => "required",
            ],
            "postRegionDel" => [
                "id" => "exists:map_region_config,id",
            ],
        ];
        return $this->useRule($rules);
    }
}
