<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 17:12
 */

namespace App\Http\Middleware;

use Closure;
use App\Support\GValue;
use DB;
use Config;
use Auth;


class InitParams
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
        //Request::setTrustedProxies()
        //print_r($request->all());

        //GValue::$ajax = preg_match("#(/page/)|(/[^\/]+/page/)#", $request->getPathInfo())?false:true;


        GValue::$orderBy = $request->input("orderBy");
        GValue::$perPage = $request->input("per_page");
        GValue::$page = $request->input("page");
        GValue::$user = Auth::user();

        GValue::$httpMethod = $request->getMethod();
        GValue::$debug = $debug = $request->input("debug",0);
        GValue::$nopage = $request->input("nopage",0);
        if(config('app.debug') && $debug == 1) {
            DB::enableQueryLog();
            $connections = array_keys(Config::get("database.connections",[]));
            foreach($connections as $connection) {
                DB::connection($connection)->enableQueryLog();
            }
        }


        // 获取监控、环控按钮的状态
        $user = Auth::user();
        $orgDb = DB::getDefaultConnection();
        $changeDb = false;

        if(\ConstInc::MULTI_DB) {
            $dbs = array();
            $connections = array_keys(\Config::get("database.connections",[]));
            foreach($connections as $conn) {
                if(strpos($conn, "opf_") === 0) {
                    $dbs[] = $conn;
                }
            }
            $db = isset($dbs[0]) ? $dbs[0] : '';
            if(!empty($user->db)){
                $db = $user->db;
            }
            if(!empty($db)) {
                DB::setDefaultConnection($db); //切换到实际的数据库
                $changeDb = true;
            }

        }

        if($user) {
            // 从 opf_base 数据库中取出监控状态值
            \ConstInc::$mOpen = DB::table('action_config')->where('key', 'pc_jk')->value('status');

            // 从 opf_base 数据库中取出环控状态值
            \ConstInc::$emOpen = DB::table('action_config')->where('key', 'pc_hk')->value('status');
        }

        //切回到默认的数据库
        if($changeDb) {
            DB::setDefaultConnection($orgDb);
        }

        return $next($request);
    }
}