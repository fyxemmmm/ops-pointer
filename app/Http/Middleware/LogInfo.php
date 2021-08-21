<?php

namespace App\Http\Middleware;

use Closure;
use Log;
use App\Http\Requests\Request;

class LogInfo
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
        Log::info("Request", $this->getRequestInfo($request));
        $response = $next($request);
        $log = ["data" => $response->getContent()];
        if(property_exists($response,"exception")){
            $log['exception'] = $response->exception;
        }
        Log::info("Response" , $log);
        return $response;
    }

    private function getRequestInfo($request) {
        //Request::setTrustedProxies(['172.17.0.1']);
        return [
            "ip" => $request->getClientIp(),
            "method" => $request->method(),
            "url" => $request->url(),
            "params" => $request->all(),
        ];
    }
}
