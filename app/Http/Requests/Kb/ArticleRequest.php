<?php

namespace App\Http\Requests\Kb;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class ArticleRequest extends Request
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
            "postAdd" => [
                "title" => "required|string|max:64",
                "content" => "required|string",
                "brief" => "string|max:512",
            ],
            "getList" => [
                "status" => "in:0,1,2",
                "begin" => "date",
                "end" => "date",
            ],
            "getEdit" => [
                "id" => "required"
            ],
            "postDel" => [
                "id" => "required"
            ],
            "postEdit" => [
                "id" => "required",
                "title" => "required|string|max:64",
                "content" => "required|string",
                "brief" => "string|max:512",
            ],
            "getView" => [
                "id" => "required"
            ],
            "postSearch" => [
                "s" => "required"
            ],
            "postApprove" => [
                "id" => "required|exists:kb_articles,id,deleted_at,NULL",
                "approve" => "required|in:1,2"
            ],
        ];
        return $this->useRule($rules);
    }
}
