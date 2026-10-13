{{-- Product price + installment calculator inside the customer file, so the employee never leaves it to check a price.
     Any <select data-price-hint> of vehicles shows the chosen product's price in the .price-hint next to it;
     any [data-qc] button opens the calculator for the product in data-qc-product or in the select data-qc-from points to. --}}
@php($qcProducts = \App\Models\Product::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['name', 'price']))
@php($qcPlans = \App\Support\InstallmentCalculator::plans())
<div class="modal" id="m-quick-calc"><div class="modal-box" style="max-width:600px">
    <div class="modal-head"><h3><i class="fa-solid fa-calculator" style="color:var(--blue-600)"></i> حساب القسط</h3><button type="button" class="modal-x" data-close="m-quick-calc"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div class="form-grid cols-2" id="qc-form">
        <div class="field span-2"><label>المنتج</label><select class="input" id="qc-product"><option value="">— اختر المنتج أو اكتب السعر —</option>@foreach($qcProducts as $p)<option value="{{ $p->name }}" data-price="{{ $p->price }}">{{ $p->name }} — {{ number_format($p->price) }} ج.م</option>@endforeach</select></div>
        <div class="field"><label>السعر الكاش (ج.م)</label><input class="input" type="number" min="0" step="1" id="qc-price" value="0"></div>
        <div class="field"><label>المقدم (ج.م)</label><input class="input" type="number" min="0" step="1" id="qc-down" value="0"></div>
        <div class="field"><label>جهة التقسيط</label><select class="input" id="qc-plan">@foreach($qcPlans as $pl)<option value="{{ $pl['name'] }}" data-months="{{ implode(',', array_keys($pl['table'])) }}">{{ $pl['name'] }}</option>@endforeach</select></div>
        <div class="field"><label>عدد الشهور</label><input class="input" type="number" min="1" max="84" step="1" id="qc-months" value="12"></div>
        <div class="field span-2"><div class="row wrap" id="qc-chips" style="gap:6px"></div></div>
        <div class="span-2" id="qc-result" style="grid-column:1/-1"><div class="muted small">اختار المنتج والمقدم وعدد الشهور.</div></div>
    </div></div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close="m-quick-calc">إغلاق</button>
        <button type="button" class="btn btn-soft" id="qc-copy-fin" style="display:none"><i class="fa-regular fa-copy"></i> نسخ لجهة التقسيط</button>
        <button type="button" class="btn btn-primary" id="qc-copy" style="display:none"><i class="fa-regular fa-copy"></i> نسخ عرض التقسيط للعميل</button>
    </div>
</div></div>
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const prices = @json($qcProducts->pluck('price', 'name'));
    const url = @json(route('calculator.calculate'));
    const title = @json(\App\Support\Settings::get('offer_title', 'عرض تقسيط بجاج قنا'));
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const money = (v) => Math.round(+v || 0).toLocaleString('en-US');
    let last = null, timer;

    // price next to any vehicle select
    const hint = (sel) => {
        const box = sel.closest('.field')?.querySelector('.price-hint');
        if (box) box.textContent = prices[sel.value] !== undefined ? 'السعر الكاش: ' + money(prices[sel.value]) + ' جنيه' : '';
    };
    document.querySelectorAll('select[data-price-hint]').forEach(sel => { sel.addEventListener('change', () => hint(sel)); hint(sel); });

    const chips = () => {
        const months = ($('qc-plan').selectedOptions[0]?.dataset.months || '').split(',').filter(Boolean);
        $('qc-chips').innerHTML = months.map(m => '<button type="button" class="btn btn-xs btn-ghost" data-m="' + m + '">' + m + ' شهر</button>').join('');
        $('qc-chips').querySelectorAll('[data-m]').forEach(b => b.addEventListener('click', () => { $('qc-months').value = b.dataset.m; run(); }));
    };
    const stat = (label, value, strong) => '<div class="card card-pad" style="margin:0;padding:10px 12px"><div class="muted small">' + label + '</div><b class="num" style="font-size:' + (strong ? '20px;color:var(--blue-600)' : '15px') + '">' + value + '</b></div>';
    const run = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const body = new URLSearchParams({ price: $('qc-price').value || 0, down: $('qc-down').value || 0, months: $('qc-months').value || 12,
                type: 'table', plan: $('qc-plan').value, product: $('qc-product').value });
            try {
                const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body });
                const d = await r.json();
                if (!r.ok || !(d.monthly > 0)) { last = null; $('qc-result').innerHTML = '<div class="muted small">' + (r.ok ? 'لا يوجد مبلغ للتقسيط — راجع السعر والمقدم.' : 'راجع البيانات (عدد الشهور من 1 إلى 84).') + '</div>'; }
                else {
                    last = d;
                    $('qc-result').innerHTML = '<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px">'
                        + stat('القسط الشهري', money(d.monthly) + ' ج.م', true)
                        + stat('المقدم + أول قسط + المصاريف (عند الاستلام)', money(d.upfront) + ' ج.م')
                        + stat('المصاريف الإدارية', money(d.fees) + ' ج.م')
                        + stat('الباقي بعد المقدم', money(d.financed) + ' ج.م')
                        + stat('باقي الأقساط', money(d.monthly) + ' × ' + (d.months - 1) + ' شهر')
                        + stat('الإجمالي الكلي', money(d.grand_total) + ' ج.م') + '</div>';
                }
            } catch (e) { last = null; }
            $('qc-copy').style.display = $('qc-copy-fin').style.display = last ? '' : 'none';
        }, 200);
    };
    const offer = (d) => [title, '', $('qc-product').value ? 'المنتج: ' + $('qc-product').value : null, 'السعر الكاش: ' + money(d.price) + ' جنيه',
        d.down > 0 ? 'المقدم: ' + money(d.down) + ' جنيه' : 'بدون مقدم', 'نظام: ' + d.months + ' شهر (' + d.plan + ')',
        'المقدم + القسط الأول + المصاريف الإدارية: ' + money(d.upfront) + ' جنيه', 'القسط الشهري: ' + money(d.monthly) + ' جنيه',
        'الإجمالي الكلي للنظام: ' + money(d.grand_total) + ' جنيه', 'أول قسط يُدفع مع الاستلام'].filter(l => l !== null).join('\n');
    const copy = (text, ok) => navigator.clipboard.writeText(text).then(() => toast(ok)).catch(() => toast('تعذر النسخ', 'error'));

    $('qc-product').addEventListener('change', () => { if (prices[$('qc-product').value] !== undefined) $('qc-price').value = prices[$('qc-product').value]; run(); });
    $('qc-plan').addEventListener('change', () => { chips(); run(); });
    ['qc-price', 'qc-down', 'qc-months'].forEach(id => $(id).addEventListener('input', run));
    $('qc-copy').addEventListener('click', () => last && copy(offer(last), 'تم نسخ عرض التقسيط'));
    $('qc-copy-fin').addEventListener('click', () => last && last.finance_text && copy(last.finance_text, 'تم نسخ التفاصيل لجهة التقسيط'));

    // open for a product (from a vehicle row, or from the vehicle select of the form)
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-qc]');
        if (!b) return;
        const from = b.dataset.qcFrom ? document.querySelector(b.dataset.qcFrom) : null;
        const name = b.dataset.qcProduct || (from ? from.value : '');
        if (name && [...$('qc-product').options].some(o => o.value === name)) { $('qc-product').value = name; $('qc-price').value = prices[name]; }
        run();
    });
    chips();
})();
</script>
