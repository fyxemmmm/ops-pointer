<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/13
 * Time: 13:51
 */

namespace App\Transformers\Report;

use App\Transformers\BaseTransformer;
use App\Repositories\Assets\DeviceRepository;


class DeviceReportTransformer extends BaseTransformer
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

        unset($ret['deleted_at']);
        $ret['sub_category'] = $resource->sub_category->name;
        return $ret;
    }
}
