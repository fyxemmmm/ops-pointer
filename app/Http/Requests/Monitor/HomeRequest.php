<?php

namespace App\Http\Requests\Monitor;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class HomeRequest extends Request
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
            "postAddDevice" => [
                "assetId" => "required|exists:assets_device,id,deleted_at,NULL",
                "ip" => "required|ip",
                "port" => "required|int",
                "read_community" => "required",
                //"write_community" => "required",
                "custom_name" => "string",
                "version" => "required|in:v1,v2,v3",
                "core" => "required|in:0,1,2,3",
                "level" => "required|in:1,2,3,4,5"
            ],
            "getUpdateDevice" => [
                "assetId" => "required|exists:assets_device,id,deleted_at,NULL",
            ],
            "postUpdateDevice" => [
                "assetId" => "required|exists:assets_device,id,deleted_at,NULL",
                "ip" => "required|ip",
                "port" => "required|int",
                "read_community" => "required",
                //"write_community" => "required",
                "custom_name" => "string",
                "version" => "required|in:v1,v2,v3",
                "core" => "required|in:0,1,2,3"
            ],
            "postDelDevice" => [
                "assetId"  => "required|exists:assets_device,id,deleted_at,NULL"
            ],
        ];
        return $this->useRule($rules);
    }
}
