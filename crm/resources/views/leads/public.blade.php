<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — {{ $appName }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body { background: linear-gradient(160deg, var(--navy-900), var(--navy-800)); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 14px; }
        .lead-card { max-width: 560px; width: 100%; background: #fff; border-radius: 20px; padding: 30px 26px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
        .lead-head { text-align: center; margin-bottom: 20px; }
        .lead-head img { width: 64px; height: 64px; border-radius: 16px; background: #000; padding: 3px; object-fit: contain; margin-bottom: 10px; }
        .lead-head h1 { font-size: 20px; margin: 4px 0; }
        .lead-head p { color: var(--muted); font-size: 13.5px; margin: 0; }
    </style>
</head>
<body>
<div class="lead-card">
    <div class="lead-head">
        <img src="{{ asset('img/logo.jpg') }}" alt="{{ $appName }}">
        <h1>{{ $title }}</h1>
        <p>{{ $intro }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb"><i class="fa-solid fa-circle-check"></i><div>{{ session('success') }}</div></div>
    @else
        @if($errors->any())<div class="alert alert-error mb"><i class="fa-solid fa-circle-xmark"></i><div>{{ $errors->first() }}</div></div>@endif
        <form method="POST" action="{{ route('lead.store') }}">
            @csrf
            <div class="form-grid cols-2">
                <div class="field span-2"><label>الاسم <span class="req">*</span></label><input class="input" name="name" value="{{ old('name') }}" required maxlength="150"></div>
                <div class="field span-2"><label>رقم الهاتف <span class="req">*</span></label><input class="input" name="phone" value="{{ old('phone') }}" required inputmode="tel" dir="ltr" style="text-align:right" placeholder="01xxxxxxxxx"></div>
                <div class="field span-2"><label>المركبة المطلوبة</label><select class="input" name="vehicle"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('vehicle') as $g)<option @selected(old('vehicle') === $g)>{{ $g }}</option>@endforeach</select></div>
                @include('partials.geo_fields', ['govValue' => old('governorate'), 'centerValue' => old('district')])
                <div class="field span-2"><label>العنوان بالتفصيل</label><input class="input" name="address" value="{{ old('address') }}" maxlength="250"></div>
            </div>
            <div class="form-actions" style="justify-content:stretch;margin-top:20px">
                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;padding:13px"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
            </div>
        </form>
    @endif
</div>
<script>window.EGYPT_GEO = @json(\App\Support\EgyptGeo::DATA);</script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
