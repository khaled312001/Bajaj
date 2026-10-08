@extends('layouts.app')
@section('title', 'سجل النشاط والأمان')
@section('heading', 'سجل النشاط والأمان')
@section('sub', 'كل عملية في النظام مسجلة باسم صاحبها')

@section('content')
@if($alertCount)
    <div class="alert alert-warning"><i class="fa-solid fa-shield-halved"></i><div><b>{{ $alertCount }} تنبيه أمني خلال آخر 7 أيام.</b>
        <a href="{{ route('logs.index', ['action' => 'security.alert']) }}">عرض التنبيهات فقط</a></div></div>
@endif
<div class="card mb">
    <form method="GET" class="card-pad"><div class="filter-bar">
        <input class="input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="بحث في الوصف">
        <select class="input" name="user"><option value="">كل المستخدمين</option>@foreach($staff as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select>
        <select class="input" name="action"><option value="">كل العمليات</option>@foreach($actions as $k => $l)<option value="{{ $k }}" @selected(($filters['action'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
        <input class="input" type="date" name="from" value="{{ $filters['from'] ?? '' }}"><input class="input" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        <label class="check"><input type="checkbox" name="with_views" value="1" @checked(!empty($filters['with_views']))> إظهار فتح الملفات</label>
        <div class="btn-group"><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> تصفية</button><a class="btn btn-ghost" href="{{ route('logs.index') }}">مسح</a></div>
    </div></form>
</div>
<div class="card">
    <div class="table-wrap"><table class="tbl">
        <thead><tr><th>الوقت</th><th>المستخدم</th><th>العملية</th><th>التفاصيل</th><th>IP</th></tr></thead>
        <tbody>@forelse($logs as $l)
            <tr>
                <td class="small muted" style="white-space:nowrap">{{ $l->created_at->format('Y/m/d H:i:s') }}</td>
                <td>{{ $l->user?->name ?? '—' }} @if($l->user)<span class="badge {{ $l->user->isAdmin() ? 'purple' : 'sky' }}" style="font-size:10px">{{ $l->user->isAdmin() ? 'مدير' : 'موظف' }}</span>@endif</td>
                <td><span class="badge {{ str_contains($l->action, 'security') || $l->action === 'login_failed' ? 'red' : (in_array($l->action, ['export', 'import']) ? 'amber' : 'blue') }}">{{ $l->label }}</span></td>
                <td class="small">{{ $l->description }}</td>
                <td class="small muted num">{{ $l->ip }}</td>
            </tr>
        @empty<tr><td colspan="5"><div class="empty">لا توجد سجلات</div></td></tr>@endforelse</tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
