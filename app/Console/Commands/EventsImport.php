<?php
/**
 *
 */

#namespace App\Http\Controllers\Home;
namespace App\Console\Commands;

use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Workflow\EventsRepository;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Workflow\Event;

use DB;
use Log;


class EventsImport extends Command
{
    protected $events;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'events:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是事件导入的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(EventsRepository $events){
        parent::__construct();
        $this->events = $events;

   }


    public function handle(){
        $categoryId = 4;//$request->file("categoryId",4);
        $source = 3;//$request->file("source",Event::SRC_TERMINAL);
        $wrongId = 0;//$request->file("wrongId",0);
        //$file = $request->file("filename");
        //$realPath = $file->getRealPath();
        $realPath = base_path('storage/app/public');//$file->getRealPath();
        $filePath = $realPath.'/events_import.xlsx';
        if(!file_exists($filePath)){
            Log::info('events_import not find!');
            return false;
        }

        $spreadsheet = IOFactory::load($filePath);
        $content = $spreadsheet->getActiveSheet()->toArray();
        $param = array(
            'categoryId'=>$categoryId,
            'source' => $source,
            'wrongId' => $wrongId,
        );
//        var_dump($content);exit;
        $data = $this->events->eventsImportAdd($content,$param);
        Log::info('events_import_count:'.$data);
    }







}



