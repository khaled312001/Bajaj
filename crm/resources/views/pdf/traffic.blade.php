<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
    body { font-family: tajawal; font-size: 13pt; font-weight: bold; color: #000; direction: rtl; }
    table { border-collapse: collapse; }
    .serial { text-align: center; font-size: 11pt; direction: ltr; margin: 0; }
    h1 { text-align: center; font-size: 27pt; margin: 0; }
    .sub { text-align: center; font-size: 12pt; margin: 0; }
    p { margin: 0; }
    .row { margin-top: 2.6mm; }
    .ind1 { padding-right: 12mm; }
    .ind2 { padding-right: 34mm; }
    .f { width: 161mm; margin-top: 2mm; table-layout: fixed; }
    .f td { padding: 1.3mm 0; font-size: 13pt; vertical-align: top; }
    .f td.k { width: 31mm; }
    .f td.v { width: 64mm; }
    .f td.k2 { width: 33mm; }
    .ltr { direction: ltr; text-align: right; }
    .center { text-align: center; line-height: 1.75; }
</style>
</head>
<body>
<p class="serial">{{ $d['mobaya_no'] ?? $d['doc_no'] ?? '' }}&nbsp;</p>
<h1>{{ \App\Support\ArabicTypography::kashidaTitle(['mobaya' => 'مبايعة', 'renewal' => 'خطاب تجديد', 'clearance' => 'مخالصة'][$type]) }}</h1>
@if($type === 'mobaya' && ! empty($d['retain_title']))<p class="sub">( مع الاحتفاظ بحق الملكية )</p>@endif

<p class="row" style="margin-top:5mm">تحريرا في : <span class="ltr">{{ $docDate }}</span></p>
<p class="row ind1">السيد مدير إدارة مرور {{ $d['traffic_dept'] ?? 'قنا' }}</p>
<p class="row ind2">تحية طيبة وبعد...</p>

@if($type === 'mobaya')
    <p class="row">نفيد سيادتكم علما بأننا قد بعنا</p>
@elseif($type === 'renewal')
    <table class="f"><tr><td>ارجو من سيادتكم التكرم بتجديد ترخيص</td><td style="text-align:left">لمدة سنه</td></tr></table>
@else
    <p class="row">نود الإفادة بأن :</p>
@endif

<table class="f">
    <tr><td class="k">ماركة :</td><td class="v">{{ $d['product'] ?? '' }}</td><td class="k2">اللون :</td><td>{{ $d['color'] ?? '' }}</td></tr>
    <tr><td class="k">شاسيه رقم :</td><td class="v ltr">{{ $d['chassis'] ?? '' }}</td><td class="k2">ماتور رقم :</td><td class="ltr">{{ $d['motor'] ?? '' }}</td></tr>
    <tr><td class="k">موديل :</td><td class="v">{{ $d['model'] ?? '' }}</td><td class="k2">عدد السلندارت :</td><td>{{ $d['cylinders'] ?? '1' }}</td></tr>
    <tr><td class="k">الي السيد :</td><td colspan="3">{{ $d['customer_name'] ?? '' }}</td></tr>
    <tr><td class="k">العنوان :</td><td colspan="3">{{ $d['address'] ?? '' }}</td></tr>
    <tr><td class="k">الرقم القومي :</td><td colspan="3" class="ltr">{{ $d['nat_id'] ?? '' }}</td></tr>
</table>

@if($type === 'clearance')
    <p class="row center">بانه قد قام بسداد كافة المستحقات المالية وسداد كامل الأقساط وليس لدينا مانع من رفع حظر البيع عنه وتمكين المالك من نقل ملكيتها او اتخاذ كافة الإجراءات المرورية اللازمة باسمه</p>
@endif
<p class="row center">الرجاء التكرم بإتخاذ اللازم نحو ترخيص المعدة<br>باسم المشتري المذكور وشكرا<br>وتفضلوا سيادتكم بقبول وافر الاحترام</p>

<table style="width:100%;margin-top:6mm"><tr><td style="width:62%"></td><td>التوقيع</td></tr></table>
<table style="width:100%;margin-top:11mm"><tr><td style="width:70%"></td><td>اعتماد مرور {{ $d['traffic_dept'] ?? 'قنا' }} (&nbsp;&nbsp;{{ $d['traffic_code'] ?? '' }}&nbsp;&nbsp;)</td></tr></table>
</body>
</html>
