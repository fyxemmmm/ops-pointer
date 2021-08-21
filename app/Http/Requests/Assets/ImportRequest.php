<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ImportRequest extends Request
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
            "postApply" => [
                "orderType" => "required|exists:workflow_category,id,deleted_at,NULL",
                "content" => "required"
            ],
            "postUpload" => [
                "orderType" => "required|exists:workflow_category,id,deleted_at,NULL",
                "filename"  => "required|file|max:10000|mimes:xlsx"
            ],
            "postSave" => [
                "orderType" => "required|exists:workflow_category,id,deleted_at,NULL",
                "result" => "required"
            ],
            "postEdit" => [
                "id"  => "required|exists:assets_enginerooms,id,deleted_at,NULL",
                "name" =>  [
                    "required",
                    Rule::unique('assets_enginerooms')->ignore($this->input("id"))->where(function ($query) {
                        return $query->whereNull('deleted_at');
                    })
                ],
                "address" => "max:128",
                "desc" => "max:128",
                "admin" => "max:32",
                "phone" => "max:32",
            ]
        ];
        return $this->useRule($rules);
    }
}
