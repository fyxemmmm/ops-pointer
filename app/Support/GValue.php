<?php
/**
 * 保存临时变量
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 17:29
 */

namespace App\Support;

class GValue {

    /**
     * page/ajax
     * @var $ajax
     */
    public static $ajax;

    /**
     * HTTP请求方式，GET/POST
     * @var $httpMethod
     */
    public static $httpMethod;

    /**
     * 菜单
     * @var
     */
    public static $menu;

    /**
     * 是否调试
     * @var
     */
    public static $debug;


    /**
     * 分页信息
     * @var
     */
    public static $pageInfo;

    /**
     * controller
     * @var
     */
    public static $controller;

    /**
     * action
     * @var
     */
    public static $action;

    /**
     * page
     * @var
     */
    public static $page;

    /**
     * nopage 指定不分页
     * @var
     */
    public static $nopage;

    /**
     * @var
     */
    public static $perPage;


    /**
     * @var
     */
    public static $orderBy;

    /**
     * @var
     */
    public static $user;

    /**
     * 当前数据库
     * @var
     */
    public static $currentDB;

    /**
     * 内存缓存
     * @var
     */
    public static $cache = [];


    public static function setCache($key, $value, $group = null) {
        if(!empty($group)) {
            if(!isset(self::$cache[$group])) {
                self::$cache[$group] = [];
            }
            self::$cache[$group][$key] = $value;
        }
        else {
            self::$cache[$key] = $value;
        }

        return false;
    }

    public static function getCache($key, $group = null) {
        if(!empty($group)) {
            if(!isset(self::$cache[$group])){
                return false;
            }
            if(!isset(self::$cache[$group][$key])) {
                return false;
            }
            return self::$cache[$group][$key];
        }
        else {
            if(!isset(self::$cache[$key])) {
                return false;
            }
            return self::$cache[$key];
        }

    }

}