@php($isAdmin = $role === 'admin')
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $isAdmin ? 'دخول الإدارة' : 'دخول خدمة العملاء' }} — {{ $appName }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="auth">
    <section class="auth-side {{ $role }}">
        <div class="row" style="gap:12px">
            <div class="brand-mark" style="width:64px;height:64px;background:#000;padding:3px;border-radius:16px"><img src="{{ asset('img/logo.jpg') }}" alt="Bajaj Qena" style="width:100%;height:100%;object-fit:contain;border-radius:12px"></div>
            <div><div class="brand-name" style="font-size:20px">{{ $appName }}</div><div class="brand-sub" style="color:#a9bddf">إدارة المبيعات والتقسيط</div></div>
        </div>
        <div>
            @if($isAdmin)
                <h2>تحكّم كامل في المبيعات،<br>الأقساط والفريق.</h2>
                <p class="lead">لوحة إدارة متكاملة بتقارير تفصيلية، تتبع لكل عميل ومن أدخله، واستيراد وتصدير Excel بنماذج ثابتة.</p>
                <div class="auth-feats">
                    <div><i class="fa-solid fa-chart-column"></i> تقارير احترافية وتصدير Excel</div>
                    <div><i class="fa-solid fa-route"></i> تتبع العميل والموظف المسؤول</div>
                    <div><i class="fa-solid fa-shield-halved"></i> سجل أمان وحماية من تسريب البيانات</div>
                </div>
            @else
                <h2>خدمة عملاء أسرع،<br>متابعة أدق.</h2>
                <p class="lead">سجّل العملاء، تابع مواعيدهم، واحسب الأقساط في ثوانٍ من مكان واحد.</p>
                <div class="auth-feats">
                    <div><i class="fa-solid fa-calendar-check"></i> متابعات يومية مرتبة حسب الأولوية</div>
                    <div><i class="fa-solid fa-calculator"></i> حاسبة أقساط فورية</div>
                    <div><i class="fa-solid fa-user-plus"></i> تسجيل العملاء مع منع التكرار</div>
                </div>
            @endif
        </div>
        <div class="small" style="color:#8ea6d1">© {{ date('Y') }} {{ $appName }} — جميع الحقوق محفوظة</div>
    </section>

    <section class="auth-form-wrap">
        <div class="auth-card">
            <span class="role-chip {{ $role }}"><i class="fa-solid {{ $isAdmin ? 'fa-user-shield' : 'fa-headset' }}"></i> {{ $isAdmin ? 'بوابة الإدارة' : 'بوابة خدمة العملاء' }}</span>
            <h1>تسجيل الدخول</h1>
            <p class="sub">أدخل بيانات حسابك للمتابعة</p>

            <form method="POST" action="{{ $isAdmin ? route('admin.login') : route('staff.login') }}" autocomplete="off">
                @csrf
                @if($errors->any())
                    <div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i><div>{{ $errors->first() }}</div></div>
                @endif
                <div class="field mb">
                    <label>اسم المستخدم</label>
                    <div class="input-icon"><i class="fa-regular fa-user"></i><input class="input" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" dir="ltr" style="text-align:right"></div>
                </div>
                <div class="field mb">
                    <label>كلمة المرور</label>
                    <div class="input-icon" style="position:relative"><i class="fa-solid fa-lock"></i><input class="input" type="password" id="pw" name="password" required autocomplete="current-password" dir="ltr" style="text-align:right;padding-left:40px">
                        <button type="button" class="pw-toggle" data-pw="pw"><i class="fa-regular fa-eye"></i></button></div>
                </div>
                <label class="check mb"><input type="checkbox" name="remember" value="1"> تذكرني على هذا الجهاز</label>
                <button class="btn-login {{ $role }}" type="submit">دخول <i class="fa-solid fa-arrow-left" style="margin-right:6px"></i></button>
            </form>

            <a class="switch-link" href="{{ $isAdmin ? route('staff.login') : route('admin.login') }}">
                {{ $isAdmin ? 'الدخول كموظف خدمة عملاء' : 'الدخول كمدير للنظام' }} <i class="fa-solid fa-arrow-left"></i>
            </a>
        </div>
    </section>
</div>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
