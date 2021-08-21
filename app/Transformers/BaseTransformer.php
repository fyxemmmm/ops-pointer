<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/26
 * Time: 14:53
 * https://packalyst.com/packages/package/cyvelnet/laravel5-fractal
 */

namespace App\Transformers;

use League\Fractal\TransformerAbstract;


class BaseTransformer extends TransformerAbstract
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
        return [];
    }
}
