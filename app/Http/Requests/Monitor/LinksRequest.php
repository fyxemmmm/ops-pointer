<?php

namespace App\Http\Requests\Monitor;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class LinksRequest extends Request
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



    public function messages()
    {
        return [
            "exists"   => "线路未绑定",
        ];
    }


    public function rules()
    {
        $rules = [
            "postAdd" => [
                "speedUp" => "required|int|min:1|max:1000000",
                "speedDown" => "required|int|min:1|max:1000000",
                "fromAssetId" => "required|exists:assets_device,id",
                "toAssetId" => "required|exists:assets_device,id",
                "name" => "nullable|string|max:255",
                "remark" => "nullable|string|max:255",
                "fromPortId" => "required|int",
                "toPortId" => "required|int",
                "forward" => "required|in:0,1",
                "speedUpLimit" => "required|int|min:20|max:99",
                "speedDownLimit" => "required|int|min:20|max:99",
                "level" => "required|int|min:1|max:5"
            ],
            "postDel" => [
                "id" => "required|exists:monitor_links,id",
            ],
            "getEdit" => [
                "id" => "nullable|exists:monitor_links,id",
                "linkId" => "nullable|exists:monitor_links,link_id",
            ],
            "postEdit" => [
                "id" => "required|exists:monitor_links,id",
                "level" => "required|int|min:1|max:5",
                "fromPortId" => "required|int",
                "toPortId" => "required|int",
            ]
        ];
        return $this->useRule($rules);
    }
}
