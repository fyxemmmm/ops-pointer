<?php
/**
 * 资产字段的计算
 */

namespace App\Console\Commands;

//use App\Repositories\Assets\DeviceRepository;
use Illuminate\Console\Command;

use DB;
use Log;


class AssetsCal extends Command
{
    protected $events;
    protected $eventsComment;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'assets:cal {db?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '资产部分字段需要计算';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(){
        parent::__construct();

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
        $update = [
            "warranty_state" => DB::raw("if (datediff(`warranty_end` , CURRENT_DATE) > 0, 1, 0 )"), //1在保  2过保
           // "warranty_time" => DB::raw("if (datediff(`warranty_end` , CURRENT_DATE) < 0, 0, datediff(`warranty_end` , CURRENT_DATE) )")
            "warranty_time" => DB::raw("datediff(`warranty_end` , CURRENT_DATE)")
        ];

        $notices = DB::table("assets_device")
            ->whereNotNull("warranty_end")
            ->whereNotNull("warranty_begin")->update($update);

        Log::info('AssetsCal done : '.$notices);
    }
}



