<?php
/**
 * 监控
 */

namespace App\Http\Controllers\Monitor;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Monitor\CommonRepository;
use App\Repositories\Monitor\HostsRepository;
use App\Models\Code;
use App\Exceptions\ApiException;

class HostsController extends Controller
{

    protected $common;
    protected $hosts;

    function __construct(CommonRepository $common,HostsRepository $hosts)
    {
        $this->common = $common;
        $this->hosts = $hosts;
    }


    public function postHostList(Request $request){
        $input = $request->post() ? $request->post() : array();
        $res = $this->common->getZbxHosts($input);
        $result = $this->common->formatJson($res);
        return $this->response->send($result);
//        return $this->response->send($res);

    }


    /**
     * 添加监控主机和资产绑定
     * @param Request $request
     */
    public function postAddAssetsHostsRel(Request $request){

        $input = $request->post() ? $request->post() : array();
//        var_dump($input);exit;
        $addAHR = $this->hosts->addAssetsHostsRelationship($input);
//        var_dump($addAHR);exit;
        return $addAHR;
    }


    /**
     * 监控主机和资产解绑
     * @param Request $request
     * @return mixe
     */
    public function postAssetsHostsUntie(Request $request){
        $input = $request->input() ? $request->input() : array();
//        var_dump($input);exit;
        $res = $this->hosts->assetsHostsUntie($input);
        return $this->response->send($res);
    }

    public function postTest()
    {
        echo 232323;
        exit;
    }




}
