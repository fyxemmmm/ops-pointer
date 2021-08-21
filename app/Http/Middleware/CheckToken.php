<?php

namespace App\Http\Middleware;

use Closure;
use App\Exceptions\ApiException;
use App\Models\Code;

class CheckToken
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

        $params = $request->all();

        if(is_null($request->input('token')) || is_null($request->input('ts'))){
            throw new ApiException(Code::ERR_NO_PARAMS);
        }

        // 验证时间有效期
        $expireTime = time() - 2 * 60;
        $ts = $request->input('ts');
        if($expireTime > $ts) throw new ApiException(Code::ERR_EXPIRE);

        if('api/inventory/device/report' == $request->path()) {
            // 去除掉数组参数
            foreach ($params as $ke => $value){
                if(is_array($value)) unset($params[$ke]);
            }
        }

        // 数组键名全部转小写
        $arrKeyToLower = array_change_key_case($params);

        // 去除 token
        if(isset($arrKeyToLower['token'])) unset($arrKeyToLower['token']);
        // 数组键名排序
        ksort($arrKeyToLower);
        $new = [];
        foreach ($arrKeyToLower as $k => $v){
            $new[] = $k.'='.$v;
        }
        $newStr = implode('',$new);
        $key = 'YeSheng123';
        $token = md5($newStr.$key);
        
        if(array_change_key_case($params)['token'] != $token){
            throw new ApiException(Code::ERR_NO_AUTH);
        }

        return $next($request);
    }
}
