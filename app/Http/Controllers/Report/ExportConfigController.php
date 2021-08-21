<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Report\ExportConfigRepository;


class ExportConfigController extends Controller
{
    protected $exportConfig;

    public function __construct(ExportConfigRepository $exportConfigRepository)
    {
        $this->exportConfig = $exportConfigRepository;
    }


    /**
     * 备份数据表
     */
    public function getSqlBackup(){
        $data = $this->exportConfig->getSqlBackup();
        return $this->response->send($data);
    }

    /**
     * 还原备份数据
     * @return mixed
     */
    public function getExecuteSql(){
        $data = $this->exportConfig->getExecuteSql();
        return $this->response->send($data);
    }


}
