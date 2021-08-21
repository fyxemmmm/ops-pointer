<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class EventsRequest extends Request
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
            "getAdd" => [
                "assetId" => "int", //资产id
            ],
            "postAdd" => [
                "assetId" => "int", //资产id
                "categoryId" => "required|int", //事件分类id
                "typeId" => "required|int", //分派类别分类id
                "userId" => "int", //工程师id
            ],
            "getProcess" => [
                "eventId" => "required|int"
            ],
            "postSaveDraft" => [
                "eventId" => "required|exists:workflow_events,id,deleted_at,NULL",
                "wrongId" => "exists:assets_wrong,id",
                "solutionId" => "exists:assets_solution,id",
            ],
            "postSave" => [
                "eventId" => "required|exists:workflow_events,id,deleted_at,NULL"
            ],
            "getList" => [
                "search"  => "max:32"
            ],
            "getPorts" => [
                "eventId" => "required|exists:workflow_events,id,deleted_at,NULL",
                "assetId" => "required",
                "type" => "in:0,1",
            ],
            "postPortConnect" => [
                "eventId" => "required|exists:workflow_events,id,deleted_at,NULL",
                "type" => "required|in:0,1",
                "assetId" => "required|int",
                "port" => "required|int|min:1",
                //"ip" => "ip",
                "remoteAssetId" => "required|int",
                "remotePort" => "required|int|min:1",
            ],
            "postPortDisonnect" => [
                "eventId" => "required|exists:workflow_events,id,deleted_at,NULL",
                "type" => "required|in:0,1",
                "assetId" => "required|int",
                "port" => "required|int|min:1",
            ],
            "engineerAdd" => [
                "categoryId" => "required|int",
                "typeId" => "required|in:0,1",
                "assetId" => "required|int",
            ],
            "engineerAddShow" => [
                "assetId" => "int", //资产id
            ]
        ];
        return $this->useRule($rules);
    }
}
