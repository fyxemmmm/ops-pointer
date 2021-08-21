<?php
/**
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/5
 * Time: 11:50
 */


namespace App\Http\Controllers\Workflow;

use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Assets\DevicePortsRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\EventsRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Workflow\Event;



class ImportController extends Controller
{

    protected $events;
    protected $devicePorts;

    function __construct(EventsRepository $events, DevicePortsRepository $devicePorts) {
        $this->events = $events;
        $this->devicePorts = $devicePorts;
    }


    public function postEvents(Request $request){
        $categoryId = $request->input("categoryId",4);
        $source = $request->input("source",Event::SRC_TERMINAL);
        $wrongId = $request->input("wrongId",0);
        $file = $request->file("filename");
        $type = $request->input("type");
        if($type){
            $realPathFile = $file->getRealPath();
        }else{
            $realPath = base_path('storage/app/public');//$file->getRealPath();
            $realPathFile =  $realPath.'/events_import.xlsx';
        }


        $spreadsheet = IOFactory::load($realPathFile);
        $content = $spreadsheet->getActiveSheet()->toArray();
        $param = array(
            'categoryId'=>$categoryId,
            'source' => $source,
            'wrongId' => $wrongId,
        );
//        var_dump($content);exit;
        $data['result'] = $this->events->eventsImportAdd($content,$param);
        return $this->response->send($data);
    }

}





