<?php
/**
 * 微信-批量发送模板通知
 */

namespace App\Console\Commands;

use App\Repositories\Weixin\BatchSendNoticeRepository;
use App\Repositories\Weixin\CommonRepository;
use Illuminate\Console\Command;
use App\Support\GValue;
use App\Models\Workflow\Event;
use App\Models\Workflow\Oa;

use DB;
use Log;


class WxBatchSendNotice extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'wxbatchsendnotice:send {db?} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '这是批量发送微信模板通知的命令.';

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $batchSendNotice;
    protected $common;

    function __construct(BatchSendNoticeRepository $batchSendNotice,
                         CommonRepository $common){
        parent::__construct();
        $this->batchSendNotice = $batchSendNotice;
        $this->common = $common;

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
        $res = array();
        $this->selectDb();
        $where = array('is_send'=>0);
        $list = $this->batchSendNotice->getListAll($where);
        $list = $list ? $list->toArray() : array();
        $hostDomain = config('app.wx_url');
        if($list) {
            foreach ($list as $v) {
                $id = getKey($v,'id');
                $openidArr = isset($v['openids']) ? explode(',', $v['openids']) : array();
                if($openidArr){
                    foreach ($openidArr as $openid) {
                        $etype = isset($v['etype']) ? $v['etype'] : 0;
                        if(1 == $etype) {
                            $state = isset(Event::$stateMsg[$v['state']]) ? Event::$stateMsg[$v['state']] : '';
                        }else{
                            $state = isset(Oa::$stateMsg[$v['state']]) ? Oa::$stateMsg[$v['state']] : '';
                        }
                        $url = isset($v['url']) ? $v['url'] : '';
                        $wxNotice = array(
                            'openid' => $openid,
                            'title' => isset($v['title']) ? $v['title'] : '',
                            'url' => $hostDomain. $url,
                            'desc' => isset($v['description']) ? $v['description'] : '',
                            'eventID' => isset($v['event_id']) ? $v['event_id'] : 0,
                            'state' => $state
                        );
                        if($openid) {
                            $this->common->sendWXNotice($wxNotice);
                        }
//                        var_dump($wxNotice);
                    }
                }
//                var_dump($openidArr);
                $update = array('is_send'=>1);
                $this->batchSendNotice->update($id, $update);
                $res[$id] = $openidArr;
            }
        }


        Log::info('send_wx_notice_batch cmd:'.json_encode($res));
    }







}



