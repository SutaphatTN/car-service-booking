<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'นัดหมาย') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%231d4ed8'/><g transform='translate(3.6 4.2) scale(0.775)' fill='none' stroke='%23fff' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'><path d='M8 15l2.6-5.2A3 3 0 0 1 13.3 8h5.4a3 3 0 0 1 2.7 1.8L24 15'/><path d='M5 15h22a2 2 0 0 1 2 2v4h-3.2M8.2 21H3v-4a2 2 0 0 1 2-2'/><circle cx='10.5' cy='21' r='2.8'/><circle cx='21.5' cy='21' r='2.8'/><path d='M13.3 21h5.4'/></g></svg>">
</head>
<body>
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
                    <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
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

@stack('scripts')
</body>
</html>
