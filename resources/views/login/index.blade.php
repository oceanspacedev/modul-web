<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login | Modul</title>
    @include('layout.partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('template') }}/plugins/fontawesome-free/css/all.min.css">

    <style>
        :root {
            --gray-50: #fafafa;
            --gray-100: #f4f4f5;
            --gray-200: #e4e4e7;
            --gray-300: #d4d4d8;
            --gray-400: #a1a1aa;
            --gray-500: #71717a;
            --gray-700: #3f3f46;
            --gray-950: #09090b;
            --primary-500: #ff6bb5;
            --primary-600: #ff4da6;
            --primary-700: #e6007a;
            --primary-800: #c20067;
            --danger-600: #dc2626;
            --ring-input: rgba(9, 9, 11, 0.1);
            --focus-ring: 0 0 0 1px #ff4da6, 0 0 0 4px rgba(255, 77, 166, 0.2);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            background: var(--gray-50);
            color: var(--gray-950);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
        }

        /* Brand mark above the card */
        .login-logo {
            display: block;
            width: 40px;
            height: 40px;
            margin: 0 auto 24px;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 32px;
        }

        /* Card heading */
        .card-heading {
            text-align: center;
            margin-bottom: 24px;
        }

        .heading-title {
            font-family: 'Figtree', 'Inter', sans-serif;
            font-size: 20px;
            font-weight: 600;
            line-height: 28px;
        }

        .heading-subtitle {
            margin-top: 4px;
            font-size: 14px;
            line-height: 20px;
            color: var(--gray-500);
        }

        .heading-subtitle strong {
            color: var(--gray-950);
            font-weight: 500;
        }

        /* Alerts */
        .alert-box {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert-box i {
            margin-top: 2px;
        }

        .alert-box.alert-error {
            background: #fef2f2;
            color: #b91c1c;
            box-shadow: inset 0 0 0 1px rgba(220, 38, 38, 0.15);
        }

        .alert-box.alert-success {
            background: #ecfdf5;
            color: #047857;
            box-shadow: inset 0 0 0 1px rgba(5, 150, 105, 0.2);
        }

        /* Fields */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-950);
        }

        .form-label .req {
            color: var(--danger-600);
            font-size: 0.75em;
            vertical-align: super;
            margin-left: 1px;
        }

        .form-input {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            font-family: inherit;
            font-size: 14px;
            color: var(--gray-950);
            background: #ffffff;
            border: 1px solid var(--ring-input);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(9, 9, 11, 0.05);
            outline: none;
            transition: border-color 0.1s ease, box-shadow 0.1s ease;
        }

        .form-input::placeholder {
            color: var(--gray-400);
        }

        .form-input:focus {
            border-color: var(--primary-600);
            box-shadow: var(--focus-ring);
        }

        /* Input with a joined button on the right (password eye) */
        .input-joined {
            display: flex;
            align-items: stretch;
            height: 40px;
            background: #ffffff;
            border: 1px solid var(--ring-input);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(9, 9, 11, 0.05);
            overflow: hidden;
            transition: border-color 0.1s ease, box-shadow 0.1s ease;
        }

        .input-joined:focus-within {
            border-color: var(--primary-600);
            box-shadow: var(--focus-ring);
        }

        .input-joined .form-input {
            flex: 1;
            min-width: 0;
            height: 100%;
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }

        .input-joined .form-input:focus {
            box-shadow: none;
        }

        .input-toggle-password {
            flex-shrink: 0;
            width: 40px;
            border: 0;
            border-left: 1px solid var(--gray-200);
            background: #ffffff;
            color: var(--gray-500);
            font-size: 14px;
            cursor: pointer;
        }

        .input-toggle-password:hover {
            color: var(--gray-950);
            background: var(--gray-50);
        }

        .otp-input-box {
            width: 100%;
            height: 48px;
            text-align: center;
            font-family: inherit;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.5em;
            padding-left: 0.5em;
            font-variant-numeric: tabular-nums;
            color: var(--gray-950);
            background: #ffffff;
            border: 1px solid var(--ring-input);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(9, 9, 11, 0.05);
            outline: none;
        }

        .otp-input-box:focus {
            border-color: var(--primary-600);
            box-shadow: var(--focus-ring);
        }

        .login-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--gray-500);
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            font-size: 14px;
            color: var(--gray-950);
        }

        .remember input {
            appearance: none;
            -webkit-appearance: none;
            display: inline-grid;
            place-content: center;
            width: 16px;
            height: 16px;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            background: #ffffff;
            cursor: pointer;
        }

        .remember input::after {
            content: '';
            width: 8px;
            height: 5px;
            margin-top: -2px;
            border-left: 2px solid #ffffff;
            border-bottom: 2px solid #ffffff;
            transform: rotate(-45deg) scale(0);
            transition: transform 0.1s ease;
        }

        .remember input:checked {
            background: var(--primary-600);
            border-color: var(--primary-600);
        }

        .remember input:checked::after {
            transform: rotate(-45deg) scale(1);
        }

        .remember input:focus-visible {
            box-shadow: var(--focus-ring);
            outline: none;
        }

        .login-options strong {
            color: var(--gray-950);
            font-weight: 500;
            font-variant-numeric: tabular-nums;
        }

        /* Buttons */
        .btn-primary-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 40px;
            border: 1px solid var(--primary-700);
            border-radius: 8px;
            background-color: var(--primary-700);
            background-image: linear-gradient(to bottom, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0));
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.2), 0 1px 2px rgba(9, 9, 11, 0.12);
            transition: background-color 0.1s ease;
        }

        .btn-primary-action:hover:not(:disabled) {
            background-color: var(--primary-800);
            border-color: var(--primary-800);
        }

        .btn-primary-action:active:not(:disabled) {
            background-color: var(--primary-800);
            border-color: var(--primary-800);
            box-shadow: inset 0 1px 2px rgba(9, 9, 11, 0.2);
        }

        .btn-primary-action:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            font-size: 12px;
            color: var(--gray-500);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        .social-row {
            display: flex;
            gap: 8px;
        }

        .btn-social {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 40px;
            border: 1px solid var(--ring-input);
            border-radius: 8px;
            background: #ffffff;
            color: var(--gray-950);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(9, 9, 11, 0.05);
            transition: background-color 0.1s ease;
        }

        .btn-social:hover {
            background: var(--gray-50);
        }

        .btn-social .icon-whatsapp {
            color: #16a34a;
            font-size: 16px;
        }

        .btn-social .icon-gallery {
            color: var(--gray-500);
            font-size: 14px;
        }

        .btn-back-link,
        .text-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 0;
            background: transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-500);
            cursor: pointer;
        }

        .btn-back-link {
            width: 100%;
            justify-content: center;
            margin-top: 16px;
        }

        .btn-back-link:hover,
        .text-link-btn:hover {
            color: var(--gray-950);
        }

        .text-link-btn {
            color: var(--primary-700);
        }

        .text-link-btn:hover {
            color: var(--primary-700);
        }

        .footer-note {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--gray-500);
        }

        .hidden {
            display: none !important;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .login-card {
                padding: 24px 20px;
            }

            .social-row {
                flex-direction: column;
            }

            .btn-social {
                flex: none;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <img src="{{ asset('images/logo.svg') }}" alt="Modul" class="login-logo">

        <div class="login-card">

            <!-- ============================================== -->
            <!-- 1. SCREEN UTAMA: LOGIN USERNAME & PASSWORD -->
            <!-- ============================================== -->
            <div id="screenLoginPassword">
                <div class="card-heading">
                    <h1 class="heading-title">Masuk ke akun Anda</h1>
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
                        <label for="pwdUsername" class="form-label">Username<span class="req">*</span></label>
                        <input type="text"
                               id="pwdUsername"
                               name="username"
                               class="form-input"
                               value="{{ old('username') }}"
                               required
                               autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Kata sandi<span class="req">*</span></label>
                        <div class="input-joined">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-input"
                                   required>
                            <button type="button"
                                    id="togglePasswordBtn"
                                    class="input-toggle-password"
                                    title="Lihat/Sembunyikan password">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <label class="remember">
                            <input type="checkbox" name="remember">
                            <span>Ingat saya</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary-action">Masuk</button>
                </form>

                <div class="divider">
                    <span>atau</span>
                </div>

                <div class="social-row">
                    <button type="button" class="btn-social" id="btnGoToOtp">
                        <i class="fab fa-whatsapp icon-whatsapp"></i>
                        <span>WhatsApp</span>
                    </button>
                    <a href="/video" class="btn-social">
                        <i class="fas fa-play-circle icon-gallery"></i>
                        <span>Galeri Video</span>
                    </a>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- 2. SCREEN OTP: INPUT NOMOR WHATSAPP -->
            <!-- ============================================== -->
            <div id="screenOtpRequest" class="hidden">
                <div class="card-heading">
                    <h1 class="heading-title">Masuk dengan WhatsApp</h1>
                    <p class="heading-subtitle">Kode OTP akan dikirim ke nomor Anda</p>
                </div>

                <!-- Alert Container -->
                <div id="otpRequestAlert" class="alert-box alert-error hidden" role="alert">
                    <i class="fas fa-exclamation-circle" id="otpRequestAlertIcon"></i>
                    <div id="otpRequestAlertContent"></div>
                </div>

                <form id="formRequestOtp">
                    <div class="form-group">
                        <label for="otpUsernameInput" class="form-label">Nomor WhatsApp<span class="req">*</span></label>
                        <input type="text"
                               id="otpUsernameInput"
                               name="username"
                               class="form-input"
                               placeholder="08xxxxxxxxxx"
                               inputmode="numeric"
                               required>
                    </div>

                    <button type="submit" class="btn-primary-action btn-wa-action" id="btnSubmitRequestOtp">
                        <span id="btnSubmitRequestOtpText">Kirim OTP</span>
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
                <div class="card-heading">
                    <h1 class="heading-title">Verifikasi OTP</h1>
                    <p class="heading-subtitle">Kode dikirim ke <strong id="maskedPhoneDisplay">-</strong></p>
                </div>

                <!-- Alert Container -->
                <div id="otpVerifyAlert" class="alert-box alert-error hidden" role="alert">
                    <i class="fas fa-exclamation-circle" id="otpVerifyAlertIcon"></i>
                    <div id="otpVerifyAlertContent"></div>
                </div>

                <form id="formVerifyOtp">
                    <div class="form-group">
                        <label for="otpCodeInput" class="form-label">Kode OTP<span class="req">*</span></label>
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

                    <div class="login-options">
                        <button type="button" class="text-link-btn" id="btnChangeNumber">
                            <i class="fas fa-arrow-left"></i>
                            <span>Ganti Nomor</span>
                        </button>
                        <div>
                            <span id="cooldownLabel">Kirim ulang: </span>
                            <strong id="cooldownSeconds">60s</strong>
                            <button type="button" class="text-link-btn hidden" id="btnResendOtp">
                                <i class="fas fa-redo-alt"></i> Kirim Ulang
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary-action btn-wa-action" id="btnSubmitVerifyOtp">
                        <span id="btnVerifyText">Verifikasi</span>
                    </button>

                    <button type="button" class="btn-back-link" id="btnBackToPasswordFromVerify">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali</span>
                    </button>
                </form>
            </div>

        </div>

        <p class="footer-note">
            &copy; {{ date('Y') }} Modul
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
