<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | Modul App</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Google Font: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('template') }}/plugins/fontawesome-free/css/all.min.css">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1.2px, transparent 1.2px);
            background-size: 20px 20px;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-wrapper {
            width: 100%;
            max-width: 390px;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem 1.75rem;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
            transition: all 0.2s ease;
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-logo-badge {
            width: 44px;
            height: 44px;
            margin: 0 auto 0.75rem;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px -2px rgba(37, 99, 235, 0.35);
        }

        .brand-logo-badge.badge-whatsapp {
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 4px 12px -2px rgba(22, 163, 74, 0.35);
        }

        .brand-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        /* Alert Notifications */
        .alert-box {
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            margin-bottom: 1.25rem;
            line-height: 1.35;
        }

        .alert-box.alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-box.alert-error i {
            color: #dc2626;
            margin-top: 1px;
            flex-shrink: 0;
        }

        .alert-box.alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-box.alert-success i {
            color: #16a34a;
            margin-top: 1px;
            flex-shrink: 0;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-label {
            display: block;
            font-size: 0.835rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }

        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.15s ease;
        }

        .form-input {
            width: 100%;
            height: 44px;
            padding: 0 14px 0 40px;
            font-size: 0.925rem;
            font-family: inherit;
            color: #0f172a;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            transition: all 0.15s ease-in-out;
        }

        .form-input::placeholder {
            color: #94a3b8;
            font-size: 0.885rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .form-input:focus ~ .input-icon-left {
            color: #2563eb;
        }

        .input-toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            padding: 4px 6px;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 6px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }

        .input-toggle-password:hover {
            color: #475569;
        }

        .has-toggle {
            padding-right: 42px;
        }

        /* 6-Digit OTP Field */
        .otp-input-box {
            width: 100%;
            height: 50px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.45em;
            color: #0f172a;
            background-color: #f8fafc;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .otp-input-box:focus {
            background-color: #ffffff;
            border-color: #16a34a;
            box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.15);
            outline: none;
        }

        /* Buttons */
        .btn-primary-action {
            width: 100%;
            height: 44px;
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.925rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 2px 5px rgba(37, 99, 235, 0.2);
        }

        .btn-primary-action:hover:not(:disabled) {
            background-color: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
            transform: translateY(-1px);
        }

        .btn-primary-action:active:not(:disabled) {
            transform: translateY(0);
            background-color: #1e40af;
        }

        .btn-primary-action:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-wa-action {
            background-color: #16a34a;
            box-shadow: 0 2px 5px rgba(22, 163, 74, 0.22);
        }

        .btn-wa-action:hover:not(:disabled) {
            background-color: #15803d;
            box-shadow: 0 4px 10px rgba(22, 163, 74, 0.28);
        }

        /* Tombol Login WA di bawah Masuk Akun */
        .btn-otp-trigger {
            width: 100%;
            height: 42px;
            background-color: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.885rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-otp-trigger:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
            transform: translateY(-1px);
        }

        .btn-otp-trigger .icon-wa-green {
            color: #16a34a;
            font-size: 1.15rem;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.1rem 0;
            color: #94a3b8;
            font-size: 0.775rem;
            font-weight: 600;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }

        .divider span {
            padding: 0 0.6rem;
        }

        /* Secondary Link */
        .btn-secondary-link {
            width: 100%;
            height: 40px;
            background-color: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-secondary-link:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .btn-secondary-link i {
            color: #2563eb;
            font-size: 0.9rem;
        }

        /* Back Link */
        .btn-back-link {
            width: 100%;
            background: none;
            border: none;
            padding: 8px;
            color: #64748b;
            font-family: inherit;
            font-size: 0.835rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: color 0.15s ease;
            margin-top: 0.6rem;
        }

        .btn-back-link:hover {
            color: #0f172a;
        }

        .text-link-btn {
            background: none;
            border: none;
            color: #2563eb;
            font-family: inherit;
            font-size: 0.825rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 4px;
        }

        .text-link-btn:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.775rem;
            color: #94a3b8;
        }

        .hidden {
            display: none !important;
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }

            .login-card {
                padding: 1.5rem 1.25rem;
                border-radius: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-card">

            <!-- ============================================== -->
            <!-- 1. SCREEN UTAMA: LOGIN USERNAME & PASSWORD -->
            <!-- ============================================== -->
            <div id="screenLoginPassword">
                <div class="brand-header">
                    <div class="brand-logo-badge">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h1 class="brand-title">Modul App</h1>
                </div>

                <!-- Alert Error -->
                @if ($message = Session::get('error'))
                    <div class="alert-box alert-error" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>{{ $message }}</div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert-box alert-error" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Form Login -->
                <form action="/login" method="POST" autocomplete="off">
                    @csrf
                    <div class="form-group">
                        <label for="pwdUsername" class="form-label">Username</label>
                        <div class="input-container">
                            <input type="text"
                                   id="pwdUsername"
                                   name="username"
                                   class="form-input"
                                   placeholder="Username"
                                   value="{{ old('username') }}"
                                   required
                                   autofocus>
                            <i class="fas fa-user input-icon-left"></i>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.85rem;">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-container">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-input has-toggle"
                                   placeholder="Password"
                                   required>
                            <i class="fas fa-lock input-icon-left"></i>
                            <button type="button"
                                    id="togglePasswordBtn"
                                    class="input-toggle-password"
                                    title="Lihat/Sembunyikan password">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.15rem; font-size: 0.835rem; color: #64748b;">
                        <label style="display: inline-flex; align-items: center; gap: 0.45rem; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="remember" style="accent-color: #2563eb; width: 15px; height: 15px; border-radius: 4px; cursor: pointer;">
                            <span>Ingat Saya</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary-action">
                        <span>Masuk ke Akun</span>
                        <i class="fas fa-arrow-right" style="font-size: 0.85rem;"></i>
                    </button>
                </form>

                <div class="divider">
                    <span>atau</span>
                </div>

                <!-- Tombol Masuk WA di bawah Masuk Akun -->
                <button type="button" class="btn-otp-trigger" id="btnGoToOtp">
                    <i class="fab fa-whatsapp icon-wa-green"></i>
                    <span>Login via WhatsApp</span>
                </button>

                <div class="divider">
                    <span>atau</span>
                </div>

                <a href="/video" class="btn-secondary-link">
                    <i class="fas fa-play-circle"></i>
                    <span>Galeri Video Publik</span>
                </a>
            </div>

            <!-- ============================================== -->
            <!-- 2. SCREEN OTP: INPUT NOMOR WHATSAPP -->
            <!-- ============================================== -->
            <div id="screenOtpRequest" class="hidden">
                <div class="brand-header">
                    <div class="brand-logo-badge badge-whatsapp">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <h1 class="brand-title">Login WhatsApp</h1>
                </div>

                <!-- Alert Container -->
                <div id="otpRequestAlert" class="alert-box alert-error hidden" role="alert">
                    <i class="fas fa-exclamation-circle" id="otpRequestAlertIcon"></i>
                    <div id="otpRequestAlertContent"></div>
                </div>

                <form id="formRequestOtp">
                    <div class="form-group">
                        <label for="otpUsernameInput" class="form-label">Nomor WhatsApp</label>
                        <div class="input-container">
                            <input type="text"
                                   id="otpUsernameInput"
                                   name="username"
                                   class="form-input"
                                   placeholder="08xxxxxxxxxx"
                                   inputmode="numeric"
                                   required>
                            <i class="fab fa-whatsapp input-icon-left" style="font-size: 1.15rem; color: #16a34a;"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary-action btn-wa-action" id="btnSubmitRequestOtp">
                        <span id="btnSubmitRequestOtpText">Kirim OTP</span>
                        <i class="fas fa-arrow-right" style="font-size: 0.85rem;"></i>
                    </button>

                    <button type="button" class="btn-back-link" id="btnCancelOtp">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali</span>
                    </button>
                </form>
            </div>

            <!-- ============================================== -->
            <!-- 3. SCREEN OTP: MASUKKAN KODE OTP -->
            <!-- ============================================== -->
            <div id="screenOtpVerify" class="hidden">
                <div class="brand-header">
                    <div class="brand-logo-badge badge-whatsapp">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1 class="brand-title">Verifikasi OTP</h1>
                    <div style="margin-top: 0.35rem; font-size: 0.885rem; color: #0f172a; font-weight: 600;" id="maskedPhoneDisplay">-</div>
                </div>

                <!-- Alert Container -->
                <div id="otpVerifyAlert" class="alert-box alert-error hidden" role="alert">
                    <i class="fas fa-exclamation-circle" id="otpVerifyAlertIcon"></i>
                    <div id="otpVerifyAlertContent"></div>
                </div>

                <form id="formVerifyOtp">
                    <div class="form-group" style="text-align: center;">
                        <label for="otpCodeInput" class="form-label" style="text-align: center; margin-bottom: 0.5rem;">
                            Kode OTP
                        </label>
                        <input type="text"
                               id="otpCodeInput"
                               name="otp"
                               class="otp-input-box"
                               maxlength="6"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               placeholder="&bull;&bull;&bull;&bull;&bull;&bull;"
                               autocomplete="one-time-code"
                               required>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.15rem; font-size: 0.825rem; color: #64748b;">
                        <button type="button" class="text-link-btn" id="btnChangeNumber">
                            <i class="fas fa-arrow-left"></i>
                            <span>Ganti Nomor</span>
                        </button>
                        <div>
                            <span id="cooldownLabel">Kirim ulang: </span>
                            <strong id="cooldownSeconds" style="color: #0f172a;">60s</strong>
                            <button type="button" class="text-link-btn hidden" id="btnResendOtp">
                                <i class="fas fa-redo-alt"></i> Kirim Ulang
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary-action btn-wa-action" id="btnSubmitVerifyOtp">
                        <span id="btnVerifyText">Verifikasi</span>
                        <i class="fas fa-arrow-right" style="font-size: 0.85rem;"></i>
                    </button>

                    <button type="button" class="btn-back-link" id="btnBackToPasswordFromVerify">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali</span>
                    </button>
                </form>
            </div>

        </div>

        <p class="footer-note">
            &copy; {{ date('Y') }} Modul App
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Screens
            const screenLoginPassword = document.getElementById('screenLoginPassword');
            const screenOtpRequest = document.getElementById('screenOtpRequest');
            const screenOtpVerify = document.getElementById('screenOtpVerify');

            // Navigation
            const btnGoToOtp = document.getElementById('btnGoToOtp');
            const btnCancelOtp = document.getElementById('btnCancelOtp');
            const btnChangeNumber = document.getElementById('btnChangeNumber');
            const btnBackToPasswordFromVerify = document.getElementById('btnBackToPasswordFromVerify');

            // Inputs & Forms
            const pwdUsername = document.getElementById('pwdUsername');
            const otpUsernameInput = document.getElementById('otpUsernameInput');
            const otpCodeInput = document.getElementById('otpCodeInput');
            const formRequestOtp = document.getElementById('formRequestOtp');
            const formVerifyOtp = document.getElementById('formVerifyOtp');
            const btnSubmitRequestOtp = document.getElementById('btnSubmitRequestOtp');
            const btnSubmitRequestOtpText = document.getElementById('btnSubmitRequestOtpText');
            const btnSubmitVerifyOtp = document.getElementById('btnSubmitVerifyOtp');
            const btnVerifyText = document.getElementById('btnVerifyText');
            const maskedPhoneDisplay = document.getElementById('maskedPhoneDisplay');
            const cooldownLabel = document.getElementById('cooldownLabel');
            const cooldownSeconds = document.getElementById('cooldownSeconds');
            const btnResendOtp = document.getElementById('btnResendOtp');

            // Alerts
            const otpRequestAlert = document.getElementById('otpRequestAlert');
            const otpRequestAlertIcon = document.getElementById('otpRequestAlertIcon');
            const otpRequestAlertContent = document.getElementById('otpRequestAlertContent');
            const otpVerifyAlert = document.getElementById('otpVerifyAlert');
            const otpVerifyAlertIcon = document.getElementById('otpVerifyAlertIcon');
            const otpVerifyAlertContent = document.getElementById('otpVerifyAlertContent');

            let currentIdentifier = '';
            let cooldownInterval = null;

            function showScreen(screen) {
                screenLoginPassword.classList.add('hidden');
                screenOtpRequest.classList.add('hidden');
                screenOtpVerify.classList.add('hidden');
                screen.classList.remove('hidden');
            }

            btnGoToOtp.addEventListener('click', function () {
                showScreen(screenOtpRequest);
                hideAlert(otpRequestAlert);
                otpUsernameInput.focus();
            });

            btnCancelOtp.addEventListener('click', function () {
                showScreen(screenLoginPassword);
                if (pwdUsername) pwdUsername.focus();
            });

            btnBackToPasswordFromVerify.addEventListener('click', function () {
                if (cooldownInterval) clearInterval(cooldownInterval);
                showScreen(screenLoginPassword);
                if (pwdUsername) pwdUsername.focus();
            });

            btnChangeNumber.addEventListener('click', function () {
                if (cooldownInterval) clearInterval(cooldownInterval);
                showScreen(screenOtpRequest);
                hideAlert(otpRequestAlert);
                otpUsernameInput.focus();
            });

            // Password Toggle
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');

            if (toggleBtn && passwordInput && toggleIcon) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    
                    if (isPassword) {
                        toggleIcon.classList.remove('fa-eye');
                        toggleIcon.classList.add('fa-eye-slash');
                    } else {
                        toggleIcon.classList.remove('fa-eye-slash');
                        toggleIcon.classList.add('fa-eye');
                    }
                });
            }

            function showAlert(alertEl, iconEl, contentEl, message, type = 'error') {
                alertEl.className = 'alert-box alert-' + type;
                if (type === 'error') {
                    iconEl.className = 'fas fa-exclamation-circle';
                } else if (type === 'success') {
                    iconEl.className = 'fas fa-check-circle';
                }
                contentEl.innerHTML = message;
                alertEl.classList.remove('hidden');
            }

            function hideAlert(alertEl) {
                alertEl.classList.add('hidden');
            }

            function startCooldown(seconds) {
                if (cooldownInterval) clearInterval(cooldownInterval);
                let remaining = seconds;
                cooldownLabel.classList.remove('hidden');
                cooldownSeconds.classList.remove('hidden');
                btnResendOtp.classList.add('hidden');
                cooldownSeconds.textContent = remaining + 's';

                cooldownInterval = setInterval(function () {
                    remaining--;
                    if (remaining <= 0) {
                        clearInterval(cooldownInterval);
                        cooldownLabel.classList.add('hidden');
                        cooldownSeconds.classList.add('hidden');
                        btnResendOtp.classList.remove('hidden');
                    } else {
                        cooldownSeconds.textContent = remaining + 's';
                    }
                }, 1000);
            }

            // Request OTP
            async function requestOtp(identifier) {
                hideAlert(otpRequestAlert);
                btnSubmitRequestOtp.disabled = true;
                btnSubmitRequestOtpText.textContent = 'Mengirim...';

                try {
                    const response = await fetch('/login/otp/request', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ username: identifier })
                    });

                    const data = await response.json();

                    if (response.ok && data.status) {
                        currentIdentifier = identifier;
                        maskedPhoneDisplay.textContent = data.phone_masked || identifier;

                        showScreen(screenOtpVerify);
                        otpCodeInput.value = '';
                        otpCodeInput.focus();

                        let successMsg = data.message || 'Kode OTP telah dikirim ke WhatsApp.';
                        if (data.dev_otp) {
                            successMsg += `<br><span style="font-size: 0.8rem;"><strong>[Dev Kode OTP]:</strong> ${data.dev_otp}</span>`;
                        }
                        showAlert(otpVerifyAlert, otpVerifyAlertIcon, otpVerifyAlertContent, successMsg, 'success');

                        startCooldown(data.cooldown || 60);
                    } else {
                        showAlert(otpRequestAlert, otpRequestAlertIcon, otpRequestAlertContent, data.message || 'Gagal mengirim kode OTP.');
                        if (data.cooldown) {
                            startCooldown(data.cooldown);
                        }
                    }
                } catch (err) {
                    showAlert(otpRequestAlert, otpRequestAlertIcon, otpRequestAlertContent, 'Terjadi kendala koneksi.');
                } finally {
                    btnSubmitRequestOtp.disabled = false;
                    btnSubmitRequestOtpText.textContent = 'Kirim OTP';
                }
            }

            formRequestOtp.addEventListener('submit', function (e) {
                e.preventDefault();
                const idVal = otpUsernameInput.value.trim();
                if (idVal) {
                    requestOtp(idVal);
                }
            });

            btnResendOtp.addEventListener('click', function () {
                if (currentIdentifier) {
                    requestOtp(currentIdentifier);
                }
            });

            // Format input OTP
            otpCodeInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    formVerifyOtp.requestSubmit();
                }
            });

            // Verify OTP
            formVerifyOtp.addEventListener('submit', async function (e) {
                e.preventDefault();
                const otp = otpCodeInput.value.trim();
                if (otp.length !== 6) {
                    showAlert(otpVerifyAlert, otpVerifyAlertIcon, otpVerifyAlertContent, 'Masukkan 6 digit kode OTP.');
                    return;
                }

                hideAlert(otpVerifyAlert);
                btnSubmitVerifyOtp.disabled = true;
                btnVerifyText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memverifikasi...';

                try {
                    const response = await fetch('/login/otp/verify', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            username: currentIdentifier,
                            otp: otp
                        })
                    });

                    const data = await response.json();

                    if (response.ok && data.status) {
                        showAlert(otpVerifyAlert, otpVerifyAlertIcon, otpVerifyAlertContent, 'Berhasil! Mengalihkan...', 'success');
                        setTimeout(function () {
                            window.location.href = data.redirect || '/dashboard';
                        }, 400);
                    } else {
                        showAlert(otpVerifyAlert, otpVerifyAlertIcon, otpVerifyAlertContent, data.message || 'Kode OTP tidak valid.');
                        otpCodeInput.select();
                        btnSubmitVerifyOtp.disabled = false;
                        btnVerifyText.textContent = 'Verifikasi';
                    }
                } catch (err) {
                    showAlert(otpVerifyAlert, otpVerifyAlertIcon, otpVerifyAlertContent, 'Terjadi kendala koneksi.');
                    btnSubmitVerifyOtp.disabled = false;
                    btnVerifyText.textContent = 'Verifikasi';
                }
            });
        });
    </script>
</body>

</html>
