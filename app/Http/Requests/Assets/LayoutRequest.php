<?php

namespace App\Http\Requests\Assets;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class LayoutRequest extends Request
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
            "postLayoutAdd" => [
                "name" => "required",
                "content" => "required",
            ],
            "getLayoutList" => [
                // "name" => "required",
                // "content" => "required",
            ],
    
        ];
        return $this->useRule($rules);
    }
}
