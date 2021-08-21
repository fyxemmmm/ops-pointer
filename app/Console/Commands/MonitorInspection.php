<?php
/**
 * 监控-巡检报告
 */

namespace App\Console\Commands;

use App\Repositories\Report\InspectionRepository;
use Illuminate\Console\Command;
use App\Support\GValue;

use DB;
use Log;


class MonitorInspection extends Command
{
    protected $inspection;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'monitorinspectionreport:add {db?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加监控巡检报告的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(InspectionRepository $inspection){
        parent::__construct();
        $this->inspection = $inspection;

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
        \ConstInc::$mOpen = DB::table('action_config')->where('key', 'pc_jk')->value('status');
        if(!\ConstInc::$mOpen) {
            return ;
        }

        $res = array();
//        $reportdateArr = \ConstInc::$mReportArr;//每天报告的时间，几个点表示几份报告,10/15表示小时（24小时制）
//        $date = date("YmdH");
//        $setReportDate = '';
//        $res = array();
//        if ($reportdateArr) {
//            foreach($reportdateArr as $v) {
//                $reportVal = date("Ymd").$v;
////                var_dump($reportVal);
//                if($date == $reportVal){
//                    $setReportDate = $reportVal;
//                }
//            }
//            $input = array();
//            if($setReportDate) {
//                $res = $this->inspection->addReport($input, $setReportDate);
//            }
//        }

        $res = $this->inspection->addReport();
        Log::info('monitorinspectionreport cmd:'.json_encode($res));
    }







}



