<?php

namespace App\Http\Requests\Report;

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
            "postFieldsSelect" => [
                'title' => [
                    "required",
                    "string",
                    "max:64",
                    Rule::unique('report_device')->ignore($this->input("id"))
                ],
                "config" => "required|array",
            ],
            "postTemplateDel" => [
                "id" => "required"
            ]
        ];
        return $this->useRule($rules);
    }
}
