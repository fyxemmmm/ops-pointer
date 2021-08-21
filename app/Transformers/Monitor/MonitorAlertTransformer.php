<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/13
 * Time: 13:51
 */

namespace App\Transformers\Monitor;

use App\Transformers\BaseTransformer;



class MonitorAlertTransformer extends BaseTransformer
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
            'content' => $resource->content,
            'new_content' => $resource->new_content,
            'type' => $resource->type,
            'level' => $resource->level,
            'event_id' => $resource->event_id,
            'managed_id' => $resource->managed_id,
            'current_value' => $resource->current_value,
            'status' => $resource->status,
            'new_status' => $resource->new_status,
            'new_status_msg' => $resource->new_status,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
            'triggered_at' => $resource->triggered_at,
            'new_triggered_at' => $resource->new_triggered_at,
            'user_id' => $resource->user_id,
            'username' => $resource->username,
        ];

        return $data;
    }
}
