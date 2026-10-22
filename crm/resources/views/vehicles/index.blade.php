@extends('layouts.app')
@section('title', 'المخزن والمركبات')
@section('heading', 'المخزن والمركبات')
@section('sub', number_format($vehicles->total()) . ' مركبة')

@section('content')
@php
    $statuses = \App\Models\Vehicle::STATUSES;
    $hasFilter = collect(request()->except('page'))->filter()->isNotEmpty();
    $mask = fn ($s) => $isAdmin ? $s : '••••' . mb_substr((string) $s, -5);
    $fields = fn ($v = null) => [
        ['chassis', 'الشاسيه', $v?->chassis, 'text'], ['motor', 'الموتور', $v?->motor, 'text'], ['type', 'النوع', $v?->type, 'text'], ['model_year', 'السنة', $v?->model_year, 'text'],
        ['color', 'اللون', $v?->color, 'text'], ['source_store', 'من مخزن', $v?->source_store, 'text'], ['branch_store', 'إلى مخزن', $v?->branch_store, 'text'],
        ['cost_price', 'سعر المنتج', $v?->cost_price, 'number'], ['transport_cost', 'مصاريف نقل', $v?->transport_cost, 'number'], ['other_cost', 'مصاريف أخرى', $v?->other_cost, 'number'],
        ['arrived_at', 'تاريخ الوصول', $v?->arrived_at?->toDateString(), 'date'], ['sold_at', 'تاريخ البيع', $v?->sold_at?->toDateString(), 'date'], ['sale_price', 'سعر البيع', $v?->sale_price, 'number'],
    ];
@endphp

<div class="grid g4 mb">
    <div class="card stat"><div class="ico green"><i class="fa-solid fa-boxes-stacked"></i></div><div><div class="lbl">متاحة بالمخزن</div><div class="val">{{ number_format($stats['in_stock']) }}</div></div></div>
    <div class="card stat"><div class="ico amber"><i class="fa-solid fa-bookmark"></i></div><div><div class="lbl">محجوزة</div><div class="val">{{ number_format($stats['reserved']) }}</div></div></div>
    @if($isAdmin)
    <div class="card stat"><div class="ico blue"><i class="fa-solid fa-handshake"></i></div><div><div class="lbl">مباعة</div><div class="val">{{ number_format($stats['sold']) }}</div></div></div>
    <div class="card stat"><div class="ico red"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="lbl">تكلفة المخزون</div><div class="val">@money($stats['stock_cost'])</div></div></div>
    @endif
</div>

@if($byType->isNotEmpty())
<div class="card mb"><div class="card-body row wrap" style="gap:8px"><b class="small muted">المتاح حسب النوع:</b>
    @foreach($byType as $t => $c)<a class="badge blue" href="{{ route('vehicles.index', ['type' => $t === 'غير محدد' ? null : $t, 'status' => 'in_stock']) }}">{{ $t }}: {{ $c }}</a>@endforeach</div></div>
@endif

@if($batch)
<div class="alert alert-info"><i class="fa-solid fa-file-lines"></i><div class="grow">
    <b>إذن الاستلام {{ $batch->permit_no ?: '#' . $batch->id }}</b> — {{ $batch->vehicles_count }} مركبة
    @if($batch->received_at) · بتاريخ {{ $batch->received_at->format('Y/m/d') }}@endif
    @if($batch->sale_order) · أمر البيع: <span class="num">{{ $batch->sale_order }}</span>@endif
    @if($batch->invoice_no) · فاتورة: <span class="num">{{ $batch->invoice_no }}</span>@endif
    @if($batch->source) · من: {{ $batch->source }}@endif
    @if($batch->driver) · السائق المستلم: {{ $batch->driver }}@endif
    @if($batch->transport_company) · النقل: {{ $batch->transport_company }}@endif
    @if($batch->notes)<div class="small muted">{{ $batch->notes }}</div>@endif
</div><div class="row"><button type="button" class="btn btn-xs btn-ghost" onclick="document.getElementById('dlg-batch').showModal()"><i class="fa-solid fa-pen"></i> تعديل</button><a class="btn btn-xs btn-ghost" href="{{ route('vehicles.index') }}">عرض الكل</a></div></div>
@elseif($batches->isNotEmpty())
<div class="card mb"><div class="card-head"><h3><i class="fa-solid fa-file-lines"></i> آخر أذون الاستلام</h3></div>
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th>التاريخ</th><th>رقم الإذن</th><th>أمر البيع</th><th>الفاتورة</th><th>المورد</th><th>السائق المستلم</th><th>العدد</th><th>مباع</th><th></th></tr></thead>
        <tbody>@foreach($batches as $b)
            <tr><td class="small">{{ $b->received_at?->format('Y/m/d') ?? '—' }}</td><td class="num">{{ $b->permit_no ?: '—' }}</td><td class="num">{{ $b->sale_order ?: '—' }}</td><td class="num">{{ $b->invoice_no ?: '—' }}</td>
                <td>{{ $b->source ?: '—' }}</td><td>{{ $b->driver ?: '—' }}</td><td><b>{{ $b->vehicles_count }}</b></td><td>{{ $b->sold_count }}</td>
                <td class="t-left"><a class="btn btn-xs btn-ghost" href="{{ route('vehicles.index', ['batch' => $b->id]) }}"><i class="fa-solid fa-eye"></i> المركبات</a></td></tr>
        @endforeach</tbody></table></div></div>
@endif

<div class="card mb">
    <form method="GET" class="card-body">
        @if(request('batch'))<input type="hidden" name="batch" value="{{ request('batch') }}">@endif
        <div class="filter-bar">
            <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="{{ request('q') }}" placeholder="شاسيه / موتور / لون / مشتري"></div>
            <select class="input" name="status"><option value="">كل الحالات</option>@foreach($statuses as $k => $l)@continue(! $isAdmin && $k === 'sold')<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
            <select class="input" name="type"><option value="">كل الأنواع</option>@foreach($types as $t)<option @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select>
            @if($isAdmin)<select class="input" name="source_store"><option value="">كل المصادر</option>@foreach($sources as $t)<option @selected(request('source_store') === $t)>{{ $t }}</option>@endforeach</select>@endif
            <input class="input" name="model_year" value="{{ request('model_year') }}" placeholder="السنة">
            <input class="input" type="date" name="from" value="{{ request('from') }}" title="وصول من">
            <input class="input" type="date" name="to" value="{{ request('to') }}" title="وصول إلى">
            <div class="row"><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> تصفية</button>@if($hasFilter)<a class="btn btn-ghost" href="{{ route('vehicles.index') }}">مسح</a>@endif</div>
        </div>
        @if($isAdmin)<div class="row wrap mt"><button type="button" class="btn btn-green" onclick="document.getElementById('dlg-new').showModal()"><i class="fa-solid fa-plus"></i> إضافة مركبة</button>
            <button type="button" class="btn btn-soft" onclick="document.getElementById('dlg-bulk').showModal()"><i class="fa-solid fa-layer-group"></i> إذن استلام جديد (مجموعة شاسيهات)</button>
            <a class="btn btn-ghost" href="{{ route('data.index') }}#vehicles"><i class="fa-solid fa-file-import" style="color:#16a34a"></i> استيراد من Excel</a>
            <a class="btn btn-ghost" href="{{ route('vehicles.export', request()->query()) }}"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> تصدير Excel</a></div>@endif
    </form>
</div>

<div class="card"><div class="table-wrap"><table class="tbl">
    <thead><tr><th>الشاسيه</th><th>الموتور</th><th>النوع</th><th>السنة</th><th>اللون</th><th>الحالة</th>@if($isAdmin)<th>الإذن</th><th>المصدر</th><th>التكلفة</th><th>المشتري / البيع</th>@endif<th>وصل</th>@if($isAdmin)<th></th>@endif</tr></thead>
    <tbody>
    @forelse($vehicles as $v)
        <tr>
            <td><span class="code" dir="ltr">{{ $mask($v->chassis) }}</span></td><td dir="ltr">{{ $isAdmin ? ($v->motor ?: '—') : '—' }}</td><td>{{ $v->type ?: '—' }}</td><td>{{ $v->model_year ?: '—' }}</td><td>{{ $v->color ?: '—' }}</td>
            <td><span class="badge dot {{ $v->status_class }}">{{ $v->status_label }}</span>@if(! $isAdmin && $v->deal?->customer)<div class="muted small">{{ $v->deal->customer->code }}</div>@endif</td>
            @if($isAdmin)<td class="small">@if($v->batch)<a href="{{ route('vehicles.index', ['batch' => $v->batch_id]) }}">{{ $v->batch->permit_no ?: '#' . $v->batch_id }}</a>@else —@endif</td><td>{{ $v->source_store ?: '—' }}</td><td class="num">{{ $v->total_cost ? number_format($v->total_cost) : '—' }}</td>
            <td>@if($v->deal?->customer)<a href="{{ route('customers.show', $v->deal->customer) }}">{{ $v->deal->customer->name }}</a> <span class="code">{{ $v->deal->customer->code }}</span>
                <div class="muted small">{{ $v->deal->pay_method === 'كاش' ? 'كاش' : 'تقسيط' . ($v->deal->finance_entity ? ' — ' . $v->deal->finance_entity : '') }}{{ $v->sale_price ? ' · ' . number_format($v->sale_price) . ' ج.م' : '' }}{{ $v->sold_at ? ' · ' . $v->sold_at->format('Y/m/d') : '' }}</div>
                @elseif($v->status === 'sold' && ($v->sale_price || $v->sold_at))<div class="muted small">{{ $v->sale_price ? number_format($v->sale_price) . ' ج.م' : '' }}{{ $v->sold_at ? ' · ' . $v->sold_at->format('Y/m/d') : '' }}</div>@else —@endif</td>@endif
            <td class="muted small">{{ $v->arrived_at?->format('Y/m/d') ?? '—' }}</td>
            @if($isAdmin)<td class="t-left"><div class="btn-group" style="flex-wrap:nowrap">
                <button class="btn btn-xs btn-ghost" onclick="document.getElementById('dlg-{{ $v->id }}').showModal()"><i class="fa-solid fa-pen"></i></button>
                <form method="POST" action="{{ route('vehicles.destroy', $v) }}" data-confirm="حذف المركبة؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form></div></td>@endif
        </tr>
    @empty<tr><td colspan="12"><div class="empty">لا توجد مركبات</div></td></tr>@endforelse
    </tbody></table></div>
    <div class="card-pad">{{ $vehicles->links('pagination.rtl') }}</div></div>

@if($isAdmin)
    @foreach(array_merge([null], $vehicles->all()) as $v)
    <dialog id="dlg-{{ $v?->id ?? 'new' }}" class="dlg">
        <form method="POST" action="{{ $v ? route('vehicles.update', $v) : route('vehicles.store') }}">
            @csrf @if($v) @method('PUT') @endif
            <div class="card-head"><h3>{{ $v ? 'تعديل مركبة' : 'إضافة مركبة للمخزن' }}</h3><button type="button" class="btn btn-xs btn-ghost" onclick="this.closest('dialog').close()"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="card-body"><div class="form-grid">
                @foreach($fields($v) as [$n, $l, $val, $k])<div class="field"><label>{{ $l }}</label><input class="input" name="{{ $n }}" type="{{ $k }}" @if($k === 'number') step="0.01" min="0" @endif value="{{ old($n, $val) }}" @if($n === 'chassis') required @endif></div>@endforeach
                <div class="field"><label>الحالة</label><select class="input" name="status">@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(($v?->status ?? 'in_stock') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field span-3"><label>ملاحظات</label><input class="input" name="notes" value="{{ $v?->notes }}"></div>
            </div><div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ</button></div></div>
        </form>
    </dialog>
    @endforeach
    <dialog id="dlg-bulk" class="dlg">
        <form method="POST" action="{{ route('vehicles.bulk') }}">
            @csrf
            <div class="card-head"><h3>إذن استلام — إضافة مجموعة شاسيهات وموتورات</h3><button type="button" class="btn btn-xs btn-ghost" onclick="this.closest('dialog').close()"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="card-body">
                <div class="form-section" style="margin-top:0"><i class="fa-solid fa-file-lines"></i> بيانات إذن الصرف (من ورقة المورد)</div>
                <div class="form-grid mb">
                    <div class="field"><label>رقم الإذن</label><input class="input" name="permit_no" dir="ltr" style="text-align:right" placeholder="CIO-RWH-009660"></div>
                    <div class="field"><label>أمر البيع</label><input class="input" name="sale_order" dir="ltr" style="text-align:right"></div>
                    <div class="field"><label>رقم الفاتورة</label><input class="input" name="invoice_no" dir="ltr" style="text-align:right"></div>
                    <div class="field"><label>السائق المستلم</label><input class="input" name="driver"></div>
                    <div class="field"><label>شركة النقل</label><input class="input" name="transport_company"></div>
                    <div class="field"><label>ملاحظات الإذن</label><input class="input" name="batch_notes"></div>
                </div>
                <div class="field mb"><label>الشاسيه والموتور (كل مركبة في سطر: رقم الشاسيه ثم مسافة ثم رقم الموتور)</label>
                    <textarea class="input" name="lines" rows="8" dir="ltr" required placeholder="MD2A12AZ5RWA12345  PFZWRA12345&#10;MD2A12AZ5RWA12346  PFZWRA12346">{{ old('lines') }}</textarea>
                    <div class="hint">يمكنك نسخ عمودي الشاسيه والموتور من Excel ولصقهما هنا مباشرة. الشاسيه المسجل من قبل يتم تخطيه.</div></div>
                <div class="form-grid">
                    <div class="field"><label>النوع</label><select class="input" name="type"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('vehicle') as $x)<option>{{ $x }}</option>@endforeach</select></div>
                    <div class="field"><label>السنة</label><input class="input" name="model_year"></div>
                    <div class="field"><label>اللون</label><input class="input" name="color"></div>
                    <div class="field"><label>من مخزن / المورد</label><input class="input" name="source_store" list="dl-sources"></div>
                    <div class="field"><label>إلى مخزن</label><input class="input" name="branch_store"></div>
                    <div class="field"><label>تاريخ الإذن / الوصول</label><input class="input" type="date" name="arrived_at" value="{{ today()->toDateString() }}"></div>
                    <div class="field"><label>سعر المنتج (للواحدة)</label><input class="input" type="number" step="0.01" min="0" name="cost_price"></div>
                    <div class="field"><label>مصاريف نقل (للواحدة)</label><input class="input" type="number" step="0.01" min="0" name="transport_cost"></div>
                </div>
                <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ الإذن وإضافة المركبات</button></div>
            </div>
        </form>
    </dialog>
    <datalist id="dl-sources">@foreach($sources as $t)<option value="{{ $t }}">@endforeach</datalist>
    @if($batch)
    <dialog id="dlg-batch" class="dlg">
        <form method="POST" action="{{ route('vehicles.batches.update', $batch) }}">
            @csrf @method('PUT')
            <div class="card-head"><h3>تعديل بيانات الإذن</h3><button type="button" class="btn btn-xs btn-ghost" onclick="this.closest('dialog').close()"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="card-body"><div class="form-grid">
                <div class="field"><label>رقم الإذن</label><input class="input" name="permit_no" value="{{ $batch->permit_no }}" dir="ltr" style="text-align:right"></div>
                <div class="field"><label>أمر البيع</label><input class="input" name="sale_order" value="{{ $batch->sale_order }}" dir="ltr" style="text-align:right"></div>
                <div class="field"><label>رقم الفاتورة</label><input class="input" name="invoice_no" value="{{ $batch->invoice_no }}" dir="ltr" style="text-align:right"></div>
                <div class="field"><label>تاريخ الإذن</label><input class="input" type="date" name="received_at" value="{{ $batch->received_at?->toDateString() }}"></div>
                <div class="field"><label>المورد / من مخزن</label><input class="input" name="source" value="{{ $batch->source }}" list="dl-sources"></div>
                <div class="field"><label>السائق المستلم</label><input class="input" name="driver" value="{{ $batch->driver }}"></div>
                <div class="field"><label>شركة النقل</label><input class="input" name="transport_company" value="{{ $batch->transport_company }}"></div>
                <div class="field span-2"><label>ملاحظات</label><input class="input" name="batch_notes" value="{{ $batch->notes }}"></div>
            </div><div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ</button></div></div>
        </form>
    </dialog>
    @endif
    @if($errors->has('chassis'))<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('dlg-new').showModal());</script>@endif
    <style>.dlg{border:0;border-radius:16px;padding:0;width:min(760px,94vw);box-shadow:0 20px 60px rgba(0,0,0,.3)}.dlg::backdrop{background:rgba(15,23,42,.55)}</style>
@endif
@endsection
