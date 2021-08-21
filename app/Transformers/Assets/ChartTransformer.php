<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Device;
use App\Models\Assets\Engineroom;
use App\Models\Workflow\Event;
use App\Repositories\Assets\DeviceRepository;

class ChartTransformer extends BaseTransformer
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
        $keys = array_keys($resource->getAttributes());
        $ret = [];
        foreach($keys as $k) {
            $val = $resource->$k;
            $value = DeviceRepository::transform($k, $val, $tkey);
            if (!empty($tkey)) {
                $ret[$tkey] = $value;
                $ret[$k] = $val;
//                var_dump($tkey . '=>' . $k . '=>' . $value . '=>' . $val);
                if ($val > 0) {
                    if ('department' == $k) {
                        $ret['area_department_msg'] = $value;
                    } elseif ('area' == $k) {
                        $ret['area_department_msg'] = $value;
                    }
                }
            } else {
                $ret[$k] = $value;
            }
        }

        $location = getKey($ret,'location');
        $location_msg = getKey($ret,'location_msg');


        if(!$location){
            $location_msg = '其它';
        }
        $ret['name'] = $location_msg;
        unset($ret['id'] );
        unset($ret['location'] );
        unset($ret['location_msg'] );

        return $ret;
    }
}
