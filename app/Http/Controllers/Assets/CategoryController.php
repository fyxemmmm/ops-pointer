<?php

namespace App\Http\Controllers\Assets;

use App\Repositories\Assets\CategoryRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Assets\CategoryRequest;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{

    protected $category;

    function __construct(CategoryRepository $category) {
        $this->category = $category;
    }

    public function getList(Request $request)
    {
        $cid = $request->input("cid", 0);
        $data = $this->category->getList($request->input("all", 0),$cid);
        return $this->response->send($data);
    }

    public function getFields(CategoryRequest $request) {
        $fields = $this->category->getFieldsList($request->input("categoryId"));
        return $this->response->send(["fields" => $fields]);
    }

    public function getEdit(CategoryRequest $request) {
        $data = $this->category->viewCategory($request->input("id"));
        return $this->response->send($data);
    }

    public function postAdd(CategoryRequest $request) {
        $this->category->addCategory($request);
        userlog("添加了资产分类：".$request->input("name"));
        return $this->response->send();
    }

    public function postDel(CategoryRequest $request) {
        $this->category->delCategory($request);
        return $this->response->send();
    }

    public function postEdit(CategoryRequest $request) {
        $this->category->editCategory($request);
        return $this->response->send();
    }
}
