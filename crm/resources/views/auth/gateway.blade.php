<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>بوابة الدخول — {{ $appName }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="gateway">
    <div class="gateway-inner">
        <div style="width:130px;height:130px;border-radius:28px;margin:0 auto;background:#000;padding:6px;box-shadow:0 14px 40px rgba(0,0,0,.45)"><img src="{{ asset('img/logo.jpg') }}" alt="Bajaj Qena" style="width:100%;height:100%;object-fit:contain;border-radius:22px"></div>
        <h1>{{ $appName }}</h1>
        <p>نظام إدارة المبيعات والتقسيط وخدمة العملاء — اختر بوابة الدخول المناسبة</p>
        <div class="gate-cards">
            <a href="{{ route('admin.login') }}" class="gate-card admin">
                <div class="gi"><i class="fa-solid fa-user-shield"></i></div>
                <h3>دخول الإدارة</h3><span>لوحة التحكم الكاملة، التقارير، الموظفون والإعدادات</span>
            </a>
            <a href="{{ route('staff.login') }}" class="gate-card agent">
                <div class="gi"><i class="fa-solid fa-headset"></i></div>
                <h3>دخول خدمة العملاء</h3><span>العملاء، المتابعات وحاسبة الأقساط</span>
            </a>
        </div>
    </div>
</div>
</body>
</html>
