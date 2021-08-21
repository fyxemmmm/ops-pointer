<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Setting;

use App\Models\Setting\Userlog;
use App\Repositories\Auth\UserRepository;
use App\Repositories\BaseRepository;
use App\Models\Code;

class UserlogRepository extends BaseRepository
{

    protected $model;
    protected $userRepository;

    public function __construct(Userlog $userlog){
        $this->model = $userlog;
    }

    /**
     * @param $request
     * @return mixed
     */
   public function getList($request) {
       $model = $this->model->with("user");
       return $this->usePage($model);
   }

}
