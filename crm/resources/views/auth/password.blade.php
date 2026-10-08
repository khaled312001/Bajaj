@extends('layouts.app')
@section('title', 'تغيير كلمة المرور')
@section('heading', 'تغيير كلمة المرور')
@section('content')
<div style="max-width:520px">
    <div class="card">
        <div class="card-head"><h2><i class="fa-solid fa-key"></i> كلمة مرور جديدة</h2></div>
        <form method="POST" action="{{ route('password.update') }}" class="card-body">
            @csrf @method('PUT')
            @if(auth()->user()->must_change_password)
                <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div>لأسباب أمنية يجب تغيير كلمة المرور المؤقتة قبل استخدام النظام.</div></div>
            @endif
            <div class="field mb"><label>كلمة المرور الحالية</label><input class="input" type="password" name="current_password" required autocomplete="current-password"></div>
            <div class="field mb"><label>كلمة المرور الجديدة</label><input class="input" type="password" name="password" required autocomplete="new-password"><div class="hint">8 أحرف على الأقل، وتحتوي على حروف وأرقام.</div></div>
            <div class="field mb"><label>تأكيد كلمة المرور الجديدة</label><input class="input" type="password" name="password_confirmation" required autocomplete="new-password"></div>
            <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-floppy-disk"></i> حفظ كلمة المرور</button>
        </form>
    </div>
</div>
@endsection
