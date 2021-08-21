<?php

namespace App\Transformers\Workflow;

use App\Transformers\BaseTransformer;


class EventCommonTransformer extends BaseTransformer
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
            $ret[$k] = $val;

        }
        if(!empty($resource->user)) {
            $ret['user'] = $resource->user->username;
        }

        if(!empty($resource->reporter)) {
            $ret['reporter'] = $resource->reporter->username;
        }
        return $ret;
    }
}
