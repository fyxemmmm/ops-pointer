<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DataSourceRequest;
use App\Repositories\Auth\DataSourceRepository;

class DataSourceController extends Controller
{
    protected $dataSourceRepository;

    public function __construct(DataSourceRepository $dataSourceRepository)
    {
        $this->dataSourceRepository = $dataSourceRepository;
    }

    public function getList(DataSourceRequest $request){
        $data = $this->dataSourceRepository->getList($request);
        return $this->response->send($data);
    }

    public function getSourceData(DataSourceRequest $request){
        $input = $request->input();
        $data = $this->dataSourceRepository->getSourceData($input);
        return $this->response->send($data);
    }



}
