{{-- add follow-up --}}
<div class="modal" id="m-fu-add"><div class="modal-box">
    <div class="modal-head"><h3><i class="fa-solid fa-calendar-plus" style="color:var(--blue-500)"></i> جدولة متابعة جديدة</h3><button class="modal-x" data-close="m-fu-add"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="{{ route('followups.store') }}">
        @csrf
        <div class="modal-body"><div class="form-grid cols-2">
            @if(!empty($customer))
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="field span-2"><label>العميل</label><input class="input" value="{{ $customer->name }}" readonly></div>
            @else
                <div class="field span-2"><label>العميل <span class="req">*</span></label>
                    <div class="picker" data-picker="{{ route('customers.lookup') }}"><input class="input" type="text" placeholder="ابحث بالاسم أو الهاتف أو الكود..." autocomplete="off" required><input type="hidden" name="customer_id" required><div class="picker-list"></div></div></div>
            @endif
            <div class="field"><label>سبب المتابعة <span class="req">*</span></label><select class="input" name="reason" required>@foreach(\App\Models\Lookup::list('followup_reason') as $r)<option>{{ $r }}</option>@endforeach</select></div>
            <div class="field"><label>الأولوية</label><select class="input" name="priority"><option value="normal">عادية</option><option value="high">مهمة</option></select></div>
            <div class="field"><label>التاريخ <span class="req">*</span></label><input class="input" type="date" name="due_date" value="{{ now()->addDay()->format('Y-m-d') }}" min="{{ today()->format('Y-m-d') }}" required></div>
            <div class="field"><label>الوقت</label><input class="input" type="time" name="due_time"></div>
            @if(auth()->user()->isAdmin() && isset($staff) && $staff->count())
                <div class="field span-2"><label>الموظف المكلّف</label><select class="input" name="assigned_to"><option value="">المسؤول عن العميل</option>@foreach($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            @endif
            <div class="field span-2"><label>ملاحظات</label><textarea class="input" name="notes" rows="3" placeholder="ماذا تريد أن تتابع مع العميل؟"></textarea></div>
        </div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-fu-add">إلغاء</button><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> جدولة</button></div>
    </form>
</div></div>

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
