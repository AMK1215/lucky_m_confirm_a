<?php

namespace App\Console;

use App\Jobs\SyncWalletBalanceToDatabase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\PullReport::class,
        Commands\ArchiveOldReports::class,
        Commands\ArchiveOldTransactions::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('make:pull-report')->everyFiveSeconds();
        //$schedule->command('archive:old-reports')->dailyAt('15:10');  // Runs every day at 2 AM
        //$schedule->command('archive:old-transactions')->dailyAt('02:00');

        //$schedule->job(new SyncWalletBalanceToDatabase)->everyFiveSeconds()->sendOutputTo(storage_path('logs/sync_wallet.log')); // Save output to custom log;  // or adjust as needed
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