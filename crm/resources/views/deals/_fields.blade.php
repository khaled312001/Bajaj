@php
    $p = !empty($prefix);           // embedded in customer form: status/notes are prefixed
    $st = $p ? 'deal_status' : 'status';
    $nt = $p ? 'deal_notes' : 'notes';
    $v = fn ($k, $d = null) => old($k, $deal->{$k} ?? $d);
    $dateVal = fn ($k) => old($k, optional($deal->{$k})->format('Y-m-d'));
@endphp
<div class="form-grid" id="deal-fields" data-calc="{{ route('calculator.calculate') }}">
    <div class="field"><label>المركبة</label><select class="input" name="vehicle"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('vehicle') as $x)<option @selected($v('vehicle') === $x)>{{ $x }}</option>@endforeach</select></div>
    <div class="field"><label>الموديل / السنة</label><input class="input" name="model" value="{{ $v('model') }}"></div>
    <div class="field"><label>حالة الصفقة</label><select class="input" name="{{ $st }}">@foreach(\App\Models\Customer::STATUSES as $s)<option @selected(old($st, $deal->status ?? 'مفتوحة') === $s)>{{ $s }}</option>@endforeach</select></div>
    <div class="field"><label>رقم الشاسيه</label><input class="input" name="chassis" value="{{ $v('chassis') }}" dir="ltr" style="text-align:right"></div>
    <div class="field"><label>رقم الموتور</label><input class="input" name="motor" value="{{ $v('motor') }}" dir="ltr" style="text-align:right"></div>
    <div class="field"><label>تاريخ التسليم</label><input class="input" type="date" name="delivery_date" value="{{ $dateVal('delivery_date') }}"></div>
    <div class="field"><label>اللون</label><input class="input" name="color" value="{{ $v('color') }}"></div>
    <div class="field"><label>تاريخ البيع</label><input class="input" type="date" name="sale_date" value="{{ $dateVal('sale_date') }}"></div>
    <div class="field"><label>الشاسيه تابع التاجر</label><input class="input" name="dealer" value="{{ $v('dealer') }}"></div>

    <div class="form-section"><i class="fa-solid fa-file-invoice"></i> سجل المبيعات والمبايعة</div>
    <div class="field"><label>PO</label><input class="input" name="po_number" value="{{ $v('po_number') }}" dir="ltr" style="text-align:right"></div>
    <div class="field"><label>Sales Order</label><input class="input" name="sales_order" value="{{ $v('sales_order') }}" dir="ltr" style="text-align:right"></div>
    <div class="field"><label>رقم الفاتورة</label><input class="input" name="invoice_no" value="{{ $v('invoice_no') }}" dir="ltr" style="text-align:right"></div>
    <div class="field"><label>إيصال الخزينة</label><input class="input" name="treasury_receipt" value="{{ $v('treasury_receipt') }}"></div>
    <div class="field"><label>رقم المبايعة</label><input class="input" name="mobaya_no" value="{{ $v('mobaya_no') }}"></div>
    <div class="field"><label>وصول المبايعة</label><input class="input" type="date" name="mobaya_arrived_at" value="{{ $dateVal('mobaya_arrived_at') }}"></div>
    <div class="field"><label>تاريخ استلام المبايعة</label><input class="input" type="date" name="mobaya_received_at" value="{{ $dateVal('mobaya_received_at') }}"></div>
    <div class="field"><label class="check" style="margin-top:28px"><input type="hidden" name="customer_notified" value="0"><input type="checkbox" name="customer_notified" value="1" @checked(old('customer_notified', $deal->customer_notified ?? false))> تم إرسال رسالة للعميل</label></div>

    <div class="form-section"><i class="fa-solid fa-coins"></i> السعر والدفع</div>
    <div class="field"><label>طريقة الدفع</label><select class="input" name="pay_method" id="pay-method">@foreach(['كاش','تقسيط'] as $m)<option @selected($v('pay_method', 'تقسيط') === $m)>{{ $m }}</option>@endforeach</select></div>
    <div class="field"><label>السعر الإجمالي (ج.م)</label><input class="input" type="number" step="0.01" min="0" name="total_price" id="d-price" value="{{ $v('total_price') }}"></div>
    <div class="field"><label>المقدم (ج.م)</label><input class="input" type="number" step="0.01" min="0" name="down_payment" id="d-down" value="{{ $v('down_payment') }}"></div>

    <div class="span-3" id="inst-block" style="display:contents">
        <div class="field"><label>جهة التقسيط</label><select class="input" name="finance_entity" id="d-entity"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('finance_entity') as $x)<option @selected($v('finance_entity') === $x)>{{ $x }}</option>@endforeach</select></div>
        <div class="field"><label>مرحلة التقسيط</label><select class="input" name="stage"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('installment_stage') as $x)<option @selected($v('stage') === $x)>{{ $x }}</option>@endforeach</select></div>
        <div class="field"><label>تاريخ أول قسط</label><input class="input" type="date" name="first_due_date" id="d-first" value="{{ $dateVal('first_due_date') }}"><div class="hint">اتركه فارغاً ليبدأ بعد شهر من اليوم.</div></div>
        <div class="field"><label>عدد الأشهر</label><input class="input" type="number" min="0" max="120" name="months" id="d-months" value="{{ $v('months', 12) }}"></div>
        <div class="field"><label>نسبة الفائدة السنوية %</label><input class="input" type="number" step="0.01" min="0" max="100" name="interest_rate" id="d-rate" value="{{ $v('interest_rate', 0) }}"><div class="hint">لا تُستخدم في نظام الشركة.</div></div>
        <div class="field"><label>طريقة احتساب الفائدة</label><select class="input" name="interest_type" id="d-type"><option value="table" @selected($v('interest_type', 'table') === 'table')>نظام الشركة (جدول النسب)</option><option value="flat" @selected($v('interest_type') === 'flat')>فائدة ثابتة (على أصل المبلغ)</option><option value="reducing" @selected($v('interest_type') === 'reducing')>متناقصة (على الرصيد)</option></select></div>
        <div class="field"><label>القسط الشهري (ج.م)</label><input class="input" type="number" step="0.01" min="0" name="monthly_installment" id="d-monthly" value="{{ $v('monthly_installment') ?: '' }}"><div class="hint">يُحسب تلقائياً (نظام الشركة أو الفائدة). اكتبه يدوياً مع الفائدة الثابتة بنسبة 0.</div></div>
        <div class="field"><label>مصاريف إدارية (ج.م)</label><input class="input" type="number" step="0.01" min="0" name="admin_fees" id="d-fees" value="{{ $v('admin_fees') ?: '' }}"><div class="hint">نظام الشركة: تُحسب تلقائياً (3% + 500) إن تُركت فارغة.</div></div>
        <div class="span-3" style="grid-column:1/-1">
            <div class="card card-pad" id="calc-summary" style="background:var(--blue-50);border-color:var(--blue-100)">
                <div class="row wrap" style="gap:28px">
                    <div><div class="xs muted">المبلغ الممول</div><b class="num" id="s-fin">—</b></div>
                    <div><div class="xs muted">القسط الشهري</div><b class="num" id="s-monthly" style="color:var(--blue-600);font-size:18px">—</b></div>
                    <div><div class="xs muted">إجمالي الفوائد</div><b class="num" id="s-int">—</b></div>
                    <div><div class="xs muted">إجمالي الأقساط</div><b class="num" id="s-total">—</b></div>
                    <div><div class="xs muted">آخر قسط</div><b class="num" id="s-last">—</b></div>
                </div>
            </div>
        </div>
    </div>

    <div class="field span-3"><label>ملاحظات الصفقة</label><textarea class="input" name="{{ $nt }}" rows="2">{{ old($nt, $deal->notes) }}</textarea></div>
</div>

@once
@push('scripts')
<script>
(function () {
  const root = document.getElementById('deal-fields'); if (!root) return;
  const $ = (id) => document.getElementById(id);
  const pay = $('pay-method'), block = $('inst-block'), monthly = $('d-monthly');
  let timer;
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const toggle = () => { block.style.display = pay.value === 'تقسيط' ? 'contents' : 'none'; if (pay.value === 'تقسيط') run(); };
  async function run() {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const price = +$('d-price').value || 0, down = +$('d-down').value || 0, months = +$('d-months').value || 0, rate = +$('d-rate').value || 0;
      if (pay.value !== 'تقسيط' || months < 1 || price <= down) { ['s-fin','s-monthly','s-int','s-total','s-last'].forEach(i => $(i).textContent = '—'); return; }
      const body = new URLSearchParams({ price, down, months, rate, type: $('d-type').value, plan: $('d-entity').value, first_due: $('d-first').value || '', fees: $('d-fees').value || 0 });
      try {
        const r = await fetch(root.dataset.calc, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body });
        const d = await r.json();
        $('s-fin').textContent = fmt(d.financed); $('s-int').textContent = fmt(d.interest_total); $('s-total').textContent = fmt(d.total_to_pay); $('s-last').textContent = d.last_due;
        if (rate > 0 || $('d-type').value === 'table') { monthly.value = d.monthly.toFixed(2); monthly.readOnly = true; } else { monthly.readOnly = false; if (!monthly.value) monthly.value = d.monthly.toFixed(2); }
        $('s-monthly').textContent = fmt(+monthly.value);
        if ($('d-type').value === 'table' && !$('d-fees').value) $('d-fees').placeholder = fmt(d.fees) + ' (تلقائي)';
      } catch (e) {}
    }, 250);
  }
  ['d-price','d-down','d-months','d-rate','d-type','d-entity','d-first'].forEach(i => $(i).addEventListener('input', run));
  monthly.addEventListener('input', () => $('s-monthly').textContent = fmt(+monthly.value));
  pay.addEventListener('change', toggle); toggle();
})();
</script>
@endpush
@endonce
