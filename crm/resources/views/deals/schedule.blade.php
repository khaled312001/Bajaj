@extends('layouts.app')
@php($allowPrint = true)
@section('title', 'جدول الأقساط')
@section('heading', 'جدول الأقساط — ' . $deal->customer->name)

@section('content')
<div class="row mb no-print">
    <a class="btn btn-ghost" href="{{ route('customers.show', $deal->customer_id) }}"><i class="fa-solid fa-arrow-right"></i> رجوع</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة الجدول</button>
</div>
<div class="card">
    <div class="card-body">
        <div class="row between mb">
            <div><h2 style="font-size:20px;font-weight:900">جدول أقساط {{ $deal->vehicle }}</h2><div class="muted">{{ $deal->customer->name }} — <span class="code">{{ $deal->customer->code }}</span></div></div>
            <div class="muted small">تاريخ الطباعة: {{ now()->format('Y/m/d') }}</div>
        </div>
        <div class="grid g4 mb">
            <div class="kv" style="display:block"><span>السعر الإجمالي</span><br><b class="num">@money($deal->total_price)</b></div>
            <div class="kv" style="display:block"><span>المقدم</span><br><b class="num">@money($deal->down_payment)</b></div>
            <div class="kv" style="display:block"><span>المبلغ الممول</span><br><b class="num">@money($deal->financed_amount)</b></div>
            <div class="kv" style="display:block"><span>المتبقي</span><br><b class="num" style="color:var(--red)">@money($deal->balance)</b></div>
        </div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th>#</th><th>تاريخ الاستحقاق</th><th>القسط</th><th>المسدد</th><th>المتبقي</th><th>الحالة</th></tr></thead>
            <tbody>@foreach($deal->installments as $i)
                <tr><td>{{ $i->number }}</td><td>{{ $i->due_date->format('Y/m/d') }}</td><td class="num">@money($i->amount)</td><td class="num">@money($i->paid_amount)</td><td class="num">@money($i->remaining)</td>
                    <td><span class="badge {{ $i->state_class }}">{{ $i->state_label }}</span></td></tr>
            @endforeach</tbody>
            <tfoot><tr><td colspan="2"><b>الإجمالي</b></td><td class="num"><b>@money($deal->installments->sum('amount'))</b></td><td class="num"><b>@money($deal->installments->sum('paid_amount'))</b></td><td class="num"><b>@money($deal->balance)</b></td><td></td></tr></tfoot>
        </table></div>
    </div>
</div>
@endsection
