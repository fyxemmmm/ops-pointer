<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Support\GValue;

Route::prefix('home/index')->group(function() {
    Route::get('/', 'Home\IndexController@index');
    Route::post('/', 'Home\IndexController@index');

    Route::get('test', 'Home\IndexController@test');
    Route::post('test', 'Home\IndexController@test');

    Route::get('testaccesstoken', 'Home\IndexController@testAccessToken');
    Route::post('testaccesstoken', 'Home\IndexController@testAccessToken');
    Route::get('testgetmenu', 'Home\IndexController@testGetMenu');
    Route::get('testaddmenu', 'Home\IndexController@testAddMenu');
    Route::post('testaddmenu', 'Home\IndexController@testAddMenu');
    Route::get('testdelmenu', 'Home\IndexController@testDelMenu');
});
//Route::any('home/index', 'Home\IndexController@index');


Route::group(['prefix' => 'home/user', 'middleware' => 'dbselect'],function(){
    Route::get('/', 'Home\UserController@index');
    Route::get('login', 'Home\UserController@getLogin');
    Route::get('register', 'Home\UserController@register');
    Route::post('registeract', 'Home\UserController@registerAct');
    Route::post('loginact','Home\UserController@loginAct');
    Route::post('sendsms','Home\UserController@sendSMS');
    Route::post('verifycodephone','Home\UserController@verifyCodePhone');
    Route::get('getsnsinfo','Home\UserController@getSnsinfo');
    Route::get('getsignpackage','Home\UserController@getSignPackage');
    //  下面的没有看
    Route::post('forgotpwdact','Home\UserController@forgotpwdAct');
    Route::get('getwxmedia','Home\UserController@getwxMedia');
    Route::get('logout','Home\UserController@logout');
    Route::get('tracknoticeshow','Home\UserController@trackNoticeShow');
    Route::get('forgetwxsession','Home\UserController@getForgetwxSession');
    Route::get('showtest','Home\UserController@showTest');
    Route::get('getforgetusersession','Home\UserController@getForgetUserSession');
    Route::get('test','Home\UserController@getTest');


});


//企业微信
Route::group(['prefix' => 'home/qy', 'middleware' => 'dbselect'],function(){
    Route::get('/', 'Home\QyController@index');
    Route::get('login', 'Home\QyController@getLogin');
    Route::post('loginact','Home\QyController@loginAct');
    Route::get('sendMsg','Home\QyController@postSendMsg');
});

//Route::group(['prefix' => 'home/event'],function(){
Route::group(['prefix' => 'home/event', 'middleware' => ['auth','dbselect']],function(){
    Route::get('/','Home\EventController@index');
    Route::get('add','Home\EventController@add');
    Route::post('addact','Home\EventController@addAct');
    Route::get('getlist','Home\EventController@getList');
    Route::get('details','Home\EventController@details');
    Route::get('assetsdetails','Home\EventController@getAssetsDetails');
    Route::post('commentact','Home\EventController@commentAct');
    Route::get('assetssearch','Home\EventController@getAssetsSearch');
    Route::post('categorylist','Home\EventController@selectCategoryByAsset');
    Route::post('trackact','Home\EventController@trackAct');
    Route::post('assignact','Home\EventController@assignAct');
    Route::get('track','Home\EventController@trackShow');
    Route::post('process','Home\EventController@processShow');
    Route::get('getdevice','Home\EventController@getDevice');
    Route::post('engineeradd','Home\EventController@engineerAdd');
    Route::get('engineeraddshow','Home\EventController@engineerAddShow');
    Route::post('engineersaveclose','Home\EventController@engineerSaveClose');
    Route::get('report','Home\EventController@report');
    Route::get('tracknoticeshow','Home\EventController@trackNoticeShow');
    Route::get('testautocomment','Home\EventController@testAutoComment');
    Route::get('getengineers','Home\EventController@getEngineers');
    Route::get('rackinfo','Home\EventController@rackInfo');
    Route::get('ports','Home\EventController@ports');
    Route::post('portconnect','Home\EventController@portConnect');
    Route::post('suspend','Home\EventController@suspend');
    Route::get('suspendlist','Home\EventController@suspendList');
});


Route::group(['prefix' => 'home/eventoa', 'middleware' => ['auth','dbselect']],function(){
    Route::get('/','Home\EventOaController@index');
    Route::post('addact','Home\EventOaController@addAct');
    Route::get('list','Home\EventOaController@getList');
    Route::get('details','Home\EventOaController@details');
    Route::get('track','Home\EventOaController@trackShow');
    Route::post('trackact','Home\EventOaController@trackAct');
    Route::post('commentact','Home\EventOaController@commentAct');
    Route::get('report','Home\EventOaController@report');
    Route::post('engineeradd','Home\EventOaController@engineerAdd');
    Route::get('engineers','Home\EventOaController@getEngineers');
    Route::post('suspend','Home\EventOaController@suspend');
    Route::get('suspendlist','Home\EventOaController@suspendList');
});



//Route::group(['prefix' => 'home/myinfo'],function(){
Route::group(['prefix' => 'home/myinfo', 'middleware' => ['auth','dbselect']],function(){
    Route::get('/','Home\MyInfoController@index');
    Route::post('modifypwdact','Home\MyInfoController@modifyPwdAct');
    Route::get('modifyinfo','Home\MyInfoController@modifyInfo');
    Route::post('modifyinfoact','Home\MyInfoController@modifyInfoAct');
    Route::post('feedbackact','Home\MyInfoController@feedbackAct');

    // 微信端功能配置按钮列表
    Route::get('actionconfiglist','Home\MyInfoController@actionConfigList');
});

Route::group(['prefix' => 'home/government','middleware'=>['auth','dbselect']],function(){
   Route::get('weekreport','Home\GovernmentController@getweekReport');
   Route::get('applist','Home\GovernmentController@getAppList');
   Route::get('resourcereport','Home\GovernmentController@getResourceReport');
});


function commonRoute($type, $module, $controller, $action = 'index') {
    $controller = "App\Http\Controllers\\".ucfirst($module)."\\".ucfirst($controller)."Controller";
    $instance = App::make($controller);
    GValue::$ajax = $type === "ajax" ? true :false;
    GValue::$controller = $controller;
    GValue::$action = $action;
    App::call([$instance, 'init']);
    if(!method_exists($instance, $action)) {
        abort(404);
    }
    return App::call([$instance, $action]);
}

Route::post('/ajax/login', function() {
    return commonRoute('ajax', "auth", "login", "postLogin");
});

Route::post('/ajax/logout', function() {
    return commonRoute('ajax', "auth", "login", "postLogout");
});

Route::group(['prefix' => 'page', 'middleware' => ['auth','dbselect']], function() {
    Route::get('/{module}/{controller}/{action?}', function($module, $controller, $action = 'index') {
        return commonRoute('page', $module, $controller, 'get'.ucfirst($action));
    });
    Route::post('/{module}/{controller}/{action?}', function($module, $controller, $action = 'index') {
        return commonRoute('page', $module, $controller, 'post'.ucfirst($action));
    });
});

Route::group(['prefix' => 'ajax', 'middleware' => ['auth','dbselect']], function() {
    Route::get('/{module}/{controller}/{action?}', function($module, $controller, $action = 'index') {
        return commonRoute('ajax', $module, $controller, 'get'.ucfirst($action));
    });
    Route::post('/{module}/{controller}/{action?}', function($module, $controller, $action = 'index') {
        return commonRoute('ajax', $module, $controller, 'post'.ucfirst($action));
    });
});


Route::group(['prefix' => 'api', 'middleware' => ['token']], function() {
    Route::get('/{module}/{controller}/{action?}', function($module, $controller, $action = 'index') {
        return commonRoute('ajax', $module, $controller, 'get'.ucfirst($action));
    });
    Route::post('/{module}/{controller}/{action?}', function($module, $controller, $action = 'index') {
        return commonRoute('ajax', $module, $controller, 'post'.ucfirst($action));
    });
});

