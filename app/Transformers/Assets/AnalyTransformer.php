<?php

namespace App\Transformers\Assets;

use App\Transformers\BaseTransformer;

# 资产分析
class AnalyTransformer extends BaseTransformer
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

    public function __construct($end_time){
        $this->end_time = $end_time;
    }


    public function transform($resource)
    {
        $end_time_month = intval(substr($this->end_time,'5','2'));
        $in_time_month = $resource->intime ? intval(substr($resource->intime,'5','2')) : 0;
        $end_time_year = intval(substr($this->end_time,'0','4'));
        $in_time_year = $resource->intime ? intval(substr($resource->intime,'0','4')) : 0;
        $year_diff = $end_time_year - $in_time_year;
        if($end_time_month < $in_time_month){
            $year_diff = $year_diff - 1;
            $month_diff = 12 - ($in_time_month - $end_time_month);
        }else{
            $month_diff = $end_time_month - $in_time_month;
        }

        /*
         * 如果是一个月都不到，那就认为他使用年限为一个月
            if($end_time_month == $in_time_month && $end_time_year == $in_time_year){
                $month_diff = 1;
        }
        */

        $event = $resource->events->toArray();
        $repair = array_column($event,'state');
        $repair_time = 0;
        foreach($repair as $k=>$v){
            if(3 == $v){
                $repair_time++;
            }
        }

        if(strtotime($this->end_time) > strtotime($resource->warranty_end)){
            $warranty = '已过保';  # 已经过保
        }else{
            $warranty = '在保';  # 在保
        }


        $state_arr = [
            0 => '闲置',
            1 => '使用中',
            2 => '维护中',
            3 => '报废',
        ];



        $data = [
            'number' => $resource->number,
            'name' => $resource->name,
            'state' => $state_arr[$resource->state],
            'use_month' => $month_diff, # 使用年限的月
            'use_year' => $year_diff, # 使用年限的年
            'use_year_msg' => $year_diff.'年'.$month_diff.'月',
            'repair_time' => $repair_time, # 维修次数
            'warranty' => $warranty, # 是否在保
            'user' => $resource->user ?? '',
            'brand' => $resource->dict->name ?? '',
            'ip' => $resource->ip ?? '',
            'location' => $resource->location ?? '',
        ];
        return $data;
    }
}
