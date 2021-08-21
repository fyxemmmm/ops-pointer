<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Setting;

use App\Models\Setting\Layout;
use App\Repositories\BaseRepository;
use App\Exceptions\ApiException;
use App\Models\Code;
use DB;

class LayoutRepository extends BaseRepository
{

    protected $model;

    public function __construct(Layout $layout){
        $this->model = $layout;
    }

    public function getList(){
        $model = $this->model;
        $data = $model->select('id','name','is_default')->get()->toArray();
        return $data;
    }

    public function getDetail($request){
        $id = $request->input('id');
        $model = $this->model;
        if(!$id){
            $data = $model->where('is_default','=','1')->firstorFail();
        }else{
            $where[] = ['id','=',$id];
            $data = $model->where($where)->firstorFail();
        }
        $data = $data->toArray();
        return $data;
    }

    public function save($request){
        $input = $request->input();
        return $this->store($input);
    }

    public function edit($request){
        $input = $request->input();
        $id = $input['id'];
        return $this->update($id,$input);
    }

    public function delete($request){
        $id = $request->input('id');
        return $this->del($id);
    }

    public function setDefault($request){
        $id = $request->input('id');
        $model = $this->model;
        DB::beginTransaction();
        $model->where('is_default','=','1')->update(['is_default'=>0]);
        $data = $model->where('id',$id)->firstorFail();
        $data->is_default = 1;
        $data->save();
        DB::commit();
        return $this->model;
    }

}
