@extends('layouts.app')

@section('title', 'Telegram 登入')

@section('content')
<div class="card">
    <div class="card-body p-5">
        <div class="text-center mb-4">
            <i class="fab fa-telegram-plane fa-4x text-white mb-3"></i>
            <h2 class="text-white">Telegram 桌面客戶端</h2>
            <p class="text-white-50">請輸入您的手機號碼以開始登入</p>
        </div>

        @if(session('message'))
            <div class="alert alert-info">{{ session('message') }}</div>
        @endif

        <!-- 手機號碼輸入表單 -->
        <form id="phoneForm" style="{{ session('telegram_phone') ? 'display: none;' : '' }}">
            <div class="mb-3">
                <label for="phone" class="form-label text-white">手機號碼</label>
                <input type="tel"
                       class="form-control"
                       id="phone"
                       name="phone"
                       placeholder="+886 912 345 678"
                       value="{{ session('telegram_phone', '') }}"
                       required>
                <div class="form-text text-white-50">請輸入完整的國際格式手機號碼</div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    發送驗證碼
                </button>
            </div>
        </form>

        <!-- 驗證碼輸入表單 -->
        <form id="codeForm" style="{{ session('telegram_phone') ? '' : 'display: none;' }}">
            <div class="mb-3">
                <label for="code" class="form-label text-white">驗證碼</label>
                <input type="text"
                       class="form-control"
                       id="code"
                       name="code"
                       placeholder="請輸入收到的驗證碼"
                       required>
                <div class="form-text text-white-50">
                    驗證碼已發送至：<strong>{{ session('telegram_phone') }}</strong>
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    確認登入
                </button>
                <button type="button" class="btn btn-outline-light" id="backBtn">
                    重新輸入手機號碼
                </button>
            </div>
        </form>

        <!-- 兩步驟驗證表單 -->
        <form id="passwordForm" style="display: none;">
            <div class="mb-3">
                <label for="password" class="form-label text-white">兩步驟驗證密碼</label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       placeholder="請輸入您的兩步驟驗證密碼"
                       required>
                <div class="form-text text-white-50">
                    您的帳號已啟用兩步驟驗證，請輸入密碼
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    確認登入
                </button>
                <button type="button" class="btn btn-outline-light" id="backToCodeBtn">
                    返回驗證碼
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // 手機號碼表單提交
    $('#phoneForm').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $spinner = $btn.find('.spinner-border');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        $.post('{{ route("telegram.login") }}', {
            phone: $('#phone').val()
        })
        .done(function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                if (response.need_code) {
                    $('#phoneForm').hide();
                    $('#codeForm').show();
                    $('#code').focus();
                }
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
                showAlert('發送驗證碼失敗，請重試', 'error');
            }
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    });

    // 驗證碼表單提交
    $('#codeForm').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $spinner = $btn.find('.spinner-border');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        $.post('{{ route("telegram.verify") }}', {
            code: $('#code').val()
        })
        .done(function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1000);
                }
            } else if (response.need_password) {
                showAlert(response.message, 'warning');
                $('#codeForm').hide();
                $('#passwordForm').show();
                $('#password').focus();
            } else if (response.restart_auth) {
                showAlert(response.message, 'warning');
                // 重新開始驗證流程
                $('#codeForm').hide();
                $('#passwordForm').hide();
                $('#phoneForm').show();
                $('#phone').focus();
                $('#code').val('');
                $('#password').val('');
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
                showAlert('驗證失敗，請重試', 'error');
            }
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    });

    // 兩步驟驗證表單提交
    $('#passwordForm').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const $spinner = $btn.find('.spinner-border');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');

        $.post('{{ route("telegram.verify2fa") }}', {
            password: $('#password').val()
        })
        .done(function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1000);
                }
            } else if (response.restart_auth) {
                showAlert(response.message, 'warning');
                // 重新開始驗證流程
                $('#codeForm').hide();
                $('#passwordForm').hide();
                $('#phoneForm').show();
                $('#phone').focus();
                $('#code').val('');
                $('#password').val('');
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
                showAlert('兩步驟驗證失敗，請重試', 'error');
            }
        })
        .always(function() {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
        });
    });

    // 返回按鈕
    $('#backBtn').on('click', function() {
        $('#codeForm').hide();
        $('#phoneForm').show();
        $('#phone').focus();
        $('#code').val('');
    });

    // 返回驗證碼按鈕
    $('#backToCodeBtn').on('click', function() {
        $('#passwordForm').hide();
        $('#codeForm').show();
        $('#code').focus();
        $('#password').val('');
    });

    // 自動聚焦
    if ($('#phoneForm').is(':visible')) {
        $('#phone').focus();
    } else {
        $('#code').focus();
    }
});
</script>
@endsection
