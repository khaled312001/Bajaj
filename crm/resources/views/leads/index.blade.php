@extends('layouts.app')
@section('title', 'الليدز')
@section('heading', 'الطلبات الواردة (الليدز)')
@section('sub', 'طلبات نموذج العملاء العام — وزّعها على موظفي المبيعات')

@section('content')
<div class="card mb">
    <div class="card-body row between wrap" style="gap:10px">
        <div class="small"><i class="fa-solid fa-link"></i> رابط النموذج العام: <a href="{{ $publicUrl }}" target="_blank" dir="ltr">{{ $publicUrl }}</a></div>
        <a class="btn btn-xs btn-ghost" href="{{ route('settings.index') }}#"><i class="fa-solid fa-gear"></i> تعديل محتوى النموذج</a>
    </div>
</div>

<form method="GET" class="card mb">
    <div class="card-body filter-bar">
        <select class="input" name="status" onchange="this.form.submit()">
            <option value="">كل الحالات ({{ $counts->sum() }})</option>
            @foreach(\App\Models\Lead::STATUSES as $st)
                <option value="{{ $st }}" @selected(($filters['status'] ?? '') === $st)>{{ ['new' => 'جديد', 'assigned' => 'مُعيّن', 'converted' => 'تم التحويل', 'rejected' => 'مرفوض'][$st] }} ({{ $counts[$st] ?? 0 }})</option>
            @endforeach
        </select>
    </div>
</form>

<form method="POST" action="{{ route('leads.assign') }}" id="assign-form">
    @csrf
    <div class="card">
        <div class="card-head row between wrap" style="gap:10px">
            <h3><i class="fa-solid fa-inbox"></i> الطلبات ({{ $leads->total() }})</h3>
            <div class="row" style="gap:8px">
                <select class="input" name="assigned_to" style="width:auto" required>
                    <option value="">تعيين إلى موظف...</option>
                    @foreach($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-primary" type="submit" data-confirm="تعيين الطلبات المحددة؟"><i class="fa-solid fa-user-check"></i> تعيين المحدد</button>
            </div>
        </div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th style="width:30px"><input type="checkbox" onclick="this.closest('table').querySelectorAll('.lead-chk').forEach(c=>c.checked=this.checked)"></th><th>الاسم</th><th>الهاتف</th><th>المركبة</th><th>المحافظة</th><th>الحالة</th><th>المسؤول</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
            @forelse($leads as $l)
                <tr>
                    <td>@if($l->status !== 'converted')<input class="lead-chk" type="checkbox" name="ids[]" value="{{ $l->id }}" form="assign-form">@endif</td>
                    <td><b>{{ $l->name }}</b></td>
                    <td class="num">{{ $l->phone }}</td>
                    <td>{{ $l->vehicle ?: '—' }}</td>
                    <td>{{ $l->governorate ?: '—' }}{{ $l->district ? ' — ' . $l->district : '' }}</td>
                    <td><span class="badge {{ ['new' => 'blue', 'assigned' => 'amber', 'converted' => 'green', 'rejected' => 'gray'][$l->status] }}">{{ ['new' => 'جديد', 'assigned' => 'مُعيّن', 'converted' => 'تم التحويل', 'rejected' => 'مرفوض'][$l->status] }}</span></td>
                    <td>{{ $l->assignee?->name ?: '—' }}</td>
                    <td class="small muted">{{ $l->created_at->format('Y/m/d H:i') }}</td>
                    <td class="row" style="gap:6px">
                        @if($l->status === 'converted' && $l->customer)
                            <a class="btn btn-xs btn-ghost" href="{{ route('customers.show', $l->customer) }}">فتح الملف</a>
                        @elseif($l->status !== 'rejected')
                            <a class="btn btn-xs btn-green" href="{{ route('leads.convert', $l) }}">تحويل لعميل</a>
                            <form method="POST" action="{{ route('leads.reject', $l) }}" data-confirm="تجاهل هذا الطلب؟">@csrf<button class="btn btn-xs btn-danger-soft">تجاهل</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">لا توجد طلبات.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</form>
<div class="mt">{{ $leads->links() }}</div>
@endsection
