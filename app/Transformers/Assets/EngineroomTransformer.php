<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;


class EngineroomTransformer extends BaseTransformer
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
            'name' => $resource->name,
            'type' => $resource->type,
            'address' => $resource->address,
            'zoneName' => $resource->zone->name,
            'zoneId' => $resource->zone_id,
            'buildingName' => $resource->building->name,
            'buildingId' => $resource->building_id,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
