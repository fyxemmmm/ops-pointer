<?php

namespace App\Transformers\Inventory;

use App\Transformers\BaseTransformer;
use App\Repositories\Assets\DeviceRepository;


class InventoryAssetTransformer extends BaseTransformer
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

        if(isset($resource->engineroom_name)){
            $erdt = $resource->engineroom_name;
        }elseif(isset($resource->department_name)){
            $erdt = $resource->department_name;
        }else{
            $erdt = '';
        }

        return [

            'inventory_id' => $resource->inventory_id,
            'asset_id' => $resource->asset_id,
            'result' => $resource->result,
            'operation_type' => $resource->operation_type,
            'number' => $resource->number,
            'assets_category' => $resource->assets_category,
            'name' => isset($resource->name) ? $resource->name : '',
            'zone' => isset($resource->zone) ? $resource->zone : '',
            'office_building' => isset($resource->office_building) ? $resource->office_building : '',
            'erdt' => $erdt,
            'rack' => DeviceRepository::transform('rack',$resource->rack,$tkey),
            'rack_pos' => $resource->rack_pos,

        ];
    }
}
