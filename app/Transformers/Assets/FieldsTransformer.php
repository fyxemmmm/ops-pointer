<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;
use App\Models\Assets\Fields;


class FieldsTransformer extends BaseTransformer
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
            'sname' => $resource->field_sname,
            'cname' => $resource->field_cname,
            'type' => $this->getType($resource->field_type),
            'typeId' => $resource->field_type,
            'length' => $resource->field_length,
            'desc' => $resource->field_desc,
            'default' => $resource->field_default,
            'fieldType' => $resource->type->name,
            'fieldTypeId' => $resource->field_type_id,
            'dict' => $resource->field_dict,
            'model' => $resource->dict_table,
            'url' => $resource->url,
            'system' => $resource->system,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    protected function getType($type) {
        switch($type) {
            case Fields::TYPE_INT;
                return "整型";
            case Fields::TYPE_STR:
                return "字符串";
            case Fields::TYPE_FLOAT:
                return "浮点";
            case Fields::TYPE_DICT:
                return "枚举";
            case Fields::TYPE_DATE:
                return "日期";
            case Fields::TYPE_DATETIME:
                return "时间";
            case Fields::TYPE_PASSWORD:
                return "密码";
        }
    }
}
