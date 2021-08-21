<?php

namespace App\Http\Controllers\Inventory;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Inventory\PlanRepository;
use App\Http\Requests\Inventory\PlanRequest;
use App\Transformers\Inventory\ChoiceAssetsTransformer;

class PlanController extends Controller
{
    protected $plan;

    function __construct(PlanRepository $planRepository)
    {
        $this->plan = $planRepository;
    }

    /**
     * 盘点计划列表
     * @return mixed
     */
    public function getList(PlanRequest $request){
        $data = $this->plan->getList($request);
        return $this->response->send($data);
    }


    /**
     * 删除盘点计划
     * @param PlanRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function postDel(PlanRequest $request){
        $this->plan->postDel($request);
        return $this->response->send();
    }

    /**
     * 盘点计划盘点或者废弃
     * @param PlanRequest $request
     * @return mixed
     */
    public function getPointOrScrap(PlanRequest $request){
        $this->plan->getPointOrScrap($request);
        return $this->response->send();
    }

    /**
     * 新增盘点计划
     * @param PlanRequest $request
     * @return mixed
     */
    public function postAdd(PlanRequest $request){
        $this->plan->add($request);
        return $this->response->send();
    }

    /**
     * 盘点资产详情展示页面
     * @param PlanRequest $request
     * @return mixed
     */
    public function getDetailsAssetList(PlanRequest $request){
        $data = $this->plan->getDetailsAssetList($request);
        return $this->response->send($data);
    }

    /**
     * 得到一条盘点计划详情
     * @param PlanRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getDetail(PlanRequest $request){
        $data = $this->plan->getDetail($request);
        return $this->response->send($data);
    }

    /**
     * 编辑盘点计划
     * @param PlanRequest $request
     * @return mixed
     */
    public function postEdit(PlanRequest $request){
        $data = $this->plan->edit($request);
        return $this->response->send($data);
    }


    /**
     * 修改盘点资产结果
     * @param PlanRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function postEditResult(PlanRequest $request){
        $this->plan->postEditResult($request);
        return $this->response->send();
    }

    /**
     * 选择机房或者科室
     * @return mixed
     */
    public function getChoiceEnginerooms(){
        $data = $this->plan->getChoiceEnginerooms();
        return $this->response->send($data);
    }

    /**
     * 通过机房或者科室id选择对应设备
     * @param PlanRequest $request
     * @return mixed
     */
    public function getChoiceAssetsByErOrDt(PlanRequest $request){
        $data = $this->plan->getChoiceAssetsByErOrDt($request);
        $obj = new ChoiceAssetsTransformer();
        $this->response->setTransformer($obj);
        return $this->response->send($data);
    }







}
