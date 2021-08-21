<?php
/**
 * 环控
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\EnvironmentalRepository;
use Illuminate\Console\Command;
use App\Support\GValue;

use DB;
use Log;


class EmMonitorpoint extends Command
{
    protected $environmental;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'emmonitorpoint:add {db?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加环境监控点的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(EnvironmentalRepository $environmental){
        parent::__construct();
        $this->environmental = $environmental;

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
        \ConstInc::$emOpen = DB::table('action_config')->where('key', 'pc_hk')->value('status');
        if(!\ConstInc::$emOpen) {
            return ;
        }
        $res = [];
        $device = $this->environmental->getDeviceList();
        $device = $device ? $device->toArray() : array();
        if($device){
            foreach($device as $d){
                $res[] = $this->environmental->monitorPoint($d);
            }
        }


        Log::info('em_device_monitor_point:'.json_encode($res));
    }







}



