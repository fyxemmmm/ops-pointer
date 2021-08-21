<?php

namespace App\Transformers\Report;

use App\Transformers\BaseTransformer;
use App\Repositories\Report\GovernmentRepository;


class JdGovernmentTransformer extends BaseTransformer
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
        $date = $resource->date;
        $date = date('Y-m-d',strtotime($date));
        $month = substr($date,0,strrpos($date,'-'));
        return [
            'month' => $month,
            'value' => $date
        ];
    }
}
