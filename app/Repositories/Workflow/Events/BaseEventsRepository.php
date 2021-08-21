<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/3/30
 * Time: 17:56
 */

namespace App\Repositories\Workflow\Events;

use App\Models\Workflow\Category;
use App\Models\Workflow\Event;
use App\Models\Workflow\Maintain;
use App\Models\Assets\Device;
use App\Models\Workflow\Device as EventDevice;
use App\Models\Workflow\MultiDevice;
use App\Repositories\Assets\CategoryRepository;
use App\Repositories\Assets\DeviceRepository;
use App\Models\Assets\CategoryFields;
use App\Repositories\Assets\DevicePortsRepository as AssetsDevicePortsRepository;
use App\Exceptions\ApiException;
use App\Models\Code;
use App;

abstract class BaseEventsRepository {

    public $categoryRepository;
    public $deviceRepository;
    public $categoryModel;
    public $deviceModel;
    public $eventDeviceModel;
    public $eventModel;
    public $multiDeviceModel;
    public $eventsTrackRepository;

    public static function init($categoryId) {

        switch($categoryId) {
            case Category::INSTORAGE:
                return App::make("App\Repositories\Workflow\Events\InstorageEventsRepository");
            case Category::UP:
                return App::make("App\Repositories\Workflow\Events\UpEventsRepository");
            case Category::MODIFY:
                return App::make("App\Repositories\Workflow\Events\ModifyEventsRepository");
            case Category::MAINTAIN:
                return App::make("App\Repositories\Workflow\Events\MaintainEventsRepository");
            case Category::DOWN:
                return App::make("App\Repositories\Workflow\Events\DownEventsRepository");
            case Category::BREAKDOWN:
                return App::make("App\Repositories\Workflow\Events\BreakdownEventsRepository");

        }
    }

    function __construct(CategoryRepository $categoryRepository,
                         Category $categoryModel,
                         DeviceRepository $deviceRepository,
                         Device $deviceModel,
                         Event $eventModel,
                         EventDevice $eventDeviceModel,
                         MultiDevice $multiDeviceModel,
                         AssetsDevicePortsRepository $assetsDevicePortsRepository,
                         Maintain $maintainModel,
                         CategoryFields $categoryFieldsModel
    ) {
        $this->categoryModel = $categoryModel;
        $this->categoryRepository = $categoryRepository;
        $this->deviceRepository = $deviceRepository;
        $this->assetsDevicePortsRepository = $assetsDevicePortsRepository;
        $this->deviceModel = $deviceModel;
        $this->eventModel = $eventModel;
        $this->eventDeviceModel = $eventDeviceModel;
        $this->multiDeviceModel = $multiDeviceModel;
        $this->maintainModel = $maintainModel;
        $this->categoryFieldsModel = $categoryFieldsModel;
    }


    /**
     * 取资产分类信息
     * @param $subDeviceCategoryId
     * @return \Illuminate\Database\Eloquent\Model|mixed|null|object|static
     * @throws ApiException
     */
    protected function getDeviceCategory($subDeviceCategoryId) {
        $categoryInfo = $this->categoryRepository->getIdPidById($subDeviceCategoryId);
        if(empty($categoryInfo)) {
            throw new ApiException(Code::ERR_PARAMS, ["资产类别不正确"]);
        }
        return $categoryInfo;
    }

    /**
     * 根据事件类别取资产状态
     * @param $categoryId
     */
    protected function getStateByCategory($categoryId) {
        return $this->categoryModel->getStateByCategory($categoryId);
    }

    protected function getFields($subDeviceCategoryId) {
        return $this->categoryRepository->getFields($subDeviceCategoryId);
    }

    protected function getEvent($eventId) {
        return $this->eventModel->findOrFail($eventId);
    }

    protected function getDeviceName($device, &$data, $includeRoom = true) {
        $data['deviceCategoryId'] = $device->category_id;
        $data['subDeviceCategoryId'] = $device->sub_category_id;
        $data['deviceCategory'] = $device->category->name;
        $data['subDeviceCategory'] = $device->sub_category->name;
        if($includeRoom) {
            $roomType = $this->categoryFieldsModel->getRoomType($device->sub_category_id);
            if(!empty($roomType)) {
                $data['room'] = $this->deviceRepository->getErDtCategory(["type" => $roomType]);
                $data['roomType'] = $roomType;
            }
        }
    }

    /**
     * 获取对象
     * @param $subDeviceCategoryId
     * @param $device
     * @param int $eventCategoryId
     * @return array
     */
    protected function getItem($subDeviceCategoryId, $device, $eventCategoryId = 0){
        $fieldsList = $this->categoryRepository->getFieldsList($subDeviceCategoryId);
        if(0 !== $eventCategoryId) {
            $categoryRequire = $this->categoryFieldsModel->getCategoryRequire($subDeviceCategoryId, $eventCategoryId);
        }

        foreach ($fieldsList as $k => &$v) {
            foreach ($v['children'] as $kk => &$vv) {
                if(in_array($vv['sname'], $this->categoryFieldsModel->hiddenFields)) { //状态过滤
                    unset($v['children'][$kk]);
                    continue;
                }
//                if(in_array($vv['sname'],[\ConstInc::ZONE, \ConstInc::ENGINEROOM, \ConstInc::BUILDING, \ConstInc::DEPARTMENT])) {
//                    unset($vv['options']);
//                }

                if(!empty($categoryRequire) && isset($categoryRequire[$vv['sname']])) {
                    if($categoryRequire[$vv['sname']]['require'] === 0 || $categoryRequire[$vv['sname']]['require'] === 4) { //删除
                        unset($v['children'][$kk]);
                    }
                    $vv['require'] = $categoryRequire[$vv['sname']]['require'];
                }
                $tvalue = DeviceRepository::transform($vv['sname'], $device->{$vv['sname']}, $tkey);
                $sname = isset($vv['sname'])?$vv['sname']:'';
                $vv['value'] = encryptStr($sname,$device->{$sname},true);
                if(!empty($tkey)) {
                    $vv['tvalue'] = $tvalue;
                }
            }
            $v['children'] = array_values($v['children']);
            if(empty($v['children'])) { //去除没有子项的内容
                unset($fieldsList[$k]);
            }
        }

        $fieldsList = array_values($fieldsList);

        return $fieldsList;
    }

    //检查
    public function check($sub_category_id, $data) {
        $fields = $this->getFields($sub_category_id);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            $cname = $field->field_cname;
            if(array_key_exists($key,$data)) {
                $length = $field->field_length;
                switch($field->field_type) {
                    case 0: //todo 增加枚举类型
                        if(empty($field->dict_table) && empty($field->field_dict) && !empty($length)) {
                            if($data[$key] > $length) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_MAX, [$cname, $length]);
                            }
                        }
                        break;
                    case 1:
                        if(!empty($length)) {
                            if(mb_strlen($data[$key]) > $length) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_LENGTH, [$cname, $length]);
                            }
                        }
                        break;
                    case 2:
                        $dict = $field->field_dict;
                        $dict = json_decode($dict, true);
                        if(!empty($dict)) {
                            if(!isset($dict[$data[$key]])) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_DICT, [$cname]);
                            }
                        }
                        break;
                    case 3:
                        if(!empty($length)) {
                            if($data[$key] > $length) {
                                throw new ApiException(Code::ERR_EVENT_FIELD_MAX, [$cname, $length]);
                            }
                        }
                        break;
                    case 4:
                        if(!strtotime($data[$key])) {
                            throw new ApiException(Code::ERR_EVENT_FIELD_DATE, [$cname]);
                        }
                        break;
                }
            }
        }
    }




    //添加
    abstract function add($assetId);

    //处理事件
    abstract function process($event);

    //暂存
    abstract function prepareSaveDraft($data);

    //保存
    abstract function save($data);


}