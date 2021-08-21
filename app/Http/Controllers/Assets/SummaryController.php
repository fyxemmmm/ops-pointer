<?php

namespace App\Http\Controllers\Assets;

use App\Repositories\Assets\DeviceRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Assets\DeviceRequest;
use App\Http\Controllers\Controller;

class SummaryController extends Controller
{

    protected $device;

    function __construct(DeviceRepository $device) {
        $this->device = $device;
    }

    public function getStatistics(DeviceRequest $request) {
        $engineroomId = $request->input("engineroomId");
        $data = $this->device->summary($engineroomId);
        return $this->response->send($data);
    }

}
