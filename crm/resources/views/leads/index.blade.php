@extends('layouts.app')
@section('title', 'الليدز')
@section('heading', 'الطلبات الواردة (الليدز)')
@section('sub', 'طلبات نموذج العملاء العام — وزّعها على موظفي المبيعات')

@section('content')
<div class="card mb">
    <div class="card-body row between wrap" style="gap:10px">
        <div class="small"><i class="fa-solid fa-link"></i> رابط النموذج العام: <a href="{{ $publicUrl }}" target="_blank" dir="ltr">{{ $publicUrl }}</a></div>
        <a class="btn btn-xs btn-primary" href="{{ route('leads.form') }}"><i class="fa-solid fa-pen-to-square"></i> تعديل محتوى النموذج</a>
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

{{-- the bulk forms stay empty and outside the table (no nested forms); the checked ids are added on submit --}}
<form method="POST" action="{{ route('leads.assign') }}" id="assign-form">@csrf</form>
<form method="POST" action="{{ route('leads.destroy') }}" id="delete-form">@csrf</form>
    <div class="card">
        <div class="card-head row between wrap" style="gap:10px">
            <h3><i class="fa-solid fa-inbox"></i> الطلبات ({{ $leads->total() }})</h3>
            <div class="row wrap" style="gap:8px">
                <select class="input" name="assigned_to" form="assign-form" style="width:auto">
                    <option value="">تعيين إلى موظف...</option>
                    @foreach($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-primary" type="button" data-bulk="assign-form"><i class="fa-solid fa-user-check"></i> تعيين وتحويل المحدد</button>
                <button class="btn btn-sm btn-danger-soft" type="button" data-bulk="delete-form"><i class="fa-solid fa-trash"></i> حذف المحدد</button>
            </div>
        </div>
        <div class="table-wrap"><table class="tbl">
            <thead><tr><th style="width:30px"><input type="checkbox" onclick="this.closest('table').querySelectorAll('.lead-chk').forEach(c=>c.checked=this.checked)"></th><th>الاسم</th><th>الهاتف</th><th>المركبة</th><th>المحافظة</th><th>الحالة</th><th>المسؤول</th><th>التاريخ</th><th></th></tr></thead>
            <tbody>
            @forelse($leads as $l)
                <tr>
                    <td><input class="lead-chk" type="checkbox" value="{{ $l->id }}" data-converted="{{ $l->status === 'converted' ? 1 : 0 }}"></td>
                    <td><b>{{ $l->name }}</b>@if($l->address)<div class="small muted">{{ $l->address }}</div>@endif
                        @foreach($l->extra ?? [] as $e)<div class="small"><span class="muted">{{ $e['label'] ?? '' }}:</span> {{ $e['value'] ?? '' }}</div>@endforeach</td>
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
<div class="mt">{{ $leads->links() }}</div>
<script>
document.querySelectorAll('[data-bulk]').forEach(btn => btn.addEventListener('click', () => {
    const form = document.getElementById(btn.dataset.bulk);
    const del = form.id === 'delete-form';
    let checked = [...document.querySelectorAll('.lead-chk:checked')];
    if (!del) checked = checked.filter(c => c.dataset.converted !== '1');
    if (!checked.length) { alert(del ? 'حدد الطلبات اللي عايز تحذفها.' : 'حدد طلبات لسه متحولتش.'); return; }
    if (!del) {
        const sel = document.querySelector('[name=assigned_to]');
        if (!sel.value) { alert('اختار الموظف الأول.'); sel.focus(); return; }
        if (!confirm('تحويل ' + checked.length + ' طلب لعملاء عند ' + sel.selectedOptions[0].text + '؟')) return;
    } else if (!confirm('حذف ' + checked.length + ' طلب نهائياً؟ (العملاء اللي اتعملوا منهم مش هيتمسحوا)')) return;
    form.querySelectorAll('input[name="ids[]"]').forEach(i => i.remove());
    checked.forEach(c => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = c.value; form.appendChild(i); });
    btn.disabled = true;
    form.submit();
}));
</script>
@endsection
