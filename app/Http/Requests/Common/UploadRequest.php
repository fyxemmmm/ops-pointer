<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class UploadRequest extends Request
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
            "postPic" => [
                "pic" => "required|max:1000|mimes:jpeg,bmp,png,gif",
            ],
        ];
        return $this->useRule($rules);
    }
}
