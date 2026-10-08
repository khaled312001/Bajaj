@extends('layouts.app')
@section('title', 'المتابعات')
@section('heading', 'المتابعات')
@section('sub', 'جدولة ومتابعة العملاء')

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $tabs = [
        'overdue' => ['متأخرة', 'fa-triangle-exclamation', true],
        'today' => ['اليوم', 'fa-calendar-day', false],
        'upcoming' => ['قادمة', 'fa-calendar-days', false],
        'done' => ['المنجزة', 'fa-circle-check', false],
        'all' => ['الكل', 'fa-list', false],
    ];
    $q = request()->except(['tab', 'page']);
@endphp

<div class="grid g4 mb">
    <a href="{{ route('followups.index', ['tab' => 'overdue'] + $q) }}" class="card stat link"><div class="ico red"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="lbl">متأخرة</div><div class="val">{{ $counts['overdue'] }}</div></div></a>
    <a href="{{ route('followups.index', ['tab' => 'today'] + $q) }}" class="card stat link"><div class="ico amber"><i class="fa-solid fa-calendar-day"></i></div><div><div class="lbl">مطلوبة اليوم</div><div class="val">{{ $counts['today'] }}</div></div></a>
    <a href="{{ route('followups.index', ['tab' => 'upcoming'] + $q) }}" class="card stat link"><div class="ico blue"><i class="fa-solid fa-calendar-days"></i></div><div><div class="lbl">قادمة</div><div class="val">{{ $counts['upcoming'] }}</div></div></a>
    <a href="{{ route('followups.index', ['tab' => 'done'] + $q) }}" class="card stat link"><div class="ico green"><i class="fa-solid fa-circle-check"></i></div><div><div class="lbl">منجزة</div><div class="val">{{ $counts['done'] }}</div></div></a>
</div>

<div class="row wrap mb" style="gap:14px">
    <div class="tabs grow">
        @foreach($tabs as $k => [$label, $icon, $red])
            <a class="tab {{ $tab === $k ? 'active' : '' }}" href="{{ route('followups.index', ['tab' => $k] + $q) }}"><i class="fa-solid {{ $icon }}"></i> {{ $label }}
                @if(isset($counts[$k]))<span class="cnt {{ $red && $counts[$k] ? 'red' : '' }}">{{ $counts[$k] }}</span>@endif</a>
        @endforeach
    </div>
    @if(auth()->user()->allows('followups', 'create'))<button class="btn btn-primary" data-open="m-fu-add"><i class="fa-solid fa-plus"></i> متابعة جديدة</button>@endif
</div>

<div class="card mb">
    <form method="GET" class="card-pad">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="filter-bar">
            <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم العميل / هاتف / كود"></div>
            <select class="input" name="reason"><option value="">كل الأسباب</option>@foreach($reasons as $r)<option @selected(($filters['reason'] ?? '') === $r)>{{ $r }}</option>@endforeach</select>
            <select class="input" name="priority"><option value="">كل الأولويات</option><option value="high" @selected(($filters['priority'] ?? '') === 'high')>مهمة فقط</option></select>
            @if($isAdmin)<select class="input" name="employee"><option value="">كل الموظفين</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected(($filters['employee'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select>@endif
            <div class="btn-group"><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> تصفية</button><a class="btn btn-ghost" href="{{ route('followups.index', ['tab' => $tab]) }}">مسح</a></div>
        </div>
    </form>
</div>

<div class="card">
    @php($lastDay = null)
    @forelse($items as $f)
        @php($day = $f->status === 'done' ? $f->completed_at?->format('Y/m/d') : $f->due_date->format('Y/m/d'))
        @if($day !== $lastDay)
            <div class="day-head"><span>{{ $f->status === 'done' ? 'أُنجزت في ' : '' }}{{ $f->status === 'done' ? $day : ($f->due_date->isToday() ? 'اليوم' : ($f->due_date->isTomorrow() ? 'غداً' : $f->due_date->translatedFormat('l j F Y'))) }}</span></div>
            @php($lastDay = $day)
        @endif
        <div class="fu">
            <div class="fu-date {{ $f->is_overdue ? 'overdue' : ($f->status === 'pending' && $f->is_today ? 'today' : '') }}"><b>{{ $f->due_date->format('d') }}</b><span>{{ $f->due_date->translatedFormat('M') }}</span></div>
            <div class="fu-body">
                <div class="title">
                    <a href="{{ route('customers.show', $f->customer_id) }}">{{ $f->customer?->name }}</a>
                    <span class="badge sky">{{ $f->reason }}</span>
                    @if($f->status === 'done')<span class="badge green">تمت</span>@elseif($f->status === 'cancelled')<span class="badge gray">ملغاة</span>@elseif($f->is_overdue)<span class="badge red">متأخرة {{ $f->due_date->diffInDays(today()) }} يوم</span>@endif
                    @if($f->priority === 'high')<span class="badge purple"><i class="fa-solid fa-bolt"></i> مهمة</span>@endif
                </div>
                @if($f->notes)<div class="notes">{{ $f->notes }}</div>@endif
                @if($f->outcome)<div class="notes" style="color:var(--green)"><i class="fa-solid fa-check"></i> {{ $f->outcome }}</div>@endif
                <div class="meta">
                    <span><i class="fa-solid fa-phone"></i> <span class="num">{{ $f->customer?->phone }}</span></span>
                    @if($f->due_time)<span><i class="fa-regular fa-clock"></i> {{ substr($f->due_time, 0, 5) }}</span>@endif
                    <span><i class="fa-solid fa-user"></i> {{ $f->assignee?->name ?? '—' }}</span>
                    @if($f->creator && $f->created_by !== $f->assigned_to)<span>أنشأها {{ $f->creator->name }}</span>@endif
                    @if($f->completed_at)<span>بواسطة {{ $f->completer?->name }}</span>@endif
                </div>
            </div>
            <div class="fu-actions no-print">
                @if($f->status === 'pending')
                    <a class="btn btn-sm btn-ghost" href="tel:{{ $f->customer?->phone }}" title="اتصال"><i class="fa-solid fa-phone"></i></a>
                    @if($f->customer?->whatsappLink())<a class="btn btn-sm btn-ghost" target="_blank" rel="noopener noreferrer" href="{{ $f->customer->whatsappLink() }}" title="واتساب"><i class="fa-brands fa-whatsapp" style="color:#16a34a"></i></a>@endif
                    <button class="btn btn-sm btn-green" data-open="m-fu-done" data-action="{{ route('followups.complete', $f) }}" data-fill-customer-name="{{ $f->customer?->name }}"><i class="fa-solid fa-check"></i> تم</button>
                    <button class="btn btn-sm btn-ghost" data-open="m-fu-move" data-action="{{ route('followups.update', $f) }}" title="تأجيل"><i class="fa-regular fa-clock"></i></button>
                    <form method="POST" action="{{ route('followups.destroy', $f) }}" data-confirm="إلغاء هذه المتابعة؟">@csrf @method('DELETE')<button class="btn btn-sm btn-danger-soft" title="إلغاء"><i class="fa-solid fa-xmark"></i></button></form>
                @endif
                <a class="btn btn-sm btn-soft" href="{{ route('customers.show', $f->customer_id) }}">الملف</a>
            </div>
        </div>
    @empty
        <div class="empty"><i class="fa-regular fa-calendar-check"></i><b>لا توجد متابعات هنا</b>غيّر التبويب أو جدول متابعة جديدة.</div>
    @endforelse
    {{ $items->links() }}
</div>

@include('followups._modals', ['staff' => $staff])
@endsection
