@extends('layouts.app')
@section('title', 'محتوى نموذج الطلب')
@section('heading', 'محتوى نموذج طلب العملاء')
@section('sub', 'اللي بيشوفه العميل لما يفتح رابط النموذج')

@section('content')
@php($picked = is_array(old('lead_form_vehicles')) ? old('lead_form_vehicles') : $picked)
@php($editorFields = json_decode((string) old('lead_form_fields', ''), true) ?: $fields)
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

                <div class="field span-3" id="lead-fields">
                    <label>حقول النموذج <span class="small muted">(بالترتيب اللي هتظهر بيه — حرّك بالأسهم)</span></label>
                    <div id="lf-list" class="lf-list"></div>
                    <div class="row wrap" style="gap:6px;margin-top:8px">
                        <button type="button" class="btn btn-xs btn-primary" id="lf-add"><i class="fa-solid fa-plus"></i> إضافة حقل</button>
                        <span id="lf-restore" class="row wrap" style="gap:6px"></span>
                    </div>
                    <input type="hidden" name="lead_form_fields" id="lf-json">
                    <div class="hint">الاسم ورقم الهاتف أساسيين ومينفعش يتشالوا (تقدر تغيّر اسمهم). الحقول الجديدة إجاباتها بتظهر في صفحة الليدز وبتتنقل لملاحظات العميل لما يتحوّل.</div>
                </div>

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

    <div class="card lead-preview-card">
        <div class="card-head"><h3><i class="fa-solid fa-eye"></i> معاينة زي ما العميل بيشوفه</h3></div>
        <iframe src="{{ route('lead.public') }}?preview=1" class="lead-preview" title="معاينة النموذج" scrolling="no"
            onload="try { var d = this.contentDocument; d.body.style.minHeight = '0'; this.style.height = d.body.scrollHeight + 'px'; } catch (e) {}"></iframe>
        <div class="card-pad small muted">المعاينة بتتحدث بعد الحفظ.</div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const TYPES = @json(\App\Support\LeadForm::TYPES);
    const BUILTIN = @json(collect(\App\Support\LeadForm::BUILTIN)->map(fn ($b) => $b['label']));
    const LOCKED = @json(\App\Support\LeadForm::LOCKED);
    const KIND = {phone: 'رقم هاتف', vehicle: 'قائمة المركبات (من تحت)', governorate: 'قائمة المحافظات', district: 'قائمة المراكز (بعد المحافظة)'};
    let fields = @json($editorFields);
    const list = document.getElementById('lf-list');
    const esc = (v) => String(v ?? '').replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));

    function render() {
        list.innerHTML = fields.map((f, i) => {
            const locked = LOCKED.includes(f.key), builtin = f.key in BUILTIN;
            const type = builtin
                ? `<span class="badge gray">${esc(KIND[f.type] || 'نص')}</span>`
                : `<select class="input lf-type">${Object.entries(TYPES).map(([k, v]) => `<option value="${k}" ${f.type === k ? 'selected' : ''}>${v}</option>`).join('')}</select>`;
            return `<div class="lf-row" data-i="${i}">
                <div class="lf-move"><button type="button" class="btn btn-xs btn-ghost lf-up" ${i ? '' : 'disabled'} title="لفوق">▲</button><button type="button" class="btn btn-xs btn-ghost lf-down" ${i < fields.length - 1 ? '' : 'disabled'} title="لتحت">▼</button></div>
                <div class="lf-main">
                    <div class="lf-line">
                        <input class="input lf-label" value="${esc(f.label)}" maxlength="80" placeholder="اسم الحقل">
                        ${type}
                    </div>
                    <div class="lf-line">
                        ${['date', 'governorate', 'district'].includes(f.type) ? '' : `<input class="input lf-ph" value="${esc(f.placeholder)}" maxlength="120" placeholder="${['select', 'vehicle'].includes(f.type) ? 'أول سطر في القائمة (اختياري)' : 'نص توضيحي جوه الخانة (اختياري)'}">`}
                        <select class="input lf-width"><option value="full" ${f.width === 'full' ? 'selected' : ''}>عرض كامل</option><option value="half" ${f.width === 'half' ? 'selected' : ''}>نص العرض</option></select>
                        <label class="check"><input type="checkbox" class="lf-req" ${f.required ? 'checked' : ''} ${locked ? 'disabled' : ''}> إجباري</label>
                    </div>
                    ${f.type === 'select' ? `<textarea class="input lf-opts" rows="3" placeholder="الاختيارات — كل اختيار في سطر">${esc((f.options || []).join('\n'))}</textarea>` : ''}
                </div>
                ${locked ? '<span class="lf-lock" title="حقل أساسي"><i class="fa-solid fa-lock"></i></span>' : '<button type="button" class="btn btn-xs btn-danger-soft lf-del" title="حذف الحقل"><i class="fa-solid fa-trash"></i></button>'}
            </div>`;
        }).join('');
        const missing = Object.keys(BUILTIN).filter((k) => !fields.some((f) => f.key === k));
        document.getElementById('lf-restore').innerHTML = missing.map((k) => `<button type="button" class="btn btn-xs btn-ghost lf-back" data-key="${k}"><i class="fa-solid fa-rotate-left"></i> رجّع «${esc(BUILTIN[k])}»</button>`).join('');
    }
    function read() {
        list.querySelectorAll('.lf-row').forEach((row) => {
            const f = fields[row.dataset.i];
            f.label = row.querySelector('.lf-label').value;
            f.placeholder = row.querySelector('.lf-ph') ? row.querySelector('.lf-ph').value : '';
            f.width = row.querySelector('.lf-width').value;
            f.required = row.querySelector('.lf-req').checked;
            if (row.querySelector('.lf-type')) f.type = row.querySelector('.lf-type').value;
            if (row.querySelector('.lf-opts')) f.options = row.querySelector('.lf-opts').value.split('\n').map((o) => o.trim()).filter(Boolean);
        });
    }
    list.addEventListener('click', (e) => {
        const row = e.target.closest('.lf-row'); if (!row) return;
        const i = +row.dataset.i; read();
        if (e.target.closest('.lf-up') && i > 0) [fields[i - 1], fields[i]] = [fields[i], fields[i - 1]];
        else if (e.target.closest('.lf-down') && i < fields.length - 1) [fields[i + 1], fields[i]] = [fields[i], fields[i + 1]];
        else if (e.target.closest('.lf-del')) { if (!confirm('حذف الحقل «' + fields[i].label + '»؟')) return; fields.splice(i, 1); }
        else return;
        render();
    });
    list.addEventListener('change', (e) => { if (e.target.classList.contains('lf-type')) { read(); render(); } });
    document.getElementById('lf-add').addEventListener('click', () => {
        read(); fields.push({key: '', type: 'text', label: 'حقل جديد', placeholder: '', required: false, width: 'full', options: []}); render();
        const last = list.lastElementChild.querySelector('.lf-label'); last.focus(); last.select();
    });
    document.getElementById('lf-restore').addEventListener('click', (e) => {
        const b = e.target.closest('.lf-back'); if (!b) return;
        read(); fields.push({key: b.dataset.key, label: BUILTIN[b.dataset.key], placeholder: '', required: false, width: b.dataset.key === 'governorate' || b.dataset.key === 'district' ? 'half' : 'full', options: []}); render();
    });
    list.closest('form').addEventListener('submit', () => { read(); document.getElementById('lf-json').value = JSON.stringify(fields); });
    render();
})();

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
