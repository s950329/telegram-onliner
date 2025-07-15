@extends('layouts.app')

@section('title', 'Telegram 自動上線設定')

@section('content')
<div class="card">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <i class="fab fa-telegram-plane fa-3x text-white mb-3"></i>
            <h2 class="text-white">自動上線設定</h2>
            @if(isset($userInfo))
                <p class="text-white-50">
                    歡迎，{{ $userInfo['first_name'] ?? '' }} {{ $userInfo['last_name'] ?? '' }}
                    @if(isset($userInfo['username']))
                        (@{{ $userInfo['username'] }})
                    @endif
                </p>
            @endif
        </div>

        <!-- 目前狀態顯示 -->
        <div class="status-card text-white mb-4" id="statusDisplay">
            <h5><i class="fas fa-info-circle"></i> 目前狀態</h5>
            <div id="statusContent">
                <p class="mb-1">自動上線：<span id="runningStatus" class="badge bg-secondary">載入中...</span></p>
                <div id="nextOnlineInfo" style="display: none;">
                    <p class="mb-1">下次上線時間：<span id="nextOnlineTime">-</span></p>
                    <p class="mb-0">倒數計時：<span id="countdown" class="countdown">-</span> 秒</p>
                </div>
            </div>
        </div>

        <!-- 自動上線設定表單 -->
        <form id="autoOnlineForm">
            <div class="mb-4">
                <label for="randomTime" class="form-label text-white">
                    <i class="fas fa-clock"></i> 隨機上線時間範圍（秒）
                </label>
                <input type="number"
                       class="form-control"
                       id="randomTime"
                       name="random_time"
                       min="1"
                       max="3600"
                       value="60"
                       required>
                <div class="form-text text-white-50">
                    系統將在 1 到設定秒數之間隨機選擇時間進行上線動作
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <button type="submit" class="btn btn-success w-100" id="startBtn">
                        <i class="fas fa-play"></i>
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        開始自動上線
                    </button>
                </div>
                <div class="col-md-6">
                    <button type="button" class="btn btn-danger w-100" id="pauseBtn">
                        <i class="fas fa-pause"></i>
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        暫停自動上線
                    </button>
                </div>
            </div>
        </form>

        <!-- 操作按鈕 -->
        <div class="row g-3">
            <div class="col-md-6">
                <button type="button" class="btn btn-outline-light w-100" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> 重新整理狀態
                </button>
            </div>
            <div class="col-md-6">
                <form method="POST" action="{{ route('telegram.logout') }}" class="d-inline w-100">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100"
                            onclick="return confirm('確定要登出嗎？')">
                        <i class="fas fa-sign-out-alt"></i> 登出
                    </button>
                </form>
            </div>
        </div>

        <!-- 說明資訊 -->
        <div class="mt-4 p-3" style="background: rgba(255, 255, 255, 0.05); border-radius: 10px;">
            <h6 class="text-white mb-2"><i class="fas fa-question-circle"></i> 使用說明</h6>
            <ul class="text-white-50 mb-0" style="font-size: 0.9em;">
                <li>設定隨機上線時間範圍（1-3600 秒）</li>
                <li>點擊「開始自動上線」後，系統會在指定時間範圍內隨機選擇時間執行上線動作</li>
                <li>上線動作會持續執行，直到您點擊「暫停自動上線」</li>
                <li>可以隨時查看下次上線的倒數計時</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    let statusInterval;
    let countdownInterval;

    // 初始載入狀態
    updateStatus();

    // 每 5 秒更新一次狀態
    statusInterval = setInterval(updateStatus, 5000);

    // 開始自動上線
    $('#autoOnlineForm').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $('#startBtn');
        const $spinner = $btn.find('.spinner-border');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        $.post('{{ route("telegram.start") }}', {
            random_time: $('#randomTime').val()
        })
        .done(function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                updateStatus();
            } else {
                showAlert(response.message, 'error');
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON;
            if (response && response.errors) {
                const errors = Object.values(response.errors).flat();
                showAlert(errors.join('<br>'), 'error');
            } else {
                showAlert('啟動自動上線失敗', 'error');
            }
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    });

    // 暫停自動上線
    $('#pauseBtn').on('click', function() {
        const $btn = $(this);
        const $spinner = $btn.find('.spinner-border');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        $.post('{{ route("telegram.pause") }}')
        .done(function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                updateStatus();
            } else {
                showAlert(response.message, 'error');
            }
        })
        .fail(function() {
            showAlert('暫停自動上線失敗', 'error');
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    });

    // 重新整理狀態
    $('#refreshBtn').on('click', function() {
        const $btn = $(this);
        const $icon = $btn.find('i');

        $icon.addClass('fa-spin');
        updateStatus();

        setTimeout(() => {
            $icon.removeClass('fa-spin');
        }, 1000);
    });

    // 更新狀態
    function updateStatus() {
        $.get('{{ route("telegram.status") }}')
        .done(function(response) {
            const isRunning = response.running;
            const nextOnlineAt = response.next_online_at;
            const countdown = response.countdown;

            // 更新執行狀態
            const $runningStatus = $('#runningStatus');
            if (isRunning) {
                $runningStatus.removeClass('bg-secondary bg-danger').addClass('bg-success').text('執行中');
                $('#startBtn').prop('disabled', true);
                $('#pauseBtn').prop('disabled', false);
                $('#randomTime').prop('disabled', true);
            } else {
                $runningStatus.removeClass('bg-success bg-secondary').addClass('bg-danger').text('已停止');
                $('#startBtn').prop('disabled', false);
                $('#pauseBtn').prop('disabled', true);
                $('#randomTime').prop('disabled', false);
            }

            // 更新下次上線資訊
            if (isRunning && nextOnlineAt && countdown > 0) {
                $('#nextOnlineTime').text(nextOnlineAt);
                $('#nextOnlineInfo').show();
                startCountdown(countdown);
            } else {
                $('#nextOnlineInfo').hide();
                stopCountdown();
            }
        })
        .fail(function() {
            $('#runningStatus').removeClass('bg-success bg-danger').addClass('bg-secondary').text('無法取得狀態');
        });
    }

    // 開始倒數計時
    function startCountdown(seconds) {
        stopCountdown(); // 停止之前的計時器

        let remaining = Math.max(0, seconds);
        updateCountdownDisplay(remaining);

        if (remaining > 0) {
            countdownInterval = setInterval(() => {
                remaining--;
                updateCountdownDisplay(remaining);

                if (remaining <= 0) {
                    stopCountdown();
                    updateStatus(); // 重新取得狀態
                }
            }, 1000);
        }
    }

    // 停止倒數計時
    function stopCountdown() {
        if (countdownInterval) {
            clearInterval(countdownInterval);
            countdownInterval = null;
        }
    }

    // 更新倒數顯示
    function updateCountdownDisplay(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        const display = minutes > 0 ? `${minutes}:${secs.toString().padStart(2, '0')}` : seconds.toString();
        $('#countdown').text(display);
    }

    // 清理定時器
    $(window).on('beforeunload', function() {
        if (statusInterval) clearInterval(statusInterval);
        if (countdownInterval) clearInterval(countdownInterval);
    });
});
</script>
@endsection
