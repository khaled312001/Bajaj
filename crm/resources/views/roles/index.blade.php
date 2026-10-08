@extends('layouts.app')
@section('title', 'الأدوار والصلاحيات')
@section('heading', 'الأدوار والصلاحيات')
@section('sub', 'تحكم في الصفحات والإجراءات المتاحة لكل دور')

@section('content')
@php
    $blank = new \App\Models\RoleProfile(['name' => '', 'permissions' => []]);
@endphp
@if($errors->has('role'))<div class="alert alert-error mb"><i class="fa-solid fa-circle-xmark"></i><div>{{ $errors->first('role') }}</div></div>@endif
<div class="alert alert-info mb"><i class="fa-solid fa-circle-info"></i><div>مدير النظام يملك كل الصلاحيات دائماً. الصفحات التالية <b>للمدير فقط ولا يمكن منحها</b> لأي دور: الموظفون، الأدوار، الإعدادات، سجل النشاط، النسخ الاحتياطي، استيراد وتصدير Excel وتصدير التقارير — وذلك لحماية بيانات العملاء من التسريب.</div></div>

<div class="grid g2">
@foreach($profiles->concat([$blank]) as $p)
    <form method="POST" action="{{ $p->exists ? route('roles.update', $p) : route('roles.store') }}" class="card">
        @csrf @if($p->exists) @method('PUT') @endif
        <div class="card-head"><h3><i class="fa-solid {{ $p->exists ? 'fa-user-shield' : 'fa-plus' }}"></i> {{ $p->exists ? $p->name : 'دور جديد' }}</h3>
            @if($p->exists)<span class="badge gray">{{ $p->users_count }} موظف</span>@if($p->is_default)<span class="badge blue">افتراضي</span>@endif @endif</div>
        <div class="card-body">
            <div class="form-grid cols-2 mb">
                <div class="field"><label>اسم الدور</label><input class="input" name="name" value="{{ $p->name }}" required maxlength="80" placeholder="مثال: محاسب"></div>
                <div class="field"><label>الوصف</label><input class="input" name="description" value="{{ $p->description }}" maxlength="200"></div>
            </div>
            <div class="table-wrap"><table class="tbl perm-tbl">
                <thead><tr><th>الصفحة / الوحدة</th>@foreach($actions as $a => $al)<th class="n">{{ $al }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($modules as $key => [$label, $icon, $applicable])
                    <tr><td><i class="fa-solid {{ $icon }}" style="color:var(--blue-600);width:20px"></i> {{ $label }}</td>
                    @foreach($actions as $a => $al)
                        <td class="n">@if(in_array($a, $applicable, true))<input type="checkbox" name="perms[{{ $key }}][{{ $a }}]" value="1" @checked(in_array($a, $p->permissions[$key] ?? [], true))>@else<span class="muted">—</span>@endif</td>
                    @endforeach</tr>
                @endforeach
                </tbody></table></div>
            <label class="check mt"><input type="checkbox" name="is_default" value="1" @checked($p->is_default)> دور افتراضي للموظفين الجدد</label>
            <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> {{ $p->exists ? 'حفظ الصلاحيات' : 'إنشاء الدور' }}</button>
                @if($p->exists)<button type="submit" form="del-{{ $p->id }}" class="btn btn-danger-soft" data-nolock><i class="fa-solid fa-trash"></i> حذف</button>@endif</div>
        </div>
    </form>
    @if($p->exists)<form id="del-{{ $p->id }}" method="POST" action="{{ route('roles.destroy', $p) }}" data-confirm="حذف الدور «{{ $p->name }}»؟">@csrf @method('DELETE')</form>@endif
@endforeach
</div>
<style>.perm-tbl td.n, .perm-tbl th.n { text-align:center } .perm-tbl input[type=checkbox] { width:18px;height:18px;accent-color:var(--blue-600) }</style>
@endsection
