<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 17:12
 */

namespace App\Http\Middleware;

use Closure;
use DB;
use Auth;
use App\Models\Auth\User;
use App\Support\Gvalue;


class DbSelect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if(!empty($user)) {
            $db = $user->db;
            if(\ConstInc::MULTI_DB && !empty($db)) {
                DB::setDefaultConnection($db);
                GValue::$currentDB = $db;
                \ConstInc::monitorCurrentConf($db);
                $user = User::where("name","=",$user->name)->first();
                if($user) {
                    Auth::setUser($user);
                }
            }else {
                if (!$db) {
                    //默认库
                    $db = DB::getDatabaseName();
                    \ConstInc::monitorCurrentConf($db);
                }
            }

        }

        return $next($request);
    }
}