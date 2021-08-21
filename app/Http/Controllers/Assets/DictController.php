<?php
/**
 * 资产字典数据
 */

namespace App\Http\Controllers\Assets;

use App\Http\Requests\Assets\DictRequest;
use App\Http\Controllers\Controller;
use App\Repositories\Assets\DictRepository;


class DictController extends Controller
{

    protected $dictRepo;

    function __construct(DictRepository $dictRepo)
    {
        $this->dictRepo = $dictRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getList(DictRequest $request)
    {
        $data = $this->dictRepo->getList($request);
        $field = $this->dictRepo->getFieldInfo($request->input("fieldId"));
        $this->response->addMeta($field);
        return $this->response->send($data);
    }

    public function getChildren(DictRequest $request) {
        $id = $request->input("id");
        $data = $this->dictRepo->getChildren($id);
        if(!empty($data['fieldId'])) {
            $field = $this->dictRepo->getFieldInfo($data['fieldId']);
            $this->response->addMeta($field);
        }
        else {
            $this->response->addMeta(["field" => [], "parentField" => []]);
        }

        return $this->response->send(["result" => $data['result']]);
    }

    public function getAdd(DictRequest $request)
    {
        $fieldId = $request->input("fieldId");
        $field = $this->dictRepo->getFieldInfo($fieldId);
        $this->response->addMeta($field);
        return $this->response->send();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function postAdd(DictRequest $request)
    {
        $this->dictRepo->add($request);
        return $this->response->send();
    }

    /**
     * 删除
     * @param DictRequest $request
     * @return mixed
     */

    public function postDel(DictRequest $request) {
        $result = $this->dictRepo->getById($request->input('id'));
        if($this->dictRepo->delete($request->input('id')) ) {
            userlog("删除了资产字典：".$result->name);
        }
        return $this->response->send();
    }


    /**
     * 编辑
     * @param DictRequest $request
     */
    public function getEdit(DictRequest $request) {
        $data = $this->dictRepo->getById($request->input("id"));
        $fieldId = $data->field_id;
        $field = $this->dictRepo->getFieldInfo($fieldId);
        $this->response->addMeta($field);
        return $this->response->send($data);
    }

    /**
     * 编辑
     * @param DictRequest $request
     * @return mixed
     */
    public function postEdit(DictRequest $request)
    {
        $this->dictRepo->edit($request);

        return $this->response->send();
    }

    public function getType(DictRequest $request) {
        $data = $this->dictRepo->getType($request);
        return $this->response->send(["result" => $data]);
    }






}
