<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Fields;


class CategoryTransformer extends BaseTransformer
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
            'name' => $resource->name,
            'shortname' => $resource->shortname,
            'icon' => $resource->icon,
            'pname' => $resource->parent->name,
            'path' => $resource->path,
        ];
    }
}
