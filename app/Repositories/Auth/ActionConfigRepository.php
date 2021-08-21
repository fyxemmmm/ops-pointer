<?php

namespace App\Repositories\Auth;

use App\Repositories\BaseRepository;
use App\Models\Auth\ActionConfig;
use App\Models\Code;
use App\Exceptions\ApiException;
use DB;
use App\Models\Menu;

class ActionConfigRepository extends BaseRepository
{

    public function __construct(ActionConfig $actionConfig,Menu $menu)
    {
        $this->model = $actionConfig;
        $this->menuModel = $menu;
    }

    public function getList($request)
    {
        $type = $request->input('type');
        $where = [];
        $model = $this->model;
        if(!empty($type)){
            $where[] = ['type', '=', intval($type)];
            $model = $model->where($where);
        }
        return $this->usePage($model,$sortColumn = "id", $sort = "asc");
    }

    public function edit($request){
        $data = $this->getParams($request);
        userlog("更新了配置信息,id为{$data['id']}");
        $this->update($data['id'],$data);

        $result = $this->getById($data['id']);
        // 当开关涉及到 监控开关的时候，将监控开关的状态同步到菜单栏中监控按钮的状态
        if('pc_jk' === $result['key']){
            $this->menuModel->where('name','监控')->update(['status' => $result['status']]);
        }

    }

    public function getParams($request)
    {
        $data = [];
        $param = $request->input();
        $param_name = ['id', 'status'];
        foreach ($param_name as $key) {
            if(isset($param[$key])){
                $val = $param[$key];
                switch ($key) {
                    case 'id':
                        if('' != $val) $data[$key] = intval($val);
                        break;
                    default:
                        $data[$key] = $val;
                        break;
                }
            }
        }
        return $data;
    }


    public function addFun($request){
        $id = $request->input('id');
        if(!$id){
        $this->model->create($request->input());
        }else{
            $data = $this->model->find($id);
            if(!$data) throw new ApiException(Code::ERR_PARAMS, ["未找到需要编辑的数据"]);
            if( $data->key == 'pc_jk' && $request->input('key') !== 'pc_jk' ){
                throw new ApiException(Code::COMMON_ERROR, ["请勿更改监控按钮的键名"]);
            }
            $data->fill($request->input());
            $data->save();
        }
        return;
    }

    public function delFun($id){
        $key = $this->getById($id)->key;
        if($key === 'pc_jk'){
            throw new ApiException(Code::COMMON_ERROR, ["请勿删除监控相关设置"]);
        }
        $this->del($id);
    }

}