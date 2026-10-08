@extends('pdf.layout')
@section('body')
@php
    $c = $s['cur']; $p = $s['prev'];
    $rows = [
        ['عملاء جدد', 'customers'], ['عملاء جادون', 'serious'], ['عملاء مفقودون', 'lost'], ['متابعات منجزة', 'followups_done'], ['متابعات مسجلة', 'followups_new'],
        ['صفقات جديدة', 'deals'], ['مبيعات منفذة', 'sales'], ['قيمة المبيعات', 'sales_value'], ['المحصّل', 'collected'], ['عدد الدفعات', 'payments'],
        ['أقساط مستحقة (عدد)', 'inst_due_count'], ['أقساط مستحقة (قيمة)', 'inst_due_amount'], ['المسدد من المستحق', 'inst_paid_amount'], ['مستندات صادرة', 'documents'],
        ['مركبات واردة', 'vehicles_in'], ['مركبات خارجة', 'vehicles_sold'],
    ];
@endphp
<h1 class="title">ملخص {{ \App\Services\SummaryService::PERIODS[$s['period']] }}</h1>
<div class="title-line">{{ $s['scope'] }} — {{ $s['label'] }}</div>
<table class="data"><thead><tr><th>المؤشر</th><th>الفترة الحالية</th><th>الفترة السابقة</th><th>التغير</th></tr></thead><tbody>
@foreach($rows as [$label, $k])
    @continue($c[$k] === null)
    @php
        $d = $c[$k] - $p[$k];
        $pct = $p[$k] > 0 ? round($d / $p[$k] * 100) : ($c[$k] > 0 ? 100 : 0);
    @endphp
    <tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ $label }}</td><td class="n"><b>{{ number_format($c[$k]) }}</b></td><td class="n">{{ number_format($p[$k]) }}</td><td class="n" style="color:{{ $d > 0 ? '#15803d' : ($d < 0 ? '#b91c1c' : '#64748b') }}">{{ $pct > 0 ? '+' : '' }}{{ $pct }}%</td></tr>
@endforeach
</tbody></table>
@if($s['employees'])
<h3 style="margin-top:6mm;color:#0b1b3a">أداء الموظفين</h3>
<table class="data"><thead><tr><th>الموظف</th><th>عملاء</th><th>متابعات</th><th>صفقات</th><th>مبيعات</th><th>المحصّل</th></tr></thead><tbody>
@foreach($s['employees'] as $e)<tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ $e['name'] }}</td><td class="n">{{ $e['customers'] }}</td><td class="n">{{ $e['followups'] }}</td><td class="n">{{ $e['deals'] }}</td><td class="n">{{ $e['sales'] }}</td><td class="n">{{ number_format($e['collected']) }}</td></tr>@endforeach
</tbody></table>
@endif
@endsection
