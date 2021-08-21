<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/13
 * Time: 13:51
 */

namespace App\Transformers\Kb;

use App\Transformers\BaseTransformer;



class ArticleTransformer extends BaseTransformer
{
    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * Transform object into a generic array
     *
     * @var $resource
     * @return array
     */
    public function transform($resource)
    {
        $data = [
            'id' => $resource->id,
            'title' => $resource->title,
            'brief' => $resource->brief,
            'status' => $resource->status,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
            'user_id' => (int) $resource->user_id,
            'username' => $resource->user->username,
            'approver_id' => (int) $resource->approver_id,
            'approver_name' => is_null($resource->approver)?"":$resource->approver->username,
        ];

        if(!is_null($resource->content)) {
            $data['content'] = $resource->content;
        }
        return $data;
    }
}
