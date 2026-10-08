@extends('layouts.app')
@section('title', 'ملخص الفترات')
@section('heading', 'ملخص ' . $periods[$s['period']])
@section('sub', $s['scope'] . ' — ' . $s['label'])

@section('content')
@php
    $cur = $s['cur']; $prev = $s['prev'];
    $delta = function ($k) use ($cur, $prev) {
        if ($cur[$k] === null) return null;
        $d = $cur[$k] - $prev[$k];
        $pct = $prev[$k] > 0 ? round($d / $prev[$k] * 100) : ($cur[$k] > 0 ? 100 : 0);
        return ['d' => $d, 'pct' => $pct, 'cls' => $d > 0 ? 'green' : ($d < 0 ? 'red' : 'gray'), 'ic' => $d > 0 ? 'fa-arrow-trend-up' : ($d < 0 ? 'fa-arrow-trend-down' : 'fa-minus')];
    };
    $cards = [
        ['customers', 'عملاء جدد', 'fa-user-plus', 'blue', false], ['followups_done', 'متابعات منجزة', 'fa-calendar-check', 'green', false],
        ['deals', 'صفقات جديدة', 'fa-handshake', 'purple', false], ['sales', 'مبيعات منفذة', 'fa-circle-check', 'green', false],
        ['sales_value', 'قيمة المبيعات', 'fa-sack-dollar', 'amber', true], ['collected', 'المحصّل', 'fa-money-bill-wave', 'green', true],
        ['inst_due_amount', 'أقساط مستحقة', 'fa-clock', 'red', true], ['documents', 'مستندات صادرة', 'fa-file-pdf', 'blue', false],
        ['serious', 'عملاء جادون', 'fa-fire', 'amber', false], ['lost', 'عملاء مفقودون', 'fa-user-xmark', 'red', false],
        ['vehicles_in', 'مركبات واردة للمخزن', 'fa-truck-ramp-box', 'blue', false], ['vehicles_sold', 'مركبات خرجت من المخزن', 'fa-motorcycle', 'purple', false],
    ];
    $qs = ['date' => $anchor->toDateString()];
@endphp

<div class="card mb"><div class="card-body">
    <div class="row wrap" style="gap:10px">
        <div class="tabs">@foreach($periods as $k => $l)<a class="tab {{ $s['period'] === $k ? 'active' : '' }}" href="{{ route('summary.index', ['period' => $k] + $qs) }}">{{ $l }}</a>@endforeach</div>
        <form method="GET" class="row" style="gap:8px"><input type="hidden" name="period" value="{{ $s['period'] }}"><input class="input" type="date" name="date" value="{{ $anchor->toDateString() }}" onchange="this.form.submit()" title="التاريخ"></form>
        <a class="btn btn-ghost" href="{{ route('summary.index', ['period' => $s['period']]) }}"><i class="fa-solid fa-rotate-left"></i> الآن</a>
        @if(auth()->user()->isAdmin())<a class="btn btn-ghost" target="_blank" rel="noopener" href="{{ route('summary.pdf', ['period' => $s['period']] + $qs) }}"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i> PDF</a>@endif
    </div>
    <div class="muted small mt">المقارنة مع الفترة السابقة: {{ $s['prev_label'] }}</div>
</div></div>

<div class="grid g4 mb">
@foreach($cards as [$k, $label, $icon, $color, $money])
    @continue($cur[$k] === null)
    @php
        $dl = $delta($k);
    @endphp
    <div class="card stat"><div class="ico {{ $color }}"><i class="fa-solid {{ $icon }}"></i></div><div>
        <div class="lbl">{{ $label }}</div><div class="val">{{ $money ? number_format($cur[$k]) : number_format($cur[$k]) }}</div>
        <div class="xs"><span class="badge {{ $dl['cls'] }}"><i class="fa-solid {{ $dl['ic'] }}"></i> {{ $dl['pct'] > 0 ? '+' : '' }}{{ $dl['pct'] }}%</span> <span class="muted">السابق: {{ number_format($prev[$k]) }}</span></div></div></div>
@endforeach
</div>

<div class="grid g-main mb">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-chart-column"></i> النشاط خلال الفترة</h3></div><div class="card-body"><canvas id="ch" height="110"></canvas></div></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-motorcycle"></i> أكثر المنتجات مبيعاً</h3></div>
        <div class="card-body">@forelse($s['products'] as $p)<div class="row between small" style="padding:7px 0;border-bottom:1px dashed var(--line)"><span>{{ $p['name'] }}</span><span><b>{{ $p['count'] }}</b> <span class="muted num">({{ number_format($p['value']) }})</span></span></div>@empty<div class="empty">لا مبيعات في الفترة</div>@endforelse</div></div>
</div>

@if($s['employees'])
<div class="card"><div class="card-head"><h3><i class="fa-solid fa-ranking-star"></i> أداء الموظفين</h3></div>
    <div class="table-wrap"><table class="tbl"><thead><tr><th>الموظف</th><th>عملاء جدد</th><th>متابعات</th><th>صفقات</th><th>مبيعات</th><th>المحصّل</th><th>مستندات</th></tr></thead><tbody>
    @foreach($s['employees'] as $e)<tr><td><b>{{ $e['name'] }}</b></td><td class="num">{{ $e['customers'] }}</td><td class="num">{{ $e['followups'] }}</td><td class="num">{{ $e['deals'] }}</td><td class="num">{{ $e['sales'] }}</td><td class="num">{{ number_format($e['collected']) }}</td><td class="num">{{ $e['documents'] }}</td></tr>@endforeach
    </tbody></table></div></div>
@endif
@endsection

@push('scripts')
<script>
(function () {
  const d = @json($s['series']);
  new Chart(document.getElementById('ch'), { type: 'bar',
    data: { labels: d.labels, datasets: [
      { label: 'عملاء جدد', data: d.customers, backgroundColor: '#3b82f6', borderRadius: 6, yAxisID: 'y' },
      { label: 'متابعات منجزة', data: d.followups, backgroundColor: '#22c55e', borderRadius: 6, yAxisID: 'y' },
      { label: 'المحصّل (ج.م)', data: d.collected, type: 'line', borderColor: '#f59e0b', backgroundColor: '#f59e0b', tension: .35, yAxisID: 'y1' } ] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { family: 'Cairo' } } } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { beginAtZero: true, position: 'left', grid: { drawOnChartArea: false } } } } });
})();
</script>
@endpush
