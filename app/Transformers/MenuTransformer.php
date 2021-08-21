<?php

namespace App\Transformers;

use App\Transformers\BaseTransformer;


class MenuTransformer extends BaseTransformer
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
            'pid' => $resource->pid,
            'p_name' => $resource->getParentName($resource->pid),
            'name' => $resource->name,
            'path' => $resource->path,
            'icon' => $resource->icon,
            'category_name' => $resource->category->name,
            'category_id' => $resource->category->id,
            'status' => $resource->status,

        ];
    }
}
