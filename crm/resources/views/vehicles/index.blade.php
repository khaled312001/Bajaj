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

<div class="card mb">
    <form method="GET" class="card-body">
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
            <a class="btn btn-ghost" href="{{ route('vehicles.export', request()->query()) }}"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> تصدير Excel</a></div>@endif
    </form>
</div>

<div class="card"><div class="table-wrap"><table class="tbl">
    <thead><tr><th>الشاسيه</th><th>الموتور</th><th>النوع</th><th>السنة</th><th>اللون</th><th>الحالة</th>@if($isAdmin)<th>المصدر</th><th>التكلفة</th><th>المشتري</th>@endif<th>وصل</th>@if($isAdmin)<th></th>@endif</tr></thead>
    <tbody>
    @forelse($vehicles as $v)
        <tr>
            <td><span class="code" dir="ltr">{{ $mask($v->chassis) }}</span></td><td dir="ltr">{{ $isAdmin ? ($v->motor ?: '—') : '—' }}</td><td>{{ $v->type ?: '—' }}</td><td>{{ $v->model_year ?: '—' }}</td><td>{{ $v->color ?: '—' }}</td>
            <td><span class="badge dot {{ $v->status_class }}">{{ $v->status_label }}</span></td>
            @if($isAdmin)<td>{{ $v->source_store ?: '—' }}</td><td class="num">{{ $v->total_cost ? number_format($v->total_cost) : '—' }}</td>
            <td>@if($v->deal?->customer)<a href="{{ route('customers.show', $v->deal->customer) }}">{{ $v->deal->customer->name }}</a>@else —@endif</td>@endif
            <td class="muted small">{{ $v->arrived_at?->format('Y/m/d') ?? '—' }}</td>
            @if($isAdmin)<td class="t-left"><div class="btn-group" style="flex-wrap:nowrap">
                <button class="btn btn-xs btn-ghost" onclick="document.getElementById('dlg-{{ $v->id }}').showModal()"><i class="fa-solid fa-pen"></i></button>
                <form method="POST" action="{{ route('vehicles.destroy', $v) }}" data-confirm="حذف المركبة؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form></div></td>@endif
        </tr>
    @empty<tr><td colspan="11"><div class="empty">لا توجد مركبات</div></td></tr>@endforelse
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
    @if($errors->has('chassis'))<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('dlg-new').showModal());</script>@endif
    <style>.dlg{border:0;border-radius:16px;padding:0;width:min(760px,94vw);box-shadow:0 20px 60px rgba(0,0,0,.3)}.dlg::backdrop{background:rgba(15,23,42,.55)}</style>
@endif
@endsection
