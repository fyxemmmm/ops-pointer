<?php

namespace App\Models\Assets;

use Illuminate\Database\Eloquent\Model;

class RackPos extends Model
{

    protected $table = "assets_rack_pos";

    protected $fillable = [
        "rack_asset_id",
        "asset_id",
        "pos_start",
        "pos_end",
    ];

    public function checkPosition($rackId, $posStart, $posEnd) {
        $where = ["rack_asset_id" => $rackId];
        $cnt = $this->where($where)->where("pos_start","<=", $posEnd)->where("pos_end", ">=", $posStart)->count();
        return $cnt === 0;
    }

}
