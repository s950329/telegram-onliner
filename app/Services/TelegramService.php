<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramService
{
    private $MadelineProto;
    private $sessionFile;

    public function __construct()
    {
        $this->sessionFile = storage_path('app/telegram_session');
    }

    /**
     * 初始化 MadelineProto
     */
    private function initMadelineProto()
    {
        if (!$this->MadelineProto) {
            $settings = new Settings;
            $settings->getAppInfo()
                ->setApiId((int) config('services.telegram.api_id', 0))
                ->setApiHash(config('services.telegram.api_hash', ''));

            $this->MadelineProto = new API($this->sessionFile, $settings);
        }
        return $this->MadelineProto;
    }

    /**
     * 開始登入流程
     */
    public function startAuth($phoneNumber)
    {
        try {
            // 如果遇到 AUTH_RESTART 錯誤，先清除 session 檔案
            if (file_exists($this->sessionFile)) {
                $fileAge = time() - filemtime($this->sessionFile);
                // 如果 session 檔案超過 1 小時，刪除它
                if ($fileAge > 3600) {
                    $this->clearSession();
                    Log::info('清除過期的 session 檔案');
                }
            }

            $this->initMadelineProto();
            $this->MadelineProto->phoneLogin($phoneNumber);
            return ['success' => true, 'message' => '驗證碼已發送'];
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            if ($e->rpc === 'AUTH_RESTART') {
                Log::info('遇到 AUTH_RESTART 錯誤，清除 session 檔案並重試');
                // 清除 session 檔案
                if (file_exists($this->sessionFile)) {
                    $this->clearSession();
                }
                // 重新初始化並嘗試登入
                try {
                    $this->MadelineProto = null; // 重置實例
                    $this->initMadelineProto();
                    if ($this->MadelineProto) {
                        $this->MadelineProto->phoneLogin($phoneNumber);
                        return ['success' => true, 'message' => '驗證碼已發送'];
                    } else {
                        return ['success' => false, 'message' => '無法初始化 MadelineProto'];
                    }
                } catch (Exception $retryE) {
                    Log::error('重試後仍然失敗: ' . $retryE->getMessage());
                    return ['success' => false, 'message' => '登入失敗，請重試: ' . $retryE->getMessage()];
                }
            } else {
                Log::error('Telegram RPC 錯誤: ' . $e->getMessage());
                return ['success' => false, 'message' => '登入失敗: ' . $e->getMessage()];
            }
        } catch (Exception $e) {
            Log::error('Telegram 登入錯誤: ' . $e->getMessage());
            return ['success' => false, 'message' => '登入失敗: ' . $e->getMessage()];
        }
    }

    /**
     * 完成登入驗證
     */
    public function completeAuth($code)
    {
        try {
            $this->initMadelineProto();
            $result = $this->MadelineProto->completePhoneLogin($code);

            Log::info('驗證碼驗證結果: ' . json_encode($result));

            // 檢查登入是否成功
            $authorization = $this->MadelineProto->getAuthorization();
            Log::info('完成驗證後的授權狀態: ' . $authorization);

            if ($authorization === 3) { // LOGGED_IN
                $userInfo = $this->MadelineProto->getSelf();
                Log::info('登入成功，用戶資訊: ' . json_encode($userInfo));

                return ['success' => true, 'message' => '登入成功'];
            } else if ($authorization === 2) { // WAITING_PASSWORD
                return ['success' => false, 'message' => '需要兩步驟驗證密碼', 'need_password' => true];
            } else {
                Log::error('驗證失敗，授權狀態: ' . $authorization);
                return ['success' => false, 'message' => '驗證失敗，請重試'];
            }

        } catch (\danog\MadelineProto\RPCErrorException $e) {
            if ($e->rpc === 'AUTH_RESTART') {
                Log::info('遇到 AUTH_RESTART 錯誤，需要重新開始登入流程');
                // 清除 session 檔案
                if (file_exists($this->sessionFile)) {
                    $this->clearSession();
                }
                return ['success' => false, 'message' => '驗證過程需要重新開始，請重新輸入手機號碼', 'restart_auth' => true];
            } else {
                Log::error('Telegram RPC 錯誤: ' . $e->getMessage());
                return ['success' => false, 'message' => '驗證失敗: ' . $e->getMessage()];
            }
        } catch (Exception $e) {
            Log::error('Telegram 驗證錯誤: ' . $e->getMessage());
            return ['success' => false, 'message' => '驗證失敗: ' . $e->getMessage()];
        }
    }

    /**
     * 完成兩步驟驗證
     */
    public function complete2FA($password)
    {
        try {
            $this->initMadelineProto();
            $result = $this->MadelineProto->complete2faLogin($password);

            Log::info('兩步驟驗證結果: ' . json_encode($result));

            // 檢查登入是否成功
            $authorization = $this->MadelineProto->getAuthorization();
            Log::info('完成兩步驟驗證後的授權狀態: ' . $authorization);

            if ($authorization === 3) { // LOGGED_IN
                $userInfo = $this->MadelineProto->getSelf();
                Log::info('兩步驟驗證成功，用戶資訊: ' . json_encode($userInfo));

                return ['success' => true, 'message' => '登入成功'];
            } else {
                Log::error('兩步驟驗證失敗，授權狀態: ' . $authorization);
                return ['success' => false, 'message' => '密碼錯誤，請重試'];
            }

        } catch (\danog\MadelineProto\RPCErrorException $e) {
            if ($e->rpc === 'AUTH_RESTART') {
                Log::info('兩步驟驗證時遇到 AUTH_RESTART 錯誤，需要重新開始登入流程');
                // 清除 session 檔案
                if (file_exists($this->sessionFile)) {
                    $this->clearSession();
                }
                return ['success' => false, 'message' => '驗證過程需要重新開始，請重新輸入手機號碼', 'restart_auth' => true];
            } else {
                Log::error('Telegram RPC 錯誤: ' . $e->getMessage());
                return ['success' => false, 'message' => '驗證失敗: ' . $e->getMessage()];
            }
        } catch (Exception $e) {
            Log::error('兩步驟驗證錯誤: ' . $e->getMessage());
            return ['success' => false, 'message' => '驗證失敗: ' . $e->getMessage()];
        }
    }

    /**
     * 檢查是否已登入
     */
    public function isLoggedIn()
    {
        try {
            if (!file_exists($this->sessionFile)) {
                Log::info('Session 檔案不存在: ' . $this->sessionFile);
                return false;
            }

            $this->initMadelineProto();

            // 檢查授權狀態
            $authorization = $this->MadelineProto->getAuthorization();
            Log::info('授權狀態: ' . $authorization);

            // MadelineProto 的授權狀態常數：
            // NOT_LOGGED_IN = 0
            // WAITING_CODE = 1
            // WAITING_PASSWORD = 2
            // LOGGED_IN = 3

            if ($authorization === 3) { // LOGGED_IN
                // 嘗試獲取用戶資訊
                $me = $this->MadelineProto->getSelf();
                $isLoggedIn = !empty($me) && isset($me['id']);

                Log::info('登入檢查結果: ' . ($isLoggedIn ? '已登入' : '未登入'));
                if ($isLoggedIn) {
                    Log::info('用戶資訊: ' . json_encode($me));
                }

                return $isLoggedIn;
            } else {
                Log::info('用戶未完全登入，授權狀態: ' . $authorization);
                return false;
            }

        } catch (\danog\MadelineProto\Exception $e) {
            Log::error('MadelineProto 錯誤: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            Log::error('檢查登入狀態時發生錯誤: ' . $e->getMessage());
            $this->clearSession();
            return false;
        }
    }

    /**
     * 獲取用戶資訊
     */
    public function getUserInfo()
    {
        try {
            $this->initMadelineProto();
            return $this->MadelineProto->getSelf();
        } catch (Exception $e) {
            Log::error('獲取用戶資訊錯誤: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 設定上線狀態
     */
    public function setOnlineStatus($online = true)
    {
        try {
            $this->initMadelineProto();

            if ($online) {
                // 設定為上線 - 使用 account.updateStatus 方法，參數名稱為 offline
                $result = $this->MadelineProto->account->updateStatus(['offline' => false]);
                Log::info('設定為上線狀態');
            } else {
                // 設定為離線
                $result = $this->MadelineProto->account->updateStatus(['offline' => true]);
                Log::info('設定為離線狀態');
            }

            Log::info('設定上線狀態結果: ' . json_encode($result));

            return ['success' => true, 'status' => $online ? 'online' : 'offline'];
        } catch (Exception $e) {
            Log::error('設定上線狀態錯誤: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 登出
     */
    public function logout()
    {
        try {
            $this->clearSession();
            return ['success' => true, 'message' => '登出成功'];
        } catch (Exception $e) {
            Log::error('登出錯誤: ' . $e->getMessage());
            return ['success' => false, 'message' => '登出失敗'];
        }
    }

    /**
     * 清除 session 檔案
     */
    private function clearSession()
    {
        try {
            if (file_exists($this->sessionFile)) {
                // 如果是目錄，則遞歸刪除整個目錄
                if (is_dir($this->sessionFile)) {
                    $this->deleteDirectory($this->sessionFile);
                } else {
                    // 如果是檔案，直接刪除
                    unlink($this->sessionFile);
                }
                Log::info('已清除 session 檔案: ' . $this->sessionFile);
            }
            $this->MadelineProto = null;
        } catch (Exception $e) {
            Log::error('清除 session 時發生錯誤: ' . $e->getMessage());
        }
    }

    /**
     * 遞歸刪除目錄及其內容
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
