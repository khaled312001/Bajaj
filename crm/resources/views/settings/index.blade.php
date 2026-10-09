@extends('layouts.app')
@section('title', 'الإعدادات')
@section('heading', 'الإعدادات')
@section('sub', 'القوائم والأمان وسياسة الوصول')

@section('content')
@php
    $s = fn ($k, $d = '') => old($k, $settings[$k] ?? $d);
@endphp
<form method="POST" action="{{ route('settings.general') }}" class="card mb">
    @csrf @method('PUT')
    <div class="card-head"><h3><i class="fa-solid fa-shield-halved"></i> الإعدادات العامة وحماية البيانات</h3></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="field"><label>اسم النظام</label><input class="input" name="app_name" value="{{ $s('app_name', 'Bajaj CRM') }}" required></div>
            <div class="field"><label>هاتف الشركة</label><input class="input" name="company_phone" value="{{ $s('company_phone') }}"></div>
            <div class="field"><label>نسبة الفائدة الافتراضية % (للفائدة الثابتة)</label><input class="input" type="number" step="0.01" name="default_interest_rate" value="{{ $s('default_interest_rate', 18) }}"></div>

            <div class="form-section"><i class="fa-solid fa-percent"></i> نظام التقسيط وعرض السعر</div>
            <div class="field span-2"><label>أنظمة التقسيط (الشركة وجهات التمويل)</label>
                @php
    $planRows = old('plans') ?: collect(\App\Support\InstallmentCalculator::plans())->map(fn ($p) => ['name' => $p['name'], 'fees_percent' => $p['fees_percent'], 'fees_fixed' => $p['fees_fixed'], 'table' => collect($p['table'])->map(fn ($x, $m) => "$m=$x")->implode(PHP_EOL)])->all(); $planRows[] = ['name' => '', 'fees_percent' => 3, 'fees_fixed' => 500, 'table' => '']; 
@endphp
                @error('plans')<div class="error-text">{{ $message }}</div>@enderror
                <div class="grid g2">
                @foreach($planRows as $i => $pl)
                    <div class="card" style="box-shadow:none;border:1px solid var(--line-2)"><div class="card-body">
                        <div class="field mb"><label>{{ $pl['name'] === '' ? 'نظام جديد (اتركه فارغاً لعدم الإضافة)' : 'اسم النظام' . ($i === 0 ? ' — الافتراضي' : '') }}</label><input class="input" name="plans[{{ $i }}][name]" value="{{ $pl['name'] }}" maxlength="60" placeholder="مثال: أمان"></div>
                        <div class="form-grid cols-2 mb"><div class="field"><label>مصاريف إدارية %</label><input class="input" type="number" step="0.01" min="0" name="plans[{{ $i }}][fees_percent]" value="{{ $pl['fees_percent'] }}"></div>
                        <div class="field"><label>مصاريف ثابتة (ج.م)</label><input class="input" type="number" step="0.01" min="0" name="plans[{{ $i }}][fees_fixed]" value="{{ $pl['fees_fixed'] }}"></div></div>
                        <div class="field"><label>جدول النسب (شهر=مضاعف)</label><textarea class="input" rows="6" dir="ltr" style="text-align:left;font-family:monospace" name="plans[{{ $i }}][table]" placeholder="12=1.24">{{ $pl['table'] }}</textarea></div>
                    </div></div>
                @endforeach
                </div>
                <div class="hint">القسط = (المتبقي بعد المقدم × المضاعف) ÷ الأشهر. أي مدة غير مذكورة تُحسب بالاستيفاء. لحذف نظام امسح اسمه. أول نظام هو الافتراضي في الحاسبة.</div></div>
            <div class="field"><label>عنوان عرض التقسيط</label><input class="input" name="offer_title" value="{{ $s('offer_title', 'عرض تقسيط بجاج قنا') }}"></div>
            <div class="field"><label>مواعيد العمل</label><input class="input" name="working_hours" value="{{ $s('working_hours', 'من السبت إلى الخميس من 10 صباحاً حتى 3 مساءً') }}"></div>
            <div class="field span-2"><label>عنوان الفرع</label><input class="input" name="company_address" value="{{ $s('company_address') }}"></div>
            <div class="form-section"><i class="fa-solid fa-file-pdf"></i> بيانات الشركة في المستندات (PDF)</div>
            <div class="field"><label>الاسم القانوني للشركة</label><input class="input" name="company_legal_name" value="{{ $s('company_legal_name', 'جنوب الصعيد لوسائل النقل الخفيف') }}"></div>
            <div class="field"><label>رقم السجل التجاري</label><input class="input" name="commercial_register" value="{{ $s('commercial_register') }}"></div>
            <div class="field"><label>رقم البطاقة الضريبية</label><input class="input" name="tax_card" value="{{ $s('tax_card') }}"></div>
            <div class="field"><label>الحساب البنكي</label><input class="input" name="bank_account" value="{{ $s('bank_account') }}"></div>
            <div class="field"><label>المدير المسؤول (للتوقيع)</label><input class="input" name="manager_name" value="{{ $s('manager_name') }}"></div>

            <div class="form-section"><i class="fa-solid fa-lock"></i> سياسة وصول موظف خدمة العملاء</div>
            <div class="field span-2"><label>العملاء الذين يراهم الموظف</label>
                <select class="input" name="agent_scope">
                    <option value="own" @selected($s('agent_scope', 'own') === 'own')>عملاؤه فقط (الذين أدخلهم أو نُقلوا إليه) — الأكثر أماناً</option>
                    <option value="all" @selected($s('agent_scope') === 'all')>كل العملاء</option></select>
                <div class="hint">في الوضع الأول يستطيع الموظف معرفة هل الرقم مسجل ومن أدخله دون رؤية بيانات الغير، لمنع التكرار.</div></div>
            <div class="field"><label>إنهاء الجلسة بعد خمول (دقيقة)</label><input class="input" type="number" name="agent_idle_minutes" value="{{ $s('agent_idle_minutes', 45) }}" min="5" max="600"></div>
            <div class="field span-2"><label>عناوين IP المسموح بها للموظفين (اختياري)</label><input class="input" name="agent_ip_allowlist" value="{{ $s('agent_ip_allowlist') }}" dir="ltr" style="text-align:right" placeholder="مثال: 197.55.10.20, 41.33.8.9"><div class="hint">إن تركته فارغاً يُسمح من أي مكان. إن حددته لن يدخل الموظفون إلا من هذه العناوين. (عنوانك الحالي: <span class="num">{{ request()->ip() }}</span>)</div></div>
            <div class="field"><label>تنبيه عند فتح ملفات (كل 10 دقائق)</label><input class="input" type="number" name="view_alert_threshold" value="{{ $s('view_alert_threshold', 40) }}" min="5"><div class="hint">يظهر تنبيه أمني لك عند تجاوز هذا العدد.</div></div>
            <div class="field"><label>إيقاف مؤقت عند فتح ملفات</label><input class="input" type="number" name="view_block_threshold" value="{{ $s('view_block_threshold', 90) }}" min="10"><div class="hint">يُمنع الموظف مؤقتاً عند تجاوزه هذا العدد (كشف التجميع الآلي).</div></div>

            <div class="form-section"><i class="fa-solid fa-link"></i> نموذج طلب العملاء العام (الليدز)</div>
            <div class="field span-3">
                <label class="check"><input type="hidden" name="lead_form_enabled" value="0"><input type="checkbox" name="lead_form_enabled" value="1" @checked($s('lead_form_enabled', '1') === '1')> تفعيل نموذج الطلب العام</label>
                <div class="hint">رابط النموذج العام (شاركه مع العملاء): <a href="{{ route('lead.public') }}" target="_blank">{{ route('lead.public') }}</a> — الطلبات الواردة تظهر في <a href="{{ route('leads.index') }}">صفحة الليدز</a> لتعيينها لموظفي المبيعات.</div>
            </div>
            <div class="field span-2"><label>عنوان النموذج</label><input class="input" name="lead_form_title" value="{{ $s('lead_form_title', 'اطلب مركبتك الآن') }}" maxlength="120"></div>
            <div class="field span-3"><label>مقدمة النموذج</label><textarea class="input" rows="2" name="lead_form_intro" maxlength="400">{{ $s('lead_form_intro', 'املأ بياناتك وسيتواصل معك فريق المبيعات في أقرب وقت.') }}</textarea></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ الإعدادات</button></div>
    </div>
</form>

<div class="grid g3">
@foreach($types as $type => $label)
    <div class="card" id="{{ $type }}">
        <div class="card-head"><h3>{{ $label }}</h3><span class="badge gray">{{ ($lookups[$type] ?? collect())->count() }}</span></div>
        <form method="POST" action="{{ route('settings.lookups.store') }}" class="card-pad row" style="border-bottom:1px solid var(--line-2)">
            @csrf <input type="hidden" name="type" value="{{ $type }}">
            <input class="input" name="name" placeholder="إضافة جديد..." required maxlength="150"><button class="btn btn-primary btn-icon" title="إضافة"><i class="fa-solid fa-plus"></i></button>
        </form>
        <div style="max-height:320px;overflow-y:auto">
            @forelse($lookups[$type] ?? [] as $item)
                <div class="row" style="padding:9px 20px;border-bottom:1px solid var(--line-2);{{ $item->is_active ? '' : 'opacity:.5' }}">
                    <span class="grow">{{ $item->name }}</span>
                    <form method="POST" action="{{ route('settings.lookups.toggle', $item) }}">@csrf<button class="btn btn-xs btn-ghost" title="{{ $item->is_active ? 'إيقاف' : 'تفعيل' }}"><i class="fa-solid {{ $item->is_active ? 'fa-eye' : 'fa-eye-slash' }}"></i></button></form>
                    <form method="POST" action="{{ route('settings.lookups.destroy', $item) }}" data-confirm="حذف '{{ $item->name }}'؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-xmark"></i></button></form>
                </div>
            @empty<div class="empty" style="padding:24px">لا توجد عناصر</div>@endforelse
        </div>
    </div>
@endforeach
</div>
@endsection
