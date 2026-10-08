<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'لوحة التحكم') — {{ $appName }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='24' fill='%232563eb'/%3E%3Cpath d='M22 62h56l-6-18H28l-6 18zm10 8a6 6 0 100-12 6 6 0 000 12zm36 0a6 6 0 100-12 6 6 0 000 12z' fill='white'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
@php
    $me = auth()->user();
    $isAdmin = $me->isAdmin();
    $badge = ['fu' => 0, 'alerts' => 0];
    try {
        $badge['fu'] = \App\Models\Followup::visibleTo($me)->pending()->whereDate('due_date', '<=', today())->count();
        if ($isAdmin) { $badge['alerts'] = \App\Models\ActivityLog::where('action', 'security.alert')->where('created_at', '>=', now()->subDay())->count(); }
    } catch (\Throwable $e) {}
    $wm = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="360" height="200"><text x="20" y="110" transform="rotate(-24 180 100)" font-family="Arial" font-size="15" font-weight="700" fill="#0b1b3a">'.e($me->name).' · '.e($me->username).' · '.now()->format('Y/m/d H:i').'</text></svg>');
@endphp
<body data-check-phone="{{ $me->allows('customers', 'create') ? route('customers.check-phone') : '' }}" class="{{ $me->isAgent() ? 'agent-view secure-view' : '' }} {{ $me->isAgent() && empty($allowPrint) ? 'agent-print-block' : '' }}" @if(!empty($allowPrint)) data-allow-print="1" @endif>
@if($me->isAgent())<div class="watermark" style="background-image:url('data:image/svg+xml;utf8,{{ $wm }}')"></div>@endif

<div class="overlay" id="overlay"></div>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-mark" style="background:#000;padding:3px"><img src="{{ asset('img/logo.jpg') }}" alt="Bajaj Qena" style="width:100%;height:100%;object-fit:contain;border-radius:8px"></div>
            <div><div class="brand-name">{{ $appName }}</div><div class="brand-sub">إدارة المبيعات والتقسيط</div></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">الرئيسية</div>
            @if($me->allows('dashboard'))<a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="fa-solid fa-chart-pie"></i> لوحة التحكم</a>@endif
            @if($me->allows('customers'))<a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}"><i class="fa-solid fa-users"></i> العملاء</a>@endif
            @if($me->allows('followups'))<a href="{{ route('followups.index') }}" class="nav-link {{ request()->routeIs('followups.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-check"></i> المتابعات
                @if($badge['fu'])<span class="nav-badge">{{ $badge['fu'] }}</span>@endif</a>@endif
            @if($me->allows('calculator'))<a href="{{ route('calculator') }}" class="nav-link {{ request()->routeIs('calculator') ? 'active' : '' }}"><i class="fa-solid fa-calculator"></i> حاسبة الأقساط</a>@endif
            @if($me->allows('vehicles'))<a href="{{ route('vehicles.index') }}" class="nav-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}"><i class="fa-solid fa-warehouse"></i> المخزن والمركبات</a>@endif
            @if($me->allows('documents'))<a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}"><i class="fa-solid fa-file-pdf"></i> المستندات والطباعة</a>@endif
            @if($me->allows('customers', 'create'))<a href="{{ route('customers.create') }}" class="nav-cta"><i class="fa-solid fa-user-plus"></i> عميل جديد</a>@endif
            @if($me->allows('summary'))<a href="{{ route('summary.index') }}" class="nav-link {{ request()->routeIs('summary.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-line"></i> ملخص الفترات</a>@endif

            @if($me->allows('reports') || $me->allows('products'))
                <div class="nav-label">التقارير والمنتجات</div>
                @if($me->allows('reports'))<a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"><i class="fa-solid fa-chart-column"></i> التقارير</a>@endif
                @if($me->allows('products'))<a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}"><i class="fa-solid fa-tags"></i> المنتجات والأسعار</a>@endif
            @endif
            @if($isAdmin)
                <div class="nav-label">الإدارة</div>

                <a href="{{ route('data.index') }}" class="nav-link {{ request()->routeIs('data.*') ? 'active' : '' }}"><i class="fa-solid fa-file-excel"></i> استيراد وتصدير Excel</a>

                <a href="{{ route('roles.index') }}" class="nav-link {{ request()->routeIs('roles.*') ? 'active' : '' }}"><i class="fa-solid fa-user-lock"></i> الأدوار والصلاحيات</a>
                <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}"><i class="fa-solid fa-database"></i> النسخ الاحتياطي</a>
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"><i class="fa-solid fa-user-tie"></i> الموظفون</a>
                <a href="{{ route('logs.index') }}" class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}"><i class="fa-solid fa-shield-halved"></i> سجل النشاط والأمان
                    @if($badge['alerts'])<span class="nav-badge amber">{{ $badge['alerts'] }}</span>@endif</a>
                <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}"><i class="fa-solid fa-sliders"></i> الإعدادات</a>
            @else
                <div class="nav-label">حسابي</div>
                <a href="{{ route('my.activity') }}" class="nav-link {{ request()->routeIs('my.activity') ? 'active' : '' }}"><i class="fa-solid fa-clock-rotate-left"></i> نشاطي وأدائي</a>
            @endif
            <a href="{{ route('password.edit') }}" class="nav-link {{ request()->routeIs('password.*') ? 'active' : '' }}"><i class="fa-solid fa-key"></i> تغيير كلمة المرور</a>
        </nav>
        <div class="sidebar-user">
            <div class="avatar {{ $isAdmin ? '' : 'agent' }}">{{ $me->initials }}</div>
            <div class="who"><b>{{ $me->name }}</b><span>{{ $me->role_label }}</span></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button title="تسجيل الخروج" data-nolock><i class="fa-solid fa-right-from-bracket"></i></button></form>
        </div>
    </aside>

    <div class="main">
        <header class="topbar no-print">
            <button class="menu-btn" data-menu aria-label="القائمة"><i class="fa-solid fa-bars"></i></button>
            <h1>@yield('heading', 'لوحة التحكم')@hasSection('sub')<small>@yield('sub')</small>@endif</h1>
            <form class="top-search" action="{{ route('customers.index') }}" method="GET">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="q" placeholder="بحث سريع: اسم / هاتف / كود" autocomplete="off">
            </form>
            <a class="top-icon" href="{{ route('followups.index') }}" title="المتابعات المستحقة"><i class="fa-regular fa-bell"></i>@if($badge['fu'])<span class="dot">{{ $badge['fu'] }}</span>@endif</a>
        </header>

        <main class="content">
            @if(session('success'))<div data-toast="{{ session('success') }}"></div>@endif
            @if(session('warning'))<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div>{{ session('warning') }}</div></div>@endif
            @if($errors->any() && !View::hasSection('own-errors'))
                <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><div><b>تعذر إتمام العملية:</b><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<div id="toasts" class="toasts"></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
