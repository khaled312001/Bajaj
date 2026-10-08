@extends('layouts.app')
@section('title', 'الموظفون')
@section('heading', 'إدارة الموظفين')
@section('sub', $users->count() . ' حساب')

@section('content')
<div class="row mb"><span class="grow"></span><a class="btn btn-primary" href="{{ route('users.create') }}"><i class="fa-solid fa-user-plus"></i> موظف جديد</a></div>
<div class="card">
    <div class="table-wrap"><table class="tbl" data-filterable>
        <thead><tr><th>الموظف</th><th data-filter="الدور">الدور</th><th data-filter="الحالة">الحالة</th><th>عملاء أدخلهم</th><th>عملاء مسؤول عنهم</th><th>متابعات معلّقة</th><th>آخر دخول</th><th></th></tr></thead>
        <tbody>@foreach($users as $u)
            <tr>
                <td><a class="person" href="{{ route('users.show', $u) }}"><div class="avatar sm {{ $u->isAdmin() ? '' : 'agent' }}">{{ $u->initials }}</div><div><b>{{ $u->name }}</b><small dir="ltr">{{ '@' . $u->username }}</small></div></a></td>
                <td><span class="badge {{ $u->isAdmin() ? 'purple' : 'sky' }}">{{ $u->role_label }}</span></td>
                <td>@if(!$u->is_active)<span class="badge gray">موقوف</span>@elseif($u->isLocked())<span class="badge red">مقفل</span>@else<span class="badge green dot">نشط</span>@endif</td>
                <td>{{ $u->created_count }}</td><td>{{ $u->assigned_count }}</td><td>{{ $u->pending_followups }}</td>
                <td class="small muted">{{ $u->last_login_at?->diffForHumans() ?? 'لم يدخل بعد' }}@if($u->last_login_ip)<br><span class="num xs">{{ $u->last_login_ip }}</span>@endif</td>
                <td class="t-left"><div class="btn-group" style="flex-wrap:nowrap">
                    <a class="btn btn-xs btn-soft" href="{{ route('users.show', $u) }}">تتبع</a>
                    <a class="btn btn-xs btn-ghost" href="{{ route('users.edit', $u) }}"><i class="fa-solid fa-pen"></i></a>
                    @if($u->id !== auth()->id())<form method="POST" action="{{ route('users.destroy', $u) }}" data-confirm="حذف الموظف {{ $u->name }}؟ سيُنقل عملاؤه ومتابعاته إليك.">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form>@endif
                </div></td>
            </tr>
        @endforeach</tbody>
    </table></div>
</div>
@endsection
