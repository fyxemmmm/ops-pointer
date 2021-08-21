<?php

namespace App\Transformers\Workflow;

use App\Transformers\BaseTransformer;


class EventTransformer extends BaseTransformer
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
        else if ($resource->source === 2) {
            $source_name = "监控报警";
        }
        else {
            $source_name = "终端上报";
        }

        return [
            'id' => $resource->id,
            'category' => $resource->category->name,
            'category_id' => $resource->category_id,
            'report_name' => $resource->report_name,
            'user' => $resource->user->username,
            'assigner' => !empty($resource->assigner)?$resource->assigner->username:null,
            'state' => $resource->state,
            'source' => $resource->source,
            'source_name' => $source_name,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
            'created_date' => $resource->created_at->format('Y-m-d'),
            'created_time' => $resource->created_at->format('H:i'),
            'user_id' => $resource->user_id,
            'assigner_id' => $resource->assigner_id,
            'asset_id' => $resource->asset_id,
            'asset_number' => $resource->asset->number,
            'asset_name' => $resource->asset->name,
            //'asset_category' => $resource->asset->category->name,
            'report_description' => $resource->description,
            'is_comment' => $resource->is_comment,
            'process_time' => $resource->process_time,
            'response_time' => $resource->response_time,
            'suspend_status' => $resource->suspend_status,
            'report_at' => $resource->report_at,
            'finished_at' => $resource->finished_at,
            'reached_at' => $resource->reached_at,
            'accept_at' => $resource->accept_at,
            'distance_time' => $resource->distance_time,
        ];
    }
}
