<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        Commands\EventsComment::class,
        Commands\EmRealtimedata::class,
        Commands\AssetsCal::class,
        Commands\Monitoralert::class,
        Commands\MonitoralertHistory::class,
        Commands\MonitorInspection::class,
        Commands\EventoaComment::class,
        Commands\EmInspection::class,
        Commands\Monitorlinks::class,
        Commands\WxBatchSendNotice::class,
        Commands\EmAlarm::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();

        //everyMinute();            每分钟运行一次任务
        //everyFiveMinutes();       每五分钟运行一次任务
        //everyTenMinutes();        每十分钟运行一次任务
        //everyFifteenMinutes();    每十五分钟运行一次任务
        //everyThirtyMinutes();     每三十分钟运行一次任务
        //hourly();                 每小时运行一次任务
        //hourlyAt(17);             每小时第十七分钟运行一次任务
        //daily();                  每天凌晨零点运行任务
        //dailyAt('13:00');         每天13:00运行任务
        //twiceDaily(1, 13);        每天1:00 & 13:00运行任务
        //weekly();                 每周运行一次任务
        //monthly();                每月运行一次任务
        //monthlyOn(4, '15:00');    每月4号15:00运行一次任务
        //quarterly();              每个季度运行一次
        //yearly();                 每年运行一次

        if(\ConstInc::MULTI_DB) {
            $connections = array_keys(\Config::get("database.connections",[]));
            foreach($connections as $conn) {
                if(strpos($conn, "opf_") === 0) {
                    $schedule->command("events:comment $conn")->everyMinute();
                    $schedule->command("realtimedata:add $conn")->hourly();
                    $schedule->command("assets:cal $conn")->daily();
                    $schedule->command("monitoralert:add $conn")->everyMinute();
                    $schedule->command("monitoralerthistory:addBatch $conn")->everyFiveMinutes();
                    $schedule->command("monitorinspectionreport:add $conn")->everyFifteenMinutes();
                    $schedule->command("eventoa:comment $conn")->everyMinute();
                    $schedule->command("monitordevice:update $conn")->everyTenMinutes();
                    $schedule->command("monitorlinks:collect $conn")->everyTenMinutes();
                    $schedule->command("eminspectionreport:add $conn")->everyFifteenMinutes();
                    $schedule->command("wxbatchsendnotice:send $conn")->everyMinute();
                    $schedule->command("emalarm:add $conn")->everyFiveMinutes();
                }
            }
        }
        else {
            $schedule->command('events:comment')->everyMinute();
            $schedule->command('realtimedata:add')->hourly();
            $schedule->command('assets:cal')->daily();
            $schedule->command('monitoralert:add')->everyMinute();
            $schedule->command('monitoralerthistory:addBatch')->everyFiveMinutes();
            $schedule->command('monitorinspectionreport:add')->everyFifteenMinutes();
            $schedule->command('eventoa:comment')->everyMinute();
            $schedule->command("monitordevice:update")->everyTenMinutes();
            $schedule->command("monitorlinks:collect")->everyTenMinutes();
            $schedule->command("eminspectionreport:add")->everyFifteenMinutes();
            $schedule->command("wxbatchsendnotice:send")->everyMinute();
            $schedule->command("emalarm:add")->everyFiveMinutes();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
