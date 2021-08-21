<?php

namespace App\Transformers\Auth;

use App\Transformers\BaseTransformer;



class UserTransformer extends BaseTransformer
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
            'name' => $resource->name,
            'username' => $resource->username,
            'phone' => $resource->phone,
            'email' => $resource->email,
            'identity_id' => $resource->identity_id,
            'identity' => $resource->identity->identity,
            'telephone' => $resource->telephone,
            'created_at' => $resource->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $resource->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
