<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Multidevice extends Model
{
    //
    use SoftDeletes;

    protected $table = "workflow_multidevice";

    protected $guarded = [
        "id",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    /**
     * 根据资产编号获取
     * @param $number
     * @return mixed
     */
    public function getByNumber($number, $where = null) {
        if (is_array($number)) {
            $obj = $this->whereIn("number" , $number);
        }
        else {
            $obj = $this->where(["number" => $number]);
        }
        if(!empty($where)) {
            $obj = $obj->where($where);
        }
        return $obj->get();
    }

    public function getByEventId($eventId) {
        return $this->where(["event_id" => $eventId])->first();
    }

    public function category() {
        return $this->hasOne("App\Models\Assets\Category", "id", "category_id")->withDefault();
    }

    public function sub_category() {
        return $this->hasOne("App\Models\Assets\Category", "id", "sub_category_id")->withDefault();
    }

}
