@extends('layouts.app')
@section('title', 'محتوى نموذج الطلب')
@section('heading', 'محتوى نموذج طلب العملاء')
@section('sub', 'اللي بيشوفه العميل لما يفتح رابط النموذج')

@section('content')
@php($picked = is_array(old('lead_form_vehicles')) ? old('lead_form_vehicles') : $picked)
<div class="card mb">
    <div class="card-body row between wrap" style="gap:10px">
        <div class="small"><i class="fa-solid fa-link"></i> رابط النموذج: <a href="{{ route('lead.public') }}" target="_blank" dir="ltr">{{ route('lead.public') }}</a>
            <button type="button" class="btn btn-xs btn-ghost" data-copy-text="{{ route('lead.public') }}"><i class="fa-regular fa-copy"></i> نسخ</button></div>
        <a class="btn btn-xs btn-ghost" href="{{ route('leads.index') }}"><i class="fa-solid fa-arrow-right"></i> رجوع للطلبات الواردة</a>
    </div>
</div>

<div class="lead-form-layout">
    <form method="POST" action="{{ route('leads.form.update') }}" class="card">
        @csrf @method('PUT')
        <div class="card-head"><h3><i class="fa-solid fa-pen-to-square"></i> محتوى النموذج</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field span-3">
                    <label class="check"><input type="hidden" name="lead_form_enabled" value="0"><input type="checkbox" name="lead_form_enabled" value="1" @checked(old('lead_form_enabled', $enabled ? '1' : '0') === '1')> النموذج شغال (لو اتشال العلامة الرابط بيقفل)</label>
                </div>
                <div class="field span-3"><label>عنوان النموذج</label><input class="input" name="lead_form_title" value="{{ old('lead_form_title', $title) }}" maxlength="120"></div>
                <div class="field span-3"><label>مقدمة النموذج</label><textarea class="input" rows="3" name="lead_form_intro" maxlength="400">{{ old('lead_form_intro', $intro) }}</textarea></div>

                <div class="field span-3" id="lead-vehicles">
                    <label>المنتجات اللي تظهر في خانة «المركبة المطلوبة»</label>
                    <div class="row wrap" style="gap:6px;margin-bottom:6px">
                        <input type="search" class="input" id="lead-veh-filter" placeholder="بحث في المنتجات..." style="max-width:220px">
                        <button type="button" class="btn btn-xs btn-ghost" onclick="document.querySelectorAll('.lead-veh').forEach(c=>{ if(c.closest('label').style.display!=='none') c.checked=true }); leadVehCount()">تحديد الكل</button>
                        <button type="button" class="btn btn-xs btn-ghost" onclick="document.querySelectorAll('.lead-veh').forEach(c=>c.checked=false); leadVehCount()">إلغاء التحديد</button>
                        <span class="badge blue" id="lead-veh-count"></span>
                    </div>
                    <div class="lead-veh-box">
                        @foreach($vehicles as $v)
                            <label class="check"><input type="checkbox" class="lead-veh" name="lead_form_vehicles[]" value="{{ $v }}" @checked(in_array($v, $picked, true))> {{ $v }}
                                @if(isset($prices[$v]))<span class="small muted num">— {{ number_format($prices[$v]) }}</span>@endif</label>
                        @endforeach
                    </div>
                    <div class="hint">علّم المنتجات اللي عايزها تظهر للعميل بس. لو مفيش ولا واحد متعلّم النموذج بيعرض كل المنتجات ({{ count($vehicles) }}). لإضافة منتج جديد للقايمة ضيفه من <a href="{{ route('products.index') }}">المنتجات والأسعار</a>.</div>
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> حفظ</button></div>
        </div>
    </form>

    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-eye"></i> معاينة زي ما العميل بيشوفه</h3></div>
        <iframe src="{{ route('lead.public') }}?preview=1" class="lead-preview" title="معاينة النموذج"></iframe>
        <div class="card-pad small muted">المعاينة بتتحدث بعد الحفظ.</div>
    </div>
</div>

@push('scripts')
<script>
function leadVehCount() {
    const n = document.querySelectorAll('.lead-veh:checked').length;
    document.getElementById('lead-veh-count').textContent = n ? 'المتعلّم: ' + n : 'هيظهر الكل';
}
document.getElementById('lead-veh-filter').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('.lead-veh').forEach(c => { const l = c.closest('label'); l.style.display = !q || l.textContent.toLowerCase().includes(q) ? '' : 'none'; });
});
document.querySelectorAll('.lead-veh').forEach(c => c.addEventListener('change', leadVehCount));
leadVehCount();
</script>
@endpush
@endsection
