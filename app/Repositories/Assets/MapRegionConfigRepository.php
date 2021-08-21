<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Repositories\BaseRepository;
use App\Models\Assets\MapRegionConfig;
use App\Models\Assets\MapLinks;
use App\Models\Code;
use App\Exceptions\ApiException;
use DB;

class MapRegionConfigRepository extends BaseRepository
{

    protected $mapLinksRepo;

    public function __construct(MapRegionConfig $model,MapLinks $map_link_model, MapLinksRepository $mapLinksRepository)
    {
        $this->model = $model;
        $this->map_link_model = $map_link_model;
        $this->mapLinksRepo = $mapLinksRepository;
    }

    public function add($request) {
        $result['result'] = '';
        $mr_id = $request->input("region");
        $er_id = $request->input("enginerooms");
        $rcParam = array(
            'mr_id' => $mr_id,
            'er_id' => $er_id,
            'px' => $request->input('position_x'),
            'py' => $request->input('position_y'),
        );
        $where = ['mr_id'=>$mr_id,'er_id' => $er_id];
        $getOne = $this->getOne($where);
        if($getOne){
            Code::setCode(Code::ERR_DATA_ALREADY_EXISTS);
            return false;
        }

        DB::beginTransaction();
        $rs = MapRegionConfig::create($rcParam);

        $rcId = isset($rs['id'])?$rs['id']:0;
        if(!$rcId) {
            Code::setCode(Code::ERR_OPERATION);
            return false;
        }

        $result = $this->mapLinksRepo->addMapLinks($request->input("links"), $rcId);
        if(!$result) {
            Code::setCode(Code::ERR_OPERATION);
            DB::rollBack();
            return false;
        }
        DB::commit();

        return $rcId;
    }

    public function edit($request) {
        $id = $request->input("id");
        $mrcmodel = $this->model->where('id',$id)->firstOrFail();
        $mr_id = $mrcmodel->mr_id;
        $er_id = $request->input("enginerooms");

        $where = ['mr_id'=>$mr_id,'er_id' => $er_id];
        $getOne = $this->model->where($where)->where("id","!=",$id)->first();
        if($getOne){
            Code::setCode(Code::ERR_DATA_ALREADY_EXISTS);
            return false;
        }

        DB::beginTransaction();
        $mrcmodel->er_id = $er_id;
        $mrcmodel->px = $request->input("position_x");
        $mrcmodel->py = $request->input("position_y");
        $mrcmodel->save();  //map_region_config表

        //重新处理links
        $this->mapLinksRepo->linkEdit($request->input("links"), $id);
        DB::commit();
    }


    /**
     * 列表数据
     * @param array $where
     * @param int $number
     * @param string $sort
     * @param string $sortColumn
     * @return mixed
     */
    public function getList($where=array()){
        $model = $this->model->where($where);
        return $this->usePage($model);
    }


    /**
     * 获取一条数据
     * @param array $where
     * @return mixed
     */
    public function getOne($where=array()){
        $res = array();
        if($where) {
            $res = $this->model->where($where)->first();
        }
        return $res;
    }

    /**
     * 获取一条数据(or条件)
     * @param array $where
     * @return mixed
     */
    public function getOneOrWhere($where=array()){
        $ret = $this->model->orWhere($where)->first();
        return $ret;
    }



    public function delete($id){
        DB::beginTransaction();
        $this->del($id);
        $this->mapLinksRepo->delLinkByMrc($id);
        DB::commit();
    }


}