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


class EmMonitordevice extends Command
{
    protected $environmental;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'emmonitordevice:add {db?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加环境监控设备的命令.';

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
//        $input = $this->arguments();
        $res = $this->environmental->addDevices();

        Log::info('em_devices:'.json_encode($res));
    }







}



