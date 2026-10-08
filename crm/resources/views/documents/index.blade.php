@extends('layouts.app')
@section('title', 'المستندات والطباعة')
@section('heading', 'المستندات والطباعة')
@section('sub', 'عروض الأسعار والإقرارات والمبايعات بصيغة PDF احترافية')

@section('content')
@php
    $groups = collect($types)->groupBy('group', true);
    $qs = array_filter(['deal' => $deal, 'customer' => $customerId, 'payment' => $payment]);
    $isAdmin = auth()->user()->isAdmin();
@endphp
@if($qs)<div class="alert alert-info mb"><i class="fa-solid fa-link"></i><div>سيتم تعبئة المستند ببيانات العميل / الصفقة المحددة تلقائياً، ويمكنك تعديلها قبل الإصدار.</div></div>@endif

@foreach($groups as $group => $items)
    <h3 class="section-title">{{ $group }}</h3>
    <div class="grid g3 mb">
    @foreach($items as $key => $t)
        <a class="card doc-card" href="{{ route('documents.create', ['type' => $key] + $qs) }}">
            <div class="card-body row" style="gap:14px;align-items:flex-start">
                <div class="doc-ic"><i class="fa-solid {{ $t['icon'] }}"></i></div>
                <div><b>{{ $t['title'] }}</b><div class="muted small" style="margin-top:4px">{{ $t['desc'] }}</div></div>
            </div>
        </a>
    @endforeach
    </div>
@endforeach

<div class="card" id="log">
    <div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> سجل المستندات الصادرة</h3><span class="badge gray">{{ $logs->total() }}</span></div>
    <form method="GET" action="{{ route('documents.index') }}#log" class="card-body" style="border-bottom:1px solid var(--line-2)">
        <div class="filter-bar">
            <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="{{ request('q') }}" placeholder="اسم العميل / المنتج / الرقم"></div>
            <select class="input" name="type"><option value="">كل الأنواع</option>@foreach($allTypes as $k => $t)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $t['title'] }}</option>@endforeach</select>
            @if($isAdmin)<select class="input" name="user"><option value="">كل الموظفين</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select>@endif
            <input class="input" type="date" name="from" value="{{ request('from') }}" title="من تاريخ">
            <input class="input" type="date" name="to" value="{{ request('to') }}" title="إلى تاريخ">
            <div class="row"><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> تصفية</button>@if(request()->query())<a class="btn btn-ghost" href="{{ route('documents.index') }}#log">مسح</a>@endif</div>
        </div>
    </form>
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th>الرقم</th><th>المستند</th><th>العميل / الاسم</th><th>المنتج</th><th>القيمة</th><th>أصدره</th><th>التاريخ</th><th></th></tr></thead>
        <tbody>
        @forelse($logs as $l)
            @php($t = $allTypes[$l->type] ?? null)
            <tr>
                <td><span class="code">{{ \App\Services\DocumentService::docNo($l->type, $l->serial, $l->legacy_ref) }}</span></td>
                <td>{{ $t['title'] ?? $l->type }}</td><td>{{ $l->customer_name ?: '—' }}</td><td>{{ $l->product_name ?: '—' }}</td>
                <td class="num">{{ $l->amount ? number_format($l->amount) : '—' }}</td><td>{{ $l->user?->name ?? '—' }}</td>
                <td class="muted small">{{ $l->printed_at->format('Y/m/d H:i') }}</td>
                <td class="t-left"><a class="btn btn-xs btn-soft" href="{{ route('documents.show', $l) }}"><i class="fa-solid fa-eye"></i> عرض / PDF</a></td>
            </tr>
        @empty<tr><td colspan="8"><div class="empty">لا توجد مستندات</div></td></tr>@endforelse
        </tbody>
    </table></div>
    <div class="card-pad">{{ $logs->links('pagination.rtl') }}</div>
</div>
<style>
.doc-card { transition: transform .15s, box-shadow .15s; display:block } .doc-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md, 0 8px 24px rgba(15,23,42,.12)) }
.doc-ic { width:44px;height:44px;border-radius:12px;background:var(--blue-50,#eff6ff);color:var(--blue-600,#2563eb);display:grid;place-items:center;font-size:18px;flex:0 0 auto }
.section-title { font-size:15px;font-weight:800;margin:6px 0 10px;color:var(--ink-2,#334155) }
</style>
@endsection
