<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') {{ trim($__env->yieldContent('title')) }}</title>
    <link rel="stylesheet" href="{{ asset_version('css/app.css') }}">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%231d4ed8'/><g transform='translate(3.6 4.2) scale(0.775)' fill='none' stroke='%23fff' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'><path d='M8 15l2.6-5.2A3 3 0 0 1 13.3 8h5.4a3 3 0 0 1 2.7 1.8L24 15'/><path d='M5 15h22a2 2 0 0 1 2 2v4h-3.2M8.2 21H3v-4a2 2 0 0 1 2-2'/><circle cx='10.5' cy='21' r='2.8'/><circle cx='21.5' cy='21' r='2.8'/><path d='M13.3 21h5.4'/></g></svg>">
</head>
<body>
<main class="wrap error-page">
    <div class="card">
        <div class="error-code">@yield('code')</div>
        <h2>@yield('title')</h2>
        <p class="hint">@yield('message')</p>

        @hasSection('detail')
            <div class="alert error" style="text-align:start">@yield('detail')</div>
        @endif

        <div class="actions" style="justify-content:center">
            <a href="{{ url('/') }}"><button type="button" class="primary">กลับหน้าหลัก</button></a>
            <a href="{{ url('/login') }}"><button type="button" class="ghost">ไปหน้าเข้าสู่ระบบ</button></a>
        </div>
    </div>
</main>
</body>
</html>
