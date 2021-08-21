<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 17:12
 */

namespace App\Http\Middleware;

use App\Support\Response;
use Closure;
use DB;
use App\Models\Auth\User;
use App\Models\Code;
use Auth;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if(1 == $request->input("nologin",0)) {
            $user = Auth::user();
            $account = $request->input("account", "admin");
            if(empty($user)) {
                $user = User::where("name", "=", $account)->first();
                if(\ConstInc::MULTI_DB && isset($user->db) && !empty($user->db)) {
                    $db = $user->db;
                    DB::setDefaultConnection($db); //切换到实际的数据库
                    $user = User::where("name", "=", $account)->first();
                }
                Auth::guard()->login($user);
            }
        }

        if(Auth::guard($guard)->guest()) {
            Code::setCode(Code::ERR_HTTP_UNAUTHORIZED);
            //todo page 重定向
            $response = new Response();
            return $response->send();
        }

        return $next($request);
    }
}