<?php

namespace App\Http\Controllers\Assets;

use App\Exceptions\ApiException;
use App\Repositories\Assets\ImportRepository;
use App\Http\Requests\Assets\ImportRequest;
use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;
use App\Models\Code;


class ImportController extends Controller
{

    protected $import;

    function __construct(ImportRepository $import) {
        $this->import = $import;
    }

    /**
     * 粘贴文本上传
     * @param ImportRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function postApply(ImportRequest $request)
    {
        $orderType = $request->input("orderType");
        $content = $request->input("content");
        $content = $this->import->transformContent($content);
        $data = $this->import->apply($content, $orderType);

        return $this->response->send($data);
    }

    /**
     * 上传文件
     * @param ImportRequest $request
     */
    public function postUpload(Request $request) {

        $orderType = $request->input("orderType");
        $file = $request->file("filename");

        $realPath = $file->getRealPath();

        $spreadsheet = IOFactory::load($realPath);
        $current = $spreadsheet->getActiveSheet();
        $allColumn = $current->getHighestColumn();
        if(strlen($allColumn) >= 3) {
            Code::setCode(Code::ERR_EXCEL_COLUMN);
            return $this->response->send();
        }
        $content = $current->toArray();
        $data = $this->import->apply($content, $orderType);
        return $this->response->send($data);
    }


    /**
     * 保存
     * @param ImportRequest $request
     */
    public function postSave(ImportRequest $request) {
        $orderType = $request->input("orderType");
        $result = $request->input("result");
        $data = $this->import->saveData($result, $orderType);
        return $this->response->send($data);
    }

    /**
     * 批量导入 RFID 标签
     * @param Request $request
     * @return mixed
     * @throws ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function postUploadRfid(Request $request){
        $orderType = $request->input("orderType");
        $file = $request->file("filename");
        $realPath = $file->getRealPath();
        $spreadsheet = IOFactory::load($realPath);
        $current = $spreadsheet->getActiveSheet();
        $allColumn = $current->getHighestColumn();
        if(strlen($allColumn) >= 3) {
            Code::setCode(Code::ERR_EXCEL_COLUMN);
            return $this->response->send();
        }
        $content = $current->toArray();
        $data = $this->import->saveRfid($content, $orderType);
        return $this->response->send($data);

    }


}
