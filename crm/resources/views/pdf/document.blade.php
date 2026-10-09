@extends('pdf.layout')
@section('body')
@php
    $v = fn ($k, $dash = '................') => (isset($d[$k]) && trim((string) $d[$k]) !== '') ? $d[$k] : $dash;
    $money = fn ($x) => number_format((float) $x, 2);
    $co = $company['name'];
    $date = fn ($k) => ! empty($d[$k]) ? \Illuminate\Support\Carbon::parse($d[$k])->format('Y/m/d') : '................';
    $sign = function ($label) { return '<table class="sign"><tr><td><div class="line">&nbsp;</div>' . $label . '</td></tr></table>'; };
@endphp

@php
    $docTitle = $type === 'mobaya' && ! empty($d['retain_title']) ? 'مبايعة (مع الاحتفاظ بحق الملكية)' : $def['title'];
@endphp
<h1 class="title doc-title">{{ \App\Support\ArabicTypography::kashidaTitle($docTitle) }}</h1>
<div class="title-line">رقم المستند: {{ $docNo }}</div>

@switch($type)

@case('quote')
    <p class="text">السيد / <b>{{ $v('customer_name') }}</b></p>
    <p class="text">تحية طيبة وبعد،،،</p>
    <p class="text">تتشرف شركة <b>{{ $co }}</b> أن تقدم لسيادتكم عرضها المالي الخاص بـ <b>{{ $v('product') }}</b>.</p>
    <div class="box"><span class="strong">السعر:</span> <b style="font-size:16pt;color:#1d4ed8">{{ number_format((float) ($d['price'] ?? 0)) }} جنيه</b><br>
        <span>{{ \App\Services\DocumentService::words($d['price'] ?? 0) }}</span></div>
    <p class="text"><b>طريقة السداد:</b> نقداً أو تحويل بنكي باسم / {{ $co }}@if($company['bank']) على حسابنا رقم ( {{ $company['bank'] }} )@endif</p>
    <p class="text"><b>التسليم:</b> الاستلام من مقر الشركة الكائن في: {{ $company['address'] ?: '................' }}@if($company['phone'])<br>هاتف: <span dir="ltr">{{ $company['phone'] }}</span>@endif</p>
    <p class="text"><b>سريان العرض:</b> {{ $v('validity', 'ثلاثة أيام من تاريخه') }}</p>
    @if(! empty($d['notes']))<p class="text"><b>ملاحظات:</b> {!! nl2br(e($d['notes'])) !!}</p>@endif
    {!! $sign('المدير المسؤول') !!}
@break

@case('statement')
    <table class="kv"><tr><td class="k">العميل</td><td>{{ $v('customer_name') }}</td><td class="k">المركبة</td><td>{{ $deal->vehicle ?? '—' }}</td></tr>
        <tr><td class="k">السعر الإجمالي</td><td>{{ $money($deal->total_price) }}</td><td class="k">المقدم</td><td>{{ $money($deal->down_payment) }}</td></tr>
        <tr><td class="k">الممول</td><td>{{ $money($deal->financed_amount) }}</td><td class="k">جهة التقسيط</td><td>{{ $deal->finance_entity ?: '—' }}</td></tr></table>
    <table class="data"><thead><tr><th>#</th><th>الاستحقاق</th><th>القسط</th><th>المسدد</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody>
    @foreach($deal->installments as $i)
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td class="n">{{ $i->number }}</td><td class="n">{{ $i->due_date->format('Y/m/d') }}</td><td class="n">{{ $money($i->amount) }}</td><td class="n">{{ $money($i->paid_amount) }}</td><td class="n">{{ $money($i->remaining) }}</td><td class="n">{{ $i->state_label }}</td></tr>
    @endforeach
    <tr><td colspan="2" class="n"><b>الإجمالي</b></td><td class="n"><b>{{ $money($deal->installments->sum('amount')) }}</b></td><td class="n"><b>{{ $money($deal->installments->sum('paid_amount')) }}</b></td><td class="n"><b>{{ $money($deal->balance) }}</b></td><td></td></tr></tbody></table>
    @if(! empty($d['notes']))<p class="note">{!! nl2br(e($d['notes'])) !!}</p>@endif
    {!! $sign('اعتماد المحاسب') !!}
@break

@case('invoice')
    <table class="kv"><tr><td class="k">العميل</td><td>{{ $v('customer_name') }}</td><td class="k">الرقم القومي</td><td>{{ $v('nat_id') }}</td></tr>
        <tr><td class="k">العنوان</td><td colspan="3">{{ $v('address') }}</td></tr></table>
    <table class="data"><thead><tr><th>البيان</th><th>الموديل</th><th>اللون</th><th>الشاسيه</th><th>الموتور</th></tr></thead><tbody>
        <tr><td class="n">{{ $v('product', '—') }}</td><td class="n">{{ $v('model', '—') }}</td><td class="n">{{ $v('color', '—') }}</td><td class="n" dir="ltr">{{ $v('chassis', '—') }}</td><td class="n" dir="ltr">{{ $v('motor', '—') }}</td></tr></tbody></table>
    <br><table class="data" style="width:60%"><tr><th>الإجمالي</th><td class="n"><b>{{ $money($d['price'] ?? 0) }} ج.م</b></td></tr>
        <tr><th>المدفوع</th><td class="n">{{ $money($d['paid'] ?? 0) }} ج.م</td></tr>
        <tr><th>المتبقي</th><td class="n"><b style="color:#b91c1c">{{ $money(max(0, (float) ($d['price'] ?? 0) - (float) ($d['paid'] ?? 0))) }} ج.م</b></td></tr>
        <tr><th>طريقة الدفع</th><td class="n">{{ $v('pay_method', '—') }}</td></tr></table>
    <p class="note">{{ \App\Services\DocumentService::words($d['price'] ?? 0) }}</p>
    @if(! empty($d['notes']))<p class="note">{!! nl2br(e($d['notes'])) !!}</p>@endif
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المستلم / العميل</td><td><div class="line">&nbsp;</div>المدير المسؤول</td></tr></table>
@break

@case('receipt')
    <div class="box"><p class="text">استلمنا من السيد / <b>{{ $v('customer_name') }}</b><br>مبلغ وقدره: <b style="font-size:15pt;color:#1d4ed8">{{ number_format((float) ($d['amount'] ?? 0), 2) }} جنيه</b><br>{{ \App\Services\DocumentService::words($d['amount'] ?? 0) }}<br>وذلك عن: <b>{{ $v('reason') }}</b> — طريقة الدفع: {{ $v('pay_method', 'نقدي') }}</p></div>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المستلم: {{ $v('received_by', '') }}</td><td><div class="line">&nbsp;</div>توقيع الدافع</td></tr></table>
@break

@case('finance_offer')
    <table class="kv"><tr><td class="k">اسم المورد</td><td>{{ $co }}</td></tr>
        <tr><td class="k">رقم السجل التجاري</td><td>{{ $company['register'] ?: '—' }}</td></tr>
        <tr><td class="k">رقم البطاقة الضريبية</td><td>{{ $company['tax'] ?: '—' }}</td></tr></table>
    <p class="text">السادة شركة <b>{{ $v('entity', 'ريفي') }}</b> — برجاء التفضل بالموافقة على عرض السعر الآتي:</p>
    <table class="kv">
        <tr><td class="k">اسم العميل</td><td>{{ $v('customer_name') }}</td></tr>
        <tr><td class="k">رقم الشاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td></tr>
        <tr><td class="k">رقم الماتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr>
        <tr><td class="k">نوع المعدة</td><td>{{ $v('product') }}</td></tr>
        <tr><td class="k">الموديل</td><td>{{ $v('model') }}</td></tr>
        <tr><td class="k">اللون</td><td>{{ $v('color') }}</td></tr>
        <tr><td class="k">السعر</td><td><b>{{ $money($d['price'] ?? 0) }} جنيه</b></td></tr>
        <tr><td class="k">تم دفع</td><td>{{ $money($d['paid'] ?? 0) }} جنيه</td></tr>
        <tr><td class="k">ويتبقى</td><td><b>{{ $money(max(0, (float) ($d['price'] ?? 0) - (float) ($d['paid'] ?? 0))) }} جنيه</b></td></tr></table>
    {!! $sign('توقيع المدير') !!}
@break

@case('finance_receipt')
    <p class="text serif">السادة / إدارة شركة <b>{{ $v('entity') }}</b><br>تحية طيبة وبعد،،،</p>
    <p class="text serif">أقر أنا / <b>{{ $co }}</b> (التاجر / المورد) الكائن بنشاط ( {{ $v('activity') }} ) بأنني قد سلمت للعميل / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}<br>المنتج / السلعة: <b>{{ $v('product') }}</b> — الكمية: {{ $v('quantity', '1') }}<br>القيمة: ( {{ $money($d['price'] ?? 0) }} جنيهاً ) {{ \App\Services\DocumentService::words($d['price'] ?? 0) }}.</p>
    <p class="text serif">وذلك بموجب التمويل المقدم من شركة {{ $v('entity') }}، وقد تم التسليم بحالة جيدة وصالحة للاستعمال ودون أي مسؤولية على الشركة بعد إتمام الاستلام. ويعتبر هذا الإقرار بمثابة تأكيد رسمي باستلام العميل للمنتج / السلعة من قبلي، ولا يحق له الرجوع على الشركة بأي مطالبات تخص طبيعة المنتج أو جودته.</p>
    <p class="text serif">تحريراً في: {{ now()->format('Y/m/d') }}</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>التاجر / المورد — التوقيع والختم</td><td><div class="line">&nbsp;</div>إقرار العميل بالاستلام: {{ $v('customer_name') }}</td></tr></table>
@break

@case('mobaya')
@case('renewal')
@case('clearance')
    <p class="text serif">السيد مدير إدارة مرور <b>{{ $v('traffic_dept', 'قنا') }}</b><br>تحية طيبة وبعد...</p>
    @if($type === 'mobaya')<p class="text serif">نفيد سيادتكم علماً بأننا قد بعنا{{ ! empty($d['retain_title']) ? ' (مع الاحتفاظ بحق الملكية)' : '' }}:</p>
    @elseif($type === 'renewal')<p class="text serif">أرجو من سيادتكم التكرم بتجديد ترخيص المركبة الآتية لمدة سنة:</p>
    @else<p class="text serif">نود الإفادة بأن المركبة الآتية:</p>@endif
    <table class="kv"><tr><td class="k">ماركة</td><td>{{ $v('product') }}</td><td class="k">اللون</td><td>{{ $v('color') }}</td></tr>
        <tr><td class="k">شاسيه رقم</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">ماتور رقم</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr>
        <tr><td class="k">موديل</td><td>{{ $v('model') }}</td><td class="k">عدد السلندرات</td><td>{{ $v('cylinders', '1') }}</td></tr>
        <tr><td class="k">{{ $type === 'mobaya' ? 'إلى السيد' : 'المملوكة للسيد' }}</td><td colspan="3">{{ $v('customer_name') }}</td></tr>
        <tr><td class="k">العنوان</td><td colspan="3">{{ $v('address') }}</td></tr>
        <tr><td class="k">الرقم القومي</td><td colspan="3">{{ $v('nat_id') }}</td></tr></table>
    @if($type === 'clearance')<p class="text serif">قد قام بسداد كافة المستحقات المالية وكامل الأقساط، وليس لدينا مانع من رفع حظر البيع عنه وتمكين المالك من نقل ملكيتها أو اتخاذ كافة الإجراءات المرورية اللازمة باسمه.</p>@endif
    <p class="text serif">الرجاء التكرم باتخاذ اللازم نحو ترخيص المعدة باسم المشتري المذكور وشكراً،<br>وتفضلوا سيادتكم بقبول وافر الاحترام.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>التوقيع</td><td><div class="line">&nbsp;</div>اعتماد مرور {{ $v('traffic_dept', 'قنا') }}</td></tr></table>
@break

@case('docs_receipt')
@case('vehicle_receipt')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}@if($type === 'docs_receipt') — كود: {{ $v('code') }}@endif<br>المقيم / {{ $v('address') }}</p>
    <p class="text serif">بأنني استلمت من شركة <b>{{ $co }}</b> {{ $type === 'docs_receipt' ? 'جميع الأوراق الخاصة بالمركبة' : 'المركبة' }} التالي بياناتها:</p>
    <table class="kv"><tr><td class="k">النوع</td><td>{{ $v('product') }}</td><td class="k">الموديل</td><td>{{ $v('model') }}</td></tr>
        <tr><td class="k">اللون</td><td>{{ $v('color') }}</td><td class="k">تاريخ الشراء</td><td>{{ $date('purchase_date') }}</td></tr>
        <tr><td class="k">شاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">موتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr>
        @if($type === 'docs_receipt')<tr><td class="k">مبايعة رقم</td><td colspan="3">{{ $v('mobaya_no') }}</td></tr>@endif</table>
    <p class="text serif">@if($type === 'docs_receipt')ويعد توقيعي على هذا الإقرار بمثابة مخالصة نهائية عن استلام الأوراق ولا يحق لي لاحقاً مطالبة الشركة بها.@else وأني استلمت جميع الملحقات الخاصة به ولا يحق لي أو للغير الرجوع على الشركة، وأتحمل كافة التبعات القانونية والمالية في حالة رجوع الغير على الشركة فيما يخص المركبة موضوع هذا الإقرار.@endif<br>وهذا إقرار مني بذلك.</p>
    <table class="kv"><tr><td class="k">تم التسليم بمعرفة</td><td>{{ $v('delivered_by') }}</td><td class="k">الموبايل</td><td>{{ $v('phone') }}</td></tr></table>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المستلم: {{ $v('customer_name') }}</td><td><div class="line">&nbsp;</div>التاريخ: {{ now()->format('Y/m/d') }}</td></tr></table>
@break

@case('broker_receipt')
    <p class="text serif">أقر أنا / <b>{{ $v('broker_name') }}</b> — رقم قومي: {{ $v('broker_nat') }}<br>المقيم / {{ $v('broker_address') }}</p>
    <p class="text serif">بأنني استلمت من شركة <b>{{ $co }}</b> المركبة التالي بياناتها:</p>
    <table class="kv"><tr><td class="k">النوع</td><td>{{ $v('product') }}</td><td class="k">الموديل</td><td>{{ $v('model') }}</td></tr>
        <tr><td class="k">اللون</td><td>{{ $v('color') }}</td><td class="k">تاريخ الشراء</td><td>{{ $date('purchase_date') }}</td></tr>
        <tr><td class="k">شاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">موتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr></table>
    <p class="text serif">وذلك لتوصيلها للسيد / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }} — المقيم / {{ $v('address') }}<br>وذلك على سبيل الأمانة، وفي حالة عدم تسليمها أكون خائناً للأمانة وأتحمل كافة المسؤوليات القانونية، وذلك بدون أدنى مسؤولية على الشركة. وأتحمل كافة التبعات القانونية والمالية في حالة رجوع الغير على الشركة فيما يخص المركبة موضوع هذا الإقرار.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المقر بما فيه: {{ $v('broker_name') }}</td><td><div class="line">&nbsp;</div>التاريخ: {{ now()->format('Y/m/d') }}</td></tr></table>
@break

@case('bulk_receipt')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}<br>المقيم / {{ $v('address') }}</p>
    <p class="text serif">بأنني استلمت من شركة <b>{{ $co }}</b> المركبات التالي بياناتها:</p>
    <table class="data"><thead><tr><th>#</th><th>النوع</th><th>الموديل</th><th>اللون</th><th>الشاسيه</th><th>الموتور</th></tr></thead><tbody>
    @foreach(preg_split('/\R+/', trim((string) ($d['items'] ?? ''))) as $line)
        @continue(trim($line) === '')
        @php
            $c = array_pad(array_map('trim', explode('|', $line)), 5, '');
        @endphp
        <tr class="{{ $loop->even ? 'alt' : '' }}"><td class="n">{{ $loop->iteration }}</td><td class="n">{{ $c[0] }}</td><td class="n">{{ $c[1] }}</td><td class="n">{{ $c[2] }}</td><td class="n" dir="ltr">{{ $c[3] }}</td><td class="n" dir="ltr">{{ $c[4] }}</td></tr>
    @endforeach</tbody></table>
    <p class="text serif">وأني استلمت جميع الملحقات الخاصة بها ولا يحق لي أو للغير الرجوع على الشركة.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المستلم: {{ $v('customer_name') }}</td><td><div class="line">&nbsp;</div>التاريخ: {{ now()->format('Y/m/d') }}</td></tr></table>
@break

@case('cash_received')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b><br>بطاقة رقم: {{ $v('nat_id') }}<br>مقيم في: {{ $v('address') }}</p>
    <p class="text serif">بأنني قمت باستلام مبلغ / <b>{{ number_format((float) ($d['amount'] ?? 0)) }} جنيه</b> فقط — {{ \App\Services\DocumentService::words($d['amount'] ?? 0) }}<br>وهو عبارة عن {{ $v('reason') }}.</p>
    <p class="text serif">وبموجب هذا الإقرار لا يحق لي الرجوع على الشركة بشأن هذا المبلغ الذي قمت باستلامه حالاً أو مستقبلاً بأي مطالبات أو دعاوى أو تعويضات أو شكاوى من أي نوع وبأي شكل.<br>وهذا إقرار مني بذلك.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المقر بما فيه: {{ $v('customer_name') }}</td><td><div class="line">&nbsp;</div>التاريخ: {{ now()->format('Y/m/d') }}</td></tr></table>
    <p class="text serif" style="margin-top:8mm">أقر أنا / ................ أنه تم توقيع هذا الإقرار أمامي وفي حضوري بعد التأكد من شخصية الموقع.@if(! empty($d['witness'])) ({{ $d['witness'] }})@endif</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>الاسم والتوقيع</td></tr></table>
@break

@case('waiver_amount')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}<br>المقيم / {{ $v('address') }}</p>
    <p class="text serif">بأن المبلغ <b>{{ number_format((float) ($d['amount'] ?? 0)) }} جنيه</b> ({{ \App\Services\DocumentService::words($d['amount'] ?? 0) }}) الذي تم تحويله أو إيداعه بحساب شركة <b>{{ $co }}</b> {{ $v('bank') }} هو خاص بشراء: <b>{{ $v('product') }}</b> التالي بياناتها:</p>
    <table class="kv"><tr><td class="k">شاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">موتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr><tr><td class="k">تاريخ الشراء</td><td colspan="3">{{ $date('purchase_date') }}</td></tr></table>
    <p class="text serif">باسم: <b>{{ $v('to_name') }}</b> — رقم قومي: {{ $v('to_nat') }} — المقيم / {{ $v('to_address') }}<br>وبأني أتحمل كافة التبعات القانونية والمالية في حالة رجوعي أو رجوع الغير على الشركة فيما يخص هذا المبلغ موضوع هذا الإقرار.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المقر بما فيه: {{ $v('customer_name') }}</td><td><div class="line">&nbsp;</div>التاريخ: {{ now()->format('Y/m/d') }}</td></tr></table>
@break

@case('transfer_rights')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b>، رقم قومي {{ $v('nat_id') }}، المقيم / {{ $v('address') }}،<br>بأنني قد تنازلت تنازلاً نهائياً لا رجعة فيه لصالح السيد / <b>{{ $v('to_name') }}</b>، رقم قومي {{ $v('to_nat') }}، المقيم / {{ $v('to_address') }}،<br>عن كافة حقوقي المتعلقة بالمبلغ المسدد في شراء المركبة <b>{{ $v('product') }}</b>، والبالغ إجمالي قيمتها <b>{{ number_format((float) ($d['price'] ?? 0)) }}</b> جنيه مصري فقط ({{ \App\Services\DocumentService::words($d['price'] ?? 0) }}).</p>
    <p class="text serif">وقد تم سداد قيمة المركبة: {{ $v('payment_note') }}.<br>وبموجب هذا الإقرار أوافق على أن يستحق السيد / {{ $v('to_name') }} كافة الحقوق المتعلقة بالمركبة، وله الحق في استكمال إجراءات الشراء والتقسيط والتعاقد، وأن يتم تسجيل وتحويل ملكية المركبة باسمه لدى الشركة والجهات المختصة.<br>كما أقر بأنني لا يحق لي بعد هذا التنازل الرجوع على الشركة أو السيد المذكور أو المطالبة بأي مبالغ أو حقوق تخص المركبة أو المعاملة محل هذا الإقرار، وأتحمل كافة التبعات القانونية والمالية عن أي مطالبة تخالف ما ورد بهذا الإقرار.</p>
    <table class="kv"><tr><td class="k">بيانات المركبة</td><td>{{ $v('product') }}</td></tr><tr><td class="k">رقم الشاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td></tr><tr><td class="k">رقم الموتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr></table>
    <p class="text serif">وهذا إقرار مني بذلك.</p>
    <table class="kv"><tr><td class="k">المتنازل</td><td>{{ $v('customer_name') }}</td><td class="k">رقم الموبايل</td><td>{{ $v('phone') }}</td></tr><tr><td class="k">التوقيع</td><td>&nbsp;<br>&nbsp;</td><td class="k">البصمة</td><td>&nbsp;</td></tr><tr><td class="k">التاريخ</td><td colspan="3">{{ now()->format('Y/m/d') }}</td></tr></table>
@break

@case('factory_auth')
    <p class="text serif">تحية طيبة وبعد..<br>السادة / <b>{{ $v('to_company') }}</b></p>
    <p class="text serif">نفوض نحن / <b>{{ $co }}</b><br>السيد / <b>{{ $v('delegate_name') }}</b> — رقم قومي: {{ $v('delegate_nat') }}<br>وذلك لاستلام البضاعة: {{ $v('goods') }}.</p>
    <p class="text serif">وهذا تفويض منا ساري حتى يوم <b>{{ $date('valid_until') }}</b>.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المدير المسؤول{{ $company['manager'] ? ': ' . $company['manager'] : '' }}</td></tr></table>
@break

@endswitch
@endsection
