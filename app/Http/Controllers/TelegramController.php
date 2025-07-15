<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use App\Jobs\TelegramAutoOnlineJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    private $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * 顯示首頁
     */
    public function index()
    {
        // 檢查是否已登入
        if ($this->telegramService->isLoggedIn()) {
            return redirect()->route('telegram.settings');
        }

        return view('telegram.login');
    }

    /**
     * 處理登入請求
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10'
        ]);

        $result = $this->telegramService->startAuth($request->phone);

        if ($result['success']) {
            session(['telegram_phone' => $request->phone]);
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'need_code' => true
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ]);
    }

    /**
     * 處理驗證碼
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:5'
        ]);

        $result = $this->telegramService->completeAuth($request->code);

        if ($result['success']) {
            session()->forget('telegram_phone');
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'redirect' => route('telegram.settings')
            ]);
        } else if (isset($result['need_password']) && $result['need_password']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'need_password' => true
            ]);
        } else if (isset($result['restart_auth']) && $result['restart_auth']) {
            session()->forget('telegram_phone');
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'restart_auth' => true
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ]);
    }

    /**
     * 處理兩步驟驗證
     */
    public function verify2FA(Request $request)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $result = $this->telegramService->complete2FA($request->password);

        if ($result['success']) {
            session()->forget('telegram_phone');
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'redirect' => route('telegram.settings')
            ]);
        } else if (isset($result['restart_auth']) && $result['restart_auth']) {
            session()->forget('telegram_phone');
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'restart_auth' => true
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ]);
    }

    /**
     * 顯示設定頁面
     */
    public function settings()
    {
        Log::info('進入設定頁面，檢查登入狀態...');

        $isLoggedIn = $this->telegramService->isLoggedIn();
        Log::info('登入狀態檢查結果: ' . ($isLoggedIn ? '已登入' : '未登入'));

        if (!$isLoggedIn) {
            Log::info('用戶未登入，重定向到首頁');
            return redirect()->route('telegram.index')->with('message', '請先登入 Telegram');
        }

        $userInfo = $this->telegramService->getUserInfo();
        $isRunning = Cache::get('telegram_auto_online_running', false);

        Log::info('顯示設定頁面，用戶資訊: ' . json_encode($userInfo));

        return view('telegram.settings', compact('userInfo', 'isRunning'));
    }

    /**
     * 開始自動上線
     */
    public function startAutoOnline(Request $request)
    {
        $request->validate([
            'random_time' => 'required|integer|min:1|max:3600'
        ]);

        if (!$this->telegramService->isLoggedIn()) {
            return response()->json([
                'success' => false,
                'message' => '請先登入 Telegram'
            ]);
        }

        // 設定自動上線參數
        Cache::put('telegram_auto_online_running', true);
        Cache::put('telegram_auto_online_time', $request->random_time);

        // 啟動背景任務
        TelegramAutoOnlineJob::dispatch($request->random_time);

        return response()->json([
            'success' => true,
            'message' => '自動上線已開始'
        ]);
    }

    /**
     * 暫停自動上線
     */
    public function pauseAutoOnline()
    {
        Cache::forget('telegram_auto_online_running');
        Cache::forget('telegram_auto_online_time');

        return response()->json([
            'success' => true,
            'message' => '自動上線已暫停'
        ]);
    }

    /**
     * 登出
     */
    public function logout()
    {
        // 停止自動上線
        Cache::forget('telegram_auto_online_running');
        Cache::forget('telegram_auto_online_time');

        $result = $this->telegramService->logout();

        return redirect()->route('telegram.index')->with('message', $result['message']);
    }

    /**
     * 取得目前狀態 (AJAX)
     */
    public function getStatus()
    {
        $isRunning = Cache::get('telegram_auto_online_running', false);
        $nextOnlineAt = Cache::get('telegram_next_online_at');

        return response()->json([
            'running' => $isRunning,
            'next_online_at' => $nextOnlineAt ? $nextOnlineAt->format('Y-m-d H:i:s') : null,
            'countdown' => $nextOnlineAt ? max(0, $nextOnlineAt->diffInSeconds(now())) : null
        ]);
    }
}
