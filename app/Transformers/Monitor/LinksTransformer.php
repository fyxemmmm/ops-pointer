<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/13
 * Time: 13:51
 */

namespace App\Transformers\Monitor;

use App\Transformers\BaseTransformer;



class LinksTransformer extends BaseTransformer
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
            'status' => $resource->status, //0 断开 1正常 2延迟
            'name' => $resource->name,
            'sourceEngineroom' => $resource->source_engineroom,
            'destEngineroom' => $resource->dest_engineroom,
            'sourceAsset' => $resource->source_name,
            'sourceAssetId' => $resource->source_asset_id,
            'sourceNumber' => $resource->source_number,
            'sourceIp' => $resource->sourceAssetsMonitor->ip,
            'destAsset' => $resource->dest_name,
            'destAssetId' => $resource->dest_asset_id,
            'destNumber' => $resource->dest_number,
            'destIp' => $resource->destAssetsMonitor->ip,
            'sourcePort' => $resource->source_port_desc,
            'sourcePortId' => $resource->source_port_id,
            'destPort' => $resource->dest_port_desc,
            'destPortId' => $resource->dest_port_id,
            'speedUp'  => $resource->custom_speed_up,
            'speedDown'  => $resource->custom_speed_down,
            'speedUpLimit'  => $resource->custom_speed_up_limit,
            'speedDownLimit'  => $resource->custom_speed_down_limit,
            'forward' => $resource->forward,
            'level' => $resource->level,
            'remark' => $resource->remark,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
        ];

        return $data;
    }
}
