<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class GovernmentRequest extends Request
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
            "postInsertExcel" => [
//                "filename"  => "required|file|max:10000|mimes:xlsx"
            ],
        ];
        return $this->useRule($rules);
    }
}
