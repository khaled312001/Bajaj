<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>الصفحة غير موجودة</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><div class="err"><div>
<div class="code-big">404</div>
<h1 style="font-size:26px;font-weight:900;margin:8px 0">الصفحة غير موجودة</h1>
<p class="muted" style="margin-bottom:22px">@if(isset($exception) && $exception->getMessage() && in_array(404, [403, 429])){{ $exception->getMessage() }}@elseالرابط غير صحيح أو أن العنصر غير متاح لحسابك.@endif</p>
<a class="btn btn-primary" href="{{ url('/') }}">العودة للرئيسية</a>
</div></div></body></html>
