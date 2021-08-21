<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/11
 * Time: 13:56
 */

namespace App\Http\Controllers\Kb;

use App\Repositories\Kb\ArticleRepository;
use App\Http\Requests\Kb\ArticleRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ArticleController extends Controller
{

    protected $articleRepository;

    function __construct(ArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }

    public function postAdd(ArticleRequest $request) {
        $this->articleRepository->add($request);
        return $this->response->send();
    }

    public function postApprove(ArticleRequest $request) {
        $this->articleRepository->approve($request);
        return $this->response->send();
    }

    public function postDelete(ArticleRequest $request) {
        $this->articleRepository->delete($request);
        return $this->response->send();
    }

    public function postEdit(ArticleRequest $request) {
        $this->articleRepository->edit($request);
        return $this->response->send();
    }

    public function getView(ArticleRequest $request) {
        $data = $this->articleRepository->view($request);
        return $this->response->send($data);

    }

    public function getList(ArticleRequest $request) {
        $data = $this->articleRepository->getList($request);
        return $this->response->send($data);
    }

    public function getEdit(ArticleRequest $request) {
        $data = $this->articleRepository->getEdit($request);
        return $this->response->send($data);
    }

}
