@extends('pdf.layout')
@section('body')
@php
    $v = fn ($k, $dash = '................') => (isset($d[$k]) && trim((string) $d[$k]) !== '') ? $d[$k] : $dash;
    $money = fn ($x) => number_format((float) $x, 2);
    $co = $company['name'];
    $date = fn ($k) => ! empty($d[$k]) ? \Illuminate\Support\Carbon::parse($d[$k])->format('Y/m/d') : '................';
    // a single signature sits on the left side of the page
    $sign = function ($label) { return '<table class="sign"><tr><td style="width:58%"></td><td><div class="line">&nbsp;</div>' . $label . '</td></tr></table>'; };
    $gap = $gap ?? 1;
    // an empty amount prints as a blank to fill by hand, never as "0 جنيه / صفر جنيه"
    $amt = fn ($k, $dec = 0) => (float) ($d[$k] ?? 0) > 0 ? number_format((float) $d[$k], $dec) : '................';
    $words = fn ($k) => (float) ($d[$k] ?? 0) > 0 ? \App\Services\DocumentService::words($d[$k]) : '';
    // signature block of the company's acknowledgment forms: employee who delivered | the person acknowledging
    $ack = function ($name) use ($v, $d, $docDate, $gap) {
        $col = fn (array $lines) => '<table style="width:100%">' . implode('', array_map(fn ($t) => '<tr><td style="padding:' . (2 * $gap) . 'mm 0;font-size:12pt;font-weight:bold">' . $t . '</td></tr>', $lines)) . '</table>';
        return '<table style="width:100%;margin-top:' . (8 * $gap) . 'mm"><tr><td style="width:50%;vertical-align:top">'
            . $col(['تم التسليم بمعرفة :', 'الاسم : ' . e($v('delivered_by')), 'التوقيع : ................'])
            . '</td><td style="vertical-align:top">'
            . $col(array_merge(['وهذا إقرار مني بذلك', 'المستلم : ' . e($name)], ! empty($d['phone']) ? ['الموبايل : ' . e($d['phone'])] : [],
                ['التوقيع : ................', 'التاريخ : ' . e($docDate ?? now()->format('Y/m/d')), '<br>البصمة']))
            . '</td></tr></table>';
    };
@endphp
<h1 class="title doc-title">{{ $def['title'] }}</h1>
<div class="title-line">رقم المستند: {{ $docNo }}</div>

@switch($type)

@case('quote')
    <p class="text">السيد / <b>{{ $v('customer_name') }}</b></p>
    <p class="text">تحية طيبة وبعد،،،</p>
    <p class="text">تتشرف شركة <b>{{ $co }}</b> أن تقدم لسيادتكم عرضها المالي الخاص بـ <b>{{ $v('product') }}</b>.</p>
    <div class="box"><span class="strong">السعر:</span> <b style="font-size:16pt;color:#1d4ed8">{{ $amt('price') }} جنيه</b>@if($words('price'))<br>
        <span>{{ $words('price') }}</span>@endif</div>
    <p class="text"><b>طريقة السداد:</b> نقداً أو تحويل بنكي باسم / {{ $co }}@if($company['bank']) على حسابنا {{ $company['bank_text'] }}@endif</p>
    <p class="text"><b>التسليم:</b> الاستلام من مقر الشركة الكائن في: {{ $company['address'] ?: '................' }}@if($company['phone'])<br>هاتف: {{ $company['phone'] }}@endif</p>
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

@case('receipt')
    <p class="text serif" style="margin-bottom:0">استلمت شركة <b>{{ $co }}</b> من السيد / <b>{{ $v('customer_name') }}</b> &nbsp;—&nbsp; كود: <b>{{ $v('code', '..........') }}</b></p>
    <p class="text serif" style="margin-top:0">رقم قومي: <b>{{ $v('nat_id') }}</b></p>
    <table align="center" style="width:82%;border:0.4mm solid #1d4ed8;background:#f8fbff;margin:{{ 2 * $gap }}mm auto"><tr><td style="padding:{{ 2.2 * $gap }}mm 4mm;text-align:center;font-size:12pt;font-weight:bold">
        مبلغاً وقدره: <span style="font-size:15pt;color:#2e3192">{{ $amt('amount', 2) }}</span> جنيه مصري@if($words('amount'))<br><span style="font-size:11pt">( {{ $words('amount') }} )</span>@endif</td></tr></table>
    <p class="text serif" style="margin-bottom:0">وذلك قيمة: <b>{{ $v('reason') }}</b> &nbsp;—&nbsp; بتاريخ: <b><bdo dir="ltr">{{ ! empty($d['paid_on']) ? \Illuminate\Support\Carbon::parse($d['paid_on'])->format('d-M-Y') : '................' }}</bdo></b></p>
    <p class="text serif" style="margin:0">طريقة الدفع: <b>{{ $v('pay_method', 'كاش') }}</b></p>
    @if(! in_array($d['pay_method'] ?? 'كاش', ['كاش', 'نقدي'], true) || ! empty($d['ref_no']) || ! empty($d['transfer_receipt']))
        <p class="text serif" style="margin:0">رقم المرجع: <b><bdo dir="ltr">{{ $v('ref_no') }}</bdo></b> &nbsp;—&nbsp; رقم إيصال التحويل: <b><bdo dir="ltr">{{ $v('transfer_receipt') }}</bdo></b></p>
    @endif
    <table style="width:100%;border:0.3mm solid #94a3b8;margin-top:{{ 3 * $gap }}mm"><tr><td style="height:{{ 20 * $gap }}mm;vertical-align:top;padding:2mm 3mm;font-size:12pt;font-weight:bold">تفاصيل: <span style="font-weight:normal">{!! nl2br(e($d['details'] ?? '')) !!}</span></td></tr></table>
    <p class="text serif" style="margin-top:{{ 3 * $gap }}mm">وهذا إيصال منا بالاستلام.</p>
    <table class="sign" style="margin-top:{{ 4 * $gap }}mm"><tr><td><div class="line">&nbsp;</div>المستلم (عن الشركة){{ ! empty($d['received_by']) ? ': ' . $d['received_by'] : '' }}
        <div style="font-size:9.5pt;color:#b91c1c;margin-top:2mm">يعتبر هذا الإيصال لاغياً بدون ختم الشركة</div></td><td style="vertical-align:top"><div class="line">&nbsp;</div>توقيع الدافع</td></tr></table>
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
        <tr><td class="k">السعر</td><td><b>{{ $amt('price', 2) }} جنيه</b></td></tr>
        <tr><td class="k">تم دفع</td><td>{{ $amt('paid', 2) }} جنيه</td></tr>
        <tr><td class="k">ويتبقى</td><td><b>{{ (float) ($d['price'] ?? 0) > 0 ? $money(max(0, (float) $d['price'] - (float) ($d['paid'] ?? 0))) : '................' }} جنيه</b></td></tr></table>
    {!! $sign('توقيع المدير') !!}
@break

@case('finance_receipt')
    <p class="text serif">السادة / إدارة شركة <b>{{ $v('entity') }}</b><br>تحية طيبة وبعد،،،</p>
    <p class="text serif">أقر أنا / <b>{{ $co }}</b> (التاجر / المورد) الكائن بنشاط ( {{ $v('activity') }} ) بأنني قد سلمت للعميل / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}<br>المنتج / السلعة: <b>{{ $v('product') }}</b> — الكمية: {{ $v('quantity', '1') }}<br>القيمة: ( {{ $amt('price', 2) }} جنيهاً ) {{ $words('price') }}.</p>
    <p class="text serif">وذلك بموجب التمويل المقدم من شركة {{ $v('entity') }}، وقد تم التسليم بحالة جيدة وصالحة للاستعمال ودون أي مسؤولية على الشركة بعد إتمام الاستلام. ويعتبر هذا الإقرار بمثابة تأكيد رسمي باستلام العميل للمنتج / السلعة من قبلي، ولا يحق له الرجوع على الشركة بأي مطالبات تخص طبيعة المنتج أو جودته.</p>
    <p class="text serif">تحريراً في: {{ $docDate ?? now()->format('Y/m/d') }}</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>التاجر / المورد — التوقيع والختم</td><td><div class="line">&nbsp;</div>إقرار العميل بالاستلام: {{ $v('customer_name') }}</td></tr></table>
@break

@case('invoice')
@case('docs_receipt')
@case('vehicle_receipt')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}@if($type === 'docs_receipt') — كود: {{ $v('code') }}@endif<br>المقيم / {{ $v('address') }}</p>
    <p class="text serif">بأنني استلمت من شركة <b>{{ $co }}</b> {{ $type === 'docs_receipt' ? 'جميع الأوراق الخاصة بالمركبة' : 'المركبة' }} التالي بياناتها:</p>
    <table class="kv"><tr><td class="k">النوع</td><td>{{ $v('product') }}</td><td class="k">الموديل</td><td>{{ $v('model') }}</td></tr>
        <tr><td class="k">اللون</td><td>{{ $v('color') }}</td><td class="k">تاريخ الشراء</td><td>{{ $date('purchase_date') }}</td></tr>
        <tr><td class="k">شاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">موتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr>
        @if($type === 'docs_receipt')<tr><td class="k">مبايعة رقم</td><td colspan="3">{{ $v('mobaya_no') }}</td></tr>@endif</table>
    <p class="text serif">@if($type === 'docs_receipt')ويعد توقيعي على هذا الإقرار بمثابة مخالصة نهائية عن استلام الأوراق ولا يحق لي لاحقاً مطالبة الشركة بها.@else وأني استلمت جميع الملحقات الخاصة به ولا يحق لي أو للغير الرجوع على الشركة، وأتحمل كافة التبعات القانونية والمالية في حالة رجوع الغير على الشركة فيما يخص المركبة موضوع هذا الإقرار.@endif</p>
    {!! $ack($v('customer_name')) !!}
@break

@case('broker_receipt')
    <p class="text serif">أقر أنا / <b>{{ $v('broker_name') }}</b> — رقم قومي: {{ $v('broker_nat') }}<br>المقيم / {{ $v('broker_address') }}</p>
    <p class="text serif">بأنني استلمت من شركة <b>{{ $co }}</b> المركبة التالي بياناتها:</p>
    <table class="kv"><tr><td class="k">النوع</td><td>{{ $v('product') }}</td><td class="k">الموديل</td><td>{{ $v('model') }}</td></tr>
        <tr><td class="k">اللون</td><td>{{ $v('color') }}</td><td class="k">تاريخ الشراء</td><td>{{ $date('purchase_date') }}</td></tr>
        <tr><td class="k">شاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">موتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr></table>
    <p class="text serif">وذلك لتوصيلها للسيد / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }} — المقيم / {{ $v('address') }}<br>وذلك على سبيل الأمانة، وفي حالة عدم تسليمها أكون خائناً للأمانة وأتحمل كافة المسؤوليات القانونية، وذلك بدون أدنى مسؤولية على الشركة.<br>ولا يحق لي أو للسيد / <b>{{ $v('customer_name') }}</b> أو للغير الرجوع على الشركة، وأتحمل كافة التبعات القانونية والمالية في حالة رجوع الغير على الشركة فيما يخص المركبة موضوع هذا الإقرار.</p>
    {!! $ack($v('broker_name')) !!}
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
    <p class="text serif">تاريخ الشراء / {{ $date('purchase_date') }}</p>
    <p class="text serif">وأني استلمت جميع الملحقات الخاصة بها ولا يحق لي أو للغير الرجوع على الشركة، وأتحمل كافة التبعات القانونية والمالية في حالة رجوع الغير على الشركة فيما يخص المركبات موضوع هذا الإقرار.</p>
    {!! $ack($v('customer_name')) !!}
@break

@case('cash_received')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b><br>بطاقة رقم: {{ $v('nat_id') }}<br>مقيم في: {{ $v('address') }}</p>
    <p class="text serif">بأنني قمت باستلام مبلغ / <b>{{ $amt('amount') }} جنيه</b> فقط@if($words('amount')) — {{ $words('amount') }}@endif<br>وهو عبارة عن {{ $v('reason') }}.</p>
    <p class="text serif">وبموجب هذا الإقرار لا يحق لي الرجوع على الشركة بشأن هذا المبلغ الذي قمت باستلامه حالاً أو مستقبلاً بأي مطالبات أو دعاوى أو تعويضات أو شكاوى من أي نوع وبأي شكل.<br>وهذا إقرار مني بذلك.</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>المقر بما فيه: {{ $v('customer_name') }}</td><td><div class="line">&nbsp;</div>التاريخ: {{ $docDate ?? now()->format('Y/m/d') }}</td></tr></table>
    <p class="text serif" style="margin-top:8mm">أقر أنا / ................ أنه تم توقيع هذا الإقرار أمامي وفي حضوري بعد التأكد من شخصية الموقع.@if(! empty($d['witness'])) ({{ $d['witness'] }})@endif</p>
    <table class="sign"><tr><td><div class="line">&nbsp;</div>الاسم والتوقيع</td></tr></table>
@break

@case('waiver_amount')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b> — رقم قومي: {{ $v('nat_id') }}<br>المقيم / {{ $v('address') }}</p>
    <p class="text serif">بأن المبلغ <b>{{ $amt('amount') }} جنيه</b>@if($words('amount')) ({{ $words('amount') }})@endif الذي تم تحويله أو إيداعه بحساب شركة <b>{{ $co }}</b> {{ $v('bank') }} هو خاص بشراء: <b>{{ $v('product') }}</b> التالي بياناتها:</p>
    <table class="kv"><tr><td class="k">شاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">موتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr><tr><td class="k">تاريخ الشراء</td><td colspan="3">{{ $date('purchase_date') }}</td></tr></table>
    <p class="text serif">باسم: <b>{{ $v('to_name') }}</b> — رقم قومي: {{ $v('to_nat') }} — المقيم / {{ $v('to_address') }}<br>وبأني أتحمل كافة التبعات القانونية والمالية في حالة رجوعي أو رجوع الغير على الشركة فيما يخص هذا المبلغ موضوع هذا الإقرار.</p>
    <table class="kv"><tr><td class="k">المقر بما فيه</td><td>{{ $v('customer_name') }}</td><td class="k">الموبايل</td><td>{{ $v('phone') }}</td></tr><tr><td class="k">التوقيع</td><td>&nbsp;<br>&nbsp;</td><td class="k">البصمة</td><td>&nbsp;</td></tr><tr><td class="k">التاريخ</td><td colspan="3">{{ $docDate ?? now()->format('Y/m/d') }}</td></tr></table>
@break

@case('transfer_rights')
    <p class="text serif">أقر أنا / <b>{{ $v('customer_name') }}</b>، رقم قومي {{ $v('nat_id') }}، المقيم / {{ $v('address') }}،<br>بأنني قد تنازلت تنازلاً نهائياً لا رجعة فيه لصالح السيد / <b>{{ $v('to_name') }}</b>، رقم قومي {{ $v('to_nat') }}، المقيم / {{ $v('to_address') }}،<br>عن كافة حقوقي المتعلقة بالمبلغ المسدد في شراء المركبة <b>{{ $v('product') }}</b>، والبالغ إجمالي قيمتها <b>{{ $amt('price') }}</b> جنيه مصري فقط@if($words('price')) ({{ $words('price') }})@endif.</p>
    <p class="text serif">وقد تم سداد قيمة المركبة: {{ $v('payment_note') }}.<br>وبموجب هذا الإقرار أوافق على أن يستحق السيد / {{ $v('to_name') }} كافة الحقوق المتعلقة بالمركبة، وله الحق في استكمال إجراءات الشراء والتقسيط والتعاقد، وأن يتم تسجيل وتحويل ملكية المركبة باسمه لدى الشركة والجهات المختصة.<br>كما أقر بأنني لا يحق لي بعد هذا التنازل الرجوع على الشركة أو السيد المذكور أو المطالبة بأي مبالغ أو حقوق تخص المركبة أو المعاملة محل هذا الإقرار، وأتحمل كافة التبعات القانونية والمالية عن أي مطالبة تخالف ما ورد بهذا الإقرار.</p>
    <table class="kv"><tr><td class="k">رقم الشاسيه</td><td dir="ltr" style="text-align:right">{{ $v('chassis') }}</td><td class="k">رقم الموتور</td><td dir="ltr" style="text-align:right">{{ $v('motor') }}</td></tr></table>
    <p class="text serif">وهذا إقرار مني بذلك.</p>
    <table class="kv"><tr><td class="k">المتنازل</td><td>{{ $v('customer_name') }}</td><td class="k">رقم الموبايل</td><td>{{ $v('phone') }}</td></tr><tr><td class="k">التوقيع</td><td>................</td><td class="k">التاريخ</td><td>{{ $docDate ?? now()->format('Y/m/d') }}</td></tr><tr><td class="k">البصمة</td><td colspan="3">&nbsp;</td></tr></table>
@break

@case('factory_auth')
    <p class="text serif">تحية طيبة وبعد..<br>السادة / <b>{{ $v('to_company') }}</b></p>
    <p class="text serif">نفوض نحن / <b>{{ $co }}</b><br>السيد / <b>{{ $v('delegate_name') }}</b> — رقم قومي: {{ $v('delegate_nat') }}<br>وذلك لاستلام البضاعة: {{ $v('goods') }}.</p>
    <p class="text serif">وهذا تفويض منا ساري حتى يوم <b>{{ $date('valid_until') }}</b>.</p>
    {!! $sign('المدير المسؤول' . ($company['manager'] ? ': ' . e($company['manager']) : '')) !!}
@break

@endswitch
@endsection
