<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk &middot; CT-PAT JAN Code Scanner</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('storage/images/signature_icon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">

    <style>
        :root {
            --ink: #0b1220;
            --blue: #2563eb;
            --indigo: #4f46e5;
            --cyan: #06b6d4;
            --slate: #64748b;
            --line: #e2e8f0;
            --surface: #f5f8fc;
            --danger: #e11d48;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--surface);
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }

        .auth-shell {
            display: flex;
            min-height: 100vh;
        }

        /* ================= PANEL KIRI (BRAND) ================= */
        .brand-panel {
            position: relative;
            flex: 1 1 45%;
            min-height: 100vh;
            padding: 42px 56px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            color: #fff;
            background:
                radial-gradient(900px 520px at 88% 8%, rgba(6, 182, 212, 0.20), transparent 62%),
                radial-gradient(760px 540px at 6% 92%, rgba(79, 70, 229, 0.32), transparent 62%),
                linear-gradient(145deg, #080e1c 0%, #101c3a 52%, #0b2740 100%);
        }

        .grid-overlay {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 44px 44px;
            -webkit-mask-image: radial-gradient(circle at 32% 34%, #000 0%, transparent 76%);
            mask-image: radial-gradient(circle at 32% 34%, #000 0%, transparent 76%);
            pointer-events: none;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.55;
            pointer-events: none;
        }
        .orb-1 {
            width: 380px; height: 380px;
            background: #2563eb;
            top: -130px; right: -90px;
            animation: float 12s ease-in-out infinite;
        }
        .orb-2 {
            width: 320px; height: 320px;
            background: #0891b2;
            bottom: -110px; left: -70px;
            animation: float 16s ease-in-out infinite reverse;
        }
        @keyframes float {
            50% { transform: translate3d(0, 26px, 0) scale(1.06); }
        }

        .brand {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .brand img {
            height: 46px;
            width: auto;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.35));
        }
        .brand-meta { line-height: 1.2; }
        .brand-meta strong {
            display: block;
            font-size: 14px;
            letter-spacing: 0.02em;
        }
        .brand-meta span {
            font-size: 10px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #93a4c8;
        }

        .brand-copy {
            position: relative;
            z-index: 2;
            margin-top: auto;
            margin-bottom: auto;
            max-width: 560px;
            padding: 52px 0;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #7dd3fc;
            background: rgba(56, 189, 248, 0.10);
            border: 1px solid rgba(56, 189, 248, 0.26);
            border-radius: 999px;
            padding: 7px 12px;
        }
        .eyebrow .pulse {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #22d3ee;
            box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.18);
            animation: blink 1.8s infinite;
        }
        @keyframes blink { 50% { opacity: 0.35; } }

        .brand-copy h1 {
            font-size: clamp(34px, 3.5vw, 52px);
            line-height: 1.08;
            letter-spacing: -0.03em;
            margin: 20px 0 16px;
            font-weight: 800;
        }
        .brand-copy h1 .grad {
            background: linear-gradient(100deg, #60a5fa, #22d3ee 55%, #a7f3d0);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .brand-copy .lead {
            font-size: 14.5px;
            line-height: 1.7;
            color: #a9b8d6;
            max-width: 480px;
            margin: 0 0 28px;
        }

        .features {
            display: grid;
            gap: 12px;
            margin: 0 0 32px;
            padding: 0;
            list-style: none;
            max-width: 470px;
        }
        .features li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            color: #cfd9ee;
        }
        .features .ico {
            width: 34px; height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(148, 163, 184, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.18);
            color: #7dd3fc;
            font-size: 13px;
        }

        .mini-board {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.18);
            -webkit-backdrop-filter: blur(6px);
            backdrop-filter: blur(6px);
        }
        .mini-grid {
            display: grid;
            grid-template-columns: repeat(8, 10px);
            grid-auto-rows: 10px;
            gap: 4px;
        }
        .mini-grid span {
            border-radius: 3px;
            background: rgba(34, 197, 94, 0.85);
        }
        .mini-grid span.p { background: rgba(245, 158, 11, 0.95); }
        .mini-grid span.f { background: rgba(239, 68, 68, 0.95); }
        .mini-grid span.v { background: rgba(148, 163, 184, 0.20); }
        .mini-board .cap {
            font-size: 9.5px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #8fa1c2;
            line-height: 1.6;
        }
        .mini-board .cap b {
            display: block;
            color: #e6ecff;
            font-size: 10.5px;
            letter-spacing: 0.04em;
        }

        .brand-foot {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 11px;
            color: #6f82a8;
        }

        /* ================= PANEL KANAN (FORM) ================= */
        .form-panel {
            position: relative;
            flex: 1 1 55%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 32px;
            background:
                radial-gradient(640px 320px at 100% 0%, #e8efff, transparent 62%),
                var(--surface);
        }

        .form-wrap {
            width: 100%;
            max-width: 480px;
            animation: rise 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: none; }
        }

        .mobile-brand { display: none; }

        .form-card {
            background: #fff;
            border: 1px solid #e8eef7;
            border-radius: 26px;
            padding: 44px 40px;
            box-shadow:
                0 30px 70px -30px rgba(15, 23, 42, 0.38),
                0 8px 24px -12px rgba(15, 23, 42, 0.12);
        }

        .badge-secure {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--blue);
            background: #eff5ff;
            border: 1px solid #dbe8ff;
            border-radius: 999px;
            padding: 6px 11px;
            margin-bottom: 20px;
        }

        .form-card h2 {
            font-size: 28px;
            letter-spacing: -0.03em;
            margin: 0 0 8px;
            font-weight: 800;
        }
        .form-card .sub {
            font-size: 13.5px;
            color: var(--slate);
            line-height: 1.6;
            margin: 0 0 28px;
        }

        .flash {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 11px 13px;
            border-radius: 12px;
            font-size: 12.5px;
            line-height: 1.5;
            margin: 0 0 16px;
            border: 1px solid transparent;
        }
        .flash i { margin-top: 2px; }
        .flash.success { background: #ecfdf5; border-color: #bbf7d0; color: #047857; }
        .flash.error   { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
        .flash.warning { background: #fffbeb; border-color: #fde68a; color: #b45309; }
        .flash.info    { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }

        .field-group { margin-bottom: 20px; }

        .field-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            margin: 0 0 8px;
        }

        .field {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 56px;
            padding: 0 16px;
            background: #f8fafc;
            border: 1.5px solid var(--line);
            border-radius: 15px;
            transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }
        .field:focus-within {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.14);
        }
        .field > i {
            width: 16px;
            text-align: center;
            font-size: 14px;
            color: #94a3b8;
            transition: color 0.18s ease;
        }
        .field:focus-within > i { color: var(--blue); }
        .field input {
            flex: 1;
            min-width: 0;
            height: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            font: inherit;
            font-size: 14.5px;
            color: #0f172a;
        }
        .field input::placeholder { color: #a3aec2; }
        .field.invalid { border-color: #fb7185; background: #fff5f7; }
        .field.invalid:focus-within { box-shadow: 0 0 0 4px rgba(244, 63, 94, 0.14); }

        .toggle-pw {
            display: grid;
            place-items: center;
            padding: 6px;
            margin-right: -4px;
            border: 0;
            border-radius: 8px;
            background: none;
            color: #94a3b8;
            font-size: 14px;
            cursor: pointer;
            transition: 0.15s;
        }
        .toggle-pw:hover { color: var(--blue); background: #eff6ff; }

        .err {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: var(--danger);
            margin: 7px 0 0;
        }
        .caps-hint {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #b45309;
            margin: 7px 0 0;
        }

        .btn-signin {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            height: 56px;
            margin-top: 8px;
            border: 0;
            border-radius: 15px;
            background: linear-gradient(100deg, var(--blue), var(--indigo));
            color: #fff;
            font: inherit;
            font-size: 14.5px;
            font-weight: 700;
            letter-spacing: 0.01em;
            cursor: pointer;
            box-shadow: 0 14px 28px -12px rgba(37, 99, 235, 0.65);
            transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }
        .btn-signin:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px -12px rgba(37, 99, 235, 0.72);
        }
        .btn-signin:active { transform: none; }
        .btn-signin:disabled { opacity: 0.72; cursor: wait; transform: none; }
        .btn-signin .fa-arrow-right { transition: transform 0.18s ease; }
        .btn-signin:hover .fa-arrow-right { transform: translateX(4px); }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .divider::before, .divider::after {
            content: '';
            height: 1px;
            flex: 1;
            background: #e8eef7;
        }

        .btn-ghost {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 52px;
            border: 1.5px solid var(--line);
            border-radius: 15px;
            background: #fff;
            color: #334155;
            font: inherit;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: 0.18s;
        }
        .btn-ghost:hover {
            border-color: #bfdbfe;
            background: #f8fbff;
            color: #1d4ed8;
            text-decoration: none;
        }

        .help {
            margin: 22px 0 0;
            text-align: center;
            font-size: 11.5px;
            color: #94a3b8;
        }
        .help i { margin-right: 4px; }

        .mobile-foot {
            display: none;
            margin-top: 18px;
            text-align: center;
            font-size: 10.5px;
            color: #94a3b8;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 1080px) {
            .brand-panel { flex-basis: 48%; padding: 36px 38px; }
            .brand-copy { padding: 40px 0; }
            .brand-copy .lead { font-size: 13.5px; }
            .form-panel { padding: 40px 24px; }
        }

        @media (max-width: 880px) {
            .auth-shell { flex-direction: column; }
            .brand-panel { display: none; }

            .form-panel {
                min-height: 100vh;
                padding: 26px 18px 34px;
                align-items: center;
                justify-content: center;
            }
            .form-panel::before {
                content: '';
                position: absolute;
                inset: 0 0 auto 0;
                height: 190px;
                background:
                    radial-gradient(420px 220px at 90% 0%, rgba(6, 182, 212, 0.24), transparent 65%),
                    linear-gradient(145deg, #080e1c, #12204a);
                border-radius: 0 0 28px 28px;
            }

            .form-wrap { position: relative; z-index: 1; max-width: 430px; }

            .mobile-brand {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 0 0 22px;
                padding: 4px 2px 0;
            }
            .mobile-brand img { height: 38px; filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.3)); }
            .mobile-brand strong { display: block; color: #fff; font-size: 13px; line-height: 1.2; }
            .mobile-brand span {
                font-size: 9px;
                letter-spacing: 0.16em;
                text-transform: uppercase;
                color: #9db0d4;
            }

            .form-card { padding: 28px 22px; border-radius: 20px; }
            .form-card h2 { font-size: 23px; }
            .mobile-foot { display: block; }
        }

        @media (max-width: 380px) {
            .form-card { padding: 24px 18px; }
            .form-card h2 { font-size: 21px; }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <!-- ================= BRAND / VISUAL ================= -->
        <aside class="brand-panel">
            <div class="grid-overlay"></div>
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>

            <header class="brand">
                <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
                <div class="brand-meta">
                    <strong>PT. Chutex International Indonesia</strong>
                    <span>CT-PAT Warehouse System</span>
                </div>
            </header>

            <div class="brand-copy">
                <span class="eyebrow"><span class="pulse"></span> Live warehouse control</span>

                <h1>Scan. Tempatkan.<br><span class="grad">Lacak real-time.</span></h1>

                <p class="lead">
                    Satu dashboard untuk scan JAN code, penempatan pallet, dan pemantauan
                    kapasitas gudang secara langsung &mdash; cepat, akurat, dan tanpa kertas.
                </p>

                <ul class="features">
                    <li>
                        <span class="ico"><i class="fas fa-barcode"></i></span>
                        Scan &amp; validasi JAN code dengan riwayat batch
                    </li>
                    <li>
                        <span class="ico"><i class="fas fa-th-large"></i></span>
                        Peta pallet 2D &amp; visualisasi gudang 3D
                    </li>
                    <li>
                        <span class="ico"><i class="fas fa-tv"></i></span>
                        Display publik fullscreen yang update otomatis
                    </li>
                </ul>

                <div class="mini-board" aria-hidden="true">
                    <div class="mini-grid">
                        <span></span><span></span><span class="p"></span><span></span>
                        <span class="f"></span><span></span><span></span><span class="v"></span>
                        <span></span><span class="p"></span><span></span><span></span>
                        <span class="v"></span><span></span><span class="f"></span><span></span>
                        <span></span><span></span><span></span><span class="p"></span>
                    </div>
                    <div class="cap">
                        <b>528 pallet</b>
                        8 row &middot; 60 posisi
                    </div>
                </div>
            </div>

            <footer class="brand-foot">
                <span>&copy; {{ date('Y') }} PT. Chutex International Indonesia</span>
                <span>CT-PAT &middot; JAN Code Scanner</span>
            </footer>
        </aside>

        <!-- ================= FORM LOGIN ================= -->
        <main class="form-panel">
            <div class="form-wrap">
                <div class="mobile-brand">
                    <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
                    <div>
                        <strong>CT-PAT Scanner</strong>
                        <span>Warehouse System</span>
                    </div>
                </div>

                <div class="form-card">
                    <span class="badge-secure"><i class="fas fa-shield-alt"></i> Secure access</span>

                    <h2>Selamat datang kembali</h2>
                    <p class="sub">Masuk untuk melanjutkan ke dashboard CT-PAT.</p>

                    @if ($message = Session::get('success'))
                        <div class="flash success">
                            <i class="fas fa-check-circle"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @endif

                    @if ($message = Session::get('error'))
                        <div class="flash error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @endif

                    @if ($message = Session::get('warning'))
                        <div class="flash warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @endif

                    @if ($message = Session::get('info'))
                        <div class="flash info">
                            <i class="fas fa-info-circle"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @endif

                    <form action="{{ route('login.post') }}" method="POST" id="loginForm">
                        @csrf

                        <div class="field-group">
                            <label class="field-label" for="email">Email</label>
                            <div class="field @error('email') invalid @enderror">
                                <i class="fas fa-envelope"></i>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       placeholder="nama@chutex.id"
                                       autocomplete="username"
                                       inputmode="email"
                                       required
                                       autofocus>
                            </div>
                            @error('email')
                                <div class="err"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>
                            @enderror
                        </div>

                        <div class="field-group">
                            <label class="field-label" for="password">Password</label>
                            <div class="field @error('password') invalid @enderror">
                                <i class="fas fa-lock"></i>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       placeholder="Masukkan password"
                                       autocomplete="current-password"
                                       required>
                                <button type="button"
                                        class="toggle-pw"
                                        id="togglePassword"
                                        aria-label="Tampilkan password"
                                        aria-controls="password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="caps-hint" id="capsHint">
                                <i class="fas fa-exclamation-triangle"></i> Caps Lock sedang aktif
                            </div>
                            @error('password')
                                <div class="err"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn-signin" id="btnLogin">
                            <span>Masuk</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="divider">atau</div>

                    <a href="{{ route('register') }}" class="btn-ghost">
                        <i class="fas fa-user-plus"></i> Buat akun baru
                    </a>

                    <p class="help">
                        <i class="fas fa-headset"></i>
                        Butuh bantuan akses? Hubungi tim IT.
                    </p>
                </div>

                <p class="mobile-foot">
                    &copy; {{ date('Y') }} PT. Chutex International Indonesia
                </p>
            </div>
        </main>
    </div>

    @include('sweetalert::alert')

    <script>
        (function () {
            var pw = document.getElementById('password');
            var toggle = document.getElementById('togglePassword');
            var capsHint = document.getElementById('capsHint');
            var form = document.getElementById('loginForm');
            var btn = document.getElementById('btnLogin');

            if (toggle && pw) {
                toggle.addEventListener('click', function () {
                    var show = pw.type === 'password';
                    pw.type = show ? 'text' : 'password';
                    toggle.innerHTML = show
                        ? '<i class="fas fa-eye-slash"></i>'
                        : '<i class="fas fa-eye"></i>';
                    toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                    pw.focus();
                });
            }

            if (pw && capsHint) {
                pw.addEventListener('keyup', function (event) {
                    var on = event.getModifierState && event.getModifierState('CapsLock');
                    capsHint.style.display = on ? 'flex' : 'none';
                });
                pw.addEventListener('blur', function () {
                    capsHint.style.display = 'none';
                });
            }

            if (form && btn) {
                form.addEventListener('submit', function () {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>Memverifikasi...</span>';
                });
            }
        })();
    </script>
</body>
</html>
