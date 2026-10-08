@extends('layouts.app')
@section('title', 'لوحة التحكم')
@section('heading', 'مرحباً، ' . auth()->user()->name)
@section('sub', now()->translatedFormat('l j F Y'))

@section('content')
@php($isAdmin = auth()->user()->isAdmin())

<div class="card mb dash-hero"><div class="card-body row" style="gap:18px">
    <div class="dash-logo"><img src="{{ asset('img/logo.jpg') }}" alt="Bajaj Qena"></div>
    <div class="grow"><div style="font-size:20px;font-weight:900">{{ $appName }}</div><div class="muted">إدارة المبيعات والتقسيط وخدمة العملاء — {{ now()->translatedFormat('l j F Y') }}</div></div>
</div></div>
<style>.dash-logo{width:84px;height:84px;border-radius:18px;background:#000;padding:4px;flex:0 0 auto}.dash-logo img{width:100%;height:100%;object-fit:contain;border-radius:14px}@media(max-width:600px){.dash-logo{width:64px;height:64px}}</style>

@if($isAdmin && $alerts->count())
    <div class="alert alert-warning" style="flex-direction:column;gap:6px">
        <div class="row"><i class="fa-solid fa-shield-halved"></i><b>تنبيهات أمنية خلال آخر 3 أيام</b><a class="btn btn-xs btn-amber" style="margin-right:auto" href="{{ route('logs.index', ['action' => 'security.alert']) }}">عرض الكل</a></div>
        @foreach($alerts as $a)<div class="small">• {{ $a->created_at->diffForHumans() }} — {{ $a->description }}</div>@endforeach
    </div>
@endif

<div class="grid g4 mb">
    <a href="{{ route('customers.index') }}" class="card stat link"><div class="ico blue"><i class="fa-solid fa-users"></i></div><div><div class="lbl">{{ $isAdmin ? 'إجمالي العملاء' : 'عملائي' }}</div><div class="val">{{ number_format($stats['customers']) }}</div><div class="xs muted">+{{ $stats['new_month'] }} هذا الشهر</div></div></a>
    <a href="{{ route('customers.index', ['status' => 'منفذة']) }}" class="card stat link"><div class="ico green"><i class="fa-solid fa-circle-check"></i></div><div><div class="lbl">مبيعات منفذة</div><div class="val">{{ number_format($stats['done']) }}</div></div></a>
    <a href="{{ route('followups.index', ['tab' => 'today']) }}" class="card stat link"><div class="ico amber"><i class="fa-solid fa-calendar-day"></i></div><div><div class="lbl">متابعات اليوم</div><div class="val">{{ $stats['following_today'] }}</div></div></a>
    <a href="{{ route('followups.index', ['tab' => 'overdue']) }}" class="card stat link"><div class="ico red"><i class="fa-solid fa-bell"></i></div><div><div class="lbl">متابعات متأخرة</div><div class="val">{{ $stats['overdue_followups'] }}</div></div></a>
</div>

@if($isAdmin)
<div class="grid g4 mb">
    <div class="card stat"><div class="ico purple"><i class="fa-solid fa-scale-balanced"></i></div><div><div class="lbl">إجمالي المديونية</div><div class="val">@money($stats['debt']) <small>ج.م</small></div></div></div>
    <div class="card stat"><div class="ico teal"><i class="fa-solid fa-money-bill-trend-up"></i></div><div><div class="lbl">تحصيل هذا الشهر</div><div class="val">@money($stats['collected_month']) <small>ج.م</small></div></div></div>
    <a href="{{ route('reports.show', 'overdue') }}" class="card stat link"><div class="ico red"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="lbl">أقساط متأخرة</div><div class="val">{{ $stats['overdue_installments'] }}</div></div></a>
    <a href="{{ route('reports.show', 'overdue') }}" class="card stat link"><div class="ico amber"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="lbl">قيمة المتأخر</div><div class="val">@money($stats['overdue_amount']) <small>ج.م</small></div></div></a>
</div>
@endif

<div class="grid g-main">
    <div class="section-gap">
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-chart-line"></i> العملاء الجدد — آخر 14 يوماً</h3></div>
            <div class="card-body"><div class="chart-box"><canvas id="dayChart"></canvas></div></div>
        </div>

        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-calendar-check"></i> متابعات اليوم والمتأخرة</h3><a class="btn btn-sm btn-soft" href="{{ route('followups.index') }}">كل المتابعات</a></div>
            @forelse($todayFollowups as $f)
                <div class="fu">
                    <div class="fu-date {{ $f->is_overdue ? 'overdue' : 'today' }}"><b>{{ $f->due_date->format('d') }}</b><span>{{ $f->due_date->translatedFormat('M') }}</span></div>
                    <div class="fu-body">
                        <div class="title"><a href="{{ route('customers.show', $f->customer_id) }}">{{ $f->customer?->name }}</a>
                            <span class="badge {{ $f->is_overdue ? 'red' : 'amber' }}">{{ $f->is_overdue ? 'متأخرة' : 'اليوم' }}</span>
                            @if($f->priority === 'high')<span class="badge purple"><i class="fa-solid fa-bolt"></i> مهمة</span>@endif</div>
                        <div class="notes">{{ $f->reason }}@if($f->notes) — {{ \Illuminate\Support\Str::limit($f->notes, 80) }}@endif</div>
                        <div class="meta"><span><i class="fa-solid fa-phone"></i> <span class="num">{{ $f->customer?->phone }}</span></span>@if($isAdmin)<span><i class="fa-solid fa-user"></i> {{ $f->assignee?->name }}</span>@endif</div>
                    </div>
                    <div class="fu-actions"><a class="btn btn-sm btn-primary" href="{{ route('customers.show', $f->customer_id) }}">فتح الملف</a></div>
                </div>
            @empty
                <div class="empty"><i class="fa-regular fa-circle-check"></i><b>لا توجد متابعات مستحقة</b>أنجزت كل شيء — عمل رائع!</div>
            @endforelse
        </div>
    </div>

    <div class="section-gap">
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-chart-pie"></i> العملاء حسب الحالة</h3></div>
            <div class="card-body"><div class="chart-box sm"><canvas id="statusChart"></canvas></div></div>
        </div>

        @if($isAdmin)
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-user-tie"></i> إدخال العملاء هذا الشهر</h3></div>
            <div class="card-body">
                @foreach($byEmployee as $e)
                    @php($max = max(1, $byEmployee->max('created')))
                    <div class="mb-s"><div class="row between small"><b>{{ $e->name }}</b><span class="muted">{{ $e->created }} عميل</span></div>
                    <div class="progress"><span style="width:{{ $e->created / $max * 100 }}%"></span></div></div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-calendar-days"></i> أقساط مستحقة خلال 7 أيام</h3></div>
            @forelse($dueInstallments as $i)
                <div class="row between" style="padding:12px 20px;border-bottom:1px solid var(--line-2)">
                    <div><a href="{{ route('customers.show', $i->deal->customer_id) }}"><b>{{ $i->deal->customer?->name }}</b></a><div class="xs muted">قسط {{ $i->number }} — {{ $i->due_date->format('Y/m/d') }}</div></div>
                    <b class="num">@money($i->remaining)</b>
                </div>
            @empty<div class="empty" style="padding:30px"><i class="fa-regular fa-calendar-check" style="font-size:30px"></i>لا توجد أقساط مستحقة قريباً</div>@endforelse
        </div>
        @endif

        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-user-plus"></i> أحدث العملاء</h3></div>
            @foreach($recent as $c)
                <a class="row" href="{{ route('customers.show', $c) }}" style="padding:12px 20px;border-bottom:1px solid var(--line-2);color:inherit">
                    <div class="avatar sm">{{ mb_substr($c->name, 0, 1) }}</div>
                    <div class="grow"><b>{{ $c->name }}</b><div class="xs muted">أدخله: {{ $c->creator?->name ?? '—' }} · {{ $c->created_at->diffForHumans() }}</div></div>
                    <span class="badge {{ $c->status_class }}">{{ $c->status }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
makeChart('dayChart', { type: 'line', data: { labels: @json($dayChart['labels']), datasets: [{ label: 'عملاء جدد', data: @json($dayChart['data']), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35, pointRadius: 3, borderWidth: 3 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } } });
makeChart('statusChart', { type: 'doughnut', data: { labels: @json($statusChart['labels']), datasets: [{ data: @json($statusChart['data']), backgroundColor: @json($statusChart['labels']).map(l => ({ 'مفتوحة': '#3b82f6', 'جاري التقسيط': '#f59e0b', 'منفذة': '#22c55e', 'مفقودة': '#ef4444' }[l] || '#94a3b8')), borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'bottom' } } } });
</script>
@endpush
