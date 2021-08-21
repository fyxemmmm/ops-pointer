<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Fields;


class CategoryFieldsTransformer extends BaseTransformer
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
        return [
            'id' => $resource->id,
            'category_id' => $resource->category_id,
            'field_id' => $resource->field_id,
            'field_sname' => $resource->fields->field_sname,
            'field_cname' => $resource->fields->field_cname,
            'require1' => $resource->require1,
            'require2' => $resource->require2,
            'require3' => $resource->require3,
            'require5' => $resource->require5,
            'is_show' => $resource->is_show,
        ];
    }
}
