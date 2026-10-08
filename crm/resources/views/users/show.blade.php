@extends('layouts.app')
@section('title', $u->name)
@section('heading', 'تتبع الموظف: ' . $u->name)

@section('content')
<div class="grid g4 mb">
    <div class="card stat"><div class="ico blue"><i class="fa-solid fa-user-plus"></i></div><div><div class="lbl">عملاء أدخلهم</div><div class="val">{{ $stats['created'] }}</div></div></div>
    <div class="card stat"><div class="ico purple"><i class="fa-solid fa-user-check"></i></div><div><div class="lbl">عملاء مسؤول عنهم</div><div class="val">{{ $stats['assigned'] }}</div></div></div>
    <div class="card stat"><div class="ico green"><i class="fa-solid fa-calendar-check"></i></div><div><div class="lbl">متابعات أنجزها</div><div class="val">{{ $stats['followups_done'] }}</div><div class="xs muted">{{ $stats['pending'] }} معلّقة</div></div></div>
    <div class="card stat"><div class="ico amber"><i class="fa-solid fa-eye"></i></div><div><div class="lbl">ملفات فتحها اليوم</div><div class="val">{{ $stats['views_today'] }}</div></div></div>
</div>
<div class="grid g2">
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-users"></i> آخر العملاء الذين أدخلهم</h3><a class="btn btn-xs btn-soft" href="{{ route('customers.index', ['created_by' => $u->id]) }}">الكل</a></div>
        @forelse($recent as $c)
            <a class="row" href="{{ route('customers.show', $c) }}" style="padding:11px 20px;border-bottom:1px solid var(--line-2);color:inherit"><div class="avatar sm">{{ mb_substr($c->name, 0, 1) }}</div><div class="grow"><b>{{ $c->name }}</b><div class="xs muted">{{ $c->created_at->format('Y/m/d H:i') }}</div></div><span class="badge {{ $c->status_class }}">{{ $c->status }}</span></a>
        @empty<div class="empty">لم يُدخل عملاء بعد</div>@endforelse
    </div>
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> آخر نشاط</h3><a class="btn btn-xs btn-soft" href="{{ route('logs.index', ['user' => $u->id]) }}">السجل الكامل</a></div>
        <div style="max-height:520px;overflow-y:auto">@foreach($logs as $l)
            <div style="padding:10px 20px;border-bottom:1px solid var(--line-2)"><div class="row between"><b class="small">{{ $l->label }}</b><span class="xs muted">{{ $l->created_at->format('m/d H:i') }}</span></div><div class="xs muted">{{ $l->description }}</div></div>
        @endforeach</div>
    </div>
</div>
@endsection
