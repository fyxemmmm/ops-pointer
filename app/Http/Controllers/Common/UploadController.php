<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/11
 * Time: 13:56
 */

namespace App\Http\Controllers\Common;

use App\Http\Requests\Common\UploadRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UploadController extends Controller
{
    function __construct()
    {
    }

    public function postPic(UploadRequest $request) {
        $file = $request->file("pic");
        $storagePath = storage_path().'/app/';
        $destinationPath = 'upload/' . date('Y-m-d'); // public文件夹下面uploads/xxxx-xx-xx 建文件夹
        $extension = $file->getClientOriginalExtension();   // 上传文件后缀
        $fileName = date('His') . mt_rand(100, 999) . '.' . $extension; // 重命名
        $file->move($storagePath . $destinationPath, $fileName); // 保存图片

//        $host = $request->getHost();
//        $port = $request->getPort();
//        if($port != 80) {
//            $host = $host.":".$port;
//        }

        $url = config("app.web_url")."/$destinationPath/$fileName";
        return $this->response->send(["url" => $url]);
    }

}
