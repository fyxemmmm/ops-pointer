<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use DB;

class Maintain extends Model
{
    //

    protected $table = "workflow_maintain";

    protected $fillable = [
        "event_id",
        "wrong_id",
        "solution_id",
        "wrong_desc",
        "solution_desc",
    ];

    public function getByEventId($eventId) {
        return $this->where(["event_id" => $eventId])->first();
    }

    public function getWrongs() {
        return DB::table("assets_wrong")->get();
    }

    public function getSolutions() {
        return DB::table("assets_solution")->get();
    }

}
