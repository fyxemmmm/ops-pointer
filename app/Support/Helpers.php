<?php
use App\Models\Code;
use App\Models\Setting\Userlog;
use App\Models\Auth\User;
use Illuminate\Pagination\LengthAwarePaginator;

if(!function_exists('apiCurl')) {
    /**
     * @param $url
     * @param $method
     * @param array $headers
     * @param array $data
     * @param bool $head
     * @param bool $http_code
     * @return mixed
     */
    function apiCurl($url, $method='get', $headers = array(), $data = array(), $head = false, $http_code = false)
    {
        Log::debug($url, ["method" => $method, "data" => $data]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, $head);
        if ($http_code && $head) {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING ,'gzip'); //加入gzip解析
        $method = strtoupper($method);

        switch ($method) {
            case 'GET':
                break;

            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//        Log::debug("response", ["response" => $response, "code" => $code]);
        if ($http_code && !$head) {
            Log::error("response", ["error" => curl_error($ch), "code" => $code, "response" => $response]);
            return $code;
        }
        return $response;
    }
}

if(!function_exists('checkCreateDir')) {
    function checkCreateDir($dir) {
        if(!is_dir($dir)) {
            return @mkdir($dir, 0777, true);
        }
        return true;
    }
}

if(!function_exists('uploadPics')){
    /**
     * 多图片上传
     * @param array $param
     * @param string $fileKey
     * @param string $uploadPath 上传的真实目录
     * @param string $path 需要保存到数据库中的目录 如果为空则为 $uploadPath
     * @return array|string
     */
    function uploadPics($param=array(),$fileKey='',$uploadPath='',$path=''){
        //$request->allFiles()

        $files = $param->file($fileKey);
//        $result = array('status'=>'error','data'=>array(),'msg'=>'','errCode'=>0);
        $filePath =[];  // 定义空数组用来存放图片路径
        $smallFilePath = [];
//        var_dump($files,$fileKey,$uploadPath);exit;
        if($files && is_array($files) && $fileKey && $uploadPath) {
            foreach ($files as $key => $value) {
//                var_dump($value);exit;
                // 判断图片上传中是否出错
                if (!$value->isValid()) {
                    return array('status'=>'error','data'=>array(),'msg'=>'上传图片出错，请重试！','errCode'=>Code::ERR_UPLOADING);
                }

                if (!empty($value)) {//此处防止没有多文件上传的情况

                    $allowed_extensions = ["png", "jpg",'jpeg', "gif"];
                    $extension = strtolower($value->getClientOriginalExtension());// 上传文件后缀
                    if ($extension && !in_array($extension, $allowed_extensions)) {
                        return array(
                            'status'=>'error',
                            'data'=>array(),
                            'msg'=>'您只能上传PNG、JPG或GIF格式的图片！',
                            'errCode'=>Code::ERR_UPLOAD_TYPE
                        );
                    }
                    $storagePath = base_path();
                    $dirName = '';//date('Y-m-d');
                    $destinationPath = $uploadPath . $dirName; // public文件夹下面/xxxx-xx-xx 建文件夹
                    $fileName = date('YmdHis') . mt_rand(100, 999) . '.' . $extension; // 重命名
                    $realBasePath = $storagePath . $destinationPath;
                    $basePathImg = $realBasePath.'/'.$fileName;
                    //获取文件大小
                    $img_size = round($value->getSize()/1000/1000,2);
                    //上传图片最大值，单位M
                    $maxSize = \ConstInc::MAX_UPLOAD_SIZE;
//                    var_dump($img_size,$maxSize);exit;
                    if($img_size > $maxSize){
                        return array(
                            'status'=>'error','data'=>array(),
                            'msg'=> $maxSize.'M！',
                            'errCode'=>Code::ERR_UPLOAD_SIZE
                        );
                    }


                    //dd($storagePath,$destinationPath);exit;
                    $up = $value->move($realBasePath, $fileName); // 保存图片



//                    $path = $path ? $path : $uploadPath;
                    $filePath[] = $fileName;
                    $realPathThumb = $storagePath . $destinationPath.'/thumb';
                    $realPathWeb = $storagePath . $destinationPath.'/web';

//                    var_dump($basePathImg,$realPathWeb);exit;
//                    $imgSize = getimagesize($basePathImg);
//                    $imgWidth = isset($imgSize[0]) ? $imgSize[0] : 0;
//                    $imgHeight = isset($imgSize[1]) ? $imgSize[1] : 0;
//                    var_dump($img_size,$imgSize,$imgSize[0],$imgSize[1]);exit;
//                    $webSize = calcSize($imgWidth,$imgHeight,1080,1440);
//                    $thumbSize = calcSize($imgWidth,$imgHeight,100,140);

                    //网页图片
//                    $webPic = smallimg($realBasePath,$realPathWeb,$fileName,$webSize['width'],$webSize['height']);
                    $webPic = smallimg($realBasePath,$realPathWeb,$fileName,1080,1440);
                    //图片缩略图
//                    $thumbPic = smallimg($realBasePath,$realPathThumb,$fileName,$thumbSize['width'],$thumbSize['height']);
                    $thumbPic = smallimg($realBasePath,$realPathThumb,$fileName,100,140);

//                    var_dump($webPic,$thumbPic);exit;
                }
            }
        }
        // 返回上传图片路径，用于保存到数据库中

        return array('status'=>'success','data'=>$filePath,'msg'=>'ok','errCode'=>0);
    }
}


if(!function_exists('smallimg')) {
    //图片缩略图
    function smallimg($basePath='',$path='',$filename='',$width = 100, $height = 100)
    {
        $pathfile = $basePath .'/'.$filename;
        $info = getimagesize($pathfile); //获取图片的基本信息
//        var_dump($pathfile);exit;
//        $imType = isset($info[2]) ? $info[2] : '';
//        $im = getPicType($imType,$pathfile);
        $w = isset($info[0]) ? $info[0] : 0;//获取宽度
        $h = isset($info[1]) ? $info[1] : 0;//获取高度

        //原图片尺寸大小比自定义的小不做调整，否则做缩放调整，根据宽度做调整
        $tmpw = $w > $width ? $width : $w;
        if ($tmpw == $w) {
            $nw = $tmpw;
            $nh = $h;
        } else {
            $nw = $tmpw;
            $nh = intval($h * ($tmpw / $w));
        }
//        var_dump($w,$h,$width,$tmpw,$nw,$nh);exit;


//        var_dump($width,$height,$path);exit;
        // 读取原图
        $im = new Imagick($pathfile);
//        $im->thumbnailImage(100,100);

        // 创建缩图
        $im->cropThumbnailImage($nw, $nh);
        // 写文件
        if(!is_dir($path)) {
            @mkdir($path, 0777,true);
        }
        $thumb = $path.'/'.$filename;
        $im->writeImage( $thumb);
        return $im;




        /*$imageData = file_get_contents($pathfile);
        $im = imagecreatefromstring($imageData);
        $w = imagesx($im);//获取宽度
        $h = imagesy($im);//获取高度
        $tmpw = $w > $width ? $width : $w;
        if ($tmpw == $w) {
            $nw = $tmpw;
            $nh = $h;
        } else {
            $nw = $tmpw;
            $nh = intval($h * ($tmpw / $w));
        }
        if(function_exists("imagecreatetruecolor")) {
            $dim = imagecreatetruecolor($nw, $nh); // 创建目标图gd2
        } else {
            $dim = imagecreate($nw, $nh); // 创建目标图gd1
        }
        imageCopyreSampled ($dim,$im,0,0,0,0,$nw,$nh,$w,$h);
        if(!is_dir($path)) {
            @mkdir($path, 0777,true);
        }
        $image = imagejpeg ($dim, $path.'/'.$filename, 80);
        imagedestroy($dim);
        return $image;*/
    }
}

if(!function_exists('calcSize')) {
    //根据指定最大宽高来计算缩略图尺寸
    function calcSize($width = '1080', $height = '1440', $maxwidth = '100', $maxheight = '100')
    {
        $width = $width ? $width : 1080;
        $height = $height ? $height : 1440;
        //缩略图最大宽度与最大高度比
        $thcrown = $maxwidth / $maxheight;
        //var_dump($thcrown);
        //原图宽高比
        $crown = $width / $height;
        //var_dump($crown,$thcrown,$crown/$thcrown);
        if ($crown / $thcrown >= 1) {
            $thwidth = $maxwidth;
            $thheight = round($maxwidth / $crown);
        } else {
            $thheight = $maxheight;
            $thwidth = round($maxheight * $crown);
        }
        return array('width'=>$thwidth, 'height'=>$thheight);
    }
}

if(!function_exists("getPicType")) {
    function getPicType($type, $picname)
    {
        $im = null;
        switch ($type) {
            case 1:  //GIF
                $im = imagecreatefromgif($picname);
                break;
            case 2:  //JPG
                $im = imagecreatefromjpeg($picname);
                break;
            case 3:  //PNG
                $im = imagecreatefrompng($picname);
                break;
            case 4:  //BMP
                $im = imagecreatefromwbmp($picname);
                break;
            default:
                die("不认识图片类型");
                break;
        }
        return $im;
    }
}


if(!function_exists("randNumber")){

    /**
     * 生成随机数
     * @param int $len
     * @return string
     */
    function randNumber($len=6){
        $number = '';
        if($len>1 && $len < 20) {
            $max = str_pad(9, $len, "9",STR_PAD_BOTH);
            $number = str_pad(mt_rand(0, $max), $len, "0", STR_PAD_BOTH);
        }
        return $number;
    }
}


if(!function_exists("sec2Time")){

    /**
     * 格式化时间戳为天，小时，分，秒
     * @param int $time
     * @param int $type 默认：天，1：最大为小，2：最大为分钟
     * @return string
     */
    function sec2Time($time,$type=''){
        $value = array(
            "days" => 0, "hours" => 0, "minutes" => 0, "seconds" => 0,
        );

        if($type==''){
            if($time >= 86400){
                $value["days"] = floor($time/86400);
                $time = ($time%86400);
            }
        }
        if(in_array($type,['',1])){
            if($time >= 3600){
                $value["hours"] = floor($time/3600);
                $time = ($time%3600);
            }
        }
        if($time >= 60){
            $value["minutes"] = floor($time/60);
            $time = ($time%60);
        }
        $value["seconds"] = floor($time);
        $t = "";
        if($value["days"] > 0) {
            $t .= $value["days"] ."天";
        }
        if($value["hours"] > 0) {
            $t .= $value["hours"] ."小时";
        }
        if($value["minutes"] > 0) {
            $t .= $value["minutes"] ."分钟";
        }
        if($value["seconds"] > 0) {
            $t .= $value["seconds"] ."秒";
        }
        return $t;
    }
}


if(!function_exists('isMobile')){
    /**
     * 用正则表达式验证手机号码(中国大陆区)
     * @param integer $mobile    所要验证的手机号
     * @return boolean
     */
    function isMobile($mobile) {
        if (!$mobile) {
            return false;
        }
        return preg_match('/^1[34578]\d{9}$/', $mobile) ? true : false;
    }
}


if(!function_exists('echoLog')){

    function echoLog($content='',$type=''){

        $logPath = base_path() . '/storage/wx/log_text.txt';
        file_put_contents($logPath, $type."DATE:" . date("Y-m-d H:i:s") . "___" . print_r($content, true)."___END\n\r", FILE_APPEND);
    }
}


if(!function_exists('xmlobjectArray')) {
    function xmlobjectArray($str)
    {
        $jsonStr = '';
        if($str) {
            $postObj = simplexml_load_string($str, 'SimpleXMLElement', LIBXML_NOCDATA);
            $jsonStr = json_encode($postObj);
        }

        return json_decode($jsonStr,true);
    }
}


if(!function_exists('xmlobjectToArray')) {
    function xmlobjectToArray($str){
        $xmlObj = '';
        if($str) {
            $xmlObj = simplexml_load_string($str, 'SimpleXMLElement', LIBXML_NOCDATA);
        }

        return json_decode($xmlObj,true);
    }
}


if(!function_exists('requestWeixinInterface')) {
    /**
     * 微信接口统一调用处
     * @param   array $paramsArr 微信具体接口请求参数
     * @param   string $action 微信接口名称
     * @return  array || false || null
     */
    function requestWeixinInterface($action = '', $paramsArr = array(), $dataArr = array(), $method = 'get', $headers = array()){
        /*if (!is_array($paramsArr) || empty($action) || is_array($action)) {
            return false;
        }*/
        $action = @strtolower($action);
        $url = \ConstInc::WEIXIN_REQUREST_BASE_URL;
        switch ($action) {
            //获取api微信用户access_token
            case 'access_token':
                //$url .= "cgi-bin/token?grant_type=client_credential&appid=" . APPID . "&secret=" . APPSECRET;
                $url .= "cgi-bin/token?grant_type=client_credential&appid=" . $paramsArr['appid'] . "&secret=" . $paramsArr['appsecret'];
                break;
            //获取api微信用户列表
            case 'user_list':
                $url .= "cgi-bin/user/get?access_token=" . $paramsArr['accToken'] . "&next_openid=" . $paramsArr['nextOpenId'];
                break;
            //获取api微信用户信息
            case 'user_info':
                $url .= "cgi-bin/user/info?access_token=" . $dataArr['accToken'] . "&openid=" . $dataArr['openId'] . "&lang=zh_CN";
                break;
            //客服推送消息，单对单发
            case 'service_autosend_mess':
                $url .= "cgi-bin/message/custom/send?access_token=" . $paramsArr['accToken'];
                break;
            //发送模板消息
            case 'template_send_mess':
                $url .= "cgi-bin/message/template/send?access_token=" . $paramsArr['accToken'];
                break;
            //扫二维码获取ticket
            case 'qrcode':
                $url .= "cgi-bin/qrcode/create?access_token=" . $paramsArr['accToken'];
                break;
            //生成二维码图片
            case 'showqrcode':
                $url = \ConstInc::WEIXIN_REQUREST_QRCODE_URL;
                $url .= "cgi-bin/showqrcode?ticket=" . $paramsArr['ticket'];
                //返回头和流
                if (isset($paramsArr['getType']) && $paramsArr['getType'] === 0) {
                    return $url;
                }
                break;
            //根据OpenID列表群发【订阅号不可用，服务号认证后可用】
            case 'openid_mass':
                $url .= 'cgi-bin/message/mass/send?access_token=' . $paramsArr['accToken'];
                break;
            //根据分组进行群发【订阅号与服务号认证后均可用】
            case 'group_mass':
                $url .= 'cgi-bin/message/mass/sendall?access_token=' . $paramsArr['accToken'];
                break;
            //查询群发消息发送状态【订阅号与服务号认证后均可用】
            case 'get_mass':
                $url .= 'cgi-bin/message/mass/get?access_token=' . $paramsArr['accToken'];
                break;
            //创建自定义菜单
            case 'create_menu':
                $url .= 'cgi-bin/menu/create?access_token='. $paramsArr['accToken'];
                break;
            //获取自定义菜单
            case 'get_menu':
                $url .= 'cgi-bin/menu/get?access_token='. $paramsArr['accToken'];
                break;
            //删除自定义菜单
            case 'del_menu':
                $url .= 'cgi-bin/menu/delete?access_token='. $paramsArr['accToken'];
                break;
            //发送模板消息
            case 'send_template':
                $url .= 'cgi-bin/message/template/send?access_token='. $paramsArr['accToken'];
                break;
            default:
                break;
        }

        if (isset($paramsArr['notDecode']) && $paramsArr['notDecode']) {
            $result = apiCurl($url, $method, $headers, $dataArr);
        } else {
            $result = @json_decode(apiCurl($url, $method, $headers, $dataArr), true);
        }
        return $result;
    }
}


if(!function_exists('requestQyWxInterface')) {
    /**
     * 微信接口统一调用处
     * @param   array $paramsArr 微信具体接口请求参数
     * @param   string $action 微信接口名称
     * @return  array || false || null
     */
    function requestQyWxInterface($action = '', $paramsArr = array(), $dataArr = array(), $method = 'get', $headers = array()){
        /*if (!is_array($paramsArr) || empty($action) || is_array($action)) {
            return false;
        }*/
        $action = @strtolower($action);
        $url = \ConstInc::WW_REQUREST_BASE_URL;
        $accessToken = getKey($paramsArr,'accToken','');
        switch ($action) {
            //获取api微信用户access_token
            case 'access_token':
                //$url .= "cgi-bin/token?grant_type=client_credential&appid=" . APPID . "&secret=" . APPSECRET;
                $url .= "/cgi-bin/gettoken?corpid=" . $paramsArr['appid'] . "&corpsecret=" . $paramsArr['appsecret'];
                break;
            case 'getuserinfo'://根据code获取成员信息
                //$url .= "cgi-bin/token?grant_type=client_credential&appid=" . APPID . "&secret=" . APPSECRET;
                $url .= "/cgi-bin/user/getuserinfo?access_token=". $accessToken.'&code='.$paramsArr['code'];
                break;
            case 'getuserdetail'://使用user_ticket获取成员详情
                $url .= "/cgi-bin/user/getuserdetail?access_token=". $accessToken;
                break;
            case 'send'://发送应用消息
                $url .= "/cgi-bin/message/send?access_token=". $accessToken;
                break;
            case 'department'://获取部门列表
                $id = getKey($paramsArr,'id','');
                $url .= "/cgi-bin/department/list?access_token=$accessToken";
                if($id){
                    $url .= "&id=$id";
                }
                break;
            case 'simplelist'://获取部门成员
                $fetch_child = getKey($paramsArr,'fetch_child','');
                $did = getKey($paramsArr,'department_id','');

                $url .= "/cgi-bin/user/simplelist?access_token=$accessToken&department_id=$did";
                if($fetch_child){
                    $url .= "&fetch_child=$fetch_child";
                }
                break;
            case 'userlist'://获取部门成员详情
                $fetch_child = getKey($paramsArr,'fetch_child','');
                $did = getKey($paramsArr,'department_id','');

                $url .= "/cgi-bin/user/list?access_token=$accessToken&department_id=$did";
                if($fetch_child){
                    $url .= "&fetch_child=$fetch_child";
                }
                break;
            default:
                break;
        }

        if (isset($paramsArr['notDecode']) && $paramsArr['notDecode']) {
            $result = apiCurl($url, $method, $headers, $dataArr);
        } else {
            $result = @json_decode(apiCurl($url, $method, $headers, $dataArr), true);
        }
        return $result;
    }
}


if(!function_exists('checkLogin')) {
    function checkLogin()
    {
        $result = false;
        $userInfo = session()->get('userInfo');
        if (!$userInfo) {
//            $result = true;
//            return redirect(\ConstInc::HOME_LOGIN_PATH);
        }
        return $result;
    }
}



/*
     * 静默授权
     *  */
function snsBase( $url , $appid ){
    $redirect_uri=urlencode( $url );
    $url='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state=ywzz_weixin#wechat_redirect';
    return $url;
}
/*
 * 手动授权
 *  */
 function snsInfo( $url , $appid)
{
    $redirect_uri=urlencode( $url );
    $url='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=ywzz_weixin#wechat_redirect';
    return $url;
}

if(!function_exists('getSnsAccessAoken')) {
    /*
     * 获取access_token
     *  */
    function getSnsAccessAoken($appid, $appkey, $code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid . '&secret=' . $appkey . '&code=' . $code . '&grant_type=authorization_code';
        $result = apiCurl($url);
        $result = json_decode($result, true);
        return $result;
    }
}
/*
 * 获取用户信息（只有snsapi_userinfo可以）
 *  */
  function get_user_info( $access_token, $openid )
{
    $url='https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
    $result=apiCurl( $url );
    return json_decode($result,true);
}

if(!function_exists('userlog')) {
    function userlog($msg)
    {
        $user = Auth::user();
        if(empty($user)) {
            $uid = User::SYSADMIN_ID;
        }
        else {
            $uid = $user->id;
        }
        $data = [
            "user_id" => $uid,
            "desc" => $msg,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        ];
        list($code, $msg) = Code::getCode();
        if ($code !== 0) {
            Log::error("userlog not save ", $data);
            return;
        }
        Userlog::insert($data);
    }
}


if(!function_exists('getYearWeeks')) {
    /**
     * 获取全年每周开始结束日期
     * @return mixed
     */
    function getYearWeeks()
    {
        $year = date('Y');
        $weekNow = date('W');
        $weeks = date("W", mktime(0, 0, 0, 12, 28, $year));
        //var_dump($weekNow);exit;
        //var_dump($weeks);

        for ($week = 1; $week <= $weekNow; $week++) {
            if ($week > $weeks || $week <= 0) {
                $week = 1;
            }
            $week = $week < 10 ? '0' . $week : $week;

            //echo $week. '<br />';

            $start = strtotime($year . 'W' . $week);
            $end = strtotime('+1 week -1 day', $start);
            $dateStart = date('Y-m-d', $start);
            $dateEnd = date('Y-m-d', $end);
            $result[intval($week)] = array('start' => $dateStart, 'end' => $dateEnd);

        }
        return $result;
    }

}


if(!function_exists('getStartEndTime')){
    /**
     * 获取开始、结束时间，自定天数
     * @param $inputBegin
     * @param $inputEnd
     * @param string $day
     * @return array
     */
    function getStartEndTime($inputBegin,$inputEnd,$day=''){
        $beginStr = strtotime($inputBegin);
        $endStr = strtotime($inputEnd);
        $defaultEnd = date('Y-m-d');
        $defaultStart = date('Y-m-d',strtotime("-6 day"));
        if(!$beginStr){
            if($endStr) {
                $start = $day ? $endStr - (86400*($day-1)) : '';
                $beginStr = $beginStr ? $beginStr : $start;
            }else{
                $beginStr = strtotime($defaultStart);
            }
        }
        $endStr = $endStr ? $endStr : strtotime($defaultEnd);
        $beginDate = $beginStr ? date('Y-m-d',$beginStr):'';
        $endDate = $endStr ? date('Y-m-d 23:59:59',$endStr) : '';
        $endStr = strtotime($endDate);

        return array('date'=>array($beginDate,$endDate),'time'=>array($beginStr,$endStr));
    }
}



if(!function_exists('getTimeSlotByDate')){
    /**
     * 根据时间段获取，年月=>开始时间，结束时间
     * @param array $between
     * @return array
     */
    function getTimeSlotByDate($between=array()){
        $datearr = array();
        $sDate = isset($between['start']) ? $between['start'] : '';
        $eDate = isset($between['end']) ? $between['end'] : '';
        if($sDate) {
            $stime = strtotime($sDate);
            $etime = strtotime($eDate);
            while ($stime <= $etime) {
                $month = date('Y-m', $stime);//得到dataarr的日期数组。
                $seDateTime = array(date('Y-m-01', strtotime($month)), date('Y-m-t 23:59:59', strtotime($month)));
                $datearr[$month] = $seDateTime;
                $stime = strtotime("$month +1 month");

            }
        }
        return $datearr;
    }
}

if(!function_exists('getPageOffset')){
    /**
     * 根据第几页和每页显示多少条获取从第几条开始查询
     * @param $page
     * @param $limit
     * @return int
     */
    function getPageOffset($page,$limit){
        $page = $page > 0 ? $page : 0;
        $page_offset = $page > 1 ? (($page-1) * $limit) : 0;
        return $page_offset;
    }
}


if(!function_exists('urlencodeTime')){
    function urlencodeTime($time=''){
        $zTime = '';
        if($time) {
            $zTime = urlencode($time . '+08:00');
        }
        return $zTime;
    }
}

if(!function_exists("getKey")) {
      function getKey(array $arr, $key, $default = null) {
          if(array_key_exists($key, $arr)) {
              return $arr[$key];
          }
          else {
              return $default;
          }
      }
}



if(!function_exists("strPad")) {
    function strPad($str='',$len=6) {
        $result = '';
        if($str){
            $result = str_pad($str,$len,0,STR_PAD_LEFT);
        }
        return $result;
    }
}

if(!function_exists("arrayFilterCallbak")) {
    /**
     * 过滤数组中的空值
     * @param $v
     * @return bool
     */
    function arrayFilterCallbak($v)
    {
        return $v != '' || $v!= null || $v != false ? true : false;
    }
}


if(!function_exists("arrayFilterHourCallbak")) {
    /**
     * 过滤数组中的空非、数字、大于23的值（只需要0-23）
     * @param $v
     * @return bool
     */
    function arrayFilterHourCallbak($v)
    {
        $pattern = '/^(0?[0-9]|1[0-9]|2[0-3])$/';
        return preg_match($pattern,$v) ? true :false;
//        return ($v != '' || $v!= null || $v != false) && $v < 24 && is_numeric($v) ? true : false;
    }
}

if(!function_exists("testMonitorData")){
    /**
     * 测试监控数据
     * @param array $input
     * @return array
     */
    function testMonitorData($input=array()){
        $data = array();
        $start = isset($input['begin']) ? $input['begin'] : date("Y-m-d H:i:s", strtotime("-1 hour"));
        $end = isset($input['end']) ? $input['end'] : date("Y-m-d H:i:s");
        $type = isset($input['type']) ? $input['type'] : '';
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        $diff = ($endTime-$startTime);
        $d7 = 604800;
        $d3 = 259200;
        $d1 = 86400;
        $h6 = 21600;
        $h12 = 43200;
        if($diff>$d7){
            $n = mt_rand(1500,1700);
        }elseif($diff>$d3 && $diff<=$d7){
            $n = mt_rand(1000,1200);
        }elseif($diff>$d1 && $diff<=$d3){
            $n = mt_rand(200,400);
        }elseif($diff>$h12 && $diff<=$d1){
            $n = mt_rand(100,200);
        }elseif($diff>$h6 && $diff<=$h12){
            $n = mt_rand(60,80);
        }else{
            $n = mt_rand(30,35);
        }
        $i = 0;
        $network = array();
        // var_dump($startTime,$endTime);
        if(in_array($type,array('interface_flux','disk_partition'))){
            for($i=$startTime; $i<=$endTime;$i+=$n){
                // var_dump($i);exit;
                $data[] = $i;
            }
            foreach($data as $k=>$val){
                $dt[$k] = date('Y-m-d H:i:s',$val);
                if('interface_flux' == $type){
                    $network[] = array(
                        'ifInOctets' => mt_rand(1000,1100),
                        'ifOutOctets' => mt_rand(1000,1100),
                        'time' => $val,
                        'datetime' => date('Y-m-d H:i:s',$val)
                    );
                }elseif('disk_partition' == $type){
                    $total1 = '107375198208';
                    $used1 = '56041676800';
                    $total2 = '196495798272';
                    $used2 = '214740992';
                    $total3 = '196234702848';
                    $used3 = '5895962624';

                    $diskc[$k] = array(
                        'free'=>$total1-$used1,
                        'used_percent'=>($used1/$total1)*100,
                        'total' => $total1,
                        'used'=>$used1,
                        'label'	=>''
                    );
                    $diskd[$k] = array(
                        'free'=>$total2-$used2,
                        'used_percent'=>($used2/$total2)*100,
                        'total' => $total2,
                        'used'=>$used2,
                        'label'	=>''
                    );
                    $diske[$k] = array(
                        'free'=>$total3-$used3,
                        'used_percent'=>($used3/$total3)*100,
                        'total' => $total3,
                        'used'=>$used3,
                        'label'	=>''
                    );
                }
            }
        }else{
            for($i=$startTime; $i<=$endTime;$i+=$n){
                // var_dump($i);exit;
                $data[] = $i;
            }
            foreach($data as $k=>$val){
                $dt[$k] = date('Y-m-d H:i:s',$val);
                if('mem2' == $type){
                    $total = '8511225856';
                    $used = mt_rand('1489371136','7489371136');
                    $mem[$k] = array(
                        'free'=> $total-$used,
                        'percent' => ($used/$total)*100,
                        'total' => $total,
                        'used' => $used
                    );
                }else{
                    $v[$k] = mt_rand(9, 12);
                    $v2[$k] = mt_rand(1,30)/10;
                    $v3[$k] = mt_rand(1,30)/10;
                    $t[$k] = $val;
                }
            }
        }
        switch($type){
            case 'cpu':
                $result = array(
                    'result'=>array(
                        'cpu' => $v,
                    ),
                    'time'=> $dt,
                );
                break;
            case 'mem2':
                $result = array(
                    'result'=>array(
                        '内存使用率' => $mem,
                    ),
                    'time'=> $dt,
                );
                break;
            case 'disk_partition':
                $result = array(
                    'result'=>array(
                        'C' => $diskc,
                        'D' => $diskd,
                        'E' => $diske,
                    ),
                    'time'=> $dt,
                );
                break;
            case 'interface_flux':
                $result = array(
                    'result'=> $network
                );
                break;
            default:


        }
        return $result;
    }
}


if(!function_exists("UsePageByMyself")) {
    /**
     * 自定义分页
     * @param $request
     * @param array $data 需要分页的数据
     * @return array
     */
    function UsePageByMyself($request,$data = array()){

        // 分页数
        if(is_null($request->input('per_page'))){
            $perPage = \ConstInc::PAGE_NUM;
        }else{
            $perPage = intval($request->input('per_page',\ConstInc::PAGE_NUM));
            if($perPage < 0){
                $perPage = \ConstInc::PAGE_NUM;
            }
        }

        // 接收的页码数
        if(is_null($request->input('page'))){
            $page = 1;
        }else{
            $page = intval($request->input('page',1));
            if($page < 0){
                $page = 1;
            }
        }

        // 总数
        $total = count($data);

        // 计算每页分页的初始位置
        $offset = ($page - 1) * $perPage;

        // 切分数据
        $items = array_slice($data, $offset, $perPage, true);

        $options = ['path' => $request->url(), 'query' => $request->query()];
        //实例化LengthAwarePaginator类，并传入对应的参数
        $dataWithPage = new LengthAwarePaginator($items, $total, $perPage, $page, $options);

        $dataWithPage = $dataWithPage->toArray();

        // 重组符合项目格式的数组
        $arr = [];
        $arr['result'] = array_merge($dataWithPage['data'],[]);

        $child = [];
        $child['total'] = $dataWithPage['total'];

        // 当前页的数据总数
        if($dataWithPage['current_page'] == $dataWithPage['last_page']){
            $child['count'] = $dataWithPage['total'] - ($dataWithPage['current_page'] - 1) * $dataWithPage['per_page'];
        }else{
            $child['count'] = $dataWithPage['per_page'];
        }

        $child['per_page'] = $dataWithPage['per_page'];
        $child['current_page'] = $dataWithPage['current_page'];
        $child['total_pages'] = $dataWithPage['last_page'];
        $child['links']['previous'] = $dataWithPage['prev_page_url'];
        $arr['meta']['pagination'] = $child;

        return $arr;
    }
}

if(!function_exists("dateArrStrCompare")) {
    /**
     * 验证当前时间是否在时间数组中
     * @param array $arr 时间数组 数组为[0,1,2....23]
     * @param string $str 时间 2019010108
     * @return bool
     */
    function dateArrStrCompare($arr = [], $str = ''){
        $res = false;
        $str = $str?$str:date('YmdH');
        if ($arr && $str) {
            foreach ($arr as $v) {
                $intv = intval($v);
                //处理小于10的前面补0
                $intv = $intv < 10 ? "0$intv" : $intv;
                $val = date("Ymd").$intv;
                if ($str == $val) {
                    $res = true;
                }
            }
        }
        return $res;
    }
}

if(!function_exists("enDeCode")) {

    /**
     * 加密解密字符
     * @param $data 加密解密字符
     * @param bool $operation  true:解密 false:加密
     * @param string $method 解密加密方法
     * @param string $passwd 解密加密密钥
     * @param int $options 数据格式选项（可选）
     * @param string $iv: 解密初始化向量（可选）16位
     * @return bool|mixed|string
     */
    function enDeCode($data='', $operation = false, $method='',$passwd = '',$options=0,$iv='') {
        $method = $method ? $method : 'AES-256-CBC';
        $passwd = $passwd ? $passwd : '';
        $options = $options ? $options : 0;
        $iv = $iv ? $iv : '1234567890123456';
        $res = '';
        if($data) {
            if ($operation == true) {
                // 解密
                $res = openssl_decrypt($data, $method, $passwd, $options, $iv);
                $res = false != $res ? $res : $data;
            } else {
                // 加密
                $res = openssl_encrypt($data, $method, $passwd, $options, $iv);
            }
        }
        return $res;
    }
}

if(!function_exists("encryptStr")) {
    /**
     * 加密解密字符
     * @param string $key
     * @param string $val
     * @return bool|mixed|string
     */
    function encryptStr($key = '', $val = '', $action = false){
        if ('userpwd' == $key) {
            //加密解密
            $val = $val ? enDeCode($val, $action) : '';
        }
        return $val;
    }
}