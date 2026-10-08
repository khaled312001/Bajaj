@extends('layouts.app')
@section('title', 'نشاطي')
@section('heading', 'نشاطي وأدائي')

@section('content')
<div class="grid g4 mb">
    <div class="card stat"><div class="ico blue"><i class="fa-solid fa-user-plus"></i></div><div><div class="lbl">عملاء أدخلتهم</div><div class="val">{{ $stats['created'] }}</div><div class="xs muted">{{ $stats['created_month'] }} هذا الشهر</div></div></div>
    <div class="card stat"><div class="ico green"><i class="fa-solid fa-calendar-check"></i></div><div><div class="lbl">متابعات أنجزتها</div><div class="val">{{ $stats['followups_done'] }}</div><div class="xs muted">{{ $stats['followups_month'] }} هذا الشهر</div></div></div>
    <a class="card stat link" href="{{ route('followups.index') }}"><div class="ico amber"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="lbl">متابعات معلّقة</div><div class="val">{{ $stats['pending'] }}</div></div></a>
    <div class="card stat"><div class="ico purple"><i class="fa-solid fa-clock"></i></div><div><div class="lbl">آخر دخول</div><div class="val" style="font-size:15px">{{ auth()->user()->last_login_at?->format('m/d H:i') }}</div></div></div>
</div>
<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> سجل عملياتي</h3></div>
    @forelse($logs as $l)
        <div class="row" style="padding:12px 20px;border-bottom:1px solid var(--line-2)"><span class="badge blue">{{ $l->label }}</span><div class="grow small">{{ $l->description }}</div><span class="xs muted">{{ $l->created_at->format('Y/m/d H:i') }}</span></div>
    @empty<div class="empty">لا توجد عمليات بعد</div>@endforelse
    {{ $logs->links() }}
</div>
@endsection
