<?php
/**
 * 监控-历史告警数据
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\CommonRepository;
use Illuminate\Console\Command;
use App\Repositories\Monitor\MonitorAlertRepository;
use App\Support\GValue;
use ConstInc;
use DB;
use Log;


class MonitoralertHistory extends Command
{
    protected $common;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'monitoralerthistory:addBatch {db?} {begin?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是批量添加监控历史告警数据的命令.';

    protected $monitoralert;

    function __construct(CommonRepository $common, MonitorAlertRepository $monitoralert){
        parent::__construct();
        $this->common = $common;
        $this->monitoralert = $monitoralert;
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
        ConstInc::monitorCurrentConf($db);
    }


    public function handle(){
        $this->selectDb();
        ConstInc::$mOpen = DB::table('action_config')->where('key', 'pc_jk')->value('status');
        if(!ConstInc::$mOpen) {
            return ;
        }
        $input = $this->arguments();
        $begin = isset($input['begin']) ? $input['begin'] : '';
        $start = date('Y-m-d H:i:s',strtotime('-1 hour'));
        $input['begin'] = $begin ? $begin : $start;

        //type 分为1和2
        for($type = 1;$type <= 2;$type++) {
            $res = $this->common->alertHistoryBatchSave($input,$type);
            if(!empty($res)) {
                foreach($res as $v) {
                    $ids = [];
                    foreach($v['add'] as $vv) {
                        $ids[] = $vv;
                    }
                    foreach($v['update'] as $vv){
                        $ids[] = $vv;
                    }
                    if(!empty($ids)) {
                        if($type === 1) {
                            $this->monitoralert->triggerEvent($ids);
                        }
                        else {
                            $this->monitoralert->triggerEventByLink($ids);
                        }
                    }
                }
            }
            Log::info("monitoralerthistory addBatch type:$type cmd:".json_encode($res));
        }
    }







}



