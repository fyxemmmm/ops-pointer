<?php

namespace App\Transformers\Workflow;

use App\Transformers\BaseTransformer;
use App\Models\Workflow\Oa;

class OaTransformer extends BaseTransformer
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
        if($resource->source === 0) {
            $source_name = "我的事件";
        }
        else if ($resource->source === 1) {
            $source_name = "主管分派";
        }
        else {
            $source_name = "终端上报";
        }

        $object_name = isset(Oa::$objectMsg[$resource->object])?Oa::$objectMsg[$resource->object]:null;

        return [
            'id' => $resource->id,
            'category' => $resource->category->name,
            'category_id' => $resource->category_id,
            'user' => $resource->user->username,
            'company' => $resource->company,
            'assigner' => !empty($resource->assigner)?$resource->assigner->username:null,
            'state' => $resource->state,
            'source' => $resource->source,
            'source_name' => $source_name,
            'object' => $resource->object,
            'object_name' => $object_name,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
            'finished_at' => $resource->finished_at,
            'reached_at' => $resource->reached_at,
            'response_time' => intval($resource->response_time),
            'accept_at' => $resource->accept_at,
            'user_id' => $resource->user_id,
            'assigner_id' => $resource->assigner_id,
            'report_name' => $resource->report_name,
            'mobile' => $resource->mobile,
            "description" => $resource->description,
            "remark" => $resource->remark,
            'problem' => $resource->problem,
            'location' => $resource->location,
            'device_name' => $resource->device_name,
            'is_comment'=> $resource->is_comment,
            'process_time' => $resource->process_time,
            'response_time' => $resource->response_time,
            'suspend_status' => $resource->suspend_status,
            'report_at' => $resource->report_at,
            'distance_time' => $resource->distance_time,
        ];
    }
}
