<?php

namespace App\Repositories\Auth;

use App\Repositories\BaseRepository;
use App\Models\Code;
use DB;
use App\Models\Auth\DataSource;

class DataSourceRepository extends BaseRepository
{

    protected $model;

    public function __construct(DataSource $dataSourceModel)
    {
        $this->model = $dataSourceModel;

    }



    /**
     * 列表
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function getList($request){

        $search = $request->input("type");
        $where = [];
        if(!empty($search)){
            $where[] = ['type','=',$search];
        }

        $data = $this->model->where($where)->get()->toArray();

        foreach ($data as &$v){
            if(!empty($v['option'])){
                $v['option'] = json_decode($v['option']);
            }
        }

        return $data;
    }


    public function getSourceData($input){

        $startTime = $input['start_time'];
        $endTime = $input['end_time'];
        if(is_array($input['id'])){
            $ids = $input['id'];
        }else{
            $ids = explode(',',$input['id']);
        }

        // 根据传入的 top 值来确定截取多少条数据
        if(isset($input['top'])){
            if(intval($input['top']) > 0){
                $top = $input['top'];
                $ids = array_slice($ids,0,$top,true);
            }
        }

        $workflowOaData = DB::table('workflow_oa')
                ->selectRaw('count( workflow_oa.id ) AS total,`workflow_oa`.`category_id`,date_format( workflow_oa.updated_at, \'%Y-%m-%d %H:%i:%s\' ) AS DAY')
                ->leftJoin('users as B','workflow_oa.user_id','=','B.id')
                ->whereBetween('workflow_oa.updated_at', [$startTime, $endTime])
                ->whereNull('workflow_oa.deleted_at')
                ->groupBy('day','workflow_oa.category_id')
                ->get()
                ->toArray();

        // 取出二维数组中所有的时间
        $times = array_column($workflowOaData,'DAY');

        // 不重复的时间值
        $onlyTimes = array_unique($times);
        asort($onlyTimes);

        $rawSql = ' 1=1 ';
        $workflowCategoryModel = DB::table('workflow_category')
            ->whereRaw($rawSql)
            ->whereNull('workflow_category.deleted_at');

        if(!empty($ids)){
            $workflowCategoryModel->whereIn('id',$ids);
        }

        $workflowCategoryData = $workflowCategoryModel->get()->toArray();

        $workflowCategoryIdArr = array_column($workflowCategoryData,'id');
        $workflowCategoryNameArr = array_column($workflowCategoryData,'name');
        $wCIdsAndNameArr = array_combine($workflowCategoryIdArr,$workflowCategoryNameArr);

        $arr = [];
        // 只挑选符合传入 id 的数据
        foreach ($workflowOaData as $k => $v){
            $new = [];
            if(!empty($ids)){
                if(in_array($v->category_id,$ids)){
                    foreach ($workflowCategoryData as $kk => $vv){
                        // 将数据的名称拼接进去
                        if(intval($v->category_id) === intval($vv->id)){
                            $new['id'] = $vv->id;
                            $new['name'] = $vv->name;
                            $new['category_id'] = $v->category_id;
                            $new['total'] = $v->total;
                            $new['DAY'] = $v->DAY;
                            $arr[$vv->id][$v->DAY] = $new;
                        }
                    }
                }
            }else{
                foreach ($workflowCategoryData as $kk => $vv){
                    // 将数据的名称拼接进去
                    if(intval($v->category_id) === intval($vv->id)){
                        $new['id'] = $vv->id;
                        $new['name'] = $vv->name;
                        $new['category_id'] = $v->category_id;
                        $new['total'] = $v->total;
                        $new['DAY'] = $v->DAY;
                        $arr[$vv->id][$v->DAY] = $new;
                    }
                }
            }
        }

        $newTime = array_fill_keys($onlyTimes,[]);

        foreach ($arr as $categoryId => &$item){
            $item = $item + $newTime;
            ksort($item);
        }

        foreach ($arr as $kkk => &$vvv){
            foreach ($vvv as $kkkk => $vvvv){
                if(isset($vvvv['name'])){
                    $name = $vvvv['name'];
                }else{
                    $name = '';
                }

                if(empty($vvv[$kkkk])){
                    $vvv[$kkkk] = [
                        'id' => $kkk,
                        'name' => $name,
                        'category_id' => $kkk,
                        'total' => 0,
                        'DAY' => $kkkk,
                    ];
                }
            }
        }

        $new = [];
        foreach ($arr as $cid => $val){
            $new[$cid] = array_column($val,'total');
        }

        $newArr = [];
        $newData = [];
        foreach ($new as $nid => $nvalue){
            $newData['name'] = $wCIdsAndNameArr[$nid];
            $newData['data'] = $nvalue;
            $newArr[] = $newData;
        }


        $newArray[] = [
            'times' => array_values($onlyTimes),
            'values' => $newArr,
        ];

        return $newArray;

    }



    public function getSourceDatas($request){
        $urlPath = $request->path();
        $dataSource = $this->getList($request);
        $arr = [];

        // 寻找和 url 相匹配数据源
        foreach ($dataSource as $k => $v){
            if(isset($v['url'])){

                if($v['url'] == $urlPath){
                    $conditions = [];
                    if(is_array($v['get_condition'])){
                        foreach ($v['get_condition'] as $kk => $vv){
                            // 删除掉没有条件的数据
                            if(!isset($vv['condition'])){
                                unset($v['get_condition'][$kk]);
                            }else{
                                $conditions[] = $vv['condition'];
                            }
                        }
                    }
                    $dataSource[$k]['conditions'] = !empty($conditions) ? implode(' ',$conditions) : ' ';
                    $arr = $dataSource[$k];
                }

            }
        }

        $rawSql = ' 1=1 ' . $arr['conditions'];
        $workflowCategoryData = DB::table('workflow_category')
                                ->whereRaw($rawSql)
                                ->whereNull('workflow_category.deleted_at')
                                ->get();


        return $workflowCategoryData;
    }





}