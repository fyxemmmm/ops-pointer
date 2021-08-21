<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/29
 * Time: 21:52
 */

namespace App\Exceptions;

use App\Models\Code;
use Exception;

class ApiException extends Exception {
    function __construct(int $code = 0, $params = null, $message = null)
    {
        Code::setCode($code, $message, $params);
        list($code, $msg) = Code::getCode();
        parent::__construct("api exception: $msg");
    }
}