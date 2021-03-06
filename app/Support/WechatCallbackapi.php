<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/8
 * Time: 15:10
 */

namespace App\Support;
//use Illuminate\Http\Request;


class wechatCallbackapi {
 
    private $token;
    private $encodingAesKey;
    private $encrypt_type;
    private $appid;
    private $postarr;
    private $item = '';
    private $input;
 
    public function __construct() {
        $this->token          = \ConstInc::WX_TOKEN;
        $this->encodingAesKey = \ConstInc::WX_ENCODINGAESKEY;
        $this->appid          = \ConstInc::WX_APPID;
    }
 
    public function checkSignature($input) {
//        $input = $request->input() ? $request->input() : array();
        $signature = isset($input["signature"])?$input["signature"]:'';
        $timestamp = isset($input["timestamp"])?$input["timestamp"]:'';
        $nonce     = isset($input["nonce"])?$input["nonce"]:'';
//        $signature = $_GET["signature"];
//        $timestamp = $_GET["timestamp"];
//        $nonce     = $_GET["nonce"];
        $tmpArr = array($this->token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = sha1( implode( $tmpArr ) );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
 
    public function valid() {
        $encryptStr="";
        if ($_SERVER['REQUEST_METHOD'] == "POST") {
            $postStr = file_get_contents("php://input");
            $array   = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->encrypt_type = isset($this->input["encrypt_type"]) ? $this->input["encrypt_type"] : '';
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
        } elseif (isset($this->input["echostr"])) {
            if($this->checkSignature()) {
                echo $this->input["echostr"];
                exit;
            }
        }
    }
 
    public function getValue($key) {
        if(isset($this->postarr[$key])) {
            return $this->postarr[$key];
        }
        return ''; 
    }
 
    public function setHeaderDate($type) {
        return array(
            'ToUserName'   => $this->getValue('FromUserName'),
            'FromUserName' => $this->getValue('ToUserName'),
            'CreateTime'   => time(),
            'MsgType'      => $type
        );
    }
 
    //??????????????????
    public function text($text='') {
        $arr = array(
            'Content'      => $text
        );
        $this->sendReply(array_merge($this->setHeaderDate('text'),$arr));
    }
 
    /**
     * ????????????
     * @param array $newsData
     * ????????????:
     *  array(
     *      "0"=>array(
     *          'Title'=>'msg title',
     *          'Description'=>'summary text',
     *          'PicUrl'=>'https://www.domain.com/1.jpg',
     *          'Url'=>'https://www.domain.com/1.html'
     *      ),
     *  )
     */
    public function news($newsData=array()) {
        $count = count($newsData);
        $arr   = array(
            'ArticleCount' => $count,
            'Articles'     => $newsData
        );
        $this->item = 'item';
        $this->sendReply(array_merge($this->setHeaderDate('news'),$arr));
    }
 
    /**
     * ????????????
     * @param string $mediaid
     */
    public function image($mediaid='')
    {
        $arr = array(
            'Image'=>array('MediaId'=>$mediaid)
        );
        $this->sendReply(array_merge($this->setHeaderDate('image'),$arr));
    }
 
    /**
     * ????????????
     * @param string $mediaid
     */
    public function voice($mediaid='')
    {
        $arr = array(
            'Voice'=>array('MediaId'=>$mediaid)
        );
        $this->sendReply(array_merge($this->setHeaderDate('voice'),$arr));
    }
 
    /**
     * ????????????
     * @param string $mediaid
     */
    public function video($mediaid='',$title='',$description='')
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
     * ????????????
     * @param string $title
     * @param string $desc
     * @param string $musicurl
     * @param string $hgmusicurl
     * @param string $thumbmediaid ??????????????????????????????id????????????
     */
    public function music($title,$desc,$musicurl,$hgmusicurl='',$thumbmediaid='') {
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
 
    //??????????????????
    public function sendReply($arr) {
        $xmldata = $this->setXml($arr);
        //file_put_contents('log_text.txt', '??????????????????'.date("Y-m-d H:i:s").'__________'.print_r($xmldata, true) . 'bb9bb', FILE_APPEND);
        if ($this->encrypt_type == 'aes') {
            $pc = new Prpcrypt($this->encodingAesKey);
            $array = $pc->encrypt($xmldata, $this->appid);
            $ret = $array[0];
            if ($ret != 0) {
                echo '';exit;
            }
            $timestamp = time();
            $nonce = rand(77,999)*rand(605,888)*rand(11,99);
            $encrypt = $array[1];
            $tmpArr = array($this->token, $timestamp, $nonce, $encrypt);
            sort($tmpArr, SORT_STRING);
            $signature = implode($tmpArr);
            $signature = sha1($signature);
            $xmldata = $this->generate($encrypt, $signature, $timestamp, $nonce);
        }
        $this->logger("T ".$xmldata);
        echo $xmldata;
    }
 
    /**
     * xml????????????????????????????????????????????????
     */
    private function generate($encrypt, $signature, $timestamp, $nonce)
    {
        //?????????????????????
        $format = "<xml>
            <encrypt><!--[CDATA[%s]]--></encrypt>
            <msgsignature><!--[CDATA[%s]]--></msgsignature>
            <timestamp>%s</timestamp>
            <nonce><!--[CDATA[%s]]--></nonce>
            </xml>";
        return sprintf($format, $encrypt, $signature, $timestamp, $nonce);
    }
 
    //????????????xml
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
 
    //????????????
    private function logger($log_content)
    {
        $max_size = 10000;
        $log_filename = "log.xml";
        if(file_exists($log_filename) and (abs(filesize($log_filename)) > $max_size)){unlink($log_filename);}
        file_put_contents($log_filename, date('H:i:s')." ".$log_content."\r\n", FILE_APPEND);
    }
 
}