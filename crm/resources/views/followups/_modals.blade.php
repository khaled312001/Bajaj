{{-- add follow-up: log a contact that just happened (no date), or schedule one; a logged contact can schedule the next one --}}
<div class="modal" id="m-fu-add"><div class="modal-box">
    <div class="modal-head"><h3><i class="fa-solid fa-calendar-plus" style="color:var(--blue-500)"></i> متابعة جديدة</h3><button class="modal-x" data-close="m-fu-add"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="{{ route('followups.store') }}" id="fu-add-form">
        @csrf
        <div class="modal-body"><div class="form-grid cols-2">
            <div class="field span-2 fu-mode">
                <label class="fu-mode-opt"><input type="radio" name="mode" value="now" checked> <span><i class="fa-solid fa-phone-volume"></i> <b>متابعة حالية</b><small>كلمت العميل وتسجل اللي حصل</small></span></label>
                <label class="fu-mode-opt"><input type="radio" name="mode" value="schedule"> <span><i class="fa-solid fa-calendar-days"></i> <b>متابعة مجدولة</b><small>تحدد ميعاد تتابع فيه بعدين</small></span></label>
            </div>
            @if(!empty($customer))
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="field span-2"><label>العميل</label><input class="input" value="{{ $customer->name }}" readonly></div>
            @else
                <div class="field span-2"><label>العميل <span class="req">*</span></label>
                    <div class="picker" data-picker="{{ route('customers.lookup') }}"><input class="input" type="text" placeholder="ابحث بالاسم أو الهاتف أو الكود..." autocomplete="off" required><input type="hidden" name="customer_id" required><div class="picker-list"></div></div></div>
            @endif
            <div class="field"><label>سبب المتابعة <span class="req">*</span></label><select class="input" name="reason" required>@foreach(\App\Models\Lookup::list('followup_reason') as $r)<option>{{ $r }}</option>@endforeach</select></div>
            <div class="field"><label>الأولوية</label><select class="input" name="priority"><option value="normal">عادية</option><option value="high">مهمة</option></select></div>
            @if(auth()->user()->isAdmin() && isset($staff) && $staff->count())
                <div class="field span-2"><label>الموظف المكلّف</label><select class="input" name="assigned_to"><option value="">المسؤول عن العميل</option>@foreach($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            @endif

            <div class="field span-2" data-fu="now"><label>تفاصيل التواصل <span class="req">*</span></label><textarea class="input" name="outcome" rows="3" placeholder="إيه اللي تم مع العميل في المكالمة / التواصل؟"></textarea></div>
            <label class="field span-2 row" data-fu="now" style="gap:8px;align-items:center;cursor:pointer"><input type="checkbox" name="schedule_next" value="1"> <span>وجدول متابعة قادمة كمان</span></label>

            <div class="form-section" data-fu="next" style="grid-column:1/-1"><i class="fa-solid fa-forward"></i> المتابعة القادمة</div>
            <div class="field" data-fu="sched"><label>التاريخ <span class="req">*</span></label><input class="input" type="date" name="due_date" value="{{ now()->addDay()->format('Y-m-d') }}" min="{{ today()->format('Y-m-d') }}"></div>
            <div class="field" data-fu="sched"><label>الوقت</label><input class="input" type="time" name="due_time"></div>
            <div class="field span-2" data-fu="sched"><label>ملاحظات</label><textarea class="input" name="notes" rows="3" placeholder="ماذا تريد أن تتابع مع العميل؟"></textarea></div>
        </div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-fu-add">إلغاء</button><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> <span data-fu-submit>حفظ</span></button></div>
    </form>
</div></div>
<style>
    #fu-add-form [hidden] { display: none !important; }
    .fu-mode { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .fu-mode .fu-mode-opt { display: flex; gap: 8px; align-items: flex-start; border: 1.5px solid var(--line); border-radius: 12px; padding: 10px 12px; cursor: pointer; margin: 0; font-size: 13.5px; color: var(--ink-2); }
    .fu-mode-opt span { display: flex; flex-direction: column; gap: 2px; }
    .fu-mode-opt small { color: var(--muted); font-weight: 600; font-size: 11.5px; }
    .fu-mode .fu-mode-opt:has(input:checked) { border-color: var(--blue-500); background: rgba(59, 130, 246, .06); }
    @media (max-width: 520px) { .fu-mode { grid-template-columns: 1fr; } }
</style>
<script>
(function () {
    const f = document.getElementById('fu-add-form');
    if (!f) return;
    const sync = () => {
        const now = f.querySelector('[name=mode]:checked').value === 'now';
        const next = now && f.querySelector('[name=schedule_next]').checked;
        const sched = !now || next;
        f.querySelectorAll('[data-fu=now]').forEach(el => el.hidden = !now);
        f.querySelectorAll('[data-fu=sched]').forEach(el => el.hidden = !sched);
        f.querySelectorAll('[data-fu=next]').forEach(el => el.hidden = !next);
        f.querySelector('[name=outcome]').required = now;
        f.querySelector('[name=due_date]').required = sched;
        f.querySelector('[data-fu-submit]').textContent = now ? (next ? 'حفظ وجدولة' : 'حفظ') : 'جدولة';
    };
    f.addEventListener('change', sync);
    sync();
})();
</script>

{{-- complete follow-up --}}
<div class="modal" id="m-fu-done"><div class="modal-box">
    <div class="modal-head"><h3><i class="fa-solid fa-circle-check" style="color:var(--green)"></i> إنهاء المتابعة — <span data-label="customer_name"></span></h3><button class="modal-x" data-close="m-fu-done"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="">
        @csrf
        <div class="modal-body"><div class="form-grid cols-2">
            <div class="field span-2"><label>نتيجة المتابعة <span class="req">*</span></label><textarea class="input" name="outcome" rows="3" required placeholder="ماذا تم مع العميل؟ (رد، وعد بالسداد، رفض، لم يرد...)"></textarea></div>
            <div class="form-section" style="grid-column:1/-1"><i class="fa-solid fa-forward"></i> جدولة متابعة تالية (اختياري)</div>
            <div class="field"><label>التاريخ</label><input class="input" type="date" name="next_date" min="{{ today()->format('Y-m-d') }}"></div>
            <div class="field"><label>السبب</label><select class="input" name="next_reason"><option value="">نفس السبب</option>@foreach(\App\Models\Lookup::list('followup_reason') as $r)<option>{{ $r }}</option>@endforeach</select></div>
        </div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-fu-done">إلغاء</button><button class="btn btn-green" type="submit"><i class="fa-solid fa-check"></i> تم</button></div>
    </form>
</div></div>

{{-- reschedule --}}
<div class="modal" id="m-fu-move"><div class="modal-box" style="max-width:440px">
    <div class="modal-head"><h3><i class="fa-solid fa-clock-rotate-left" style="color:var(--amber)"></i> تأجيل المتابعة</h3><button class="modal-x" data-close="m-fu-move"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="">
        @csrf @method('PUT')
        <div class="modal-body"><div class="form-grid cols-2">
            <div class="field"><label>التاريخ الجديد</label><input class="input" type="date" name="due_date" min="{{ today()->format('Y-m-d') }}" required></div>
            <div class="field"><label>الوقت</label><input class="input" type="time" name="due_time"></div>
        </div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-fu-move">إلغاء</button><button class="btn btn-primary" type="submit">تأجيل</button></div>
    </form>
</div></div>
