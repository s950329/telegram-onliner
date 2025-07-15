<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessTelegramQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:queue {--timeout=60}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '處理 Telegram 相關的佇列工作';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = $this->option('timeout');

        $this->info('開始處理 Telegram 佇列...');
        $this->info("超時設定: {$timeout} 秒");

        // 執行佇列工作
        $this->call('queue:work', [
            '--queue' => 'default',
            '--timeout' => $timeout,
            '--tries' => 3,
            '--backoff' => 3
        ]);
    }
}
