<?php
/**
 * 环控-告警消息数据入库
 */

namespace App\Console\Commands;

use App\Repositories\Monitor\EnvironmentalRepository;
use Illuminate\Console\Command;
use App\Support\GValue;

use DB;
use Log;


class EmAlarm extends Command
{
    protected $inspection;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'emalarm:add {db?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是添加环控告警消息数据入库的命令.';

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

        $res = $this->emRepository->addEmAlarm();

        Log::info('em_alert cmd:'.json_encode($res));
    }







}



