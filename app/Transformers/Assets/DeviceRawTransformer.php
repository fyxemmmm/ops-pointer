<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Device;
use App\Models\Assets\Engineroom;
use App\Models\Workflow\Event;
use App\Repositories\Assets\DeviceRepository;

class DeviceRawTransformer extends BaseTransformer
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

    private $type=false;


    public function __construct($type=false) {
        $this->type = $type;
    }

    /**
     * Transform object into a generic array
     *
     * @var $resource
     * @return array
     */
    public function transform($resource)
    {
        $keys = array_keys($resource->getAttributes());
        $ret = [];
        foreach($keys as $k) {
            $value = DeviceRepository::transform($k, $resource->$k, $tkey,$this->type);
            if(!empty($tkey)) {
                $ret[$tkey] = $value;
                $ret[$k] = $resource->$k;
            }
            else {
                $ret[$k] = $value;
            }
        }

        $ret['category'] = $resource->category->name;
        $ret['sub_category'] = $resource->sub_category->name;
        return $ret;
    }
}
