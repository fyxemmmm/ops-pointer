<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Assets\Device;
use App\Repositories\Assets\DeviceRepository;
use App\Repositories\Report\GovernmentRepository;
use Log;

/*
 *
php artisan get:qr 打印出所有二维码
php aritsan get:qr --number 10  打印出数据库最初的10张二维码
php artisan get:qr --begin=MLSWNEBSW00003 --end=MLSWNEBSW00005  打印区间二维码
php artisan get:qr --range MLSWNEBSW00003A,MLSWNEBSW00005A,MLSWNEBSW00008A  打印指定二维码
*
*/

class GetQrCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:qr {--range=default}{--number=0}{--begin=default}{--end=default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '导出指定的二维码信息';

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
        $begin_value = $this->option('begin');
        $end_value = $this->option('end');
        $range = $this->option('range');

        if($begin_value == 'default' && $end_value =='default' && 0 == $opt && $range == 'default'){
            // 得到所有的二维码
            $data = $this->model->select('id','number')->withTrashed()->get()->toArray();
        }
        elseif ($range !== 'default'){
            $range_data = explode(',',$range);
            $data = $this->model->select('id','number')->withTrashed()->whereIn('number',$range_data)->get()->toArray();

        }
        elseif ($opt != 0){
            // --number 得到 number 个二维码
            $data = $this->model->select('id','number')->orderBy('id','asc')->withTrashed()->limit($opt)->get()->toArray();
        }
        elseif ($begin_value !== 'default' && $end_value !== 'default'){
            // 获得区间二维码
            $begin_len = strlen($begin_value);
            $end_len = strlen($end_value);
            if($begin_len != $end_len){
                echo '您输入的编码长度有出入,请检查';
                return;
            }
            $num_info = preg_match_all('/\d+/',$begin_value);
            // 匹配标签类型
            if($num_info == 1){
                preg_match('/[a-zA-Z]$/',$begin_value,$suffix);
                $suffix = $suffix[0] ?? '';
                if($suffix != '') {
                    $data = $this->model->select('id','number')->where(function ($query) use ($begin_value,$end_value,$suffix) {
                        $query->whereBetween('number', [$begin_value, $end_value])
                              ->where('number', 'like', "%{$suffix}");
                    });
                    $data = $data->withTrashed()->get()->toArray();
                }else{
                    $data = $this->model->select('id','number')->where(function ($query) use ($begin_value,$end_value,$suffix) {
                        $query->whereBetween('number', [$begin_value, $end_value]);
                    });
                    $data = $data->withTrashed()->get()->toArray();
                    $data = $this->cutSuffix($data);
                }
                if($data == array()){
                    echo '输入的区间有误,请核对';
                    return;
                }

            }elseif ($num_info == 2){
                $data = $this->model->select('id','number')->where(function ($query) use ($begin_value,$end_value) {
                    $query->whereBetween('number', [$begin_value, $end_value]);
                });
                $data = $data->withTrashed()->get()->toArray();
                if($data == array()){
                    echo '输入的区间有误,请核对';
                    return;
                }
            }else{
                // 06-71-1-7-01-0204-0105
                $data = $this->model->select('id','number')->where(function ($query) use ($begin_value,$end_value) {
                    $query->whereBetween('number', [$begin_value, $end_value]);
                });
                $data = $data->withTrashed()->get()->toArray();
                if($data == array()){
                    echo '输入的区间有误,请核对';
                    return;
                }
            }
        }else{
            echo "您的输入可能有误,请检查";
            return;
        }


        $path = './storage/app/allqr';
        if(!file_exists($path)){
            mkdir($path);
        }
        $same_data = $data;
        $this->government->getQrExcel($data);
        $switch = false; // 是否要在二维码图片下面显示资产编号,true代表需要
        $this->deviceRepository->getAllQrCode($same_data,$path,$switch);
        Log::info('二维码导出成功');
    }

    // 去除有后缀是英文的数据
    public function cutSuffix($data){
        $list = [];
        foreach($data as $k=>$v){
            $number = $v['number'];
            $ret = preg_match('/\d+$/',$number,$m);
            if(0 == $ret){
                continue;
            }
            $list[] = $v;
        }
        return $list;

    }

}
