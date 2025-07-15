<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramAutoOnlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $maxRandomTime;

    /**
     * 建立新的工作實例
     */
    public function __construct($maxRandomTime)
    {
        $this->maxRandomTime = $maxRandomTime;
    }

    /**
     * 執行工作
     */
    public function handle(TelegramService $telegramService)
    {
        // 檢查是否還在執行中
        if (!Cache::get('telegram_auto_online_running', false)) {
            return;
        }

        try {
            // 設定為上線狀態
            $result = $telegramService->setOnlineStatus(true);

            if ($result['success']) {
                Log::info('Telegram 自動上線成功');

                // 排程下一次上線
                $this->scheduleNext();
            } else {
                Log::error('Telegram 自動上線失敗: ' . ($result['message'] ?? '未知錯誤'));
            }
        } catch (\Exception $e) {
            Log::error('Telegram 自動上線例外: ' . $e->getMessage());
        }
    }

    /**
     * 排程下一次上線
     */
    private function scheduleNext()
    {
        if (!Cache::get('telegram_auto_online_running', false)) {
            return;
        }

        // 產生隨機延遲時間 (1 到 maxRandomTime 秒)
        $randomDelay = rand(1, $this->maxRandomTime);

        // 排程下一次執行
        TelegramAutoOnlineJob::dispatch($this->maxRandomTime)
            ->delay(now()->addSeconds($randomDelay));

        // 記錄下次執行時間
        Cache::put('telegram_next_online_at', now()->addSeconds($randomDelay));

        Log::info("下次 Telegram 自動上線時間: " . now()->addSeconds($randomDelay));
    }
}
