<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/13
 * Time: 13:51
 */

namespace App\Transformers\Report;

use App\Transformers\BaseTransformer;
use App\Repositories\Report\GovernmentRepository;


class GovernmentResourceTransformer extends BaseTransformer
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
//            dump($date);exit;
            $date = date('m/d/Y',strtotime($date)); # 转换格式
//            dump($date);exit;
            $year = substr($date,-4);
            $month = $year.'-'. sprintf('%02s',substr($date,0,strpos($date,'/')));
            $data = [
                'id' => $resource->id,
                'month' => $month,
                'value' => $month
            ];

        return $data;
    }
}
