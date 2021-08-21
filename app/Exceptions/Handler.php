<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Models\Code;
use App\Support\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use App\Support\GValue;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if(config('app.debug') && $request->input("raw") == 1) {
            return parent::render($request, $exception);    //validtion的异常会跳转302,raw会把错误输出
        }

        $response = new Response();
        if($exception instanceof QueryException) {
            Code::setCode(Code::ERR_QUERY);
        }
        else if ($exception instanceof \PDOException) {
            Code::setCode(Code::ERR_DB);
        }
        else if ($exception instanceof ModelNotFoundException) {
            Code::setCode(Code::ERR_MODEL);
        }
        else if ($exception instanceof ValidationException) {
            Code::setDetail($exception->errors());
            Code::setCode(Code::ERR_PARAMS, null, array_values($exception->errors())[0]);
        }
        else if ($exception instanceof AuthenticationException) {
            Code::setCode(Code::ERR_HTTP_UNAUTHORIZED);
        }
        else if($exception instanceof ApiException) {

        }
        else {
            $ret = parent::render($request, $exception);
            $code = $ret->getStatusCode();
            Code::setCode($code);
        }

        $response->setException($exception);
        return $response->send();
    }
}
