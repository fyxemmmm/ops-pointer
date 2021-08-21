<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Kb;

use App\Models\Kb\Article;
use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Models\Code;

class ArticleRepository extends BaseRepository
{

    protected $model;
    protected $userRepository;

    public function __construct(Article $articleModel,
                                UserRepository $userRepository

){
        $this->model = $articleModel;
        $this->userRepository = $userRepository;
    }

    public function add($request) {
        $input = [
            "title" => $request->input("title"),
            "content" => $request->input("content"),
            "brief" => $request->input("brief"),
            "user_id" => $this->userRepository->getUser()->id,
        ];
        $this->store($input);
        userlog("添加了知识库文章：".$request->input("title"));
    }

    public function approve($request) {
        if(!$this->userRepository->isManager() && !$this->userRepository->isAdmin()) {
            Code::setCode(Code::ERR_KB_PERM);
            return;
        }

        $id = $request->input("id");

        $input = [
            "approved_at" => date("Y-m-d H:i:s"),
            "status" => $request->input("approve"),
            "approver_id" => $this->userRepository->getUser()->id,
        ];
        $this->update($id, $input);
        userlog("审核了知识库文章：".$request->input("title")." 审核结果：".$request->input("approve") == "1" ? "通过" : "不通过");
    }

    public function delete($request) {
        if(!$this->userRepository->isManager() && !$this->userRepository->isAdmin()) {
            Code::setCode(Code::ERR_KB_PERM);
            return;
        }

        $this->del($request->input("id"));
        userlog("删除了知识库文章，文章id：".$request->input("id"));
    }


    public function edit($request){
        $id = $request->input("id");
        $article = $this->getById($id);
        if($article->user_id != $this->userRepository->getUser()->id && !$this->userRepository->isManager() && !$this->userRepository->isAdmin()) {
            Code::setCode(Code::ERR_KB_PERM);
            return;
        }

        $input = [
            "title" => $request->input("title"),
            "content" => $request->input("content"),
            "brief" => $request->input("brief"),
            "status" => Article::STATUS_PREPARE
        ];
        $this->update($id, $input);
        userlog("编辑了知识库文章，文章id：".$request->input("id")." 标题：".$request->input("title"));
    }

    public function getEdit($request) {
        $id = $request->input("id");
        $article = $this->getById($id);

        //仅仅本人和管理员可以查看修改页面
        if($article->user_id != $this->userRepository->getUser()->id && !$this->userRepository->isManager() && !$this->userRepository->isAdmin()) {
            Code::setCode(Code::ERR_KB_PERM);
            return;
        }

        return $article;
    }

    /**
     * 查看文章内容
     * @param $request
     * @return mixed|void
     */
    public function view($request) {
        $id = $request->input("id");
        $article = $this->getById($id);
        if($article->status == Article::STATUS_APPROVE) {
            return $article;
        }
        else if($article->user_id != $this->userRepository->getUser()->id && !$this->userRepository->isManager() && !$this->userRepository->isAdmin()) {
            Code::setCode(Code::ERR_KB_PREPARE);
            return;
        }

        return $article;
    }

    /**
     * @param $request
     * @return mixed
     */
   public function getList($request) {
       //分为全部，已审核，待审核
       //管理员能看全部人的
       //普通人只能看到审核通过的和自己的

       $model = $this->model->with("user","approver");
       if(!is_null($request->input("status"))) {
           //看到自己的，或者管理员看到所有的
           if($this->userRepository->isManager() || $this->userRepository->isAdmin()) {
               $where['status'] = $request->input("status");
           }
           else {
               $where['status'] = $request->input("status");
               $where['user_id'] = $this->userRepository->getUser()->id;
           }

           $model->where($where);
       }
       else {
           $where = [];
           //查看全部时只能看审核通过的
           if(!$this->userRepository->isManager() && !$this->userRepository->isAdmin()) {
               $where["status"] = Article::STATUS_APPROVE;
               $model->where(function ($q) use ($where) {
                   $q->where($where)->orWhere(["user_id" => $this->userRepository->getUser()->id]);
               });
           }
       }

       if(!empty($request->input("search"))) {
           $model->where("title","like", "%".$request->input("search")."%");
       }

       if (false !== ($between = $this->searchTime($request))) {
           $model->whereBetween("updated_at", $between);
       }

       $model->select(["id","title","brief","created_at","updated_at","user_id","approver_id","status"]);
       return $this->usePage($model);
   }





}
