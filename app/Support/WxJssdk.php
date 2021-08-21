<?php

/**
 * @description wechatJSSDK 操作类
 * authot：wangwei
 */
namespace App\Support;
class WxJssdk {
    private $appId;
    private $appSecret;
    private $accessToken;
    private $appDirTemp;

    public function __construct($accessToken=array()) {
        $this->appId     = \ConstInc::WX_PUBLIC == 2 ?  \ConstInc::WW_CORPID: \ConstInc::WX_APPID;
        $this->appSecret = \ConstInc::WX_PUBLIC == 2 ? \ConstInc::WW_CORPSECRET[\ConstInc::WW_AGENTID] : \ConstInc::WEIXINAPPIDS[\ConstInc::WX_APPID];
        $this->accessToken = $accessToken;
        $this->appDirTemp = base_path('storage');
    }

    public function getSignPackage($url='') {
        if(\ConstInc::WX_PUBLIC == 2){
            $jsapiTicket = $this->getQyJsApiTicket();
        }else {
            $jsapiTicket = $this->getJsApiTicket();
        }
        // 注意 URL 一定要动态获取，不能 hardcode.

        if(!$url) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        } else {
            $url = urldecode($url);
        }

        $timestamp = time();
        $nonceStr  = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $this->appId,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string,
        );
        if(\ConstInc::WX_PUBLIC == 2){
            $signPackage['beta'] = true;
            $signPackage['agentid'] = \ConstInc::WW_AGENTID;
        }
        return $signPackage;
    }

    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getJsApiTicket() {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        $tempPath = $this->appDirTemp.'/wxJSSDKTemp/';
        $filename = 'jsapi_ticket.json';
        $jsapiTicketFile = $tempPath.$filename;
        $initData = json_encode(array('expire_time'=>'','jsapi_ticket'=>''));
        $this->getPath($tempPath,$filename,$initData);

//        var_dump($jsapiTicketFile);
        $data = json_decode($this->getFile($jsapiTicketFile));
        $expireTime = isset($data->expire_time)?$data->expire_time:0;
        $accToken = isset($this->accessToken['accToken'])?$this->accessToken['accToken']:'';
//        var_dump($data);
        // $data = F('jsapi_ticket');
        if (!$data || $expireTime < time()) {
//            $accessToken = $this->getAccessToken();
            // 如果是企业号用以下 URL 获取 ticket
            $url = \ConstInc::WEIXIN_REQUREST_BASE_URL."cgi-bin/ticket/getticket?type=jsapi&access_token=$accToken";
            $res = json_decode(apiCurl($url));
//            var_dump($res);exit;
            if (!$res) {
                return 'get JsApiTicket error!';
            }
            $ticket = isset($res->ticket)?$res->ticket:'';
            if ($ticket) {
                $data->expire_time  = time() + 7000;
                $data->jsapi_ticket = $ticket;
                $jsonData = json_encode($data);
                $this->setFile($jsapiTicketFile, $jsonData);
                // F('jsapi_ticket', $data);
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }

        return $ticket;
    }


    /**
     * 企业微信
     * @return string
     */
    private function getQyJsApiTicket() {
        // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
        $tempPath = $this->appDirTemp.'/wxJSSDKTemp/';
        $filename = 'qy_jsapi_ticket.json';
        $jsapiTicketFile = $tempPath.$filename;
        $initData = json_encode(array('expire_time'=>'','jsapi_ticket'=>''));
        $this->getPath($tempPath,$filename,$initData);

//        var_dump($jsapiTicketFile);
        $data = json_decode($this->getFile($jsapiTicketFile));
        $expireTime = isset($data->expire_time)?$data->expire_time:0;
        $accToken = isset($this->accessToken['accToken'])?$this->accessToken['accToken']:'';
//        var_dump($data);
        // $data = F('jsapi_ticket');
        if (!$data || $expireTime < time()) {
//            $accessToken = $this->getAccessToken();
            // 如果是企业号用以下 URL 获取 ticket
            $url = \ConstInc::WW_REQUREST_BASE_URL."/cgi-bin/get_jsapi_ticket?access_token=$accToken";
            $res = json_decode(apiCurl($url));
//            var_dump($res);exit;
            if (!$res) {
                return 'get QyJsApiTicket error!';
            }
            $ticket = isset($res->ticket)?$res->ticket:'';
            if ($ticket) {
                $data->expire_time  = time() + 7000;
                $data->jsapi_ticket = $ticket;
                $jsonData = json_encode($data);
                $this->setFile($jsapiTicketFile, $jsonData);
                // F('jsapi_ticket', $data);
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }

        return $ticket;
    }

    private function getFile($filename) {
        return file_get_contents($filename);
    }
    private function setFile($filename, $data='',$fm='w') {
//        var_dump($filename,$data);exit;
        $fm = $fm ? $fm : 'w';
        $fp = fopen($filename, $fm);
        fwrite($fp, $data);
        fclose($fp);
    }

    private function getPath($path='',$filename='',$data='',$fm=''){
        $temp = $path.$filename;
        if(!is_dir($path)) {
            @mkdir($path, 0777,true);
        }
        if(!file_exists($temp)) {
            $this->setFile($temp, $data, $fm);
        }
        return true;
    }


    public function getMedia($media_id){
        $accToken = isset($this->accessToken['accToken'])?$this->accessToken['accToken']:'';
        $url = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=".$accToken."&media_id=".$media_id;
        //获取微信“获取临时素材”接口返回来的内容（即刚上传的图片）
        $a = apiCurl($url);
//        echo $a;
//        preg_match('/^(data:\s*image\/(\w+);base64,)/', $a, $result);
//        preg_match('/^(data:\s*image\/(\w+);base64,)/', $a, $result);
//            $type = $result[2];
//        var_dump($result);exit;
        $types = array('image/bmp'=>'.bmp', 'image/gif'=>'.gif', 'image/jpeg'=>'.jpg', 'image/png'=>'.png');
        if (isset($types[$a['header']['content_type']])) {
            echo $types[$a['header']['content_type']];exit;
        }exit;
        $filename = date('YmdHis').rand(1000,9999).'.jpg';
        //以读写方式打开一个文件，若没有，则自动创建
        $tempPath = $this->appDirTemp.'/wxuploads/'.date('Y-m-d').'/';
//        var_dump($tempPath,$filename);exit;
        $tempImg = $tempPath.$filename;
        $this->getPath($tempPath,$filename,$a,'W+');
        var_dump($filename);exit;
    }
}