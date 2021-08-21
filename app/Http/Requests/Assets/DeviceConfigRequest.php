<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class DeviceConfigRequest extends Request
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
                "assetId" => "required|exists:assets_device,id,deleted_at,NULL",
                "title" => "required",
                "conf" => "required",
            ],
            "getList" => [
                "assetId" => "required",
            ],
            "getView" => [
                "assetId" => "exists:assets_device_config,asset_id,deleted_at,NULL",
                "id"    => "exists:assets_device_config,id,deleted_at,NULL"
            ]
        ];
        return $this->useRule($rules);
    }
}
