<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Support\Response;
use Illuminate\Http\Request;
use App\Support\GValue;
use App\Models\Userlog;
use App;
use Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected $response;

    public function init(Request $request) {
        //$this->initPageParams($request);
        $this->response = new Response();
        if(false === GValue::$ajax && GValue::$httpMethod === "GET") {
            $this->response->setMenu();
            $this->response->setUser();
        }
    }


//    public function initPageParams($request){
//        GValue::$orderBy = $request->input("orderBy");
//        GValue::$perPage = $request->input("per_page");
//        GValue::$page = $request->input("page");
//    }
}
