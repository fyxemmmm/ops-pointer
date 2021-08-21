<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Assets\Device;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Report\GovernmentRepository;
use Log;

class GetAllQrCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:qrcode {--number=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '导出资产二维码，--number选项后面填写要导出的数量，不填写默认全部导出';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Device $deviceModel,DeviceRepository $deviceRepository,GovernmentRepository $government)
    {
        parent::__construct();
        $this->government = $government;
        $this->model = $deviceModel;
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $opt = $this->option('number');
        $model = $this->model;
        if($opt == 0){
            $data = $model->select('id','number')->withTrashed()->get()->toArray();
        }
        else{
            $data = $model->select('id','number')->withTrashed()->limit($opt)->get()->toArray();
        }
        // 所有二维码保存的目录
        $path = './storage/app/allqr';
//        $file = array_map('basename',glob('./storage/app/public'.'*',GLOB_ONLYDIR));
        if(!file_exists($path)){
            mkdir($path);
        }
        $same_data = $data;   // 下面第一个方法用了引用，为了避免混淆
        $this->government->getQrExcel($data);
        $this->deviceRepository->getAllQrCode($same_data,$path);

        Log::info(json_encode(['导出二维码成功']));
    }
}
