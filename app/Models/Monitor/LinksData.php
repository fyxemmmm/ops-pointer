<?php
/**
 * 巡检报告
 * Created by PhpStorm.
 * User: wangwei
 * Date: 2018/6/13
 * Time: 16:20
 */

namespace App\Models\Monitor;

use Illuminate\Database\Eloquent\Model;

class LinksData extends Model
{
    protected $table = "monitor_links_data";

    protected $fillable = [
        "monitor_link_id",
        "ifOctets",
        "ifDiscards",
        "ifErrors",
        "ifUcastPkts",
        "ifNUcastPkts",
        "ifInDiscards",
        "ifInErrors",
        "ifInNUcastPkts",
        "ifInOctets",
        "ifInOctetsPercent",
        "ifInUcastPkts",
        "ifOutDiscards",
        "ifOutErrors",
        "ifOutNUcastPkts",
        "ifOutOctets",
        "ifOutOctetsPercent",
        "ifOutUcastPkts",
        "ifStatus"
    ];
}
