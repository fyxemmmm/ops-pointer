<?php
/**
 *
 */

#namespace App\Http\Controllers\Home;
namespace App\Console\Commands;

use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Workflow\EventsRepository;
use Illuminate\Console\Command;
use App\Support\GValue;

use DB;
use Log;


class EventsComment extends Command
{
    protected $events;
    protected $eventsComment;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'events:comment {db?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是用户事件评论的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */

    function __construct(EventsRepository $events, EventsCommentRepository $eventsComment){
        parent::__construct();
        $this->events = $events;
        $this->eventsComment = $eventsComment;

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
        $where = array('source' => 3, 'state' => 3, 'is_comment' => 0);
        $now = time();
        $where[] = [DB::raw("$now-UNIX_TIMESTAMP(updated_at)"), '>', \ConstInc::USER_AUTO_COMMENT_TIME];
        $eRes = $this->events->getListByWhere($where);
        $eids = array();
        if($eRes) {
            foreach($eRes as $v){
                $eid= isset($v['id']) ? $v['id']: '';
                if($eid){
                    $eids[] = $eid;
                }
            }
            $eids = array_filter(array_unique($eids));
            if($eids){

//                var_dump($eids);exit;
                $paramEC = array('star_level'=>5,'content'=>'此用户没有填写评价。','feedback'=>'此用户没有填写反馈。');
                $this->eventsComment->addBatch($eids,$paramEC);
                $whereUP = array('id',$eids);
                $paramUP = array('is_comment'=>1);
                $this->events->updateBatch($whereUP,$paramUP);
            }

        }
        Log::info('event cmd success:'.json_encode($eids));
    }







}



