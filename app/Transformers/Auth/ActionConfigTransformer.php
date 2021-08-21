<?php

namespace App\Transformers\Auth;

use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;


class ActionConfigTransformer extends TransformerAbstract
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
            'msg' => $resource->msg,
            'name' => $resource->name,
            'desc' => $resource->desc,
            'type' => $resource->type,
            'status' => $resource->status ? TRUE : FALSE,
            'key' => $resource->key,

        ];
    }
}
