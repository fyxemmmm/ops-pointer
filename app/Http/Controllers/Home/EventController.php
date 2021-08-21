<?php
/**
 * 事件
 */

namespace App\Http\Controllers\Home;
//namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\Weixin\CommonRepository;
//use App\Repositories\Weixin\EventsRepository;
use App\Repositories\Weixin\EventsPicRepository;
use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Weixin\EventsTrackRepository;
use App\Repositories\Weixin\WeixinUserRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Assets\DevicePortsRepository;
use App\Models\Code;
use App\Models\Workflow\Category;
use App\Models\Workflow\Event;
use App\Exceptions\ApiException;
use App\Support\Response;
use App\Http\Requests\Assets\DeviceRequest;
use Illuminate\Support\Facades\Redirect;
use phpDocumentor\Reflection\Location;
use Auth;
use App\Repositories\Auth\UserRepository;
use App\Http\Requests\Workflow\EventsRequest;
use DB;
use App\Repositories\Workflow\Events\BaseEventsRepository;
use Log;
use App\Repositories\Workflow\EventsSuspendRepository;
use App\Repositories\Weixin\QyWxUserRepository;
use App\Repositories\Weixin\BatchSendNoticeRepository;
use App\Models\Workflow\Maintain;

class EventController extends Controller
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

    function __construct(CommonRepository $common,
                         EventsRepository $events,
                         EventsPicRepository $eventsPic,
                         DeviceRepository $device,
                         EventsCommentRepository $eventsComment,
                         Category $categoryModel,
                         EventsTrackRepository $eventsTrack,
                         WeixinUserRepository $weixinuser,
                         UserRepository $user,
                         DevicePortsRepository $devicePorts,
                         EventsSuspendRepository $eventssuspend,
                         QyWxUserRepository $qywxuser,
                         BatchSendNoticeRepository $batchSendNotice,
                         Maintain $maintainModel,
                         Event $eventModel
    )
    {
        $this->eventModel = $eventModel;
        $this->common = $common;
        $this->events = $events;
        $this->eventsPic = $eventsPic;
        $this->eventsComment = $eventsComment;
        $this->categoryModel = $categoryModel;
        $this->eventsTrack = $eventsTrack;
        $this->weixinuser = $weixinuser;
        $this->user = $user;
        $this->devicePorts = $devicePorts;
        $this->eventssuspend = $eventssuspend;
        $this->maintainModel = $maintainModel;
        $this->response = new Response();
        $this->device = $device;

        $scheme = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
        $this->hostName = $scheme.$_SERVER['HTTP_HOST'];
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
     * 用户（用户主管）上报事件
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function addAct(Request $request)
    {
        $input = $request->post() ? $request->post() : array();
        $processUserId = isset($input['processUserId']) ? $input['processUserId'] : '';
        $description = isset($input['description']) ? trim($input['description']) : '';
        $wxUser = array();

//        if (!$description) {
//            throw new ApiException(Code::ERR_PARAMS, ["事件描述不能为空"]);
//        } else {

            $sessuInfo = Auth::user();
            $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
            $input['uid'] = $sessuid;
            $res = $this->events->addByReport($input);
            $res = $res->toArray();
            $resEventID = isset($res['id']) ? $res['id'] : 0;
//            $handler_id = isset($res['user_id']) ? $res['user_id'] : 0;
            $result['result'] = $resEventID;

            if ($resEventID) {
                $picRes = uploadPics($request, 'event_pic', '/storage/app/home/event/' . $resEventID . '/', '/home/event/');
                $status = isset($picRes['status']) ? $picRes['status'] : '';
                $picPaths = isset($picRes['data']) ? $picRes['data'] : '';
                $picResErrMsg = isset($picRes['msg']) ? $picRes['msg'] : '';
                $picResErrCode = isset($picRes['errCode']) ? $picRes['errCode'] : '';
                if ($picResErrCode) {
                    throw new ApiException($picResErrCode);
                } elseif ($picPaths) {
                    $this->eventsPic->addBatch($picPaths, $resEventID);
                }
                $flag = true;
                //用户主管
                if ($processUserId) {
                    if (5 == $identity_id) {
                    $event = $this->events->getById($resEventID);

                        $accept = $this->events->wxAccept($request,$resEventID);
                        $handler_id = isset($accept['user_id']) ? $accept['user_id'] : 0;
//                        var_dump($accept);
                        if ($accept) {
                            $wxNotice = array(
                                'title' => '事件处理已更新，点击查看',
                                'desc' => isset($event['description']) ? $event['description'] : '',
                                'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $resEventID,
                                'eventID' => $resEventID,
                                'state' => isset(Event::$stateMsg[$accept['state']]) ? Event::$stateMsg[$accept['state']] : '',
                                'dtype' => 1,
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
                                    $whereWx = array('userid' => $handler_id);
                                    $wxUser = $this->weixinuser->getOne($whereWx);
                                    $wxNoticeH = $wxNotice;
                                    $wxNoticeH['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
//                                var_dump($wxNoticeH);
                                    $this->common->sendWXNotice($wxNoticeH);
                                }
                                //获取事件处理人的openid，通知处理人（工程师，本人接单不通知）
                                $flag = false;
                            }
                            //事件跟踪
                            $input['eventId'] = $resEventID;
                            $input['step'] = 1;
                            $this->eventsTrack->add($input);
                        }else{
                            Log::info('更新接单或派发出错，事件ID:'.$resEventID);
                            throw new ApiException(Code::ERR_PARAM_EMPTY);
                        }
                    }else{
                        throw new ApiException(Code::ERR_EVENT_ASSIGN);
                    }
                }
                if($flag) {
                    $this->sendWxNoticeBatch($res);
                }

            }


            return $this->response->send($result);
//        }
    }

    /**
     * 事件列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getList(Request $request)
    {
//        $request->offsetSet('source', 3);
        $data = $this->events->wxGetList($request, 'updated_at');

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
        $where[] = ["source", "=", 3];
        $where2 = null;
        if (!$eventId) {
            throw new ApiException(Code::ERR_PARAM_EMPTY);
        }
        if ($eventId) {
            $details = array();
            $event = $this->events->getById($eventId);
//            var_dump($event->asset->number);exit;

//            var_dump($details);exit;

            $where2[] = ["event_id", "=", $eventId];
            $where2[] = ["etype", "=", \ConstInc::WX_ETYPE];
            $imgs = $this->eventsPic->getList($where2);
//            var_dump($result,$imgs);exit;
            if ($event) {
                $details['id'] = $event->id;
                $details['asset_id'] = $event->asset_id;
                $details['assetNumber'] = is_null($event->asset) ? '' : $event->asset->number;
                $details['remark'] = $event->remark;
                $details['created_at'] = $event->created_at->format('Y-m-d H:i:s');
                $details['state'] = $event->state;
                $details['category_id'] = $event->category_id;
                $details['category'] = is_null($event->category) ? '' : $event->category->name;
                $details['user_id'] = $event->user_id;
                $details['user'] = is_null($event->user) ? '' : $event->user->username;
                $details['assigner_id'] = $event->assigner_id;
                $details['assigner'] = is_null($event->assigner) ? '' : $event->assigner->username;
                $details['report_id'] = $event->report_id;
                $details['report_name'] = $event->report_name;
                $details['mobile'] = $event->mobile;
                $details['description'] = $event->description;
                $details['source'] = $event->source;
                $details['report_at'] = $event->report_at;
//            $details = $event ? $event->toArray() : array();
                $details['images'] = $imgs ? $imgs : array();
            }
            $categoryId = $event->category_id;
            if($categoryId) {
                $moreData = BaseEventsRepository::init($categoryId)->process($event);
                $details = array_merge($details, $moreData);
            }
            $data['details'] = $details;

            // start 3/4  $this->eventModel
            $maintain_info = $this->eventModel->select('assets_solution.name as so_name','assets_wrong.name as wrong_name')->leftjoin('workflow_maintain','workflow_events.id','workflow_maintain.event_id')->leftjoin('assets_solution','workflow_maintain.solution_id','assets_solution.id')->leftjoin('assets_wrong','workflow_maintain.wrong_id','assets_wrong.id')->where('workflow_events.id',$eventId)->first()->toArray();
            if($maintain_info){
                $data['details']['info']['wrongname'] = $maintain_info['wrong_name'];
                $data['details']['info']['solutionname'] = $maintain_info['so_name'];
            }
            // end

            
            $track = $this->eventsTrack->getList($where2);
            $track = $track->toArray();
//            var_dump($track);exit;
            $data['track'] = isset($track['data']) ? $track['data'] : '';//$track;//
        }

        return $this->response->send($data);
    }


    /**
     * 资产详情
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function getAssetsDetails(DeviceRequest $request)
    {
        $data = array();

        $assetId = intval($request->input("assetId"));
        if (!$assetId) {
            $number = $request->input("number"); //兼容支持资产编号
            $device = $this->device->all(["number" => $number])->first();
            if(!empty($device)) {
                $assetId = $device->id;
            }
            else {
                throw new ApiException(Code::ERR_PARAMS, ["资产不存在！"]);
            }
        }
        $this->events->wxSelectCategory($assetId);
        if ($assetId) {
            //检查资产是否有事件进行中
            if($this->events->checkAssetInEvent($assetId)){
                throw new ApiException(Code::ERR_ASSETS_IN_EVENT);
            }
            $data = $this->device->getItem($assetId);

        }
        return $this->response->send($data);
    }


    public function comment()
    {

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
        $eventID = isset($input['event_id']) ? $input['event_id'] : 0;
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
            $where = null;
            $where[] = ["event_id", "=", $eventID];
            $where[] = ["etype", "=", \ConstInc::WX_ETYPE];
            $res = $this->eventsComment->getOne($where);
            if ($res) {
                throw new ApiException(Code::ERR_COMMENTED);
            }
            $event = $this->events->getById($eventID);
            $userid = isset($event['user_id']) ? $event['user_id'] : 0;
            $eventState = isset($event['state']) ? $event['state'] : 0;
            if (3 != $eventState) {
                throw new ApiException(Code::ERR_UNFINISHED);
            }
//            var_dump($input);exit;
            if ($input && !$res) {
                $comment = $this->eventsComment->add($input);
                $data['result'] = $comment;
                if ($comment) {
                    $model = $this->events->getById($eventID);
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
     * 资产搜索
     * @param DeviceRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssetsSearch(DeviceRequest $request)
    {
        $search = $request->input("s");
        $data = $this->device->search($search);
        return $this->response->send($data);
    }


    /**
     * 获取事件分类
     * @return mixed
     */
    public function _getCategoryList()
    {
        $result = $this->categoryModel->get();
        return $this->response->send($result);
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
        $eventID = isset($input['eventId']) ? $input['eventId'] : 0;
        $assetId = isset($input['assetId']) ? intval($input['assetId']) : 0;
        $step = isset($input['step']) ? intval($input['step']) : 0;
        if (!$eventID) {
            throw new ApiException(Code::ERR_PARAM_EMPTY);
        }
        if ($eventID) {
            //显示用户提交事件
//            $where = array("id"=>$eventID);
//            $eventRes = $this->events->getOne($where);
//            $eventRes = $eventRes ? $eventRes->toArray() : array();
            $event = $this->events->getById($eventID);
            if (!$event) {
                throw new ApiException(Code::ERR_MODEL);
            }
            $eventRes = $this->events->wxProcess($event);
            $whereEP = array("event_id" => $eventID,'etype'=>\ConstInc::WX_ETYPE);
            $imgs = $this->eventsPic->getList($whereEP);
//            var_dump($result,$imgs);exit;
            if ($eventRes) {
                $eventRes['images'] = $imgs ? $imgs : array();
            }
            $result['event'] = $eventRes;


        } else {
            $result['asset'] = '';
            //扫资产二维码
            if ($assetId) {
                $result['asset'] = $this->device->getItem($assetId);
            }
        }
        if ($this->isManageAccess(array(1, 2))) {
            $result['processers'] = $this->user->getEngineers();
        }


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
            $assetId = isset($input["assetId"]) ? $input["assetId"] : 0;
            $categoryId = isset($input["categoryId"]) ? $input["categoryId"] : 0;
            $eventId = isset($input["eventId"]) ? $input["eventId"] : 0;
            if (!$assetId) {
                $number = $request->input("number"); //兼容支持资产编号
                $device = $this->device->all(["number" => $number])->first();
                if (!empty($device)) {
                    $assetId = $device->id;
                    $input["assetId"] = $assetId;
                    $request->offsetSet("assetId", $assetId);
                }
            }
            if(in_array($step,array(1,2,3))) {
                if (!$this->isManageAccess(array(1, 2, 3, 5))) {
                    throw new ApiException(Code::ERR_EVENT_PERM);
                }
            }
            //更新事件、事件相关操作，3/4中二选一
            if (!$eventId) {
                throw new ApiException(Code::ERR_PARAM_EMPTY);
            }
            $event = $this->events->getById($eventId);
            $input['assetId'] = isset($event['asset_id']) ? $event['asset_id'] : 0;
            if (!$event) {
                throw new ApiException(Code::ERR_MODEL);
            }
            //$step
            $whereET = array('event_id' => $eventId, 'step' => $step,'etype'=>\ConstInc::WX_ETYPE);
            $etRes = $this->eventsTrack->getOne($whereET);
            if ($etRes) {
                throw new ApiException(Code::ERR_REPEAT_SUBMIT);
            }
            $sessuInfo = Auth::user();
            $sessuid = isset($sessuInfo['id']) ? $sessuInfo['id'] : 0;
            $identity_id = isset($sessuInfo['identity_id']) ? $sessuInfo['identity_id'] : '';
            $eUserID = isset($event['user_id']) ? $event['user_id'] : '';
            $assigner_id = isset($event['assigner_id']) ? $event['assigner_id'] : 0;
            if (in_array($step, array(2, 3))) {
                if ($sessuid != $eUserID) {
                    throw new ApiException(Code::ERR_OPERATION_ACCESS_NOT);//ERR_KB_PERM
                }
            }
//            if (4 == $step) {
//                if ($sessuid != $assigner_id) {
//                    throw new ApiException(Code::ERR_CLOSE_ACCESS_NOT);
//                }
//            }
            $opFlag = false;
            switch ($step) {
                case "1" ://已接单
                    $eventId = $request->input("eventId");
                    $event = $this->events->getById($eventId);
                    $eventState = isset($event['state']) ? $event['state'] : '';
                    if(!$eventState || $eventState == 0) {
                        $accept = $this->events->wxAccept($request,$eventId);
                        $handler_id = isset($accept['user_id']) ? $accept['user_id'] : 0;
                        if ($accept) {
                            $wxNotice = array(
                                'title' => '事件处理已更新，点击查看',
                                'desc' => isset($event['description']) ? $event['description'] : '',
                                'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                                'eventID' => $eventId,
                                'state' => isset(Event::$stateMsg[$accept['state']]) ? Event::$stateMsg[$accept['state']] : '',
                                'dtype' => 1
                            );
                            if ($handler_id && $sessuid && $sessuid != $handler_id) {
                                //获取事件处理人的openid，通知处理人（工程师，本人接单不通知）
                                if(2 == \ConstInc::WX_PUBLIC){
                                    //企业微信通知消息
                                    $whereWx = array('user_id' => $handler_id);
                                    $wxUser = $this->qywxuser->getOne($whereWx);
                                    $wxNoticeH = $wxNotice;
                                    $wxNoticeH['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                    $this->common->qySendTextcard($wxNoticeH);
                                }else {
                                    //微信公众号通知消息
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
                                if(2 == \ConstInc::WX_PUBLIC) {
                                    //企业微信通知消息
                                    $whereWx = array('user_id' => $reportid);
                                    $wxUser = $this->qywxuser->getOne($whereWx);
                                    $wxNoticeU = $wxNotice;
                                    $wxNoticeU['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                    $wxNoticeU['title'] = '事件处理已更新，点击查看';
                                    $this->common->qySendTextcard($wxNoticeU);
                                }else {
                                    //微信公众号通知消息
                                    $whereWx = array('userid' => $reportid);
                                    $wxUser = $this->weixinuser->getOne($whereWx);
                                    $wxNoticeU = $wxNotice;
                                    $wxNoticeU['openid'] = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                                    $wxNoticeU['title'] = '事件处理已更新，点击查看';
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
                    if (!$assetId || !$categoryId) {
                        throw new ApiException(Code::ERR_PARAMS, ["非法请求，缺少参数"]);
                    }
                    $opFlag = $this->events->wxSelectCategorySave($request);
                    break;
                case "3"://已完成
                    $suspend = $this->eventssuspend->getUsetimeByEventId($eventId);
                    $event['suspend_usetime'] = isset($suspend['usetime']) ? $suspend['usetime'] : '';
                    $finish = $this->events->wxSave($request, $event);
                    if ($finish) {
                        $handler_id = isset($event['user_id']) ? $event['user_id'] : 0;
                        $assigner_id = isset($event['assigner_id']) ? $event['assigner_id'] : 0;
                        $wxNotice = array(
                            'title' => '事件处理已更新，点击查看',
                            'desc' => isset($finish['remark']) ? $finish['remark'] : '',
                            'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                            'eventID' => $eventId,
                            'state' => isset(Event::$stateMsg[$finish['state']]) ? Event::$stateMsg[$finish['state']] : '',
                            'dtype' => 1
                        );

                        //处理人是当前登录用户，并且处理人不等于分派人，说明是主管分派的
                        if ($handler_id != $assigner_id && $handler_id && $handler_id == $sessuid) {
                            if(2 == \ConstInc::WX_PUBLIC){
                                //企业微信通知消息
                                $whereWx = array('user_id' => $assigner_id);
                                $wxUser = $this->qywxuser->getOne($whereWx);
                                $wxNotice = $wxNotice;
                                $wxNotice['touser'] = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                                $this->common->qySendTextcard($wxNotice);
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
                    $close = $this->events->close($request);
                    if ($close) {
                        $remark = $request->input("remark");
                        $handler_id = isset($event['user_id']) ? $event['user_id'] : 0;
                        $assigner_id = isset($event['assigner_id']) ? $event['assigner_id'] : 0;
                        $wxNotice = array(
                            'title' => '事件处理已更新，点击查看',
                            'desc' => $remark ? $remark : '',
                            'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventId,
                            'eventID' => $eventId,
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
                default:
                    break;
            }
        }
        if ($opFlag) {
            $this->eventsTrack->add($input);
            $result['result'] = true;
        }

        return $this->response->send($result);
    }


    /**
     * 事件指派
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignAct(Request $request)
    {
        $sessUInfo = session('userInfo');
        $identity_id = isset($sessUInfo['identity_id']) ? $sessUInfo['identity_id'] : 0;//1;//
        $user_id = isset($sessUInfo['id']) ? $sessUInfo['id'] : 0;//1;//
        $result = array();
        $input = $request->input() ? $request->input() : '';
        $eventid = isset($input['id']) ? $input['id'] : 0;
//        var_dump($input);exit;
        if (!$eventid) {
            throw new ApiException(Code::ERR_PARAMS, ["非法请求，未找到该事件"]);
        } elseif (!in_array($identity_id, array(User::USER_ADMIN, User::USER_MANAGER))) {
            throw new ApiException(Code::ERR_PARAMS, ["无权限操作"]);
        } elseif ($input && $eventid) {
            $id = isset($input['id']) ? $input['id'] : '';
            $where = array('id' => $id);
            $data = $this->events->getOne($where);
            $resHandlerID = isset($data['user_id']) ? $data['user_id'] : 0;
            if (!$data) {
                throw new ApiException(Code::ERR_PARAMS, ["非法请求，未找到该事件"]);
            }
            if ($resHandlerID) {
                throw new ApiException(Code::ERR_PARAMS, ["事件已指派或已认领"]);
            }
            $handler_id = isset($input['user_id']) ? $input['user_id'] : '';
            $param = array('user_id' => $handler_id, 'assigner_id' => $user_id);
            $res = $this->events->update($id, $param);
            $result['result'] = $res ? $id : 0;

        }
        return $this->response->send($result);
    }


    /**
     * 根据资产选择可操作事件类型
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectCategoryByAsset(Request $request)
    {
        $input = $request->input() ? $request->input() : '';
        $assetId = isset($input['assetId']) ? $input['assetId'] : 0;
        if (!$assetId) {
            $number = $request->input("number"); //兼容支持资产编号
            $device = $this->device->all(["number" => $number])->first();
            if(!empty($device)) {
                $assetId = $device->id;
            } else {
                throw new ApiException(Code::ERR_PARAMS, ["资产不存在！"]);
            }
        }
        $result = $this->events->wxSelectCategory($assetId);
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
        $eventID = isset($event['id']) ? $event['id'] : '';
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
//        var_dump(json_encode($uids));//exit;
        $uids = array_filter(array_unique($uids));
        $uids = $this->events->checkAssetOperationAccess($event,$uids,$onlyEManager);
//        var_dump(json_encode($uids));exit;
        Log::info($eventID.'_asset_operation_access:'.json_encode($uids));
        if(2 == \ConstInc::WX_PUBLIC){

            $description = isset($event['description']) ? $event['description'] : '';
            //企业微信通知消息
            $wxUsers = $this->qywxuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $useridArr = array();
//        var_dump($wxUsers);exit;
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $userid = isset($wxUser['userid']) ? $wxUser['userid'] : '';
                    $useridArr[] = $userid;
                }
                $wxNotice = array(
                    'touser' => $useridArr,
                    'title' => '用户提交了一起新事件，点击查看',
                    'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventID,
                    'desc' => $msg ? $msg : $description,
                    'eventID' => $eventID,
                    'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : '',
                    'dtype' => 1
                );
                $this->common->qySendTextcard($wxNotice);
                Log::info($eventID . '_send_qywx_notice_batch:' . json_encode($useridArr));
            }
        }else {
            //微信公众号通知消息
            $wxUsers = $this->weixinuser->getListByuid($uids);
            $wxUsers = $wxUsers ? $wxUsers->toArray() : array();
            $openidArr = array();
//        var_dump($wxUsers);exit;
            if ($wxUsers && is_array($wxUsers)) {
                foreach ($wxUsers as $wxUser) {
                    $openid = isset($wxUser['openid']) ? $wxUser['openid'] : '';
                    $openidArr[] = $openid;
                    /*$description = isset($event['description']) ? $event['description'] : '';
                    $wxNotice = array(
                        'openid' => $openid,
                        'title' => '用户提交了一起新事件，点击查看',
                        'url' => $this->hostName . '/home/user/tracknoticeshow?eventId=' . $eventID,
                        'desc' => $msg ? $msg : $description,
                        'eventID' => $eventID,
                        'state' => isset(Event::$stateMsg[$event['state']]) ? Event::$stateMsg[$event['state']] : ''
                    );
                    $this->common->sendWXNotice($wxNotice);*/
                }
                if($openidArr) {
                    $description = isset($event['description']) ? $event['description'] : '';
                    $wxNotice = array(
                        'openids' => implode(',', $openidArr),
                        'title' => '用户提交了一起新事件，点击查看',
                        'url' => '/home/user/tracknoticeshow?eventId=' . $eventID,
                        'description' => $msg ? $msg : $description,
                        'eventId' => $eventID,
                        'state' => isset($event['state']) ? $event['state'] : '',
                    );
                    //保存推送微信通知消息数据入库（定时任务发送消息）
                    $this->batchSendNotice->add($wxNotice);
                }
//                Log::info($eventID . '_send_wx_notice_batch:' . json_encode($openidArr));
            }
        }
        return true;
    }


    /**
     * 根据资产id获取一条数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ApiException
     */
    public function getDevice(Request $request)
    {
        $assetId = $request->input('assetId');
        if (!$assetId) {
            throw new ApiException(Code::ERR_EVENT_FIELD_REQUIRE, ['assetId']);
        }
        $asset = $this->device->getById($assetId);
//            $asset = $asset->toArray();
        $result = array(
            'id' => $asset->id,
            'number' => $asset->number,
        );

        return $this->response->send($result);
    }


    /**
     * 显示工程师或主管自建事件
     * @param EventsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function engineerAddShow(EventsRequest $request)
    {
        $assetId = intval($request->input("assetId"));
        if (!$assetId) {
            $number = $request->input("number"); //兼容支持资产编号
            $device = $this->device->all(["number" => $number])->first();
            if(!empty($device)) {
                $assetId = $device->id;
            }
            else {
                throw new ApiException(Code::ERR_PARAMS, ["资产不存在！"]);
            }
        }

        $data = $this->events->getAdd($assetId,true);
        return $this->response->send($data);
    }

    /**
     * 保存工程师或主管自建事件
     * @param EventsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function engineerAdd(EventsRequest $request)
    {
        $assetId = $request->input("assetId");
        $categoryId = $request->input("categoryId");
        $typeId = $request->input("typeId");
        $userId = $request->input("userId");
        $description = $request->input("description");
        $input = $request->input();
        $id = $this->events->wxAdd($input);
        $data = ["eventId" => $id];
        return $this->response->send($data);
    }


    public function report(Request $request)
    {
        $input = $request->input() ? $request->input() : '';
        $eventId = isset($input["eventId"]) ? $input["eventId"] : 0;
        if (!$eventId) {
            throw new ApiException(Code::ERR_PARAM_EMPTY);
        }
        $whereEid[] = ["event_id", "=", $eventId];
        $whereEid[] = ["etype", "=", \ConstInc::WX_ETYPE];
        if ($eventId) {
            if (!is_null($eventId)) {
                $where[] = ["id", "=", $eventId];
            }
            $events = $this->events->getOne($where);
            $details = $events ? $events->toArray() : array();
            $state = isset($details['state']) ? $details['state'] : 0;
            $assetId = isset($details['asset_id']) ? $details['asset_id'] : 0;
            $source = isset($details['source']) ? $details['source'] : '';
            $created_at = isset($details['created_at']) ? $details['created_at'] : '';
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
            $handler = array();
            $handler_id = isset($details['user_id']) ? $details['user_id'] : 0;
            if ($handler_id) {
                $whereU[] = ["id", "=", $handler_id];
                $hObj = $this->user->getOne($whereU);
                $handler = array(
                    'username' => isset($hObj['username']) ? $hObj['username'] : '',
                    'phone' => isset($hObj['phone']) ? $hObj['phone'] : '',

                );
            }

            $comment = $this->eventsComment->getOne($whereEid);
            $comment = $comment ? $comment->toArray() : array();


            $device = $this->device->getById($assetId);

            //处理人信息
            $details['handler'] = $handler ? $handler : array();
            $details['images'] = $imgs ? $imgs : array();
            $details['comment'] = array(
                'content' => isset($comment['content']) ? $comment['content'] : '',
                'feedback' => isset($comment['feedback']) ? $comment['feedback'] : '',
                'star_level' => isset($comment['star_level']) ? $comment['star_level'] : 0,
            );
            $details['asset_number'] = isset($device->number) ? $device->number : '';

            $categoryId = $events->category_id;

            $moreData = BaseEventsRepository::init($categoryId)->process($events);
            $details = array_merge($details,$moreData);

        }
        return $this->response->send($details);

    }


    /**
     * 获取工程师和工程程主管
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEngineers(Request $request){
        $result = array();
        $assetId = $request->input('assetId');
        if ($this->isManageAccess(array(1, 2, 5))) {
            if($assetId) {
                $device = $this->device->getById($assetId);
                $result = $this->user->getEngineersByCategory([$device->sub_category_id]);
            }else{
                $result = $this->user->getEngineers();
            }
        }


        return $this->response->send($result);;
    }

    /**
     * 取机柜详情
     * @param DeviceRequest $request
     * @return mixed
     * @throws \App\Exceptions\ApiException
     */
    public function rackInfo(DeviceRequest $request) {
        $data = $this->device->getRackInfo($request->input("assetId"));
        return $this->response->send($data);
    }


    /**
     * 获取type下的点位列表信息
     * @param DevicePortRequest $request
     * @return mixed
     */
    public function ports(EventsRequest $request)
    {
        $result = $this->devicePorts->get($request->input());
        return $this->response->send(["result" => $result]);
    }


    /**
     * 点位
     * @param DevicePortRequest $request
     * @return mixed
     * @throws \Exception
     */
    public function portConnect(EventsRequest $request) {
        $this->devicePorts->connect($request->input());
        return $this->response->send();
    }


    public function testAutoComment()
    {
        $result = '';
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
        return $this->response->send();
    }


    /**
     * 事件挂起或恢复
     * @param Request $request
     * @return mixed
     */
    public function suspend(Request $request){
        $input = $request->input() ? $request->input() : '';
        $input['etype'] = \ConstInc::WX_ETYPE;
        $eventId = getkey($input,'eventId');
        $result = false;
//        DB::beginTransaction(); //开启事务
//        try {
            $event = $this->events->getById($eventId);
            $inputSuspend = intval(getkey($input,'suspend'));
            $suspend = $this->eventssuspend->addUpdate($input,$event);

            if($suspend) {
                $input['suspend'] = 0;
                if($inputSuspend) {
                    $input['suspend'] = $suspend;
                }
                $eventUp = $this->events->updateSuspendstatus($input,$event);
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
        $where = array('event_id'=>$eventId,'etype'=>\ConstInc::WX_ETYPE);
        if(!$eventId){
            throw new ApiException(Code::ERR_PARAMS,['事件ID不能为空']);
        }
        $result = $this->eventssuspend->getListByWhere($where);
        $result = $result ? $result->toArray() : array();
        return $this->response->send($result);
    }








}



