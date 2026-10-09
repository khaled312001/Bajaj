@extends('layouts.app')
@section('title', 'طلبات نقل العملاء')
@section('heading', 'طلبات نقل العملاء')
@section('sub', 'طلبات الموظفين لتولي عملاء متابعتهم متأخرة 48 ساعة فأكثر')

@section('content')
<div class="card mb">
    <div class="card-head"><h3><i class="fa-solid fa-hourglass-half"></i> قيد المراجعة ({{ $pending->count() }})</h3></div>
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th>العميل</th><th>المسؤول الحالي</th><th>طالب النقل</th><th>السبب</th><th>التاريخ</th><th></th></tr></thead>
        <tbody>
        @forelse($pending as $r)
            <tr>
                <td><a href="{{ route('customers.show', $r->customer) }}"><b>{{ $r->customer?->name }}</b></a> <span class="muted small">{{ $r->customer?->code }}</span></td>
                <td>{{ $r->previousAssignee?->name ?: '—' }}</td>
                <td>{{ $r->requester?->name }}</td>
                <td class="small">{{ $r->reason ?: '—' }}</td>
                <td class="small muted">{{ $r->created_at->format('Y/m/d H:i') }}</td>
                <td class="row" style="gap:6px">
                    <form method="POST" action="{{ route('reassignments.approve', $r) }}" data-confirm="نقل العميل إلى {{ $r->requester?->name }}؟">@csrf<button class="btn btn-xs btn-green"><i class="fa-solid fa-check"></i> موافقة</button></form>
                    <form method="POST" action="{{ route('reassignments.reject', $r) }}">@csrf<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-xmark"></i> رفض</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد طلبات قيد المراجعة.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> القرارات الأخيرة</h3></div>
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th>العميل</th><th>طالب النقل</th><th>القرار</th><th>بواسطة</th><th>التاريخ</th></tr></thead>
        <tbody>
        @forelse($recent as $r)
            <tr>
                <td>{{ $r->customer?->name }}</td>
                <td>{{ $r->requester?->name }}</td>
                <td><span class="badge {{ $r->status === 'approved' ? 'green' : 'red' }}">{{ $r->status === 'approved' ? 'تمت الموافقة' : 'مرفوض' }}</span></td>
                <td>{{ $r->decider?->name ?: '—' }}</td>
                <td class="small muted">{{ $r->decided_at?->format('Y/m/d H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">لا توجد قرارات سابقة.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
