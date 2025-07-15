<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Validator;

// Telegram 路由
Route::get('/', [TelegramController::class, 'index'])->name('telegram.index');
Route::post('/login', [TelegramController::class, 'login'])->name('telegram.login');
Route::post('/verify-code', [TelegramController::class, 'verifyCode'])->name('telegram.verify');
Route::post('/verify-2fa', [TelegramController::class, 'verify2FA'])->name('telegram.verify2fa');
Route::get('/settings', [TelegramController::class, 'settings'])->name('telegram.settings');
Route::post('/start-auto-online', [TelegramController::class, 'startAutoOnline'])->name('telegram.start');
Route::post('/pause-auto-online', [TelegramController::class, 'pauseAutoOnline'])->name('telegram.pause');
Route::post('/logout', [TelegramController::class, 'logout'])->name('telegram.logout');
Route::get('/status', [TelegramController::class, 'getStatus'])->name('telegram.status');

// 除錯路由
Route::get('/debug', function () {
    $telegramService = app(App\Services\TelegramService::class);

    $sessionFile = storage_path('app/telegram_session');
    $sessionExists = file_exists($sessionFile);

    try {
        $isLoggedIn = $telegramService->isLoggedIn();
        $userInfo = $isLoggedIn ? $telegramService->getUserInfo() : null;

        return response()->json([
            'session_file_exists' => $sessionExists,
            'session_file_path' => $sessionFile,
            'is_logged_in' => $isLoggedIn,
            'user_info' => $userInfo,
            'telegram_api_id' => config('services.telegram.api_id'),
            'telegram_api_hash' => config('services.telegram.api_hash'),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'session_file_exists' => $sessionExists,
            'session_file_path' => $sessionFile,
        ]);
    }
});

// 測試上線狀態路由
Route::get('/test-online', function () {
    $telegramService = app(App\Services\TelegramService::class);

    try {
        $result = $telegramService->setOnlineStatus(true);
        return response()->json([
            'test' => 'set_online_status',
            'result' => $result,
            'timestamp' => now()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
});

Route::get('test', function() {
    try {
    $result = Validator::make(request()->all(), [
        'test' => 'required|numeric'
    ])->validate();
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }
});
