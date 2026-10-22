<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Laravel\Sanctum\PersonalAccessToken;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // sanctum:prune-expired ignores idle tokens (no sanctum.expiration), so prune them with the same rule as AuthServiceProvider.
        $schedule->call(fn () => PersonalAccessToken::whereRaw('COALESCE(last_used_at, created_at) < ?', [now()->subMinutes(config('session.lifetime'))])->delete())
            ->daily()
            ->name('prune-idle-tokens');
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
