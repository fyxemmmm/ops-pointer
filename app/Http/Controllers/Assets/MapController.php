<?php
/**
 * 地图
 */

namespace App\Http\Controllers\Assets;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\MapRequest;
use App\Http\Requests\Assets\LayoutRequest;
use App\Repositories\Assets\LayoutRepository;
use App\Repositories\Assets\MapRegionRepository;
use Illuminate\Http\Request;
use App\Repositories\Assets\MapRegionConfigRepository;
use App\Repositories\Assets\MapLinksRepository;
use App\Models\Code;
use App\Exceptions\ApiException;


class MapController extends Controller
{

    protected $mapRegionRepository;
    protected $layout;
    protected $mapRegionConfig;
    protected $mapLinks;

    function __construct(MapRegionRepository $mapRegionRepository,
                         LayoutRepository $layout,
                         MapRegionConfigRepository $mapRegionConfig,
                         MapLinksRepository $mapLinks)
    {
        $this->mapRegionRepository = $mapRegionRepository;
        $this->layout = $layout;
        $this->mapRegionConfig = $mapRegionConfig;
        $this->mapLinks = $mapLinks;
    }

    /*
    ** 地图区域列表
    */
    public function getAreaList(MapRequest $request){
        $name = $request->input('name');
        $where = null;
        if(!empty($name)){
            $where[] = ['name', '=' , $name];
        }
        $data = $this->mapRegionRepository->page($where);
        return $this->response->send($data);
    }

    /*
    ** 地图区域新增
    */
    public function postAreaAdd(MapRequest $request){
        $input = $request->input();
        $this->mapRegionRepository->store($input);
        return $this->response->send();
    }


    /*
    ** 布局列表
    */
    public function getLayoutList(LayoutRequest $request){
        $name = $request->input('name');
        $where = null;
        if(!empty($name)){
            $where[] = ['name', '=' , $name];
        }
        $data = $this->layout->page($where);
        return $this->response->send($data);
    }


    /*
    ** 布局新增
    */
    public function postLayoutAdd(LayoutRequest $request){
        $input = $request->input();
        $this->layout->store($input);
        return $this->response->send();
    }


    /**
     * 地图地区配置
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */
    public function postRegionConfig(MapRequest $request){
        $rcId = $this->mapRegionConfig->add($request);
        $result['result'] = $rcId;
        return $this->response->send($result);

    }



    /**
     * 线路、机房编辑
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */

    public function postLinkEdit(MapRequest $request){
        $this->mapRegionConfig->edit($request);
        return $this->response->send();
    }



    /**
     * 线路、机房列表
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */

     public function getLinkList(MapRequest $request){
        $region_id = $request->input('id');
        $data = $this->mapLinks->getList($region_id);
        return $this->response->send($data);
    }


    /**
     * 线路、机房删除
     * @param Request $request
     * @return mixed
     * @throws ApiException
     */

     public function postRegionDel(MapRequest $request){
        $id = $request->input('id');
        $this->mapRegionConfig->delete($id);
        return $this->response->send();
    }

    public function getLinksPos() {
        $data = $this->mapLinks->getPos();
        return $this->response->send($data);
    }

    public function getLinks(MapRequest $request) {
        $data = $this->mapLinks->getLists();
        $result = ["links" => $data];
        return $this->response->send($result);
    }

}
