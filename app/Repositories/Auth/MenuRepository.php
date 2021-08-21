<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Auth;

use App\Repositories\BaseRepository;
use App\Models\Menu;
use App\Models\Code;
use DB;

class MenuRepository extends BaseRepository
{

    const KB_MENU = 6; //知识库
    const OA_MENU = 7;
    const SYSTEM_MENU = 1000; //系统菜单ID

    public function __construct(Menu $menuModel, UserRepository $userRepository)
    {
        $this->model = $menuModel;
        $this->userRepository = $userRepository;
    }

    public function getMenuList() {
        if($this->userRepository->isEngineer() || $this->userRepository->isManager()) {
            $categories = $this->userRepository->getCategories();
        }

        $list1 = $this->model->getList();
        $list2 = $this->model->getList($list1->pluck("id")->all());
        $list3 = $this->model->getList($list2->pluck("id")->all());

        $list2Group = $list2->groupBy("pid")->toArray();
        $list3Group = $list3->groupBy("pid")->toArray();
        $data = [];
        foreach($list1 as $value) {
            $cur = $value->toArray();
            /*if($this->userRepository->isUserLeader() && ($value->id == self::KB_MENU || $value->id == self::OA_MENU)){
                continue;
            }*/

            if(!$this->userRepository->isAdmin() && $value->id == self::SYSTEM_MENU){
                continue;
            }
            if(isset($list2Group[$value->id])){
                $cur["children"] = $list2Group[$value->id];
                foreach($cur['children'] as $key => &$child) {
                    if(!empty($child['category_id']) && isset($categories)) {
                        $all = 0;
                        $exist = 0;
                        if(in_array($child['category_id'], $categories)) { //全部显示
                            $all = 1;
                        }
                        if(isset($list3Group[$child['id']])) { //部分显示
                            foreach($list3Group[$child['id']] as $subchild) {
                                if($all === 1 || in_array($subchild['category_id'], $categories)) {
                                    $child['children'][] = $subchild;
                                    $exist = 1; //至少有一个
                                }
                            }
                            if($exist === 0) {
                                unset($cur['children'][$key]);
                            }
                        }
                    }
                    else {
                        if(isset($list3Group[$child['id']])) {
                            $child['children'] = $list3Group[$child['id']];
                        }
                    }
                }
                $cur['children'] = array_values($cur['children']);
            }
            $data[] = $cur;
        }

        return $data;
    }

    /**
     * 列表
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function getList($request){


        $search = $request->input("s");
        $model = $this->model->where(function ($query) use ($search){
            $query->orWhere("path","<>","/systems/config/menu");
            if(!empty($search)){
                $query->orWhere("name","like","%" . $search . "%");
            }
        });
        return $this->usePage($model);
    }

    public function getMenu() {
        $list = DB::select("
            select aaa.id,aaa.p_name from (
            select m.pid ,m.id,
            concat(sec.name,'|-',m.name ) as p_name
            ,concat(m.pid,m.id ) as p_id

            from menus m
            ,(select pid ,id,name    from menus
            where id in (select id   from menus
            where pid =0 and id <>0) ) sec
            where m.pid = sec.id
            union all
            select m1.pid ,m1.id,m1.name
            ,concat(m1.id,m1.pid ) as p_id
            from menus m1
            where m1.id in (select id   from menus
            where pid =0 and id <>0 )
            union all
            select DISTINCT 0,0,'无','00' from menus m2

            ) aaa

            order by aaa.p_id
            ");
        return $list;
    }

    /**
     * 添加
     * @param [type] $request [description]
     */
    public function add($request){
        $data = $this->getParams($request);
        $this->store($data);
        userlog("添加了新菜单".$data['name']);
    }

    /**
     * 修改显示页面
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function view($request) {
        return $this->getById($request->input("id"));
    }

    /**
     * 修改提交数据
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function edit($request){
        $data = $this->getParams($request);

        // 不能将自己选择为父级菜单
        if($data['id'] == $data['pid']){
            Code::setCode(Code::ERR_EDIT_MENU);
            return false;
        }

        $this->update($data['id'],$data);
        userlog("修改了菜单栏".$data['id']);
    }

    /**
     * 删除
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function delete($request){
        $data = $this->getParams($request);
        // 查询是否有数据将数据id作为父级
        $res = $this->model->where('pid',$data['id'])->first();
        if(!empty($res)){
            Code::setCode(Code::ERR_MENU_FIELD);
            return false;
        }
        $this->del($data['id']);
        userlog("删除了菜单栏".$data['id']);
    }

    /**
     * 获取提交参数
     * @param  [type] $request [description]
     * @return [array]          数据数组
     */
    public function getParams($request){
        $data = [];
        $param = $request->input();
        // 分类id和图标没有传值时 直接为空
        if(!isset($param['category_id'])) $data['category_id'] = NULL;
        if(!isset($param['icon'])) $data['icon'] = NULL;
        $param_name = ["pid", "name", "path", "icon", "status", "id", "category_id"];
        // 循环接收提交数据键名
        foreach ($param_name as $key) {
            if(isset($param[$key])){
                $val = $param[$key];
                switch ($key){
                    case 'status':
                        if('' != $val) $val = intval($val);
                        if(0 <= $val) $data[$key] = $val;
                            break;
                    case 'name':
                    case 'path':
                        $val = trim($val);
                        if('' != $val) $data[$key] = $val;
                            break;
                    default:
                        $data[$key] = $val;
                            break;
                }
            }
        }
        return $data;
    }



}