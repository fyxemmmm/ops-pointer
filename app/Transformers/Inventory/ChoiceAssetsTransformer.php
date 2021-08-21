<?php

namespace App\Transformers\Inventory;

use App\Transformers\BaseTransformer;
use App\Repositories\Assets\DeviceRepository;
use App\Models\Assets\Department;

class ChoiceAssetsTransformer extends BaseTransformer
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

        if(isset($resource->engineroom->name)){
            $erdt = $resource->engineroom->name;
        }elseif(isset($resource->department)){
            $erdt = DeviceRepository::transform('department',$resource->department,$tkey);
        }else{
            $erdt = '';
        }

        return [
            'id' => $resource->id,
            'number' => $resource->number,
            'assets_category' => $resource->sub_category->name,
            'name' => $resource->name,
            'zone' => $resource->zone->name,
            'office_building' => $resource->office_building->name,
            'erdt' => $erdt,
            'rack' => DeviceRepository::transform('rack',$resource->rack,$tkey),
            'rack_pos' => $resource->rack_pos,
        ];
    }
}
