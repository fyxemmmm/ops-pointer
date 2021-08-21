<?php

namespace App\Http\Controllers\Assets;

use App\Repositories\Assets\CategoryRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Assets\CategoryFieldsRequest;
use App\Http\Controllers\Controller;

class CategoryFieldsController extends Controller
{

    protected $category;

    function __construct(CategoryRepository $category) {
        $this->category = $category;
    }


    public function postModifyFields(CategoryFieldsRequest $request) {
        $this->category->modifyFields($request); //json
        return $this->response->send();
    }


    /**
     * @param CategoryFieldsRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getList(CategoryFieldsRequest $request) {
        $data = $this->category->getCategoryFields($request);
        return $this->response->send($data);
    }


}
