<?php
/**
 * 保存临时变量
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 17:29
 */

namespace App\Support;

class Singleton {

    private static $instance = null;

    public static function getInstance($cls)
    {
        if (!isset(self::$instance)) {
            self::$instance = new $cls;
        }
        return self::$instance;
    }


}