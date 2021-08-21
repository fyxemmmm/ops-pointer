<?php
/**
 * OA事件
 * User: wangwei
 * Date: 2018/7/10
 * Time: 17:10
 */

namespace App\Http\Controllers\Home;
//namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Weixin\CommonRepository;
use App\Repositories\Weixin\EventsPicRepository;
use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Weixin\EventsTrackRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Workflow\OaRepository;
use App\Repositories\Assets\DevicePortsRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Models\Code;
use App\Models\Workflow\Category;
use App\Models\Workflow\Event;
use App\Models\Workflow\Oa;
use App\Exceptions\ApiException;
use App\Support\Response;
use Auth;
use App\Repositories\Auth\UserRepository;
use App\Http\Requests\Workflow\EventsRequest;
use DB;
use App\Repositories\Workflow\EventsSuspendRepository;
use App\Repositories\Weixin\QyWxUserRepository;
use Log;
use App\Repositories\Weixin\BatchSendNoticeRepository;


class EventOaController extends Controller
{

    protected $common;
    protected $response;
    protected $events;
    protected $eventsPic;
    protected $upload;
    protected $device;
    protected $eventsComment;
    protected $categoryModel;
    protected $weixinuser;
    protected $user;
    protected $eventsTrack;
    protected $devicePorts;
    protected $eventoa;
    protected $eventssuspend;
    protected $qywxuser;
    protected $batchSendNotice;
    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';
    protected $hostName;
    protected $eventpicPath = '/storage/app/home/eventoa';
    protected $homeEventoaPath = '/home/eventoa';
    protected $tracknoticesPath = '';
    protected $tracknoticesTitle = 'OA事件处理已更新，点击查看';
    protected $baseTracknoticesPath = '';

    function __construct(CommonRepository $common,
                         OaRepository $eventoa,
                         EventsPicRepository $eventsPic,
                         DeviceRepository $device,
                         EventsCommentRepository $eventsComment,
                         Category $categoryModel,
                         EventsTrackRepository $eventsTrack,
                         WeixinUserRepository $weixinuser,
                         UserRepository $user,
                         DevicePortsRepository $devicePorts,
                         EventsRepository $events,
                         EventsSuspendRepository $eventssuspend,
                         QyWxUserRepository $qywxuser,
                         BatchSendNoticeRepository $batchSendNotice
    )
    {
        $this->common = $common;
        $this->eventoa = $eventoa;
        $this->eventsPic = $eventsPic;
        $this->eventsComment = $eventsComment;
        $this->categoryModel = $categoryModel;
        $this->eventsTrack = $eventsTrack;
        $this->weixinuser = $weixinuser;
        $this->user = $user;
        $this->devicePorts = $devicePorts;
        $this->events = $events;
        $this->eventssuspend = $eventssuspend;

        $this->response = new Response();
//        $this->upload = new Upload();
        $this->device = $device;
//        dd(checkLogin());exit;
//        session()->put('userInfo',array('id'=>111,'name'=>'aaddd'));
        $scheme = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
        $hostDomain = $scheme.$_SERVER['HTTP_HOST'];
        $this->baseTracknoticesPath = '/home/user/tracknoticeshow?eventoaId=';
        $this->tracknoticesPath = $hostDomain.$this->baseTracknoticesPath;
        $this->qywxuser = $qywxuser;
        $this->batchSendNotice = $batchSendNotice;

    }

    public function index(Request $request)
    {
        echo 'index';
        dd($request->session()->all());//get('userInfo')
    }


    public function add()
    {
        var_dump($_SERVER['SERVER_NAME']);

        return view('home.login');
    }


    /**
     * 用户上报事件
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function addAct(Request $request)
    {
        $input = $request->post() ? $request->post() : array();
        $reportName = isset($input['report_name']) ? trim($input['report_name']) : '';
        $problem = isset($input['problem']) ? trim($input['problem']) : '';
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : '';
        $location = isset($input['location']) ? trim($input['location']) : '';
        $company = isset($input['company']) ? trim($input['company']) : '';
        $processUserId = isset($input['user_id']) ? $input['user_id'] : '';
        $wxUser = array();

//        var_dump($input);exit;

        if (!$problem || !$reportName || !$company) {
            $errMsg = '';
            if(!$reportName){
                $symbol = !$company || !$problem ? ',' : '.';
                $errMsg .= '上报人不能为空'.$symbol;
            }
            if(!$company){
                $symbol = !$problem ? ',' : '.';
                $errMsg .= '事件单位不能为空'.$symbol;
            }
            if(!$problem){
                $errMsg .= '故障描述不能为空.';
            }
            throw new ApiException(Code::ERR_PARAMS, [$errMsg]);
        }
        DB::beginTransaction(); //开启事务

        try {
            $res = $this->eventoa->wxReport($input);
            if(!$res){
                return $this->response->send($res);
            }
            $resEventID = isset($res['id']) ? $res['id'] : 0;
            $result['result'] = $resEventID;

            if ($resEventID) {
                //上传图片
                $uploadPath = $this->eventpicPath .'/'.$resEventID;
                $picRes = uploadPics($request, 'event_pic', $uploadPath, $this->homeEventoaPath);
    //            $status = isset($picRes['status']) ? $picRes['status'] : '';
                $picPaths = isset($picRes['data']) ? $picRes['data'] : '';
                $picResErrMsg = isset($picRes['msg']) ? $picRes['msg'] : '';
                $picResErrCode = isset($picRes['errCode']) ? $picRes['errCode'] : '';
                if ($picResErrCode) {
                    $errmsg = '';
                    Log::info('上传图片出错，事件ID:' . $resEventID);
                    if(12003 == $picResErrCode){
                        $errmsg = $picResErrMsg;
                    }
                    throw new ApiException($picResErrCode,[$errmsg]);
                } elseif ($picPaths) {
                    //保存图片到数据库
                    $this->eventsPic->addBatch($picPaths, $resEventID, \ConstInc::WX_OAETYPE);
                }
                $sessuInfo = Auth::user();
                $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
                $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
                $flag = true;
                if($processUserId){
                    if (5 == $identity_id) {
                        $wxNotice = array(
                            'title' => '用户提交了一起新OA事件，点击查看',
                            'url' => $this->tracknoticesPath . $resEventID,
                            'desc' => $problem,
                            'eventID' => strPad($resEventID),
                            'state' => isset(Oa::$stateMsg[0]) ? Oa::$stateMsg[0] : '',
                            'dtype' => 1
                        );
                        if ($sessuid && $sessuid != $processUserId) {
                            if(2 == \ConstInc::WX_PUBLIC){
                                //企业微信通知消息
                                $whereWx = array('user_id' => $processUserId);
                                $wxUser = $this->qywxuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                $this->common->qySendTextcard($wxNoticeH);
                            }else {
                                //微信公众号通知消息
                                //获取事件处理人的openid，通知处理人（工程师，本人接单不通知）
                                $whereWx = array('userid' => $processUserId);
                                $wxUser = $this->weixinuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                // 之前方法获取用户 openid ，此处换成 qywx_user 表中 userid
                                $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                //发送微信模板消息
                                $this->common->sendWXNotice($wxNoticeH);
                            }
                            $flag = false;
                        }
                    }

                }
                //无指定派发人，通知所有工程师或工程师主管
                if($flag){
                    $this->sendWxNoticeBatch($res);
                }

            }
            DB::commit();

        }catch(Exception $e){
            Log::info(Code::ERR_USER_REPORT_OAEVENT);
            DB::rollback();
            throw new ApiException(Code::ERR_USER_REPORT_OAEVENT);
        }


        return $this->response->send($result);

    }

    /**
     * 事件列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList(Request $request)
    {
//        $request->offsetSet('source', 3);
        $data = $this->eventoa->wxList($request, 'updated_at');

        return $this->response->send($data);
    }


    /**
     * 事件详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function details(Request $request)
    {
        $eventId = $request->input("id");
        $data = array();
        $where2 = null;
        if (!$eventId) {
            throw new ApiException(Code::ERR_PARAM_EMPTY);
        }
        if ($eventId) {
            $details = array();
            $event = $this->eventoa->getById($eventId);
//            var_dump($event->asset->number);exit;

//            var_dump($details);exit;

            $where2[] = ["event_id", "=", $eventId];
            $where2[] = ["etype", "=", \ConstInc::WX_OAETYPE];
            //图片
            $imgs = $this->eventsPic->getList($where2);
            //事件跟踪
            $track = $this->eventsTrack->getList($where2);
            $track = $track->toArray();
//            var_dump($track);exit;
//            var_dump($result,$imgs);exit;
            if ($event) {
                $details['id'] = $event->id;
                $details['remark'] = $event->remark;
                $details['state'] = $event->state;
                $details['category_id'] = $event->category_id;
                $details['user_id'] = $event->user_id;
                $details['assigner_id'] = $event->assigner_id;
                $details['report_id'] = $event->report_id;
                $details['report_name'] = $event->report_name;
                $details['mobile'] = $event->mobile;
                $details['description'] = $event->description;
                $details['source'] = $event->source;
                $details['problem'] = $event->problem;
                $details['location'] = $event->location;
                $details['device_name'] = $event->device_name;
                $details['object'] = $event->object;
                $details['company'] = $event->company;
                $details['is_comment'] = $event->is_comment;
                $details['close_uid'] = $event->close_uid;
                $details['response_time'] = $event->response_time;
                $details['created_at'] = $event->created_at;
                $details['accept_at'] = $event->accept_at;
                $details['reached_at'] = $event->reached_at;
                $details['finished_at'] = $event->finished_at;
                $details['updated_at'] = $event->updated_at->format('Y-m-d H:i:s');
                $details['category'] = is_null($event->category) ? '' : $event->category->name;
                $details['user'] = is_null($event->user) ? '' : $event->user->username;
                $details['assigner'] = is_null($event->assigner) ? '' : $event->assigner->username;
                $details['created_at'] = $event->created_at->format('Y-m-d H:i:s');
                $details['object_name'] = getKey(Oa::$objectMsg, $event->object);
//            $details = $event ? $event->toArray() : array();
                $details['images'] = $imgs ? $imgs : array();
                $details['report_at'] = $event->report_at;
                $details['distance_time'] = $event->distance_time;
            }
            $data['details'] = $details;
            $data['track'] = isset($track['data']) ? $track['data'] : '';//$track;//
        }
        return $this->response->send($data);
    }


    /**
     * 事件评论
     * @param Request $request
     * @return \Illuminate\Http\JsonRespons
     *
     */
    public function commentAct(Request $request)
    {
        $data = array();
        $input = $request->input() ? $request->input() : '';
        $eventID = isset($input['id']) ? $input['id'] : 0;
        $star_level = isset($input['star_level'])?intval($input['star_level']):0;
        if (!$eventID) {
            throw new ApiException(Code::ERR_EVENT_NOT);
        }
        if ($eventID) {
            $sessuInfo = Auth::user();
//            var_dump($sessuInfo['id']);exit;
            $content = isset($input['content']) ? trim($input['content']) : '';
            $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            $input['uid'] = $sessuid;//isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            if (!$sessuid) {
                throw new ApiException(Code::ERR_HTTP_UNAUTHORIZED);
            }
            if($star_level < 5) {
                if (!$content) {
                    throw new ApiException(Code::ERR_COMMENT_CONTENT);
                }
            }
//            var_dump($input);exit;
            $where = null;
            $where[] = ["event_id", "=", $eventID];
            $where[] = ["etype", "=", \ConstInc::WX_OAETYPE];
            $res = $this->eventsComment->getOne($where);
            if ($res) {
                throw new ApiException(Code::ERR_COMMENTED);
            }
            $event = $this->eventoa->getById($eventID);
            $userid = isset($event['user_id']) ? $event['user_id'] : 0;
            $eventState = isset($event['state']) ? $event['state'] : 0;
            if (3 != $eventState) {
                throw new ApiException(Code::ERR_UNFINISHED);
            }
//            var_dump($input);exit;
            if ($input && !$res) {
                $input['event_id'] = $eventID;
                $input['etype'] = \ConstInc::WX_OAETYPE;
                $comment = $this->eventsComment->add($input);
                $data['result'] = $comment;
                if ($comment) {
                    $model = $this->eventoa->getById($eventID);
                    $model->is_comment = 1;
                    $model->timestamps = false;
                    $model->save();
                    if($star_level < 5 && $userid != $sessuid && $sessuid) {
                        $eParam = array('manager'=>true,'admin'=>false,'onlyEManager' => true);
                        $msg = '评论未达到5星';
                        $this->sendWxNoticeBatch($event,$eParam,$msg);
                    }
                }
            }
        }

        return $this->response->send($data);
    }


    /**
     * 显示工程师处理页面
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trackShow(Request $request)
    {
        $result = array('processers' => '');
        $input = $request->input() ? $request->input() : '';
        $eventID = isset($input['id']) ? $input['id'] : 0;
        if (!$eventID) {
            throw new ApiException(Code::ERR_PARAM_EMPTY);
        }

        //显示用户提交事件
        $event = $this->eventoa->getById($eventID);
        if (!$event) {
            throw new ApiException(Code::ERR_MODEL);
        }
        $whereEP = array("event_id" => $eventID,'etype'=>\ConstInc::WX_OAETYPE);
        $imgs = $this->eventsPic->getList($whereEP);
//        var_dump($result,$imgs);exit;
        $event['images'] = $imgs ? $imgs : array();
        $result['event'] = $event;
        $reportId = isset($event['report_id']) ? $event['report_id'] : 0;
        $reporter = array();
        if($reportId) {
            $reporter = $this->user->getById($reportId);
            $reporter = $reporter->toArray();
        }
        $result['report_user'] = array(
            'id' => getKey($reporter,'id'),
            'name' => getKey($reporter,'username'),
            'phone' => getKey($reporter,'phone'),
        );

        if ($this->isManageAccess(array(1,2,5))) {

            $result['processers'] = $this->user->getEngineersUsersTypework();
        }

        $metaArr = $this->eventoa->getmeta();

        $result = array_merge($result,$metaArr);


        return $this->response->send($result);
    }


    /**
     * 事件跟踪
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trackAct(Request $request)
    {
        $result = array('result' => false);
        $input = $request->input() ? $request->input() : '';
        if ($input) {
            $step = isset($input['step']) ? intval($input['step']) : 0;
            $eventId = isset($input["id"]) ? $input["id"] : 0;
            $handler_id = isset($input['user_id']) ? $input['user_id'] : '';
            if(in_array($step,array(1,2,3))) {
                if (!$this->isManageAccess(array(1, 2, 3, 5))) {
                    throw new ApiException(Code::ERR_EVENT_PERM);
                }
            }
            //更新事件、事件相关操作，3/4中二选一
            if (!$eventId) {
                throw new ApiException(Code::ERR_PARAM_EMPTY);
            }
            $event = $this->eventoa->getById($eventId);
            $resHandlerID = isset($event['user_id']) ? $event['user_id'] : 0;
            $eventState = isset($event['state']) ? $event['state'] : '';
            if (!$event) {
                throw new ApiException(Code::ERR_MODEL);
            }


            $whereET = array('event_id' => $eventId, 'step' => $step,'etype'=>\ConstInc::WX_OAETYPE);
            $etRes = $this->eventsTrack->getOne($whereET);
            if ($etRes) {
                throw new ApiException(Code::ERR_REPEAT_SUBMIT);
            }
            $sessuInfo = Auth::user();
            $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
            $eUserID = isset($event['user_id']) ? $event['user_id'] : '';
            $assigner_id = isset($event['assigner_id']) ? $event['assigner_id'] : 0;

            //分派
            if($handler_id && (!$eventState || $eventState == 0)) {
                if($this->user->isManager() && $handler_id != $sessuid) {
                    $res = $this->eventoa->wxAssign($input, $event);
                    $assigner_id = isset($res['assigner_id']) ? $res['assigner_id'] : 0;

                    $result['result'] = $res ? true : false;
                    //分派人是当前登录用户，并且分派人不等于处理人，说明是主管分派的
                    if ($handler_id != $assigner_id && $assigner_id && $assigner_id == $sessuid && $res) {
                        $wxNotice = array(
                            'title' => $this->tracknoticesTitle,
                            'desc' => isset($event['problem']) ? $event['problem'] : '',
                            'url' => $this->tracknoticesPath . $eventId,
                            'eventID' => strPad($eventId),
                            'state' => getKey(Oa::$stateMsg,Oa::STATE_WAIT),
                            'dtype' => 1
                        );
                        if(2 == \ConstInc::WX_PUBLIC){
                            //企业微信通知消息
                            $whereWx = array('user_id' => $handler_id);
                            $wxUser = $this->qywxuser->getOne($whereWx);
                            $wxNoticeH = $wxNotice;
                            $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                            $this->common->qySendTextcard($wxNoticeH);
                        }else {
                            //微信公众号通知消息
                            //获取通知人（工程师）的openid
                            $whereWx = array('userid' => $handler_id);
                            $wxUser = $this->weixinuser->getOne($whereWx);
                            $wxNoticeH = $wxNotice;
                            $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                            $this->common->sendWXNotice($wxNoticeH);
                        }
                    }
                    return $this->response->send($result);
                }
            }

            if (in_array($step, array(2, 3))) {
                if ($sessuid != $eUserID) {
                    throw new ApiException(Code::ERR_OPERATION_ACCESS_NOT);//ERR_KB_PERM
                }
            }


            $opFlag = false;
            switch ($step) {
                case "1" ://已接单
//                    $eventId = $request->input("eventId");
//                    $event = $this->events->getById($eventId);
                    if(!$eventState || $eventState == 0) {
                        $accept = $this->eventoa->wxAccept($input,$event);
                        $handler_id = isset($accept['user_id']) ? $accept['user_id'] : 0;
                        if ($accept) {
                            $wxNotice = array(
                                'title' => $this->tracknoticesTitle,
                                'desc' => isset($event['problem']) ? $event['problem'] : '',
                                'url' => $this->tracknoticesPath . $eventId,
                                'eventID' => strPad($eventId),
                                'state' => getKey(Oa::$stateMsg,Oa::STATE_ACCEPT),
                                'dtype' => 1
                            );
                            if ($handler_id && $sessuid && $sessuid != $handler_id) {
                                if(2 == \ConstInc::WX_PUBLIC){
                                    //企业微信通知消息
                                    $whereWx = array('user_id' => $handler_id);
                                    $wxUser = $this->qywxuser->getOne($whereWx);
                                    $wxNoticeH = $wxNotice;
                                    $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                    $this->common->qySendTextcard($wxNoticeH);
                                }else {
                                    //微信公众号通知消息
                                    //获取事件处理人的openid，通知处理人（工程师，本人接单不通知）
                                    $whereWx = array('userid' => $handler_id);
                                    $wxUser = $this->weixinuser->getOne($whereWx);
                                    $wxNoticeH = $wxNotice;
                                    $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                    $this->common->sendWXNotice($wxNoticeH);
                                }
                            }
                            //获取上报用户openid，通知用户
                            $reportid = isset($event['report_id']) ? $event['report_id'] : 0;
                            if ($reportid) {
                                if(2 == \ConstInc::WX_PUBLIC){
                                    //企业微信通知消息
                                    $whereWx = array('user_id' => $reportid);
                                    $wxUser = $this->qywxuser->getOne($whereWx);
                                    $wxNoticeU = $wxNotice;
                                    $wxNoticeU['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                    $wxNoticeU['title'] = $this->tracknoticesTitle;
                                    $this->common->qySendTextcard($wxNoticeU);
                                }else {
                                    //微信公众号通知消息
                                    $whereWx = array('userid' => $reportid);
                                    $wxUser = $this->weixinuser->getOne($whereWx);
                                    $wxNoticeU = $wxNotice;
                                    $wxNoticeU['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                    $wxNoticeU['title'] = $this->tracknoticesTitle;
                                    $this->common->sendWXNotice($wxNoticeU);
                                }
                            }
                            $opFlag = true;
                        }
                    }else{
                        throw new ApiException(Code::ERR_EVENT_RECEIPT);
                    }
                    break;
                case "2"://处理中
                    $opFlag = $this->eventoa->wxReach($input,$event);
                    break;
                case "3"://已完成
                    $suspend = $this->eventssuspend->getUsetimeByEventId($eventId,\ConstInc::WX_OAETYPE);
                    $event['suspend_usetime'] = isset($suspend['usetime']) ? $suspend['usetime'] : '';
                    $finish = $this->eventoa->wxFinish($input,$event);
                    if ($finish) {
                        $handler_id = isset($event['user_id']) ? $event['user_id'] : 0;
                        $assigner_id = isset($event['assigner_id']) ? $event['assigner_id'] : 0;
                        $wxNotice = array(
                            'title' => $this->tracknoticesTitle,
                            'desc' => isset($event['problem']) ? $event['problem'] : '',
                            'url' => $this->tracknoticesPath . $eventId,
                            'eventID' => strPad($eventId),
                            'state' => getKey(Oa::$stateMsg,Oa::STATE_END),
                            'dtype' => 1
                        );

                        //处理人是当前登录用户，并且处理人不等于分派人，说明是主管分派的
                        if ($handler_id != $assigner_id && $handler_id && $handler_id == $sessuid) {
                            if(2 == \ConstInc::WX_PUBLIC){
                                //企业微信通知消息
                                $whereWx = array('user_id' => $assigner_id);
                                $wxUser = $this->qywxuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                $this->common->qySendTextcard($wxNoticeH);
                            }else {
                                //微信公众号通知消息
                                //获取事件主管的openid
                                $whereWx = array('userid' => $assigner_id);
                                $wxUser = $this->weixinuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                $this->common->sendWXNotice($wxNotice);
                            }
                        }
                        //获取上报用户openid，通知用户
                        $reportid = isset($event['report_id']) ? $event['report_id'] : 0;
                        if ($reportid) {
                            if(2 == \ConstInc::WX_PUBLIC){
                                //企业微信通知消息
                                $whereWx = array('user_id' => $reportid);
                                $wxUser = $this->qywxuser->getOne($whereWx);
                                $wxNoticeU = $wxNotice;
                                $wxNoticeU['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                $this->common->qySendTextcard($wxNoticeU);
                            }else {
                                //微信公众号通知消息
                                $whereWx = array('userid' => $reportid);
                                $wxUser = $this->weixinuser->getOne($whereWx);
                                $wxNoticeU = $wxNotice;
                                $wxNoticeU['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                $this->common->sendWXNotice($wxNoticeU);
                            }
                        }
                        $opFlag = true;
                    }
                    break;
                case "4"://已关闭
                    $close = $this->eventoa->close($input,$event);
                    if ($close) {
                        $remark = $request->input("remark");
                        $handler_id = isset($event['user_id']) ? $event['user_id'] : 0;
//                        $assigner_id = isset($event['assigner_id']) ? $event['assigner_id'] : 0;
                        $wxNotice = array(
                            'title' => $this->tracknoticesTitle,
                            'desc' => $remark ? $remark : '',
                            'url' => $this->tracknoticesPath . $eventId,
                            'eventID' => strPad($eventId),
                            'state' => isset(Event::$stateMsg[Event::STATE_CLOSE]) ? Event::$stateMsg[Event::STATE_CLOSE] : '',
                            'dtype' => 1
                        );

                        //分派人是当前登录用户，并且分派人不等于处理人，说明是主管分派的
                        if ($handler_id != $assigner_id && $assigner_id && $assigner_id == $sessuid) {
                            if(2 == \ConstInc::WX_PUBLIC){
                                //企业微信通知消息
                                $whereWx = array('user_id' => $handler_id);
                                $wxUser = $this->qywxuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                $this->common->qySendTextcard($wxNoticeH);
                            }else {
                                //微信公众号通知消息
                                //获取通知人（工程师）的openid
                                $whereWx = array('userid' => $handler_id);
                                $wxUser = $this->weixinuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                $this->common->sendWXNotice($wxNoticeH);
                            }
                        }
                        //获取上报用户openid，通知用户
                        $reportid = isset($event['report_id']) ? $event['report_id'] : 0;
                        if ($reportid && 4 == $identity_id && $reportid != $sessuid) {
                            if(2 == \ConstInc::WX_PUBLIC){
                                //企业微信通知消息
                                $whereWx = array('user_id' => $reportid);
                                $wxUser = $this->qywxuser->getOne($whereWx);
                                $wxNoticeH = $wxNotice;
                                $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                $this->common->qySendTextcard($wxNoticeH);
                            }else {
                                //微信公众号通知消息
                                $whereWx = array('userid' => $reportid);
                                $wxUser = $this->weixinuser->getOne($whereWx);
                                $wxNoticeU = $wxNotice;
                                $wxNoticeU['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                $this->common->sendWXNotice($wxNoticeU);
                            }
                        }
                        $opFlag = true;
                    }
                    break;
                default:
                    break;
            }
        }
        if ($opFlag) {
            $input['eventId'] = $eventId;
            $input['etype'] = \ConstInc::WX_OAETYPE;
            $this->eventsTrack->add($input);
            $result['result'] = true;
        }

        return $this->response->send($result);
    }


    /**
     * 验证是否有分配权限、操作权限或包含工程师
     * @return bool
     */
    private function isManageAccess($roles = array(1, 2, 3))
    {
        $result = false;
        $sessuInfo = Auth::user();
        if (in_array($sessuInfo->identity_id, $roles)) {
            $result = true;
        }
        return $result;
    }


    /**
     * 批量微信模板通知消息(发送给工程师或主管)
     * @param array $event
     * @return bool
     */
    private function sendWxNoticeBatch($event = array(),$eParam=array(),$msg='')
    {
        $eventId = isset($event['id']) ? $event['id'] : '';
        $emanager = isset($eParam['manager']) ? $eParam['manager'] : '';
        $admin = isset($eParam['admin']) ? $eParam['admin'] : '';
        $onlyEManager = isset($eParam['onlyEManager'])?$eParam['onlyEManager']:'';
        $engineers = $this->user->getEngineers($emanager,$admin,$onlyEManager);
        $uids = array();
        if ($engineers) {
            foreach ($engineers as $v) {
                $uids[] = isset($v['id']) ? $v['id'] : 0;
            }
        }
//        var_dump($uids);exit;
        $uids = array_filter(array_unique($uids));
//        var_dump($uids);exit;
        Log::info($eventId.'_asset_operation_access:'.json_encode($uids));
        if(2 == \ConstInc::WX_PUBLIC){
            $problem = isset($event['problem']) ? $event['problem'] : '';
            //企业微信通知消息
            $wxUsers = $this->qywxuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $useridArr = array();
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $userid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    $useridArr[] = $userid;
                }
                $wxNotice = array(
                    'touser' => $useridArr,
                    'title' => '用户提交了一起新OA事件，点击查看',
                    'url' => $this->tracknoticesPath . $eventId,
                    'desc' => $msg ? $msg : $problem,
                    'eventID' => strPad($eventId),
                    'state' => isset(Oa::$stateMsg[$event['state']]) ? Oa::$stateMsg[$event['state']] : '',
                    'dtype' => 1
                );
                $this->common->qySendTextcard($wxNotice);
                Log::info($eventId . '_send_qywx_notice_batch:' . json_encode($useridArr));
            }
        } else {
            //微信公众号通知消息
            $wxUsers = $this->weixinuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $openidArr = array();
//        var_dump($wxUsers);exit;
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $openid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    $openidArr[] = $openid;
                    /*$problem = isset($event['problem']) ? $event['problem'] : '';
                    $wxNotice = array(
                        'openid' => $openid,
                        'title' => '用户提交了一起新OA事件，点击查看',
                        'url' => $this->tracknoticesPath . $eventId,
                        'desc' => $msg ? $msg : $problem,
                        'eventID' => strPad($eventId),
                        'state' => isset(Oa::$stateMsg[$event['state']]) ? Oa::$stateMsg[$event['state']] : ''
                    );
                    $this->common->sendWXNotice($wxNotice);*/
                }
                if($openidArr) {
                    $problem = isset($event['problem']) ? $event['problem'] : '';
                    $wxNotice = array(
                        'openids' => implode(',', $openidArr),
                        'title' => '用户提交了一起新OA事件，点击查看',
                        'url' => $this->baseTracknoticesPath . $eventId,
                        'description' => $msg ? $msg : $problem,
                        'eventId' => strPad($eventId),
                        'state' => isset($event['state']) ? $event['state'] : '',
                        'etype' => 1,
                    );
                    //保存推送微信通知消息数据入库（定时任务发送消息）
                    $this->batchSendNotice->add($wxNotice);
                }
//                Log::info($eventId . '_send_wx_notice_batch:' . json_encode($openidArr));
            }
        }
        return true;
    }


    /**
     * 保存工程师或主管自建事件
     * @param EventsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function engineerAdd(Request $request)
    {
        $data = array();
        $input = $request->input() ? $request->input() : '';
        $reportName = isset($input['report_name']) ? trim($input['report_name']) : '';
        $problem = isset($input['problem']) ? trim($input['problem']) : '';
        $mobile = isset($input['mobile']) ? trim($input['mobile']) : '';
        $location = isset($input['location']) ? trim($input['location']) : '';
        $company = isset($input['company']) ? trim($input['company']) : '';
        $report_at = isset($input['report_at']) ? trim($input['report_at']) : '';
        $wxUser = array();
        $date = date("Y-m-d H:i:s");

//        var_dump($input);exit;

        if (!$problem || !$reportName || !$company) {
            $errMsg = '';
            if(!$reportName){
                $symbol = !$company || !$problem ? ',' : '.';
                $errMsg .= '上报人不能为空'.$symbol;
            }
            if(!$company){
                $symbol = !$problem ? ',' : '.';
                $errMsg .= '事件单位不能为空'.$symbol;
            }
            if(!$problem){
                $errMsg .= '故障描述不能为空.';
            }
            throw new ApiException(Code::ERR_PARAMS, [$errMsg]);
        }
        if(!$report_at){
            throw new ApiException(Code::ERR_PARAMS, ['上报时间不能为空']);
        }elseif(strtotime($report_at)>strtotime($date)){
            throw new ApiException(Code::ERR_PARAMS, ['上报时间不能大于当前时间']);
        }
        $sessuInfo = Auth::user();
        $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
//        $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
        DB::beginTransaction(); //开启事务

        try {
            $eObj = $this->eventoa->wxAdd($input);
            $event = $eObj ? $eObj->toArray() : array();
            $id = getkey($event, 'id');
            $handler_id = getkey($event, 'user_id');
            $assigner_id = getkey($event, 'assigner_id');
            if ($id) {
                $picRes = uploadPics($request, 'event_pic', $this->eventpicPath .'/'. $id , $this->homeEventoaPath);
//            $status = getkey($picRes,'status');
                $picPaths = getkey($picRes, 'data');
                $picResErrMsg = getkey($picRes,'msg');
                $picResErrCode = getkey($picRes, 'errCode');
                if ($picResErrCode) {
                    $errmsg = '';
                    Log::info('工程师或工主管上传图片出错，事件ID:' . $id);
                    if(12003 == $picResErrCode){
                        $errmsg = $picResErrMsg;
                    }
                    throw new ApiException($picResErrCode,[$errmsg]);
                } elseif ($picPaths) {
                    $this->eventsPic->addBatch($picPaths, $id, \ConstInc::WX_OAETYPE);
                }
                $wxNotice = array(
                    'title' => $this->tracknoticesTitle,
                    'desc' => isset($event['problem']) ? $event['problem'] : '',
                    'url' => $this->tracknoticesPath . $id,
                    'eventID' => strPad($id),
                    'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : '',
                    'dtype' => 1
                );
                if ($handler_id && $sessuid) {
                    if ($sessuid != $handler_id) {
                        if(2 == \ConstInc::WX_PUBLIC){
                            //企业微信通知消息
                            $whereWx = array('user_id' => $handler_id);
                            $wxUser = $this->qywxuser->getOne($whereWx);
                            $wxNoticeH = $wxNotice;
                            $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                            $this->common->qySendTextcard($wxNoticeH);
                        }else {
                            //获取事件处理人的openid，通知处理人（工程师(主管)本人接单不通知）
                            $whereWx = array('userid' => $handler_id);
                            $wxUser = $this->weixinuser->getOne($whereWx);
                            $wxNoticeH = $wxNotice;
                            $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
//                                var_dump($wxNoticeH);
                            $sendwxnotice = $this->common->sendWXNotice($wxNoticeH);
                            if (!$sendwxnotice) {
                                Log::info('发送微信通知消息出错，事件ID:' . $id);
                            }
                        }
                    }
                    //自建无派发时记录跟踪
                    if ($sessuid == $handler_id && $handler_id == $assigner_id) {
                        //事件跟踪
                        $input['eventId'] = $id;
                        $input['step'] = Oa::STATE_ACCEPT;
                        $input['etype'] = \ConstInc::WX_OAETYPE;
                        $etrack = $this->eventsTrack->add($input);
                        if(!$etrack){
                            Log::info('添加跟踪记录出错，事件ID:' . $id);
                        }
                    }
                }
                DB::commit();
                $data = ["eventId" => $id];
            }

        }catch(Exception $e){
            Log::info('更新接单或派发出错，事件ID:' . $id);
            DB::rollback();
            throw new ApiException(Code::ERR_UPDATE_ASSIGN_EVENTOA,[$id]);
        }

        return $this->response->send($data);
    }


    public function report(Request $request)
    {
        $input = $request->input() ? $request->input() : '';
        $eventId = isset($input["id"]) ? $input["id"] : 0;
        if (!$eventId) {
            throw new ApiException(Code::ERR_PARAM_EMPTY);
        }
        $whereEid[] = ["event_id", "=", $eventId];
        $whereEid[] = ["etype", "=", \ConstInc::WX_OAETYPE];
        if ($eventId) {
            $events = $this->eventoa->getById($eventId);
            $details = $events ? $events->toArray() : array();
            $state = isset($details['state']) ? $details['state'] : 0;
            $source = isset($details['source']) ? $details['source'] : '';
            $created_at = isset($details['created_at']) ? $details['created_at'] : '';
            $category_id = isset($details['category_id']) ? $details['category_id'] : '';
            $object = isset($details['object']) ? $details['object'] : '';
            $report_id = isset($details['report_id']) ? $details['report_id'] : '';
//            var_dump($details);exit;
            if (!$details) {
                throw new ApiException(Code::ERR_MODEL);
            }
            if (3 != $state) {
                throw new ApiException(Code::ERR_EVENT_FINISH_NOT);
            }

            $imgs = $this->eventsPic->getList($whereEid);
//            var_dump($result,$imgs);exit;
//
            $track = $this->eventsTrack->getList($whereEid);
            $track = $track->toArray();
            $trackData = isset($track['data']) ? $track['data'] : '';
            $details['processTime'] = $created_at;//处理时间
            $details['finishTime'] = '';//完成时间
            if ($trackData) {
                foreach ($trackData as $v) {
                    $step = isset($v['step']) ? $v['step'] : '';
                    if (2 == $step) {
                        $details['processTime'] = isset($v['created_at']) ? $v['created_at'] : '';
                    } elseif (3 == $step) {
                        $details['finishTime'] = isset($v['created_at']) ? $v['created_at'] : '';
                    }
                }
            }
            //处理人id，工程师或主管
            $handler_id = isset($details['user_id']) ? $details['user_id'] : 0;
            $hObj = $handler_id ? $this->user->getById($handler_id) : array();
            //处理人信息
            $details['handler'] = array(
                'username' => isset($hObj['username']) ? $hObj['username'] : '',
                'phone' => isset($hObj['phone']) ? $hObj['phone'] : '',

            );

            $comment = $this->eventsComment->getOne($whereEid);
            $comment = $comment ? $comment->toArray() : array();

            $details['images'] = $imgs ? $imgs : array();
            $details['comment'] = array(
                'content' => isset($comment['content']) ? $comment['content'] : '',
                'feedback' => isset($comment['feedback']) ? $comment['feedback'] : '',
                'star_level' => isset($comment['star_level']) ? $comment['star_level'] : 0,
            );

            $whereC = array('id' => $category_id);
            $category = $this->eventoa->getCategoryOne($whereC);
            $category = $category ? $category->toArray() : array();
            $details['category_name'] = getKey($category, 'name');
            $details['object_name'] = getKey(Oa::$objectMsg, $object);
            //上报人
            $user = $report_id ? $this->user->getById($report_id) : '';
            $user = $user ? $user->toArray() : array();
            $details['report_user'] = getKey($user, 'username');

        }
        return $this->response->send($details);

    }


    /**
     * 获取工程师和工程程主管
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEngineers(){
        $result = array();
        if ($this->isManageAccess(array(1, 2, 5))) {
            $result = $this->user->getEngineersUsersTypework();
        }
        return $this->response->send($result);;
    }


    /**
     * 事件挂起或恢复
     * @param Request $request
     * @return mixed
     */
    public function suspend(Request $request){
        $input = $request->input() ? $request->input() : '';
        $input['etype'] = \ConstInc::WX_OAETYPE;
        $eventId = getkey($input,'eventId');
        $result = false;
//        var_dump($input);exit;
//        DB::beginTransaction(); //开启事务
//        try {
        $event = $this->eventoa->getById($eventId);
        $inputSuspend = intval(getkey($input,'suspend'));
        $suspend = $this->eventssuspend->addUpdate($input,$event);
//        var_dump($suspend);exit;

        if($suspend) {
            $input['suspend'] = 0;
            if($inputSuspend) {
                $input['suspend'] = $suspend;
            }
            $eventUp = $this->eventoa->updateSuspendstatus($input,$event);
        }
        if($suspend && $eventUp) {
            $result = true;
        }
        return $this->response->send($result);
    }


    /**
     * 事件挂起或恢复列表
     * @param Request $request
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function suspendList(Request $request){
        $input = $request->input() ? $request->input() : '';
        $eventId = intval(getkey($input,'eventId'));
//        $result = array();
        $where = array('event_id'=>$eventId,'etype'=>\ConstInc::WX_OAETYPE);
        if(!$eventId){
            throw new ApiException(Code::ERR_PARAMS,['事件ID不能为空']);
        }
        $result = $this->eventssuspend->getListByWhere($where);
        $result = $result ? $result->toArray() : array();
        return $this->response->send($result);
    }









}



