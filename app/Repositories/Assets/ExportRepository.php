<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/1/18
 * Time: 14:01
 */

namespace App\Repositories\Assets;

use App\Exceptions\ApiException;
use App\Repositories\BaseRepository;
use App\Models\Assets\Device;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\Code;
use App\Models\Workflow\Category as EvtCategory;
use Log;

class ExportRepository extends BaseRepository
{

    protected $device;
    protected $fieldsRepo;
    protected $categoryRepo;

    const PATH = "app/export/";
    const ZIPFILE = "导出资产.zip";

    public function __construct(Device $deviceModel,FieldsRepository $fieldsRepo,CategoryRepository $categoryRepo)
    {
        $this->device = $deviceModel; //资产模型
        $this->fieldsRepo = $fieldsRepo; //资产字段
        $this->categoryRepo = $categoryRepo;
    }

    public function genByCateId(array $categoryId,$template) {
        $result = array();
        if(!$template) {
            $result = $this->device->whereIn("sub_category_id", $categoryId)->get()->groupBy("sub_category_id");
        }
        return $this->processGen($result, $categoryId,$template);
    }

    public function genById(array $id)
    {
        $result = $this->device->whereIn("id", $id)->get()->groupBy("sub_category_id");
        if ($result->isEmpty()) {
            throw new ApiException(Code::ERR_EXPORT_EMPTY);
        }
        return $this->processGen($result);
    }

    protected function processGen($result=array(), $categoryIds = null,$template=false) {
        $templName = '';
        $templEnName = '';
        if($template) {
            $templName = '_模板';
            $templEnName = '_template';
        }
        $path = storage_path(self::PATH) . date("Y-m-d") . "/" . date("His") . mt_rand(1, 99).$templEnName . "/";
        if (!checkCreateDir($path)) {
            Log::error("can not create dir $path");
            throw new ApiException(Code::ERR_EXPORT);
        }

        $files = 0;
        $filename = "";
        if(empty($categoryIds)) {
            foreach($result as $categoryId => $value) {
                $data = [];
                $categoryInfo = $this->categoryRepo->getById($categoryId);
                $category = $categoryInfo->name;
                $fields = $this->categoryRepo->getValidCategoryFields($categoryId);
                $data[] = ["类型明细"];

                foreach($fields as $field) {
                    array_push($data[0], $field->field_cname);
                }

                foreach($value as $k => $v) {
                    $current = [$category];
                    foreach($fields as $field) {
                        $sname = $field->field_sname;
                        $value = $this->fieldsRepo->transform($v->$sname, $field);
                        if(false === $value) {
                            throw new ApiException(Code::ERR_EXPORT);
                        }
                        array_push($current, $value);
                    }
                    $data[] = $current;
                }

                $filename = $path.$category.".xlsx";
                $this->saveFile($filename, $data);
                $files++;
            }
        }else {
            foreach($categoryIds as $categoryId) {
                if(empty($categoryId)) continue;
                $categoryRequire1 = $this->categoryRepo->getCategoryRequire($categoryId, EvtCategory::INSTORAGE);
                $categoryRequire2 = $this->categoryRepo->getCategoryRequire($categoryId, EvtCategory::UP);
                $categoryInfo = $this->categoryRepo->getById($categoryId);
                $category = $categoryInfo->name;
                $fields = $this->categoryRepo->getValidCategoryFields($categoryId);
                $data = [];
                if($template){
                    $data[] = [["类型明细",1,1]];
                    foreach($fields as $field) {
                        array_push($data[0], [
                            $field->field_cname,
                            $categoryRequire1[$field->field_sname]['require'],  //入库的require
                            $categoryRequire2[$field->field_sname]['require'],  //上架的require
                        ]);
                    }
                    $data[] = [$category];
                }
                else {
                    $data[] = ["类型明细"];
                    foreach($fields as $field) {
                        array_push($data[0], $field->field_cname);
                    }
                    if (isset($result[$categoryId])) {
                        $value = $result[$categoryId];
                        foreach ($value as $k => $v) {
                            $current = [$category];
                            foreach ($fields as $field) {
                                $sname = $field->field_sname;
                                $vv = $this->fieldsRepo->transform($v->$sname, $field);
                                if (false === $vv) {
                                    throw new ApiException(Code::ERR_EXPORT);
                                }
                                array_push($current, $vv);
                            }
                            $data[] = $current;
                        }
                    }
                }

                $filename = $path.$category.$templName.".xlsx";
                $this->saveFile($filename, $data);
                $files++;
            }
        }

        if($files > 1) {
            //打包多个文件
            $zipFileName = '导出资产'.$templName.'.zip';
            $this->zip($path,$zipFileName);
            $filename = $path.$zipFileName;
        }

        return $filename;
    }

    protected function zip($path,$zipFileName='') {
        $zipFileName = $zipFileName ? $zipFileName : self::ZIPFILE;
        $zipfile = $path.$zipFileName;
        $zip = new \ZipArchive;
        if ($zip->open($zipfile,\ZipArchive::CREATE |\ZipArchive::OVERWRITE ) === TRUE) {
            foreach(glob($path . '*') as $file) {
                if($file === $zipfile) {
                    continue;
                }
                $zip->addFile($file, ltrim($file, $path));
            }
            $zip->close();
        }
        else {
            Log::error("can not zip file: $zipfile");
            throw new ApiException(Code::ERR_EXPORT);
        }
    }

    public function saveFile($filename, $data) {
        Log::info("saveFile", ["filename"=> $filename]);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach($data as $row => $value) {
            foreach($value as $column => $v) {
                if(is_array($v)) {
                    list($v,$r1,$r2) = $v;
                }
                $sheet->setCellValueByColumnAndRow($column + 1, $row + 1, $v);
                if(isset($r1) && $r1 == 1) {    //只有模板做变色处理，r1字体变红表示入库必须
                    $sheet->getStyleByColumnAndRow($column + 1, $row + 1)->applyFromArray(
                        [
                            'font' => [
                                'color' => ['rgb' => 'FF0000']
                            ]
                        ]
                    );
                }

                if(isset($r2) && $r2 == 1) {    //只有模板做变色处理，r2背景变黄表示上架必须
                    $sheet->getStyleByColumnAndRow($column + 1, $row + 1)->getFill()->applyFromArray(
                        [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                            'color' => ['rgb' => 'FFFF00'],
                        ]
                    );
                }
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filename);
    }

    public function saveReport($filename, $data, $titles) {
        $path = storage_path(self::PATH) . date("Y-m-d") . "/";
        if (!checkCreateDir($path)) {
            Log::error("can not create dir $path");
            throw new ApiException(Code::ERR_EXPORT);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $startRow = 1;
        $lastRow = 1;
        foreach($titles as $k => $v) {
            if(isset($data[$k])) {
                $column = 0;
                foreach($v as $title => $key) {
                    $column++;
                    $sheet->setCellValueByColumnAndRow($column , $startRow, $title);
                    foreach($data[$k] as $row => $vv) {
                        $vv = !is_array($vv) ? $vv->toArray(): $vv ;
                        if(array_key_exists($key,$vv)) {
                            $sheet->setCellValueByColumnAndRow($column , $startRow + $row + 1, $vv[$key]);
                            $lastRow = $startRow + $row + 1;
                        }
                    }
                }
                $startRow += $lastRow + 2;
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path.$filename);
        return $path.$filename;
    }

    /**
     * @param $filename  文件存放路径
     * @param $data 内容数组
     * @param $titles 内容名称
     * @return string excel 创建之后的文件路径
     * @throws ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function saveReportByMerge($filename, $data, $titles){
        $path = storage_path(self::PATH) . date("Y-m-d") . "/";
        if (!checkCreateDir($path)) {
            Log::error("can not create dir $path");
            throw new ApiException(Code::ERR_EXPORT);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置单元格垂直居中
//        $spreadsheet->getActiveSheet()
//            ->getStyle('A1:D4')
//            ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

        $row_idx = 1;
        $col_idx = 0;

        foreach ($titles as $title => $key){
            $col_idx++;
            // 写入第一行数据（列，行，值）
            $sheet->setCellValueByColumnAndRow($col_idx , $row_idx, $title);
            // 自动设置列宽
            $sheet->calculateColumnWidths();

        }

        foreach ($data as $key => $item){

            foreach ($item as $k => $it){
                $row_idx++;
                $col_idx = 1;
                foreach ($it as $fieldName => $fieldValue){
                    // 写入内容
                    $sheet->setCellValueByColumnAndRow($col_idx++ , $row_idx, $fieldValue);

                }
            }

            // 取出合并所需长度
            $mergeNumber = substr($key,strrpos($key,'_')+1,10);
            // 合并单元格（从纵2开始合并）
            $sheet->mergeCellsByColumnAndRow(1, $row_idx-$mergeNumber+1, 1, $row_idx);

        }


        // 导出
        $writer = new Xlsx($spreadsheet);
        $writer->save($path.$filename);
        return $path.$filename;
    }

    /**
     * 处理二维数组 excel 数据导出
     *
     * @param $filename 文件名称
     * @param $data  内容数组
     * @param $titles  内容名称
     * @param $isWriteTitles  是否写入标题
     * @return string  excel 文件路径
     * @throws ApiException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function saveReportForTwoArray($filename, $data, $titles,$isWriteTitles = true,$isTemplatePath = false){

        $path = storage_path(self::PATH) . date("Y-m-d") . "/";

        if($isTemplatePath){
            $path .= date('his') . mt_rand(10, 99) . '_template/';
        }

        if (!checkCreateDir($path)) {
            Log::error("can not create dir $path");
            throw new ApiException(Code::ERR_EXPORT);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row_idx = 1;
        $col_idx = 0;
        foreach ($titles as $title => $key){
            $col_idx++;

            if($isWriteTitles){
                // 写入第一行数据（列，行，值）
                $sheet->setCellValueByColumnAndRow($col_idx , $row_idx, $title);
            }

            foreach ($data as $rowNumber => $value){
                if(isset($value[$key])){
                    $sheet->setCellValueByColumnAndRow($col_idx , $row_idx + $rowNumber + 1, $value[$key]);
                }
            }

        }

        // 导出
        $writer = new Xlsx($spreadsheet);

        $writer->save($path.$filename);
        return $path.$filename;

    }

}