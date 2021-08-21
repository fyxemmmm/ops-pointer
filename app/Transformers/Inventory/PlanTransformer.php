<?php

namespace App\Transformers\Inventory;

use League\Fractal;
use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use App\Models\Inventory\Plan;

class PlanTransformer extends TransformerAbstract
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
            'number' => $resource->number,
            'name' => $resource->name,
            'user' => $resource->user,
            'desc' => $resource->desc,
            'status' => $resource->status,
            'start_time' => $resource->start_time,
            'plan_time' => $resource->plan_time,
            'result' => $resource->result,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),

        ];
    }
}
