<?php
/**
 * 监控-告警数据
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\CommonRepository;
use Illuminate\Console\Command;
use App\Repositories\Monitor\MonitorCurrentAlertRepository;
use App\Support\GValue;

use DB;
use Log;


class Monitoralert extends Command
{
    protected $common;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'monitoralert:add {db?} {begin?} {end?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加监控告警数据的命令.';

    protected $monitorcurrentalert;

    function __construct(CommonRepository $common, MonitorCurrentAlertRepository $monitorcurrentalert){
        parent::__construct();
        $this->common = $common;
        $this->monitorcurrentalert = $monitorcurrentalert;

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

    public function handle(){
        $this->selectDb();
        \ConstInc::$mOpen = DB::table('action_config')->where('key', 'pc_jk')->value('status');
        if(!\ConstInc::$mOpen) {
            return ;
        }
        $input = $this->arguments();
//        $this->monitorcurrentalert->triggerEventByLink([2310]); //测试告警
//        die;
        for($type = 1;$type <= 2;$type++) {
            $res = $this->common->getCurrentAlertListSave($input, $type);

            $ids = [];
            foreach ($res['add'] as $v) {
                $ids[] = $v;
            }
            foreach ($res['update'] as $v) {
                $ids[] = $v;
            }
            if (!empty($ids)) {
                if($type === 1) {
                    $this->monitorcurrentalert->triggerEvent($ids);
                }
                else {
                    $this->monitorcurrentalert->triggerEventByLink($ids);
                }
            }
            Log::info("monitoralert type: $type insert cmd:" . json_encode($res));
        }
    }







}



