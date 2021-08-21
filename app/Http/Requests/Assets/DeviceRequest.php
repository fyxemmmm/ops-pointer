<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class DeviceRequest extends Request
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
            "getSummary" => [
                "engineroomId" => "exists:assets_enginerooms,id,deleted_at,NULL"
            ],
            "getList" => [
                //"category" => "required",
                "fields" => "string",
            ],
            "postDel" => [
                "assetId" => "required"
            ],
            "postTrashRestore" => [
                "assetId" => "required"
            ],
            "getView" => [
                "assetId" => "required"
            ],
            "postSearch" => [
                "s" => "required"
            ],
            "postRackUp" => [
                "rackId" => "required",
                "assetId" => "required",
                "position" => "required|min:1|max:50",
            ],
            "postCheckMonitor" => [
                "assetId" => "required"
            ],
            "postAddMonitor" => [
                "assetId" => "required",
                "host" => "required",
                "ip" => "required|ip",
                "port" => "required|int",
                "type" => "required|int",
                "groupId" => "required|int",
                "templateId" => "required|int",
            ],
            "postAddEmMonitor" => [
                "assetId" => "required",
                "emDeviceId" => "required"
            ],
            "getRackInfo" => [
                "assetId" => "required"
            ]
        ];
        return $this->useRule($rules);
    }
}
