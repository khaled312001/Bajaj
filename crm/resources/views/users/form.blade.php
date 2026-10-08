@extends('layouts.app')
@php($editing = $user->exists)
@section('title', $editing ? 'تعديل موظف' : 'موظف جديد')
@section('heading', $editing ? 'تعديل الموظف: ' . $user->name : 'إضافة موظف')

@section('content')
<form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="card" style="max-width:820px" autocomplete="off">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card-body">
        <div class="form-grid cols-2">
            <div class="field"><label>الاسم الكامل <span class="req">*</span></label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div class="field"><label>اسم المستخدم <span class="req">*</span></label><input class="input" name="username" value="{{ old('username', $user->username) }}" required dir="ltr" style="text-align:right" autocomplete="off"><div class="hint">حروف إنجليزية وأرقام فقط — يُستخدم لتسجيل الدخول.</div></div>
            <div class="field"><label>الهاتف</label><input class="input" name="phone" value="{{ old('phone', $user->phone) }}" dir="ltr" style="text-align:right"></div>
            <div class="field"><label>البريد الإلكتروني</label><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" dir="ltr" style="text-align:right"></div>
            <div class="field"><label>الدور <span class="req">*</span></label>
                <select class="input" name="role" @disabled($editing && $user->id === auth()->id())>
                    <option value="agent" @selected(old('role', $user->role) === 'agent')>خدمة عملاء (صلاحيات محدودة)</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>مدير النظام (كل الصلاحيات)</option></select></div>
            <div class="field"><label>دور الصلاحيات (للموظف)</label>
                <select class="input" name="role_profile_id"><option value="">الافتراضي</option>
                    @foreach(\App\Models\RoleProfile::orderBy('name')->get() as $rp)<option value="{{ $rp->id }}" @selected(old('role_profile_id', $user->role_profile_id) == $rp->id)>{{ $rp->name }}</option>@endforeach</select>
                <div class="hint">تحدد الصفحات والإجراءات المتاحة له — <a href="{{ route('roles.index') }}">إدارة الأدوار</a>. لا يؤثر على مدير النظام.</div></div>
            <div class="field"><label>كلمة المرور @unless($editing)<span class="req">*</span>@endunless</label><input class="input" type="password" name="password" autocomplete="new-password" {{ $editing ? '' : 'required' }} dir="ltr" style="text-align:right"><div class="hint">{{ $editing ? 'اتركها فارغة للإبقاء على الحالية.' : '8 أحرف على الأقل بها حروف وأرقام.' }}</div></div>
            <div class="field span-2 row wrap" style="gap:26px">
                <input type="hidden" name="is_active" value="0">
                <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($editing && $user->id === auth()->id())> الحساب نشط</label>
                <input type="hidden" name="must_change_password" value="0">
                <label class="check"><input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', !$editing))> إجبار تغيير كلمة المرور عند أول دخول</label>
                @if($editing && ($user->isLocked() || $user->failed_attempts))<label class="check"><input type="checkbox" name="unlock" value="1"> فك قفل الحساب</label>@endif
            </div>
        </div>

        <div class="card card-pad mt" style="background:var(--blue-50);border-color:var(--blue-100)">
            <b><i class="fa-solid fa-shield-halved"></i> صلاحيات خدمة العملاء</b>
            <div class="small" style="margin-top:6px">✓ إضافة وتعديل العملاء (حسب نطاق الرؤية في الإعدادات) · ✓ المتابعات · ✓ الصفقات وتسجيل الدفعات · ✓ حاسبة الأقساط<br>
            ✗ لا تصدير ولا استيراد · ✗ لا حذف · ✗ لا تقارير · ✗ لا إدارة موظفين أو إعدادات · ✗ الرقم القومي مخفي</div>
        </div>

        <div class="form-actions"><a class="btn btn-ghost" href="{{ route('users.index') }}">إلغاء</a><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ</button></div>
    </div>
</form>
@endsection
