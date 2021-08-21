<?php

namespace App\Transformers\Auth;

use App\Transformers\BaseTransformer;

class EngineersWorktypeTransformer extends BaseTransformer
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
            'id' => $resource->id,
            'engineer_id' => $resource->engineer_id,
            'engineer_name' => $resource->engineer->name,
            'category_id' => $resource->category->id,
            'category' => $resource->category->name,
        ];
    }
}
