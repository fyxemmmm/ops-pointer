<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/4/4
 * Time: 17:25
 */

namespace App\Support;
use App\Support\submail\SUBMAILAutoload;

class Submail
{
    protected $submail;

    function __construct(){

        $this->submail = new \MESSAGEXsend(\ConstInc::SMS_MESSAGE);
    }


    public function sendSMS($phone=''){

        $randNum = randNumber();
        $this->submail->setTo($phone);
        $this->submail->SetProject('JBZwB3');
        $this->submail->AddVar('code',$randNum);

        // 发送短信验证码
        $xsend = $this->submail->xsend();

        $status = isset($xsend['status'])?$xsend['status']:'';
        if('success' == trim(strtolower($status))){
            $xsend['phone'] = $phone;
            $xsend['phoneCode'] = $randNum;
        }
//        dd($xsend);
        return $xsend;
    }


}