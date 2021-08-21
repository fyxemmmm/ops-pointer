<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class DevicePortRequest extends Request
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
            "getList" => [
                "assetId" => "required",
                "type" => "in:0,1",
            ],
            "postConnect" => [
                "type" => "required|in:0,1",
                "assetId" => "required|int",
                "port" => "required|int|min:1",
                //"ip" => "ip",
                "remoteAssetId" => "required|int",
                "remotePort" => "required|int|min:1",
            ],
            "postDefaultConnect" => [
                "assetId" => "required|int",
                "port" => "required",
                "remoteAssetId" => "required|int",
                "remotePort" => "required",
            ],
            "postDisonnect" => [
                "type" => "required|in:0,1",
                "assetId" => "required|int",
                "port" => "required|int|min:1",
            ],
        ];
        return $this->useRule($rules);
    }
}
