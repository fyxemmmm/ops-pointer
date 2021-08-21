<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Device;
use App\Models\Assets\Engineroom;
use App\Models\Workflow\Event;
use App\Repositories\Assets\DeviceRepository;

class DeviceTransformer extends BaseTransformer
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
            $value = DeviceRepository::transform($k, $resource->$k, $tkey);
            if(!empty($tkey)) {
                $ret[$tkey] = $value;
                $ret[$k] = $resource->$k;
            }
            else {
                $ret[$k] = $value;
            }
        }

        $events = $resource->events;
        $eventsRet = [];
        $alertCnt = 0;
        $eventId = null;
        foreach($events as $event) {
            if(in_array($event->state, [Event::STATE_WAIT, Event::STATE_ACCESS, Event::STATE_ING])) {
                $eventsRet[] = [
                    "id" => $event->id,
                    "source" => $event->source,
                    "state" => $event->state
                ];
                if($event->source == 2) {
                    $alertCnt++;
                }
                else {
                    $eventId = $event->id;
                }
            }
        }

        //$ret['events'] = $eventsRet;
        $ret['event_id'] = $eventId;
        $ret['alert_cnt'] = $alertCnt;
        $ret['zbx_hostid'] = !is_null($resource->monitor) ? $resource->monitor->device_id : '';
        $ret['em_deviceid'] = $resource->emdevice->device_id;
        $ret['em_type'] = in_array($resource->category_id, \ConstInc::$em_category_id) ? 1: 0;
        $ret['category'] = $resource->category->name;
        $ret['sub_category'] = $resource->sub_category->name;
        return $ret;
    }
}
