<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Support\Diff;


class DeviceConfigTransformer extends BaseTransformer
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
            'asset_id' => $resource->asset_id,
            'asset_number' => $resource->device->number,
            'asset_name' => $resource->device->name,
            'title' => $resource->title,
            'diff' => Diff::toTable(json_decode($resource->diff,true)),
            'conf' => $resource->conf,
            'remark' => $resource->remark,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
