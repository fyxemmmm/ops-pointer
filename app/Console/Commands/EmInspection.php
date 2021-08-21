<?php
/**
 * 环控-巡检报告
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\EnvironmentalRepository;
use Illuminate\Console\Command;
use App\Support\GValue;

use DB;
use Log;


class EmInspection extends Command
{
    protected $inspection;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'eminspectionreport:add {db?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加环控巡检报告的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(EnvironmentalRepository $emRepository){
        parent::__construct();
        $this->emRepository = $emRepository;

   }

    public function selectDb() {
        $db = $this->argument('db');
        if(!empty($db)) {
            //分库
            DB::setDefaultConnection($db);
            GValue::$currentDB = $db;
        }else{
            //默认库
            $db = DB::getDatabaseName();
        }
        \ConstInc::monitorCurrentConf($db);
    }


    public function handle()
    {
        $this->selectDb();
        \ConstInc::$emOpen = DB::table('action_config')->where('key', 'pc_hk')->value('status');
        if(!\ConstInc::$emOpen) {
            return ;
        }
        $res = array();
//        $reportdateArr = \ConstInc::$mReportArr;//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
//        $date = date("YmdH");
//        $setReportDate = '';
//        if ($reportdateArr) {
//            foreach($reportdateArr as $v) {
//                $reportVal = date("Ymd").$v;
////                var_dump($reportVal);
//                if($date == $reportVal){
//                    $setReportDate = $reportVal;
//                }
//            }
//            $input = array('reportDate'=>$setReportDate);
//            if($setReportDate) {
//                $res = $this->emRepository->addEmReport($input);
//            }
//        }
        $res = $this->emRepository->addEmReport();
        Log::info('em_inspection_report cmd:'.json_encode($res));
    }







}



