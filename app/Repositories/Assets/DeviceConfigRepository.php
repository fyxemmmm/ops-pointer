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
use App\Models\Assets\DeviceConfig;
use App\Support\Diff;
use App\Models\Code;

class DeviceConfigRepository extends BaseRepository
{

    public function __construct(DeviceConfig $deviceConfigModel)
    {
        $this->model = $deviceConfigModel;

    }

    public function get($input) {
        if(isset($input['id'])) {
            return $this->getById($input['id']);
        }
        else if(isset($input['assetId'])){
            return $this->model->where(["asset_id" => $input['assetId']])->orderBy("created_at","desc")->first();
        }
        else {
            throw new ApiException(Code::ERR_PARAMS, ["id"]);
        }
    }

    public function add($input) {
        $last = $this->model->where(["asset_id" => $input['assetId']])->orderBy("created_at", "desc")->first();
        if(empty($last)) {
            $diff = json_encode(Diff::compare($input['conf'], ""));
        }
        else {
            $diff = json_encode(Diff::compare($input['conf'], $last->conf));
        }
        $data = $input;
        $data['asset_id'] = $input['assetId'];
        $data['diff'] = $diff;
        $this->store($data);
/*
     echo <<<EOF
        <style type="text/css">

      .diff td{
        padding:0 0.667em;
        vertical-align:top;
        white-space:pre;
        white-space:pre-wrap;
        font-family:Consolas,'Courier New',Courier,monospace;
        font-size:0.75em;
        line-height:1.333;
      }

      .diff span{
        display:block;
        min-height:1.333em;
        margin-top:-1px;
        padding:0 3px;
      }

      * html .diff span{
        height:1.333em;
      }

      .diff span:first-child{
        margin-top:0;
      }

      .diffDeleted span{
        border:1px solid rgb(255,192,192);
        background:rgb(255,224,224);
      }

      .diffInserted span{
        border:1px solid rgb(192,255,192);
        background:rgb(224,255,224);
      }

      #toStringOutput{
        margin:0 2em 2em;
      }

    </style>
EOF;
*/
    }



}