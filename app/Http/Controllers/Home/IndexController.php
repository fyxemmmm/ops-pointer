<?php
/**
 * 首页
 */

namespace App\Http\Controllers\Home;
//namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Weixin\CommonRepository;
//use App\Repositories\Weixin\WeixinUserRepository;
use App\Models\Code;
use App\Exceptions\ApiException;
use Cache;

class IndexController extends Controller
{

    protected $common;

    private $token;
    private $encodingAesKey;
    private $encrypt_type;
    private $appid;
    private $postarr;
    private $item = '';
    private $key = '';
    private $weixinuser;

    function __construct(CommonRepository $common,Request $request)
    {
        $this->common = $common;
        $this->token          = \ConstInc::WX_TOKEN;
        $this->encodingAesKey = \ConstInc::WX_ENCODINGAESKEY;
        $this->appid          = \ConstInc::WX_APPID;
//        $this->postValid($request);
//        $this->getValid($request);
//        $this->valid($request);

        $this->key = base64_decode($this->encodingAesKey . "=");
//        ,WeixinUserRepository $weixinuser
//        $this->weixinuser = $weixinuser;
    }




    public function index(Request $request){
//        $cacheKey = 'testkey';
//        $setCache = Cache::put($cacheKey,'tesssssaaaa','asdfasd');
//        $getCache = Cache::get($cacheKey);
//
//        dd($setCache,$getCache);exit;



//        $echoStr = isset($_GET["echostr"])?$_GET["echostr"]:'';
        $input = $request->input() ? $request->input() : array();

//        if($this->checkSignature($request)) {
//            echo isset($input["echostr"])?$input["echostr"]:'';
//            exit;
//        }

//        $options = array(
//            'token'=>'opfinger.wx', //填写你设定的key
//            'encodingaeskey'=>'hYfeJHyvSOhKMHSL4qxEalXyXtYAwkKGZ1tyYzxj5S4', //填写加密用的EncodingAESKey
//            'appid'=>'wx3150b4604a89abd4', //填写高级调用功能的app id
//        );
//        $weObj = new wechatCallbackapiTest($options);



            $logPath = base_path() . '/storage/wx/log_text.txt';

//            $postArr = $GLOBALS["HTTP_RAW_POST_DATA"];


//        echoLog($postArr,'111');
        if(!isset($input['echostr'])){
            $this->responseMsg($request);
        }else{
            $this->checkSignature($request);
            echo isset($input["echostr"])?$input["echostr"]:'';
            exit;
        }


    }


    public function test(){
//        $accessToken = $this->common->getAccessToken();
//        dd($accessToken);
        $rs = $this->common->test('o6UHYw15VkW6QghcqsXggReOWqug');
        dd($rs);
        exit;
    }

    public function testAccessToken(){
        $accessToken = $this->common->getAccessToken();
        dd($accessToken);
        exit;
    }



    public function testGetMenu(){
        $rs = $this->common->getMenu();
        dd($rs);exit;
    }


    private function testAddMenu(Request $request){
        $input = $request->post() ? $request->post() : array();

        $input = array(
            'button'=>array(
                array(
                    'type'=>'view',
                    'name'=>'报修',
                    'url'=>'http://wx.dev.opfinger.com/home/event/add'
                ),
                array(
                    'name'=>'我的事件',
                    'sub_button'=>array(
                        array(
                            'type'=>'view',
                            'name'=>'待处理',
                            'url'=>'http://wx.dev.opfinger.com/home/event/add'
                        ),array(
                            'type'=>'view',
                            'name'=>'处理中',
                            'url'=>'http://wx.dev.opfinger.com/home/event/add'
                        ),array(
                            'type'=>'view',
                            'name'=>'已处理',
                            'url'=>'http://wx.dev.opfinger.com/home/event/add'
                        ),array(
                            'type'=>'view',
                            'name'=>'已完成',
                            'url'=>'http://wx.dev.opfinger.com/home/event/add'
                        /*),
                        array(
                            'type'=>'view',
                            'name'=>'已关闭',
                            'url'=>'http://wx.dev.opfinger.com/home/event/add'*/
                        )
                    )
                /*),
                array(
                    'type'=>'view',
                    'name'=>'我的信息',
                    'url'=>'http://wx.dev.opfinger.com/home/event/add'*/
                )

            )

        );
//        dd(json_encode($menu));exit;
//        dd(json_encode($input));exit;
        $rs = $this->common->addMenu($input);
        dd($rs);exit;
    }


    private function testDelMenu(){
        $arr = array();
        $rs = $this->common->delMenu();
        dd($rs);exit;
    }




    private function responseMsg($request){

//        $where = array('openid'=>'o6UHYw15VkW6QghcqsXggReOWqug');
//        $wxUser = $this->weixinuser->getOne($where);
//        var_dump($wxUser);exit;

        $postStr = file_get_contents('php://input');
//        var_dump($postStr);exit;
//        echoLog($postStr,'111');

        $this->postarr = xmlobjectArray($postStr);

        $rxType = trim($this->getValue('MsgType'));
        $openid = trim($this->getValue('FromUserName'));//'o6UHYw15VkW6QghcqsXggReOWqug';//
        $wxInput = $this->postarr;
        $wxInput['openid'] = $openid;

        echoLog($wxInput,'111');
        $wxUserinfo = $this->common->getWeixinUserInfo($wxInput);
        session()->put('wxUserInfo',$wxUserinfo);
        //dd($wxUserinfo);exit;
//exit;

        switch ($rxType) {
            case 'text':
//                echoLog($this->postarr,'111');
                $content = $this->getValue('Content');
                $this->text($content);
                //业务处理...
                break;
            case 'event':
                switch ($this->getValue('Event')) {
                    case "subscribe":  // 关注
                        if (!empty($this->getValue('EventKey'))) { // 二维码参数值
                            //扫描带参数二维码，进行关注后的事件推送
                        }
                        echoLog($this->postarr,'subscribe ');
                        $this->text('客官你好！');
//业务处理...
                        break;
                    case "unsubscribe": //取消关注

                        echoLog($this->postarr,'unsubscribe');
                        //业务处理...
                        break;
                    case "SCAN": //扫描带参数二维码（用户已关注时的事件推送）
                        $content = $this->getValue('EventKey');  // 二维码参数值
                        //业务处理...
                        break;
                    case "CLICK": //菜单 - 点击菜单拉取消息
                        $content = $this->getValue('EventKey');  // 设置的关键字
                        //业务处理...
                        break;
                    case "LOCATION": //上报地理位置
                        $lat = $this->getValue('Latitude');  //地理位置纬度
                        $lng = $this->getValue('Longitude'); //地理位置经度
                        //业务处理...
                        break;
                    case "VIEW": //菜单 - 点击菜单跳转链接
                        $content = $this->getValue('EventKey'); // 跳转链接
                        //业务处理...
                        break;
                    case "TEMPLATESENDJOBFINISH"://模板消息返回信息
                        echoLog($wxInput,'tempate');
                        break;
                    default:
                        //业务处理...
                        break;
                }
                break;
            case 'image':
                break;
            case 'location':
                break;
            case 'voice':
                break;
            case 'video':
                break;
            case 'link':
                break;
            default:
                echo '';
                break;
        }
        /*$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);
            switch($RX_TYPE){
                case "text":
                    $resultStr = $this->handleText($postObj);
                    break;
                case "event":
                    $resultStr = $this->handleEvent($postObj);
                    break;
                default:
                    $resultStr = "Unknow msg type: ".$RX_TYPE;
                    break;
            }
            echo $resultStr;
        }else {
            echo "";
            exit;
        }*/
    }



    protected function checkSignature($request) {
        $input = $request->input() ? $request->input() : array();
        $signature = isset($input["signature"])?$input["signature"]:'';
        $timestamp = isset($input["timestamp"])?$input["timestamp"]:'';
        $nonce     = isset($input["nonce"])?$input["nonce"]:'';
//        $signature = isset($_GET["signature"])?$_GET["signature"]:'';
//        $timestamp = isset($_GET["timestamp"])?$_GET["timestamp"]:'';
//        $nonce = isset($_GET["nonce"])?$_GET["nonce"]:'';
        // 获取到微信请求里包含的几项内容
        echoLog($input,'checkSignature');
        $tmpArr = array($this->token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = sha1( implode( $tmpArr ) );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }



    private function valid($request) {
        $input = $request->input() ? $request->input() : array();
        $encryptStr="";
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $postStr = file_get_contents("php://input");
            $array   = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->encrypt_type = isset($input["encrypt_type"]) ? $input["encrypt_type"] : '';
            if($this->encrypt_type == 'aes') {
                $pc     = new Prpcrypt($this->encodingAesKey);
                $array  = $pc->decrypt($array['Encrypt'],$this->appid);
                if (!isset($array[0]) || ($array[0] != 0)) {
                    echo '';exit;
                }
                $this->postarr = $array[1];
            } else {
                $this->postarr = $array;
            }
        } elseif (isset($input["echostr"])) {
            if($this->checkSignature($request)) {
                echo $input["echostr"];
                exit;
            }
        }
    }

    /*public function postValid($request) {
        $input = $request->post() ? $request->post() : array();
//        var_dump($input);exit;
        $encryptStr="";
//        if ($_SERVER['REQUEST_METHOD'] == "POST") {
//            $postStr = file_get_contents("php://input");
        if(isset($input) && is_array($input)) {
            $array = (array)simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->postarr = $array;
        }
//        }
    }

    public function getValid($request){
        $input = $request->input() ? $request->input() : array();
        $array = (array)simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA);
        $this->encrypt_type = isset($input["encrypt_type"]) ? $input["encrypt_type"] : '';
        if ($this->encrypt_type == 'aes') {
            $array = $this->decrypt($array['Encrypt'], $this->appid);
            if (!isset($array[0]) || ($array[0] != 0)) {
                echo '';
                exit;
            }
            $this->postarr = $array[1];
        }
        if($this->checkSignature()) {
            echo $input["echostr"];
            exit;
        }
    }*/


    private function getValue($key) {
        if(isset($this->postarr[$key])) {
            return $this->postarr[$key];
        }
        return '';
    }

    private function setHeaderDate($type) {
        return array(
            'ToUserName'   => $this->getValue('FromUserName'),
            'FromUserName' => $this->getValue('ToUserName'),
            'CreateTime'   => time(),
            'MsgType'      => $type
        );
    }

    //回复文本消息
    private function text($text='') {
        $arr = array(
            'Content'      => $text
        );
        $this->sendReply(array_merge($this->setHeaderDate('text'),$arr));
    }

    /**
     * 回复图文
     * @param array $newsData
     * 数组结构:
     *  array(
     *      "0"=>array(
     *          'Title'=>'msg title',
     *          'Description'=>'summary text',
     *          'PicUrl'=>'https://www.domain.com/1.jpg',
     *          'Url'=>'https://www.domain.com/1.html'
     *      ),
     *  )
     */
    private function news($newsData=array()) {
        $count = count($newsData);
        $arr   = array(
            'ArticleCount' => $count,
            'Articles'     => $newsData
        );
        $this->item = 'item';
        $this->sendReply(array_merge($this->setHeaderDate('news'),$arr));
    }

    /**
     * 回复图片
     * @param string $mediaid
     */
    private function image($mediaid='')
    {
        $arr = array(
            'Image'=>array('MediaId'=>$mediaid)
        );
        $this->sendReply(array_merge($this->setHeaderDate('image'),$arr));
    }

    /**
     * 回复语音
     * @param string $mediaid
     */
    private function voice($mediaid='')
    {
        $arr = array(
            'Voice'=>array('MediaId'=>$mediaid)
        );
        $this->sendReply(array_merge($this->setHeaderDate('voice'),$arr));
    }

    /**
     * 回复视频
     * @param string $mediaid
     */
    private function video($mediaid='',$title='',$description='')
    {

        $arr = array(
            'Video'=>array(
                'MediaId'=>$mediaid,
                'Title'=>$title,
                'Description'=>$description
            )
        );
        $this->sendReply(array_merge($this->setHeaderDate('video'),$arr));
    }

    /**
     * 回复音乐
     * @param string $title
     * @param string $desc
     * @param string $musicurl
     * @param string $hgmusicurl
     * @param string $thumbmediaid 音乐图片缩略图的媒体id，非必须
     */
    private function music($title,$desc,$musicurl,$hgmusicurl='',$thumbmediaid='') {
        $arr = array(
            'Music'=>array(
                'Title'=>$title,
                'Description'=>$desc,
                'MusicUrl'=>$musicurl,
                'HQMusicUrl'=>$hgmusicurl
            )
        );
        if ($thumbmediaid) {
            $arr['Music']['ThumbMediaId'] = $thumbmediaid;
        }
        $this->sendReply(array_merge($this->setHeaderDate('music'),$arr));

    }

    //发送回复代码
    private function sendReply($arr,$isEncrypt=false) {
        $xmldata = $this->setXml($arr);
        if($isEncrypt) {
            if ($this->encrypt_type == 'aes') {
//            $pc = new Prpcrypt($this->encodingAesKey);
                $array = $this->encrypt($xmldata, $this->appid);
                $ret = $array[0];
                if ($ret != 0) {
                    echo '';
                    exit;
                }
                $timestamp = time();
                $nonce = rand(77, 999) * rand(605, 888) * rand(11, 99);
                $encrypt = $array[1];
                $tmpArr = array($this->token, $timestamp, $nonce, $encrypt);
                sort($tmpArr, SORT_STRING);
                $signature = implode($tmpArr);
                $signature = sha1($signature);
                $xmldata = $this->generate($encrypt, $signature, $timestamp, $nonce);
            }
        }
//        $this->logger("T ".$xmldata);
        echo $xmldata;exit;
    }

    /**
     * xml格式加密，仅请求为加密方式时再用
     */
    private function generate($encrypt, $signature, $timestamp, $nonce)
    {
        //格式化加密信息
        $format = "<xml>
            <encrypt><!--[CDATA[%s]]--></encrypt>
            <msgsignature><!--[CDATA[%s]]--></msgsignature>
            <timestamp>%s</timestamp>
            <nonce><!--[CDATA[%s]]--></nonce>
            </xml>";
        return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
    }

    //数组组装xml
    protected function arrayToXml($arr) {
        $xml = '';
        foreach ($arr as $key => $val) {
            $key = is_numeric($key) ? $this->item : $key;
            $xml .= "<$key>";
            if(is_numeric($val)) {
                $xml .= "$val";
            } else {
                $xml .= is_array($val) ? $this->arrayToXml($val) : '<![CDATA['.preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/",'',$val).']]>';
            }
            $xml .= "</$key>";
        }
        return $xml;
    }

    protected function setXml($arr) {
        return "<xml>".$this->arrayToXml($arr)."</xml>";
    }

    //日志记录
    private function logger($log_content)
    {
        $max_size = 10000;
        $log_filename = "log.xml";
        if(file_exists($log_filename) and (abs(filesize($log_filename)) > $max_size)){unlink($log_filename);}
        file_put_contents($log_filename, date('H:i:s')." ".$log_content."\r\n", FILE_APPEND);
    }




    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @return string 加密后的密文
     */
    public function encrypt($text, $appid)
    {
        try {
            //获得16位随机字符串，填充到明文之前
            $random = $this->getRandomStr();//"aaaabbbbccccdddd";
            $text = $random . pack("N", strlen($text)) . $text . $appid;
            // 网络字节序
            $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            //使用自定义的填充方式对明文进行补位填充
            $pkc_encoder = new PKCS7Encoder;
            $text = $pkc_encoder->encode($text);
            mcrypt_generic_init($module, $this->key, $iv);
            //加密
            $encrypted = mcrypt_generic($module, $text);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);

            //          print(base64_encode($encrypted));
            //使用BASE64对加密后的字符串进行编码
            return array(ErrorCode::$OK, base64_encode($encrypted));
        } catch (Exception $e) {
            //print $e;
            return array(ErrorCode::$EncryptAESError, null);
        }
    }
    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @return string 解密得到的明文
     */
    public function decrypt($encrypted, $appid)
    {
        try {
            //使用BASE64对需要解密的字符串进行解码
            $ciphertext_dec = base64_decode($encrypted);
            $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
            $iv = substr($this->key, 0, 16);
            mcrypt_generic_init($module, $this->key, $iv);
            //解密
            $decrypted = mdecrypt_generic($module, $ciphertext_dec);
            mcrypt_generic_deinit($module);
            mcrypt_module_close($module);
        } catch (Exception $e) {
            return array(ErrorCode::$DecryptAESError, null);
        }
        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16)
                return "";
            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appid = substr($content, $xml_len + 4);
            if (!$appid)
                $appid = $from_appid;
            //如果传入的appid是空的，则认为是订阅号，使用数据中提取出来的appid
        } catch (Exception $e) {
            //print $e;
            return array(ErrorCode::$IllegalBuffer, null);
        }
        if ($from_appid != $appid)
            return array(ErrorCode::$ValidateAppidError, null);
        //不注释上边两行，避免传入appid是错误的情况
        return array(0, $xml_content, $from_appid); //增加appid，为了解决后面加密回复消息的时候没有appid的订阅号会无法回复

    }
    /**
     * 随机生成16位字符串
     * @return string 生成的字符串
     */
    function getRandomStr()
    {
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < 16; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }
        return $str;
    }






}



