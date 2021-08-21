<?php
/**
 * 监控-设备数据更新
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\LinksRepository;
use Illuminate\Console\Command;
use App\Support\GValue;

use DB;
use Log;


class Monitorlinks extends Command
{
    protected $links;

    protected $runDb = ["opf_base"];

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'monitorlinks:collect {db?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '线路数据采集.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(LinksRepository $links){
        parent::__construct();
        $this->links = $links;
    }

    public function selectDb() {
        $db = $this->argument('db');
        if(!empty($db)) {
            if(!in_array($db, $this->runDb)) {
                return false;
            }
            //分库
            DB::setDefaultConnection($db);
            GValue::$currentDB = $db;
        }else{
            //默认库
            $db = DB::getDatabaseName();
        }
        \ConstInc::monitorCurrentConf($db);
        return true;
    }

    public function handle(){
        if(false === $this->selectDb()) {
            return ;
        }
        \ConstInc::$mOpen = DB::table('action_config')->where('key', 'pc_jk')->value('status');
        if(!\ConstInc::$mOpen) {
            return ;
        }

        $data = $this->links->collect();

        Log::info('monitorlinks collect ', ["insertId" => $data]);
    }

}



