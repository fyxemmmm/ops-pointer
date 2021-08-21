<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/2
 * Time: 10:49
 */


namespace App\Http\Controllers\Workflow;

use App\Models\Workflow\Oa;
use App\Repositories\Workflow\OaRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\OaRequest;
use App\Repositories\Workflow\EventsSuspendRepository;
use App\Repositories\Weixin\EventsCommentRepository;
use App\Repositories\Weixin\EventsPicRepository;
use App\Models\Code;
use App\Exceptions\ApiException;



class OaController extends Controller
{

    protected $oaRepository;
    protected $eventssuspend;
    protected $eventsComment;
    protected $eventsPic;

    function __construct(OaRepository $oaRepository,
                         EventsSuspendRepository $eventssuspend,
                         EventsCommentRepository $eventsComment,
                         EventsPicRepository $eventsPic) {
        $this->oaRepository = $oaRepository;
        $this->eventssuspend = $eventssuspend;
        $this->eventsComment = $eventsComment;
        $this->eventsPic = $eventsPic;
    }

    public function getAssign(OaRequest $request) {
        $data = $this->oaRepository->getAssign($request);
        return $this->response->send($data);
    }

    public function getAdd(OaRequest $request) {
        $oaId = $request->input("id");
        $data = $this->oaRepository->getAdd($request);
        $event = isset($data['info']) ? $data['info'] : array();

        $event['images'] = array();
        if ($event) {
            $whereEP = array("event_id" => $oaId,'etype'=>\ConstInc::WX_OAETYPE);
            $imgs = $this->eventsPic->getList($whereEP);
            $event['images'] = $imgs ? $imgs : array();
        }
        return $this->response->send($data);
    }

    public function postAssign(OaRequest $request) {
        $this->oaRepository->assign($request);
        return $this->response->send();
    }

    public function postAdd(OaRequest $request) {
        $oaId = $request->input("id");
        $suspend = $this->eventssuspend->getUsetimeByEventId($oaId,\ConstInc::WX_OAETYPE);
        $event['suspend_usetime'] = isset($suspend['usetime']) ? $suspend['usetime'] : '';
        $this->oaRepository->add($request,$event);
        return $this->response->send();
    }

    /**
     * 处理事件
     * @param EventsRequest $request
     * @return mixed
     */
    public function getView(OaRequest $request) {
        $oaId = $request->input("id");
        $event = $this->oaRepository->get($oaId);
        $data = $event ? $event->toArray() : array();
        if($data) {
            $data['user'] = $event->user->username;
            $data['assigner'] = $event->user->username;
            $data['category'] = $event->category->name;
            $data['source_name'] = getKey(Oa::$sourceMsg,$event->source,'');
            $data['object_name'] = getKey(Oa::$objectMsg,$event->object,'');
            
            $data['comment'] = array();
            $state = isset($data['state']) ? $data['state'] : 0;
            if (Oa::STATE_END == $state) {
                $whereEid[] = ["event_id", "=", $oaId];
                $whereEid[] = ["etype", "=", \ConstInc::WX_OAETYPE];
                $comment = $this->eventsComment->getOne($whereEid);
                $comment = $comment ? $comment->toArray() : array();
                $data['comment'] = array(
                    'content' => isset($comment['content']) ? $comment['content'] : '',
                    'feedback' => isset($comment['feedback']) ? $comment['feedback'] : '',
                    'star_level' => isset($comment['star_level']) ? $comment['star_level'] : 0,
                );
            }


            $data['images'] = array();
            if ($event) {
                $whereEP = array("event_id" => $oaId,'etype'=>\ConstInc::WX_OAETYPE);
                $imgs = $this->eventsPic->getList($whereEP);
                $data['images'] = $imgs ? $imgs : array();
            }
        }else{
            Code::setCode(Code::ERR_MODEL);
        }

        return $this->response->send($data);
    }


    public function getList(OaRequest $request) {
        $data = $this->oaRepository->getList($request);
        return $this->response->send($data);
    }

    /**
     * 关闭事件
     * @param EventsRequest $request
     */
    public function postClose(OaRequest $request) {
        $this->oaRepository->close($request->input());
        return $this->response->send();
    }

    public function getMeta() {
        $data = $this->oaRepository->getMeta();
        return $this->response->send($data);
    }



    /**
     * 事件挂起或恢复
     * @param Request $request
     * @return mixed
     */
    public function postSuspend(Request $request){
        $input = $request->input() ? $request->input() : '';
        $input['etype'] = \ConstInc::WX_OAETYPE;
        $eventId = getkey($input,'eventId');
        $result = false;
//        var_dump($input);exit;
//        DB::beginTransaction(); //开启事务
//        try {
        $event = $this->oaRepository->getById($eventId);
        $inputSuspend = intval(getkey($input,'suspend'));
        $suspend = $this->eventssuspend->addUpdate($input,$event);
//        var_dump($suspend);exit;

        if($suspend) {
            $input['suspend'] = 0;
            if($inputSuspend) {
                $input['suspend'] = $suspend;
            }
            $eventUp = $this->oaRepository->updateSuspendstatus($input,$event);
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
    public function getSuspendList(Request $request){
        $input = $request->input() ? $request->input() : array();
        $eventId = intval(getKey($input,'eventId',0));
//        $result = array();
        $where = array('event_id'=>$eventId,'etype'=>\ConstInc::WX_OAETYPE);
        if(!$eventId){
            throw new ApiException(Code::ERR_PARAMS,['事件ID不能为空']);
        }
        $result = $this->eventssuspend->getListByWhere($where);
        $result = $result ? $result->toArray() : array();
        return $this->response->send($result);
    }


    /**
     * 事件来源
     * @return mixed
     */
    public function getSourceList(){
        $result = $this->oaRepository->getSource();
        return $this->response->send($result);
    }


    /**
     * 事件状态
     * @return mixed
     */
    public function getStateList(){
        $result = $this->oaRepository->getState();
        return $this->response->send($result);
    }


    /**
     * 事件对象
     * @return mixed
     */
    public function getObjectList(){
        $result = $this->oaRepository->getObject();
        return $this->response->send($result);
    }

}





