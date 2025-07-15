#!/bin/bash

# Telegram 桌面客戶端啟動腳本

echo "=== Telegram 桌面客戶端 ==="
echo "正在啟動應用程式..."

# 檢查 .env 設定
if [ ! -f .env ]; then
    echo "錯誤：找不到 .env 檔案"
    exit 1
fi

# 檢查 Telegram API 設定
if ! grep -q "TELEGRAM_API_ID=" .env || ! grep -q "TELEGRAM_API_HASH=" .env; then
    echo "⚠️  請先設定 Telegram API 資訊："
    echo "   1. 前往 https://my.telegram.org"
    echo "   2. 登入您的帳號並建立新的應用程式"
    echo "   3. 將 API ID 和 API Hash 填入 .env 檔案"
    echo "   4. 修改 .env 中的以下設定："
    echo "      TELEGRAM_API_ID=你的API_ID"
    echo "      TELEGRAM_API_HASH=你的API_HASH"
    echo ""
fi

# 確保儲存目錄存在
mkdir -p storage/app

# 啟動佇列工作處理程序（背景執行）
echo "啟動佇列工作處理程序..."
php artisan queue:work --timeout=300 &
QUEUE_PID=$!

# 啟動 NativePHP 應用程式
echo "啟動桌面應用程式..."
php artisan native:serve

# 當應用程式關閉時，也關閉佇列處理程序
echo "正在關閉應用程式..."
kill $QUEUE_PID 2>/dev/null
echo "應用程式已關閉"
