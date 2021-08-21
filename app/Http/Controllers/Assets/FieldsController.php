<?php

namespace App\Http\Controllers\Assets;

use App\Repositories\Assets\FieldsRepository;
use App\Repositories\Assets\DeviceRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Assets\FieldsRequest;
use App\Http\Controllers\Controller;

class FieldsController extends Controller
{

    protected $device;
    protected $category;
    protected $fields;

    function __construct(FieldsRepository $fields) {
        $this->fields = $fields;
    }

    public function postAdd(FieldsRequest $request) {
        $this->fields->add($request);
        return $this->response->send();
    }

    public function postEdit(FieldsRequest $request) {
        $this->fields->edit($request);
        return $this->response->send();
    }

    public function postDel(FieldsRequest $request) {
        $this->fields->delete($request);
        return $this->response->send();
    }

    public function getList(FieldsRequest $request) {
        $data = $this->fields->getList($request);
        return $this->response->send($data);
    }

    public function getEdit(FieldsRequest $request) {
        $data = $this->fields->view($request);
        return $this->response->send($data);
    }


    public function postSearch(CategoryFieldsRequest $request) {
        $search =  $request->input("s");
        $data = $this->device->search($search);
        return $this->response->send($data);
    }



}
