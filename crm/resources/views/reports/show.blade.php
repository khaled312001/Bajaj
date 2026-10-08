@extends('layouts.app')
@section('title', $report['title'])
@section('heading', $report['title'])
@section('sub', $from->format('Y/m/d') . ' ← ' . $to->format('Y/m/d'))

@section('content')
@php($qs = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'employee' => $emp])
<div class="card mb no-print">
    <form method="GET" class="card-pad">
        <div class="filter-bar">
            <div class="field"><label>من تاريخ</label><input class="input" type="date" name="from" value="{{ $from->toDateString() }}"></div>
            <div class="field"><label>إلى تاريخ</label><input class="input" type="date" name="to" value="{{ $to->toDateString() }}"></div>
            <div class="field"><label>الموظف</label><select class="input" name="employee"><option value="">كل الموظفين</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected($emp == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="btn-group">
                <button class="btn btn-primary"><i class="fa-solid fa-rotate"></i> تحديث</button>
                @if(auth()->user()->isAdmin())<a class="btn btn-green" href="{{ route('reports.export', ['key' => $key] + $qs) }}"><i class="fa-solid fa-file-excel"></i> تصدير Excel</a>
                <a class="btn btn-ghost" target="_blank" rel="noopener" href="{{ route('reports.pdf', ['key' => $key] + $qs) }}"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i> PDF</a>@endif
                <button type="button" class="btn btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i></button>
                <a class="btn btn-ghost" href="{{ route('reports.index') }}">كل التقارير</a>
            </div>
        </div>
        <div class="row wrap mt" style="gap:8px">
            @foreach([['اليوم', today(), today()], ['هذا الأسبوع', now()->startOfWeek(), now()], ['هذا الشهر', now()->startOfMonth(), now()], ['الشهر الماضي', now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()], ['آخر 90 يوماً', now()->subDays(90), now()], ['هذه السنة', now()->startOfYear(), now()]] as [$l, $a, $b])
                <a class="btn btn-xs btn-soft" href="{{ route('reports.show', ['key' => $key, 'from' => $a->toDateString(), 'to' => $b->toDateString(), 'employee' => $emp]) }}">{{ $l }}</a>
            @endforeach
        </div>
    </form>
</div>

@if(!empty($report['cards']))
<div class="grid g4 mb">
    @foreach($report['cards'] as $label => $value)
        <div class="card stat"><div class="ico blue"><i class="fa-solid fa-hashtag"></i></div><div><div class="lbl">{{ $label }}</div>
            <div class="val">@if(in_array($label, $report['money_cards'] ?? [], true))@money($value) <small>ج.م</small>@else{{ is_numeric($value) ? number_format($value) : $value }}@endif</div></div></div>
    @endforeach
</div>
@endif

<div class="grid {{ !empty($report['chart']) && count($report['chart']['labels']) ? 'g-main' : '' }}">
    @if(!empty($report['chart']) && count($report['chart']['labels']))
        <div class="card" style="order:2">
            <div class="card-head"><h3><i class="fa-solid fa-chart-simple"></i> {{ $report['chart']['label'] }}</h3></div>
            <div class="card-body"><div class="chart-box"><canvas id="rc"></canvas></div></div>
        </div>
    @endif
    <div class="card" style="order:1;min-width:0">
        <div class="card-head"><h3><i class="fa-solid fa-table"></i> البيانات ({{ count($report['rows']) }})</h3></div>
        <div class="table-wrap" style="max-height:640px;overflow-y:auto">
            <table class="tbl">
                <thead><tr>@foreach($report['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($report['rows'] as $r)
                    <tr>@foreach($r as $i => $cell)<td>@if(in_array($i + 1, $report['money'], true) && is_numeric($cell))<span class="num">@money($cell)</span>@elseif(is_numeric($cell) && !in_array($i, [1, 2, 3]) && strlen((string) $cell) < 9)<span class="num">{{ $cell }}</span>@else{{ $cell }}@endif</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}"><div class="empty"><i class="fa-regular fa-folder-open"></i><b>لا توجد بيانات في هذه الفترة</b></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if(!empty($report['chart']) && count($report['chart']['labels']))
<script>
const rc = @json($report['chart']);
makeChart('rc', { type: rc.type, data: { labels: rc.labels, datasets: [{ label: rc.label, data: rc.data, backgroundColor: rc.type === 'line' ? 'rgba(37,99,235,.12)' : (rc.type === 'doughnut' ? PALETTE : '#2563eb'), borderColor: '#2563eb', borderWidth: rc.type === 'line' ? 3 : 0, fill: rc.type === 'line', tension: .35, borderRadius: 6 }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: rc.type === 'doughnut', position: 'bottom' } }, scales: rc.type === 'doughnut' ? {} : { y: { beginAtZero: true }, x: { grid: { display: false } } } } });
</script>
@endif
@endpush
