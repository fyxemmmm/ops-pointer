<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\Report\ExportConfigRepository;
use Log;

class OutPutConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outputconfig';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '导出配置命令';

    protected $exportConfigRepository;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ExportConfigRepository $exportConfigRepository)
    {
        parent::__construct();

        $this->exportConfigRepository = $exportConfigRepository;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 备份配置文件和相应 sql 文件
        if ($this->confirm('Are you sure backup data? [y|N]')) {
            $data = $this->exportConfigRepository->getSqlBackup();
            $headers = ['notice', 'msg'];
            $arr = [];
            $newArr = [];
            foreach ($data as $k => $v){
                $arr['notice'] = $k;
                $arr['msg'] = $v;
                $newArr[] = $arr;
            }
            $this->table($headers, $newArr);
            Log::info($newArr);
        }

    }
}
