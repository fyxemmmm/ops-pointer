<?php
/**
 * @description 删除入库事件
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/3/30
 * Time: 17:48
 */

namespace App\Repositories\Workflow\Events;

use App\Models\Workflow\Category;
use App\Exceptions\ApiException;
use App\Models\Code;
use DB;

class InstorageEventsRepository extends BaseEventsRepository
{

    const EVT_CATEGORY = Category::INSTORAGE;

    /**
     * 根据资产分类ID取number
     * @param $deviceCategoryId
     * @return mixed
     */
    protected function genNumber($subDeviceCategoryId) {
        $numberInfo = $this->deviceRepository->genNumber($subDeviceCategoryId);
        return $numberInfo['number'];
    }

    /**
     * @param $number
     * @throws ApiException
     */
    protected function checkNumber($number) {
        $ret = $this->deviceModel->getByNumber($number);
        if(!$ret->isEmpty()) {
            $numbers = $ret->pluck("number")->all();
            throw new ApiException(Code::ERR_NUMBER_EXISTS, $numbers);
        }
    }

    public function add($assetId) {

    }

    public function process($event) {
        $eventId = $event->id;
        if(empty($event->event_id)) {
            //批量事件
            $device = $this->eventDeviceModel->getByEventId($eventId);
        }
        else {
            $device = $this->multiDeviceModel->getByEventId($eventId);
        }

        $categories = $this->categoryRepository->getList();
        $data['deviceCategories'] = $categories['result'];
        if(!empty($device)) { //无记录，先提供资产分类数据
            $item = $this->getItem($device->sub_category_id, $device, self::EVT_CATEGORY);
            $this->getDeviceName($device, $data);
            $data['lastUpdateTime'] = $device->updated_at->format('Y-m-d H:i:s');
            $data['info'] = $item;
        }
        return $data;
    }

    public function prepareSaveDraft($data) {
        //入库需要指定资产类别
        if(!isset($data['subDeviceCategoryId'])) {
            throw new ApiException(Code::ERR_EVENT_DEVICECATE);
        }

        //获取资产分类
        $categoryInfo = $this->getDeviceCategory($data['subDeviceCategoryId']);

        //取该类别字段
        $fields = $this->getFields($categoryInfo->id);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            if(isset($data[$key])) {
                $insert[$key] = encryptStr($key,$data[$key]);
            }
        }

        $insert['sub_category_id'] = $categoryInfo->id;
        $insert['category_id'] = $categoryInfo->pid;
        $insert["updated_at"] = date("Y-m-d H:i:s");
        return $insert;
    }

    public function save($data) {
        if(!isset($data['subDeviceCategoryId'])) {
            throw new ApiException(Code::ERR_EVENT_DEVICECATE);
        }

        //检查number
        if(isset($data['number']) && !empty($data['number'])) {
            $this->checkNumber($data['number']);
        }

        //获取资产分类
        $categoryInfo = $this->getDeviceCategory($data['subDeviceCategoryId']);

        //取该类别字段
        $fields = $this->getFields($categoryInfo->id);

        //根据字段入到表中
        foreach($fields as $field){
            $key = $field->field_sname;
            if(isset($data[$key])) {
                $insert[$key] = encryptStr($key,$data[$key]);
            }
        }

        $this->check($data['subDeviceCategoryId'], $insert);

        if(!isset($insert['number'])) {
            $insert['number'] = $this->genNumber($data['subDeviceCategoryId']);
        }

        //检查该类别的内容
        $categoryRequire = $this->categoryRepository->getCategoryRequire($data['subDeviceCategoryId'], self::EVT_CATEGORY);
        foreach($categoryRequire as $sname => $v) {
            if($v['require'] === 1 && $insert[$sname] === "") {
                throw new ApiException(Code::ERR_EVENT_FIELD_REQUIRE, [$v['cname']]);
            }
        }

        $insert['intime'] = date("Y-m-d H:i:s"); //入库时间
        $insert['state'] = $this->getStateByCategory(self::EVT_CATEGORY);
        $insert['sub_category_id'] = $categoryInfo->id;
        $insert['category_id'] = $categoryInfo->pid;
        $insert["created_at"] = date("Y-m-d H:i:s");
        $insert["updated_at"] = date("Y-m-d H:i:s");

        $id = $this->deviceModel->insertGetId($insert);

        //更新事件表的id
        $model = $this->eventModel->findOrFail($data['eventId']);
        $model->fill(["asset_id" => $id])->save();
    }


}