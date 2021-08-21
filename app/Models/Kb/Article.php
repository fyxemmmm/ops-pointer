<?php
/**
 * Created by PhpStorm.
 * User: yanxiang
 * Date: 2018/4/11
 * Time: 15:55
 */

namespace App\Models\Kb;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    //
    use SoftDeletes;

    protected $table = "kb_articles";

    const STATUS_PREPARE = 0;
    const STATUS_APPROVE = 1;
    const STATUS_DENY = 2;

    protected $guarded = [
        "id",
        "created_at",
        "updated_at",
        "deleted_at",
    ];

    public function user() {
        return $this->hasOne("App\Models\Auth\User", "id", "user_id")->withTrashed()->withDefault();
    }

    public function approver() {
        return $this->hasOne("App\Models\Auth\User", "id", "approver_id")->withTrashed()->withDefault();
    }

}
