<?php

namespace App\Transformers\Auth;

use App\Transformers\BaseTransformer;

class UsersEngineersTransformer extends BaseTransformer
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
        return [
            'engineer_id' => $resource->engineer_id,
            'engineer_name' => $resource->engineer->name,
            'user_id' => $resource->user_id,
            'username' => $resource->user->name,
        ];
    }
}
