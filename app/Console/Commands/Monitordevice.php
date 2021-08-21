<?php
/**
 * 监控-设备数据更新
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\CommonRepository;
use Illuminate\Console\Command;
use App\Repositories\Monitor\AssetsMonitorRepository;
use App\Support\GValue;

use DB;
use Log;


class Monitordevice extends Command
{
    protected $common;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'monitordevice:update {db?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是监控设备数据更新的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(CommonRepository $common,
                         AssetsMonitorRepository $assetsmonitor
    ){
        parent::__construct();
        $this->common = $common;
        $this->assetsmonitor = $assetsmonitor;

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
//        $input = $this->arguments();
        $data = $this->assetsmonitor->getHWDeviceInfo(array(),true);
        $res = array_keys($data);
        Log::info('monitordevice update_'.date('Y-m-d H:i:s').' cmd:'.json_encode($res));
    }







}



