<?php

namespace App\Http\Controllers\Assets;

use App\Exceptions\ApiException;
use App\Repositories\Assets\PrintRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Code;
use Response;

class PrintController extends Controller
{
    protected $print;

    function __construct(PrintRepository $print) {
        $this->print = $print;
    }

    public function postQr(Request $request) {
        $id = $request->input("id");
        $category = $request->input("category");
        $overwrite = $request->input("overwrite", 0);
        if(!empty($id)) {
            $id = trim($id,",");
            if(empty($id)) {
                throw new ApiException(Code::ERR_PARAMS,["id", "category"]);
            }
            $id = explode("," , $id);
            $url = $this->print->genById($id, $overwrite);
        }
        else if (!empty($category)) {
            $category = trim($category, ",");
            if(empty($category)) {
                throw new ApiException(Code::ERR_PARAMS,["id", "category"]);
            }
            $categoryId = explode(",", $category);
            $url = $this->print->genByCateId($categoryId, $overwrite);
        }
        else {
            throw new ApiException(Code::ERR_PARAMS,["id", "category"]);
        }

        userlog("批量打印了资产二维码");

        return $this->response->send($url);
    }



}
