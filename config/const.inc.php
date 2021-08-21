<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/25
 * Time: 11:21
 */

use App\Support\Gvalue;

class ConstInc {

    /**
     * 分页每页的数量
     */
    const PAGE_NUM = 20;

    /**
     * Home分页每页的数量
     */
    const  HPAGE_NUM = 2;

    /**
     * 公用
     */
    const HEADERS_JSON = array('Content-Type:application/json');

    /**
     * 导出限制
     */
    const EXPORT_LIMIT = 200;

    /**
     * 多数据库
     */
    const MULTI_DB = 0;

    /**
     * 上传图片大小，单位M
     */
    const MAX_UPLOAD_SIZE = 5;

    /**
     * QR信息
     */
    const QRSIZE = 150;
    const QR_PATH = "app/qr/";
    /**
     * 终端事件
     */
    const USER_AUTO_COMMENT_TIME = '300';//秒

    /**
     * 终端类别的ID
     * @var array
     */
    public static $terminalCategory = [5,6];

    /*
     * 地区相关固定字段
     */
    const ZONE="location"; //使用地
    const BUILDING="officeBuilding"; //办公楼
    const ENGINEROOM="area"; //机房
    const DEPARTMENT="department";  //科室
    
    /**
     * submail
     */
    const SMS_MESSAGE = array(
        'appid'=>'21498',
        'appkey' => '31699832c4cf44cfc800ab91f45a7b19',
        'sign_type' => 'normal'
    );


    /**
     * 监控，环近。每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
     */
    public static $mReportArr = [10,15];

    /**
     * 监控，环控，自动恢复的id号
     */
    public static $mRecoveryId = 25;

    /**
     * 监控，环控，日常巡检的id号
     */
    public static $mInspectionId = 24;

    /**
     * 监控
     */
    public static $mApiUrl='http://123.206.224.113:18081/yesheng';
    public static $mApiUsername = 'hq';
    public static $mApiPwd = 'abcd12345678abcd';
    public static $mAlertTime = 60;
    public static $mTokenFilename='hw_token.json';
    //监控功能开关
    public static $mOpen = 1;
    //每个模板最大的显示几份报告
    public static $mTemplateReportMax = 20;
    //批量绑定设备时每页显示多少条
    public static $mPages = 100;


    /**
     * 环控
     */
    const EM_API_URL_WS = "http://192.168.18.252/wems/WebServiceData.asmx";//万联
//    const EM_API_URL_WS = "http://139.196.98.23:8080/wems/WebServiceData.asmx";//万联外网系统
    //数据来源，1:默顿尔,2:万联,3:中联通
    const EM_DATA_SOURCE = 2;
    //最多几个模板
    const EM_TEMPLATE_NUM = 6;
    //每天最大报告数
    const REPORT_DATES_NUM = 3;
    //环控功能开关
    public static $emOpen = 0;
    //分类id
    public static $em_category_id = [51];
    //下拉选项分类
    public static $em_category_op = [52=>'ups',64=>'power',60=>'battery'];


    /**
     * 微信
     */
    const WX_TOKEN = "wxdevopfinger"; //填写你设定的key
    const WX_ENCODINGAESKEY = "ng6uDj8IMIrirmKAgwcSLDiUcg6gIZ2SjmYyIliAZM8"; //填写加密用的EncodingAESKey
    const WX_APPID = "wx3150b4604a89abd4"; //填写开发者ID(AppID)
    const WEIXINAPPIDS = array(
        self::WX_APPID => '443d3c3118bf581b9863dd07db95bbd9', //AppID:AppSecret
    );
    const WEIXIN_REQUREST_BASE_URL = 'https://api.weixin.qq.com/'; //微信平台请求接口基本路径
    const WEIXIN_REQUREST_QRCODE_URL = 'https://mp.weixin.qq.com/'; //微信生成二维码
    const ACCESS_TOKEN_TIMEOUT = 7000; //ACCESS_TOKEN超时时间10000s
    const HOME_LOGIN_PATH = 'http://monitordev.com/home/user/login'; //登陆页路径
    const TEMPLATE_MESSAGE = 'auWQw0J89b8u9RcmWGGbL3SpsuM2mmIRZ6lTq9Y1xBQ'; //模板消息ID
    //开启或关闭注册功能
    const WX_REGISTER = false;
    //OA事件
    const WX_OAETYPE = 1;
    //事件
    const WX_ETYPE = 0;


    /**
     * 企业微信
     */
    const WX_PUBLIC = 2; //企业微信号或公众号标示 0：停用此功能，1:公众号（服务号），2：企业微信
    const WW_REQUREST_BASE_URL = 'https://qyapi.weixin.qq.com';
    const WW_CORPID = 'ww456ec20f317c3b95'; //企业ID
    const WW_AGENTID = '1000006'; //应用agentid
    const WW_CORPSECRET = array(
        self::WW_AGENTID=>'X4rdIkzBhgNfyIcaotjhUn4tWmLm0v3E7QvcWuEhfng' //应用Secret
    );
    const WW_USER_DEFAULT_PWD = 'aa123456'; //默认密码


    public static $monitorConf = array(
        'opf_base' => array(
            'open' => 1,//监控功能开关,1:开启,0:关闭
            'url' => 'http://123.206.224.113:18081/yesheng',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//(监控，环近)每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),
        'opf_xxzx' => array(
            'open' => 1,//监控功能开关,1:开启,0:关闭
            'url' => 'http://123.206.224.113:18081/yesheng',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//(监控，环近)每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),
        /*'opf_dev' => array(
            'open' => 0,//监控功能开关,1:开启,0:关闭
            'url' => '',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),
        'opf_dev2' => array(
            'open' => 0,//监控功能开关,1:开启,0:关闭
            'url' => '',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),
        'opf_new' => array(
            'open' => 1,//监控功能开关,1:开启,0:关闭
            'url' => 'http://123.206.224.113:18081',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),
        'opf_mlsw' => array(
            'open' => 1,//监控功能开关,1:开启,0:关闭
            'url' => 'http://123.206.224.113:18081',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),
        'opf_mlsw2' => array(
            'open' => 1,//监控功能开关,1:开启,0:关闭
            'url' => 'http://123.206.224.113:18081',
            'username' => 'hq',
            'pwd' => 'abcd12345678abcd',
            'alert_time' => 60,
            'token_filename' => 'hw_token.json',
            'report_arr' => [10,15],//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
            'em_open' => 1,
        ),*/

    );


   /*
    * 监控设置
    */
    public static function monitorCurrentConf($db){
        $conf = isset(self::$monitorConf[$db]) ? self::$monitorConf[$db] : '';

        if($conf) {
            self::$mApiUrl = $conf['url'];
            self::$mApiUsername = $conf['username'];
            self::$mApiPwd = $conf['pwd'];
            self::$mAlertTime = $conf['alert_time'];
            self::$mTokenFilename = $conf['token_filename'];
            self::$mReportArr = $conf['report_arr'];//监控，环近
        }
        return $conf;
    }


}