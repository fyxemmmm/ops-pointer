<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/2/2
 * Time: 10:49
 */


namespace App\Http\Controllers\Workflow;

use App\Repositories\Workflow\EventsRepository;
use App\Repositories\Assets\DevicePortsRepository;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\EventsRequest;
use App\Repositories\Weixin\EventsTrackRepository;
use App\Models\Workflow\Event;
use App\Repositories\Workflow\EventsSuspendRepository;
use App\Exceptions\ApiException;
use App\Models\Code;

class EventsController extends Controller
{

    protected $events;
    protected $devicePorts;
    protected $eventssuspend;

    function __construct(EventsRepository $events,
                         DevicePortsRepository $devicePorts,
                         EventsTrackRepository $eventsTrack,
                         EventsSuspendRepository $eventssuspend
    ) {
        $this->events = $events;
        $this->devicePorts = $devicePorts;
        $this->eventsTrack = $eventsTrack;
        $this->eventssuspend = $eventssuspend;
    }

    public function getCategories(Request $request) {
        $multi = $request->input("batch", 0);
        $ret = $this->events->getCategories($multi);
        return $this->response->send($ret);
    }

    public function getAdd(EventsRequest $request) {
        $assetId = $request->input("assetId");
        $data = $this->events->getAdd($assetId);
        return $this->response->send($data);
    }

    public function postAdd(EventsRequest $request) {
        $assetId = $request->input("assetId");
        $categoryId = $request->input("categoryId");
        $typeId = $request->input("typeId");
        $userId = $request->input("userId");
        $input = $request->input();
        $id = $this->events->add($input);
        $data = ["eventId" => $id];
        return $this->response->send($data);
    }

    /**
     * 处理事件
     * @param EventsRequest $request
     * @return mixed
     */
    public function getProcess(EventsRequest $request) {
        $data = $this->events->process($request->input("eventId"), $request->input("multi",0));
        return $this->response->send($data);
    }


    /**
     * 根据资产类别确定字段
     * @param EventsRequest $request
     */
    public function getFields(EventsRequest $request) {
        $data = $this->events->getFields($request->input("deviceCategoryId"));
        return $this->response->send($data);
    }


    /**
     * 暂存
     * @param EventsRequest $request
     * @return mixed
     */
    public function postSaveDraft(EventsRequest $request) {
        $eventId = $request->input("eventId");
        $suspend = $this->eventssuspend->getUsetimeByEventId($eventId);
        $event = $this->events->getById($eventId);
        $event['suspend_usetime'] = isset($suspend['usetime']) ? $suspend['usetime'] : '';
        $this->events->saveDraft($request->input(),$event);
        return $this->response->send();
    }


    /**
     * 保存
     * @param EventsRequest $request
     * @return mixed
     */
    public function postSave(EventsRequest $request) {
        $eventId = $request->input("eventId");
        $suspend = $this->eventssuspend->getUsetimeByEventId($eventId);
        $event = $this->events->getById($eventId);
        $event['suspend_usetime'] = isset($suspend['usetime']) ? $suspend['usetime'] : '';
        $this->events->save($request->input(),$event);
        if($event->category_id == 4) {
            $trackParam = [
                "eventId" => $eventId,
                "assetId" => $event->asset_id,
                "step" => Event::STATE_END
            ];
            $this->eventsTrack->add($trackParam);
        }
        return $this->response->send();
    }

    public function getList(EventsRequest $request) {
        //$request->offsetSet('source', 3);
        $data = $this->events->getList($request);
        return $this->response->send($data);
    }

    public function getMultiList(EventsRequest $request) {
        //$request->offsetSet('source', 3);
        $data = $this->events->getMultiList($request);

        return $this->response->send($data);
    }

    public function getMultiProcess(EventsRequest $request) {
        $data = $this->events->multiProcess($request->input("eventId"));
        return $this->response->send($data);
    }

    /**
     * 关闭事件
     * @param EventsRequest $request
     */
    public function postClose(EventsRequest $request) {
        $this->events->close($request->input());
        $eventId = $request->input("eventId");
        $event = $this->events->getById($eventId);
        if($event->category_id == 4) {
            $trackParam = [
                "eventId" => $eventId,
                "assetId" => $event->asset_id,
                "step" => Event::STATE_CLOSE
            ];
            $this->eventsTrack->add($trackParam);
        }
        return $this->response->send();
    }


    /**
     * 获取type下的点位信息
     * @param DevicePortRequest $request
     * @return mixed
     */
    public function getPorts(EventsRequest $request)
    {
        $result = $this->devicePorts->get($request->input());
        return $this->response->send(["result" => $result]);
    }

    /**
     * @param DevicePortRequest $request
     * @return mixed
     * @throws \Exception
     */
    public function postPortConnect(EventsRequest $request) {
        $ip = trim($request->input("ip"));
        if(!empty($ip)) {
            $this->validate($request, [
                'ip' => 'ip',
            ],
                [
                    "ip" => "IP地址不合法",
                ]);
        }
        $this->devicePorts->connect($request->input());
        return $this->response->send();
    }

    /**
     * @param DevicePortRequest $request
     * @return mixed
     */
    public function postPortDisconnect(EventsRequest $request) {
        $this->devicePorts->disconnect($request->input());
        return $this->response->send();
    }


    /**
     * 事件来源
     * @return mixed
     */
    public function getSourceList(){
        $result = $this->events->getSource();
        return $this->response->send($result);
    }


    /**
     * 事件状态
     * @return mixed
     */
    public function getStateList(){
        $result = $this->events->getState();
        return $this->response->send($result);
    }



    /**
     * 事件挂起或恢复
     * @param Request $request
     * @return mixed
     */
    public function postSuspend(Request $request){
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
    public function getSuspendList(Request $request){
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





