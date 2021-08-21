<?php

namespace App\Http\Requests\Workflow;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class OaRequest extends Request
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
            "postAssign" => [
                "userId" => "required|int",
                "accept_at" => "required|date",
            ],
            "postAdd" => [
                "categoryId" => "required|int",
                "object" => "required|int",
            ],
            "getView" => [
                "id" => "required|int"
            ],
            "getList" => [
                "search"  => "max:32"
            ],
        ];
        return $this->useRule($rules);
    }
}
