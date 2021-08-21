<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Device;
use App\Models\Assets\Engineroom;
use App\Models\Workflow\Event;
use App\Repositories\Assets\DeviceRepository;

class ChartListTransformer extends BaseTransformer
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

        if(!is_null($resource->event_state)){
            $ret['event_state_msg'] = isset(Event::$stateMsg[$resource->event_state]) ? Event::$stateMsg[$resource->event_state] : '';
        }
        $location = getKey($ret,'location');
        $location_msg = getKey($ret,'location_msg');


        if(!$location_msg){
            $location_msg = '其它';
        }
        $officeBuilding_msg = getKey($ret, 'officeBuilding_msg');
        $location_area = $location_msg;
        $location_area .= $location_msg && $officeBuilding_msg ? '｜' : '';
        $location_area .= $officeBuilding_msg;
        $ret['location_area'] = $location_area;


        if(!is_null($resource->category)) {
            $ret['category'] = $resource->category->name;
            $ret['sub_category'] = $resource->sub_category->name;
        }
        return $ret;
    }
}
