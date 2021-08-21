<?php

namespace App\Transformers\Assets;
use App\Transformers\BaseTransformer;



class MapLinksTransformer extends BaseTransformer
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
            'link_id' => $resource->mlinks_id,
            'source_mrc_id' => $resource->source_mrc_id,
            'dest_mrc_id' => $resource->dest_mrc_id,
            'source_mr_id' => $resource->sourceMapRegionConfig->mr_id,
            'dest_mr_id' => $resource->destMapRegionConfig->mr_id,
            'source_er_id' => $resource->sourceMapRegionConfig->er_id,
            'dest_er_id' => $resource->destMapRegionConfig->er_id,
            'source_asset_id' => $resource->source_asset_id,
            'dest_asset_id' => $resource->dest_asset_id,
            'source_px' =>  $resource->sourceMapRegionConfig->px,
            'source_py' =>  $resource->sourceMapRegionConfig->py,
            'dest_px' =>  $resource->destMapRegionConfig->px,
            'dest_py' =>  $resource->destMapRegionConfig->py,
            "status" => $resource->status
        ];
    }
}
