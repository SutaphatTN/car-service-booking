<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'นัดหมาย') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset_version('css/app.css') }}">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%231d4ed8'/><g transform='translate(3.6 4.2) scale(0.775)' fill='none' stroke='%23fff' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'><path d='M8 15l2.6-5.2A3 3 0 0 1 13.3 8h5.4a3 3 0 0 1 2.7 1.8L24 15'/><path d='M5 15h22a2 2 0 0 1 2 2v4h-3.2M8.2 21H3v-4a2 2 0 0 1 2-2'/><circle cx='10.5' cy='21' r='2.8'/><circle cx='21.5' cy='21' r='2.8'/><path d='M13.3 21h5.4'/></g></svg>">
</head>
<body class="@yield('body-class')">
{{-- แถบบอกว่ากำลังโหลดหน้าใหม่ — สปินเนอร์บนแท็บเบราว์เซอร์เล็กเกินกว่าผู้ใช้จะสังเกต --}}
<div id="pageProgress" class="page-progress" role="presentation" hidden></div>

{{-- กล่องกลางจอ — โผล่เมื่อโหลดนานกว่าครู่เดียว ใช้ <dialog> เพราะต้องลอยเหนือโมดอลที่เปิดค้างอยู่ --}}
<dialog id="pageLoader" class="page-loader" aria-live="polite" aria-busy="true">
    <div class="page-loader-box">
        <span class="spinner" aria-hidden="true"></span>
        <b>กำลังโหลด…</b>
    </div>
</dialog>

<header class="topbar">
    <div class="wrap">
        <a class="brand" href="{{ url('/') }}">
            <span class="logo">
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M8 15l2.6-5.2A3 3 0 0 1 13.3 8h5.4a3 3 0 0 1 2.7 1.8L24 15"/>
                    <path d="M5 15h22a2 2 0 0 1 2 2v4h-3.2M8.2 21H3v-4a2 2 0 0 1 2-2"/>
                    <circle cx="10.5" cy="21" r="2.8"/>
                    <circle cx="21.5" cy="21" r="2.8"/>
                    <path d="M13.3 21h5.4"/>
                </svg>
            </span>
            <span class="brand-text">
                <h1>{{ config('app.name') }}</h1>
                <p>
                    @auth
                        @if ($activeBrand = current_brand())
                            ยี่ห้อ {{ $activeBrand->name }}
                        @else
                            ระบบนัดหมายสำหรับใช้งานภายใน
                        @endif
                    @else
                        ระบบนัดหมายสำหรับใช้งานภายใน
                    @endauth
                </p>
            </span>
        </a>

        @auth
            <div class="account" id="accountMenu">
                <button type="button" class="account-btn" id="accountBtn" aria-expanded="false" aria-haspopup="true">
                    {{-- ไอคอนรูปคน ไม่ใช้อักษรตัวแรกของชื่อ — ชื่อไทยขึ้นต้นด้วยสระหรือ ก-ฮ ตัวไหนก็ได้ ดูไม่ออกว่าเป็นของใคร --}}
                    <span class="avatar" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8.2" r="3.7"/>
                            <path d="M4.8 20c.6-3.8 3.6-6 7.2-6s6.6 2.2 7.2 6"/>
                        </svg>
                    </span>
                    <span class="account-text">
                        <b>{{ auth()->user()->name }}</b>
                        <small>{{ auth()->user()->role->label() }}</small>
                    </span>
                    <span class="caret" aria-hidden="true"></span>
                </button>

                <div class="account-drop" id="accountDrop" hidden>
                    <div class="account-drop-head">
                        <b>{{ auth()->user()->name }}</b>
                        <div class="who">{{ '@'.auth()->user()->username }}</div>
                        <span class="badge role-{{ auth()->user()->role->value }}">{{ auth()->user()->role->label() }}</span>
                    </div>

                    @include('partials.brand-switch')

                    @if (auth()->user()->canManageUsers())
                        <a href="{{ route('users.create') }}">ลงทะเบียนผู้ใช้ใหม่</a>
                        <a href="{{ route('users.index') }}">รายชื่อผู้ใช้งาน</a>
                        <div class="sep"></div>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="danger">ออกจากระบบ</button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</header>

@auth
    <nav class="navbar">
        <div class="wrap">
            <a href="{{ route('appointments.index') }}" @class(['active' => request()->routeIs('appointments.index')])>รายการนัดหมาย</a>
            <a href="{{ route('calendar.index') }}" @class(['active' => request()->routeIs('calendar.*')])>ปฏิทิน</a>
            <a href="{{ route('calls.index') }}" @class(['active' => request()->routeIs('calls.*')])>ต้องโทรหา</a>
            <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])>รายงาน</a>
        </div>
    </nav>
@endauth

<main class="wrap">
    @if (session('status'))
        <div class="alert ok flash">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>

@auth
    <script>
        // เมนูบัญชีผู้ใช้มุมขวาบน
        const accountMenu = document.getElementById('accountMenu');
        const accountBtn = document.getElementById('accountBtn');
        const accountDrop = document.getElementById('accountDrop');

        function toggleAccount(open) {
            accountDrop.hidden = !open;
            accountBtn.setAttribute('aria-expanded', String(open));
        }

        accountBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            toggleAccount(accountDrop.hidden);
        });

        document.addEventListener('click', (event) => {
            if (!accountMenu.contains(event.target)) toggleAccount(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') toggleAccount(false);
        });
    </script>
@endauth

<script>
    // กันผู้ใช้ใจร้อนกดปุ่มบันทึกรัว ๆ จนได้ข้อมูลซ้ำ — ดักที่ document จึงครอบฟอร์มทุกใบ
    // รวมถึงฟอร์มที่เพิ่มเข้ามาทีหลังโดยไม่ต้องไปแก้ทีละหน้า
    (function () {
        function submitButtonsOf(form) {
            return form.querySelectorAll(
                'button[type="submit"], button:not([type]), input[type="submit"], [data-lock-on-submit]'
            );
        }

        // ---- บอกว่ากำลังโหลดหน้าใหม่ ----
        // แถบบนสุดขึ้นทันทีเพื่อตอบสนองเร็ว ส่วนกล่องกลางจอรอก่อนครู่หนึ่งค่อยโผล่
        // หน้าที่โหลดเร็วจะได้ไม่เห็นกล่องแวบขึ้นมาแล้วหายไปจนกวนตา
        var LOADER_DELAY = 180;
        var LOADER_TIMEOUT = 20000;

        var progress = document.getElementById('pageProgress');
        var loader = document.getElementById('pageLoader');
        var loaderTimer = null;
        var safetyTimer = null;

        function showLoader() {
            if (!loader || loader.open) return;

            try {
                loader.showModal();
            } catch (error) {
                // เบราว์เซอร์ที่ไม่รองรับ showModal() ก็ยังให้เห็นกล่อง (แค่ไม่มีฉากหลังทึบ)
                loader.setAttribute('open', '');
            }
        }

        function startProgress() {
            clearTimeout(loaderTimer);
            clearTimeout(safetyTimer);

            if (progress) progress.hidden = false;

            loaderTimer = setTimeout(showLoader, LOADER_DELAY);
            // เผื่อเบราว์เซอร์ไม่ได้เปลี่ยนหน้าจริง (เช่นโดนยกเลิก) อย่าให้ค้างคาหน้าจอไว้ตลอด
            safetyTimer = setTimeout(stopProgress, LOADER_TIMEOUT);
        }

        function stopProgress() {
            clearTimeout(loaderTimer);
            clearTimeout(safetyTimer);

            if (progress) progress.hidden = true;

            if (loader && loader.open) loader.close();
            if (loader) loader.removeAttribute('open');
        }

        // กดลิงก์ในระบบ (เมนู แถบกรอง เปลี่ยนหน้า แบ่งหน้า) ก็ต้องเห็นว่ากำลังโหลด
        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (!(event.target instanceof Element)) return;

            var link = event.target.closest('a[href]');
            if (!link || link.hasAttribute('download')) return;
            if (link.target && link.target !== '_self') return;
            if (link.origin !== window.location.origin) return;

            var href = link.getAttribute('href');
            // ลิงก์ในหน้า เบอร์โทร อีเมล และลิงก์ที่ชี้หน้าเดิมเป๊ะ ๆ ไม่ได้โหลดหน้าใหม่
            if (!href || href.charAt(0) === '#') return;
            if (link.href === window.location.href) return;

            startProgress();
        });

        document.addEventListener('submit', function (event) {
            // ฟอร์มลบมี confirm() อยู่ ถ้าผู้ใช้กดยกเลิกต้องไม่ล็อกปุ่มค้างไว้
            if (event.defaultPrevented) return;

            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = '1';
            startProgress();

            // ต้องรอให้เบราว์เซอร์เก็บค่าของปุ่มที่กดไปก่อนหนึ่งจังหวะ ถ้าสั่ง disabled ทันทีตรงนี้
            // ชื่อกับค่าของปุ่มจะหายไปจากข้อมูลที่ส่ง (เช่นปุ่ม "ล้างสถานะนี้" ที่ส่ง clear=1)
            setTimeout(function () {
                submitButtonsOf(form).forEach(function (button) {
                    button.disabled = true;
                    button.dataset.lockedBySubmit = '1';

                    if (button.dataset.busy) {
                        button.dataset.idleText = button.textContent;
                        button.textContent = button.dataset.busy;
                    }
                });
            }, 0);
        });

        // กดย้อนกลับมา เบราว์เซอร์คืนหน้าเดิมจากแคชพร้อมปุ่มที่ถูกปิดไว้ ต้องปลดล็อกให้
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;

            document.querySelectorAll('[data-locked-by-submit]').forEach(function (button) {
                button.disabled = false;
                delete button.dataset.lockedBySubmit;

                if (button.dataset.idleText) {
                    button.textContent = button.dataset.idleText;
                    delete button.dataset.idleText;
                }
            });

            document.querySelectorAll('form[data-submitting]').forEach(function (form) {
                delete form.dataset.submitting;
            });

            stopProgress();
        });
    })();
</script>

@stack('scripts')
</body>
</html>
