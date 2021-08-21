<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

class Solution extends Model
{

    protected $table = "assets_solution";

    protected $fillable = [
        "wrong_id",
        "name",
        "remark",
    ];

    public function wrong() {
        return $this->hasOne("App\Models\Assets\Wrong","id", "wrong_id");
    }


    /**
     * 列表数据
     * @param array $where
     * @param int $number
     * @param string $sort
     * @param string $sortColumn
     * @return mixed
     */
    public function getListByWhere($where=array()) {
        $result = array();
        if($where) {
            $result = $this->where($where)->get();
        }
        return $result;
    }
}
