<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/8
 * Time: 21:52
 */

namespace App\Http\Controllers\Report;

use App\Repositories\Report\GovernmentRepository;
use App\Http\Requests\Report\GovernmentRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Exceptions\ApiException;
use App\Models\Code;

class GovernmentController extends Controller {

    protected $government;

    function __construct(GovernmentRepository $governmentRepository) {
        $this->government = $governmentRepository;
    }

    // 获取智慧政务运行情况周汇报,默认模板
    public function getWeekReport(GovernmentRequest $request){
        $data = $this->government->getWeekReport();
        return $this->response->send($data);
    }

    // 导入Excel 到数据库
    public function postInsertExcel(Request $request){
        $file = $request->file('filename');
        $realPath = $file->getRealPath();
        if(!$realPath){
            throw new ApiException(Code::ERR_PARAMS, ["请上传文件"]);
        }
        if(strpos($file->getClientOriginalName(),'运维数据导入') === false){
            throw new ApiException(Code::ERR_PARAMS, ["上传文件模板不正确"]);
        }
        $this->government->addMonthData($file);
        return $this->response->send();
    }

    // 下载 Excel 模板
    public function getGovExcel(){
        return response()->download(public_path('excel').'/month_report.xlsx',
            '运维数据导入.xlsx');
    }

    public function getDateList(GovernmentRequest $request){
        $input = $request->all();
        $list = $this->government->getDateList($input);
        $is_default = $this->government->getDateId($input['type']);
        if($list){
            $this->response->addMeta(['is_default' => $is_default]);
        }
        return $this->response->send($list);
    }

    # 将某个时段的周报设置为默认模板
    public function postSetDefault(GovernmentRequest $request){
        $this->government->setDefaultTemplate($request->all());
        return $this->response->send();
    }

    public function postAddResources(Request $request)
    {
        $file = $request->file('filename');
        $realPath = $file->getRealPath();
        if(!$realPath){
            throw new ApiException(Code::ERR_PARAMS, ["请上传文件"]);
        }
        if(strpos($file->getClientOriginalName(),'云平台资源情况') === false){
            throw new ApiException(Code::ERR_PARAMS, ["上传文件模板不正确"]);
        }
        $this->government->addResourceData($request->file('filename'));
        return $this->response->send();
    }



    # 获取智慧政务应用情况列表
    public function getAppList(){
        $data = $this->government->getAppList();
        return $this->response->send($data);
    }


    # 导入智慧政务应用情况
    public function postAddAppExcel(Request $request){
        $file = $request->file('filename');
        $realPath = $file->getRealPath();
        if(!$realPath){
            throw new ApiException(Code::ERR_PARAMS, ["请上传文件"]);
        }
        if(strpos($file->getClientOriginalName(),'智慧政务网应用情况') === false){
            throw new ApiException(Code::ERR_PARAMS, ["上传文件模板不正确"]);
        }
        $this->government->insertAppExcel($file);
        return $this->response->send();
    }


    # 下载智慧政务应用情况 Excel 模板
    public function getAppExcel(){
        return response()->download(public_path('excel').'/app_gov.xlsx',
            '智慧政务网应用情况.xlsx');
    }

    //下载云资源平台情况
    public function getResourceExcel(){
        return response()->download(public_path('excel').'/resource_report.xlsx',
            '云平台资源情况.xlsx');
    }

    public function getResourceReport(){
        $data = $this->government->getResourceReport();
        return $this->response->send($data);
    }

}
