@extends('layouts.app')
@section('title', 'العملاء')
@section('heading', 'العملاء')
@section('sub', number_format($customers->total()) . ' عميل')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $f = $filters;
    $hasFilter = collect($f)->except(['page', 'sort', 'dir'])->filter()->isNotEmpty();
@endphp
<div class="card mb">
    <form method="GET" class="card-body">
        <div class="row wrap mb">
            <div class="input-icon grow" style="min-width:260px"><i class="fa-solid fa-magnifying-glass"></i>
                <input class="input" name="q" value="{{ $f['q'] ?? '' }}" placeholder="ابحث بالاسم أو الهاتف أو الكود أو الرقم القومي..."></div>
            <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> بحث</button>
            @if($hasFilter)<a class="btn btn-ghost" href="{{ route('customers.index') }}"><i class="fa-solid fa-xmark"></i> مسح</a>@endif
            @if(auth()->user()->allows('customers', 'create'))<a class="btn btn-green" href="{{ route('customers.create') }}"><i class="fa-solid fa-user-plus"></i> عميل جديد</a>@endif
            @if($isAdmin)<a class="btn btn-ghost" href="{{ route('data.export.customers', request()->query()) }}"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> تصدير Excel</a>@endif
        </div>
        <div class="filter-bar">
            <select class="input" name="status"><option value="">كل الحالات</option>@foreach(\App\Models\Customer::STATUSES as $s)<option @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="governorate"><option value="">كل المحافظات</option>@foreach(\App\Support\EgyptGeo::all() as $s)<option @selected(($f['governorate'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="channel"><option value="">كل القنوات</option>@foreach(\App\Models\Lookup::list('channel') as $s)<option @selected(($f['channel'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="seriousness"><option value="">كل درجات الجدية</option>@foreach(\App\Models\Lookup::list('seriousness') as $s)<option @selected(($f['seriousness'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="branch"><option value="">كل الفروع</option>@foreach(\App\Models\Lookup::list('branch') as $s)<option @selected(($f['branch'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="vehicle"><option value="">كل المركبات</option>@foreach(\App\Models\Lookup::list('vehicle') as $s)<option @selected(($f['vehicle'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="finance_entity"><option value="">كل جهات التقسيط</option>@foreach(\App\Models\Lookup::list('finance_entity') as $s)<option @selected(($f['finance_entity'] ?? '') === $s)>{{ $s }}</option>@endforeach</select>
            <select class="input" name="pay_method"><option value="">كاش / تقسيط</option><option @selected(($f['pay_method'] ?? '') === 'كاش')>كاش</option><option @selected(($f['pay_method'] ?? '') === 'تقسيط')>تقسيط</option></select>
            @if($isAdmin)
                <select class="input" name="created_by"><option value="">أدخله: الكل</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected(($f['created_by'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select>
                <select class="input" name="assigned_to"><option value="">المسؤول: الكل</option><option value="none" @selected(($f['assigned_to'] ?? '') === 'none')>بدون مسؤول</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected(($f['assigned_to'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select>
            @endif
            <input class="input" type="date" name="from" value="{{ $f['from'] ?? '' }}" title="من تاريخ">
            <input class="input" type="date" name="to" value="{{ $f['to'] ?? '' }}" title="إلى تاريخ">
            <label class="check"><input type="checkbox" name="has_debt" value="1" @checked(!empty($f['has_debt']))> عليه مديونية</label>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="tbl">
            <thead><tr>
                <th>العميل</th><th>الهاتف</th><th>المحافظة</th><th>الحالة</th><th>المديونية</th><th>أدخله</th><th>المسؤول</th><th>التاريخ</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($customers as $c)
                <tr>
                    <td><a class="person" href="{{ route('customers.show', $c) }}"><div class="avatar sm">{{ mb_substr($c->name, 0, 1) }}</div><div><b>{{ $c->name }}</b><small><span class="code">{{ $c->code }}</span></small></div></a></td>
                    <td><span class="num">{{ $c->phone }}</span></td>
                    <td>{{ $c->governorate ?: '—' }}</td>
                    <td><span class="badge dot {{ $c->status_class }}">{{ $c->status }}</span>@if($c->seriousness)<div class="xs muted" style="margin-top:3px">{{ $c->seriousness }}</div>@endif</td>
                    <td>@if($c->debt > 0)<b class="num" style="color:var(--amber)">@money($c->debt)</b>@else<span class="muted">—</span>@endif</td>
                    <td>{{ $c->creator?->name ?? '—' }}</td>
                    <td>{{ $c->assignee?->name ?? '—' }}</td>
                    <td class="muted small">{{ $c->created_at->format('Y/m/d') }}</td>
                    <td class="t-left"><div class="btn-group" style="flex-wrap:nowrap">
                        <a class="btn btn-xs btn-soft" href="tel:{{ $c->phone }}" title="اتصال"><i class="fa-solid fa-phone"></i></a>
                        <a class="btn btn-xs btn-soft" href="{{ route('customers.show', $c) }}">فتح</a></div></td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty"><i class="fa-solid fa-user-slash"></i><b>لا يوجد عملاء</b>
                    @if(! empty($searchedPhone) && auth()->user()->allows('customers', 'create'))الرقم <span class="num">{{ $searchedPhone }}</span> غير مسجل.<br><a class="btn btn-primary mt" href="{{ route('customers.create', ['phone' => $searchedPhone]) }}"><i class="fa-solid fa-user-plus"></i> إضافة عميل جديد بهذا الرقم</a>
                    @else جرّب تغيير البحث أو أضف عميلاً جديداً.@endif</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
@endsection
