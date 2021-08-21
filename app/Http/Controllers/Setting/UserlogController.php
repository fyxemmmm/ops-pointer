<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/11
 * Time: 13:56
 */

namespace App\Http\Controllers\Setting;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Setting\UserlogRepository;

class UserlogController extends Controller
{

    protected $userlog;

    function __construct(UserlogRepository $userlog)
    {
        $this->userlog = $userlog;
    }

    public function getList(Request $request) {
        $data = $this->userlog->getList($request);
        return $this->response->send($data);
    }

}
