<?php

namespace App\Http\Controllers\Assets;

use App\Exceptions\ApiException;
use App\Repositories\Assets\ExportRepository;
use App\Http\Requests\Assets\ExportRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Code;
use Response;
use Illuminate\Support\Facades\Schema;

class ExportController extends Controller
{
    protected $export;

    function __construct(ExportRepository $export) {
        $this->export = $export;
    }

    public function postDownload(ExportRequest $request) {
        $id = $request->input("id");
        $category = $request->input("category");
        $template = $request->input("template");
        if(!empty($id)) {
            $id = trim($id,",");
            if(empty($id)) {
                throw new ApiException(Code::ERR_PARAMS,["id", "category"]);
            }
            $id = explode("," , $id);
            $filename = $this->export->genById($id);
        }
        else if (!empty($category)) {
            $category = trim($category, ",");
            if(empty($category)) {
                throw new ApiException(Code::ERR_PARAMS,["id", "category"]);
            }
            $categoryId = explode(",", $category);
            $filename = $this->export->genByCateId($categoryId,$template);
        }
        else {
            throw new ApiException(Code::ERR_PARAMS,["id", "category"]);
        }
        $pos = strrpos($filename, "/");
        $realFilename = substr($filename, $pos + 1);
        $filename = str_replace(storage_path("app/export"), public_path("export"), $filename);

        if(strpos($realFilename, ".zip")) {
            $headers = array(
                'Content-Type: application/zip',
            );
        }
        else {
            $headers = array(
                'Content-Type: application/vnd.ms-excel',
            );
        }

        userlog("批量导出了资产，文件名: $realFilename");

        return Response::download($filename, $realFilename, $headers);
    }

    /**
     * 下载 Ifid 标签模板
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     * @throws ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function getIfidDownload(){

        $titles = [
            '资产编号' => 'number',
            '盘点标签' => 'ifid',
        ];
        $data[] = [
            'number' => '',
            'ifid' => '',
        ];

        $filename = '盘点标签_模板.xlsx' ;

        $filepath = $this->export->saveReportForTwoArray($filename, $data, $titles,true,true);

        $headers = array(
            'Content-Type: application/vnd.ms-excel',
        );
        userlog("下载了盘点标签模板，文件名: $filepath");
        return Response::download($filepath, $filename, $headers);

    }



}
