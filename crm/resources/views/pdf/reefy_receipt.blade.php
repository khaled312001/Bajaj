<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<style>
    body { font-family: tajawal; font-size: 13.5pt; color: #000; direction: rtl; }
    p { line-height: 1.75; margin: 0 0 3.2mm 0; text-align: right; }
    h1 { text-align: center; font-size: 18pt; font-weight: bold; text-decoration: underline; margin: 6mm 0 8mm 0; }
    .gap { margin-top: 5mm; }
    .dots { letter-spacing: 0; }
</style>
</head>
@php
    $v = fn ($k, $dash = '..........................................') => (isset($d[$k]) && trim((string) $d[$k]) !== '') ? $d[$k] : $dash;
    $amount = (float) ($d['price'] ?? 0);
    $words = $amount > 0 ? str_replace(' جنيه مصري فقط لا غير', '', \App\Support\ArabicNumber::egp($amount)) : '..................';
@endphp
<body>
<h1>إقرار استلام منتج</h1>

<p>السادة / إدارة شركة ريفي لتمويل المشروعات متناهي الصغر<br>تحية طيبة وبعد،،،</p>

<p class="gap">أقــر أنا/ <b>{{ $company['name'] }}</b> &nbsp;&nbsp; (اسم التاجر/المورد)<br>
الكائن بنشاط ({{ $v('activity') }})<br>
بأني قد سلمت للعميل / <b>{{ $v('customer_name') }}</b><br>
رقم بطاقة رقم قومي : {{ $v('nat_id') }}</p>

<p class="gap">المنتج/السلعة : <b>{{ $v('product') }}</b><br>
الكمية : {{ $v('quantity', '1') }}<br>
القيمة: ({{ $words }} جنيهاً مصرياً فقط لا غير) @if($amount > 0)— {{ number_format($amount, 2) }} جنيه @endif</p>

<p class="gap">وذلك بموجب التمويل المقدم من شركة ريفي، وقد تم التسليم بحالة جيدة وصالحة للاستعمال ودون أي مسؤولية على الشركة بعد إتمام الاستلام.</p>

<p>ويعتبر هذا الإقرار بمثابة تأكيد رسمي باستلام العميل للمنتج/السلعة من قبلي، ولا يحق له الرجوع على شركة ريفي بأي مطالبات تخص طبيعة المنتج أو جودته.</p>

<p class="gap">تحريراً في {{ $docDate ?? now()->format('Y/m/d') }}</p>

<p>التاجر/المورد : {{ $company['name'] }}<br>
التوقيع والختم :</p>

<p class="gap">إقرار العميل بالاستلام:<br>
أقر أنا/ <b>{{ $v('customer_name') }}</b> باستلام المنتج/السلعة الموضحة أعلاه بحالة جيدة وصالحة للاستعمال.</p>

<p class="gap">توقيع العميل ............................</p>
</body>
</html>
