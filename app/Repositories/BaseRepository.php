<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/16
 * Time: 18:12
 */

namespace App\Repositories;
use App\Support\GValue;
use Db;

abstract class BaseRepository
{
    protected $model;

    public function all($where = null) {
        $model = $this->model;
        if(!empty($where)) {
            $model = $this->model->where($where);
        }
        return $model->get();
    }

    /**
     * 分页记录集
     * @param null $where
     * @param array $with
     * @return mixed
     */
    public function page($where = null, $with = []) {
        $model =  $this->model;
        if(!empty($where)) {
            $model = $this->model->where($where);
        }

        if(!empty($with)) {
            $model = $model->with($with);
        }

        return $this->usePage($model);
    }

    /**
     * 生成开始结束时间的搜索条件
     * @param $request
     * @param null $defaultBegin
     * @param null $defaultEnd
     * @return array
     */
    public function searchTime($request, $defaultBegin = null, $defaultEnd = null) {
        $begin = $request->input("begin", $defaultBegin);
        if(!empty($begin) && empty($defaultEnd)) {
            $defaultEnd = date("Y-m-d H:i:s");
        }
        $end = $request->input("end", $defaultEnd);

        if(!strtotime($begin) && !strtotime($end)) {
            return false;
        }
        return [$begin, $end];
    }


    /**
     * 使用分页
     * @param $model
     * @param string $sortColumn
     * @param string $sort
     * @return mixed
     */
    protected function usePage($model, $sortColumn = "id", $sort = "desc") {
        if(GValue::$perPage > 0) {
            $number = GValue::$perPage;
        }
        else {
            $number = \ConstInc::PAGE_NUM;
        }
        if(!empty(GValue::$orderBy)) {
            $order = explode("|", GValue::$orderBy);
            foreach($order as $value) {
                if(!empty($value)) {
                    list($sortColumn, $sort) = explode(",", $value);
                    $model = $model->orderBy($sortColumn, $sort);
                }
            }
        }elseif($sortColumn && $sort){
            //默认排序id倒序
            if(is_array($sortColumn)) {
                foreach($sortColumn as $k => $col) {
                    $model = $model->orderBy($col, $sort[$k]);
                }
            }
            else {
                $model = $model->orderBy($sortColumn, $sort);
            }
        }

        if(GValue::$nopage) {
            return $model->get();
        }
        else {
            return $model->paginate($number);
        }
    }


    /**
     * 新增
     * @param $input
     * @return mixed
     */
    public function store($input)
    {
        $this->model->fill($input);
        $this->model->save();
        return $this->model;
    }

    /**
     * 更新
     * @param $id
     * @param $input
     * @return mixed
     */
    public function update($id, $input) {
        $this->model = $this->getById($id);
        $this->model->fill($input);
        $this->model->save();
        return $this->model;
    }

    /**
     * 删除
     * @param $id
     * @return mixed
     */
    public function del($id) {
        $this->model = $this->getById($id);
        $this->model->delete();
        return $this->model;
    }

    /**
     * 根据ID获取MODEL
     * @param $id
     * @return mixed
     */
    public function getById($id, $fail = true) {
        if($fail) {
            return $this->model->findOrFail($id);
        }
        else {
            return $this->model->find($id);
        }
    }

    public function addAll($model,$data){
        foreach($data as $v){
            $model->create($v);
        }
        return true;
    }

    public function updateAll($model,$field,$data){
        foreach($data as $v){
            $model->where($field,$v[$field])->update($v);
        }
        return true;
    }

}