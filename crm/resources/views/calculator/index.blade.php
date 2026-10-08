@extends('layouts.app')
@section('title', 'حاسبة الأقساط')
@section('heading', 'حاسبة الأقساط وعرض السعر')
@section('sub', 'اختر المنتج واحسب القسط الشهري فوراً')
@php $allowPrint = true; @endphp

@section('content')
@php
    $S = fn ($k, $d = '') => \App\Support\Settings::get($k, $d);
    $cfg = ['title' => $S('offer_title', 'عرض تقسيط بجاج قنا'), 'phone' => $S('company_phone'), 'hours' => $S('working_hours'), 'address' => $S('company_address')];
@endphp
<div class="grid" style="grid-template-columns:minmax(320px,410px) 1fr;align-items:start" id="calc-grid">
    <form class="card" id="calc-form" onsubmit="return false">
        <div class="card-head"><h3><i class="fa-solid fa-sliders"></i> بيانات التمويل</h3></div>
        <div class="card-body">
            <div class="field mb"><label>المنتج</label>
                <select class="input" id="c-product"><option value="">— اختر منتجاً أو اكتب السعر —</option>
                    @foreach($products as $p)<option value="{{ $p->id }}" data-price="{{ $p->price }}" data-name="{{ $p->name }}" data-warranty="{{ $p->warranty }}" data-req="{{ $p->requirements }}">{{ $p->name }} — {{ number_format($p->price) }}</option>@endforeach
                </select></div>
            <div class="field mb"><label>السعر الكاش (ج.م)</label><input class="input" type="number" step="0.01" min="0" id="c-price" value="0"></div>
            <div class="field mb"><label>المقدم</label>
                <div class="row"><input class="input" type="number" step="0.01" min="0" id="c-down" value="0">
                    <select class="input" id="c-down-mode" style="width:90px"><option value="amt">ج.م</option><option value="pct">%</option></select></div>
                <div class="row wrap mt" style="gap:6px"><button type="button" class="btn btn-xs btn-soft" data-down="0">بدون مقدم</button><button type="button" class="btn btn-xs btn-soft" data-down="10">10%</button><button type="button" class="btn btn-xs btn-soft" data-down="20">20%</button><button type="button" class="btn btn-xs btn-soft" data-down="30">30%</button></div></div>
            <div class="field mb"><label>طريقة الحساب</label>
                <select class="input" id="c-type">
                    <option value="table">نظام جدول النسب (الشركة / جهات التمويل)</option>
                    <option value="flat">فائدة ثابتة على أصل المبلغ</option>
                    <option value="reducing">فائدة متناقصة على الرصيد</option></select></div>
            <div class="field mb" id="plan-box"><label>النظام / جهة التمويل</label>
                <select class="input" id="c-plan">@foreach($plans as $pl)<option value="{{ $pl['name'] }}" data-table='@json($pl["table"])' data-fp="{{ $pl['fees_percent'] }}" data-ff="{{ $pl['fees_fixed'] }}">{{ $pl['name'] }}</option>@endforeach</select></div>
            <div class="field mb"><label>عدد الأشهر: <b id="c-months-lbl">24</b></label>
                <input type="range" min="3" max="84" step="1" id="c-months" value="24" style="width:100%;accent-color:var(--blue-600)">
                <div class="row wrap mt" id="month-chips" style="gap:6px"></div></div>
            <div class="form-grid cols-2 mb" id="rate-box" style="display:none">
                <div class="field"><label>الفائدة السنوية %</label><input class="input" type="number" step="0.01" min="0" max="100" id="c-rate" value="{{ $S('default_interest_rate', 18) }}"></div>
                <div class="field"><label>مصاريف إدارية</label><input class="input" type="number" step="0.01" min="0" id="c-fees-o" value="0"></div>
            </div>
            <div class="field mb" id="fees-box"><label>المصاريف الإدارية (ج.م)</label><input class="input" type="number" step="0.01" min="0" id="c-fees" placeholder="تلقائي حسب النظام"><div class="hint">اتركها فارغة للحساب التلقائي.</div></div>
            <div class="field"><label>تاريخ أول قسط</label><input class="input" type="date" id="c-first"><div class="hint" id="first-hint">في نظام الشركة يُدفع أول قسط مع المقدم عند الاستلام.</div></div>
        </div>
    </form>

    <div class="section-gap">
        <div class="calc-result">
            <div class="lbl">القسط الشهري</div>
            <div class="big"><span id="r-monthly" class="num">—</span> <small style="font-size:16px">ج.م</small></div>
            <div class="lbl" id="r-sub">—</div>
            <div class="calc-stats">
                <div><span class="lbl">المقدم + أول قسط + المصاريف (عند الاستلام)</span><b id="r-upfront" class="num">—</b></div>
                <div><span class="lbl">المصاريف الإدارية</span><b id="r-fees" class="num">—</b></div>
                <div><span class="lbl">المتبقي بعد المقدم</span><b id="r-fin" class="num">—</b></div>
                <div><span class="lbl">إجمالي الأقساط</span><b id="r-total" class="num">—</b></div>
                <div><span class="lbl">الإجمالي الكلي للنظام</span><b id="r-grand" class="num">—</b></div>
                <div><span class="lbl">الزيادة عن السعر الكاش</span><b id="r-inc" class="num">—</b></div>
            </div>
        </div>
        <div class="btn-group no-print">
            <button class="btn btn-primary" id="btn-copy"><i class="fa-regular fa-copy"></i> نسخ عرض التقسيط للعميل</button>
            <a class="btn btn-ghost" id="btn-wa" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" style="color:#16a34a"></i> إرسال واتساب</a>
            <a class="btn btn-ghost" id="btn-pdf" href="#" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i> عرض سعر PDF</a>
            <button class="btn btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i> طباعة</button>
            <button class="btn btn-ghost" id="btn-xlsx"><i class="fa-solid fa-file-excel" style="color:#16a34a"></i> Excel</button>
        </div>
        <div class="card" id="offer-card">
            <div class="card-head"><h3><i class="fa-solid fa-file-lines"></i> نص العرض</h3></div>
            <div class="card-body"><pre id="offer" style="font-family:inherit;white-space:pre-wrap;line-height:1.9;font-weight:700"></pre></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><i class="fa-solid fa-table-list"></i> جدول الأقساط</h3></div>
            <div class="table-wrap" style="max-height:480px;overflow-y:auto"><table class="tbl" id="sched">
                <thead><tr><th>#</th><th>الاستحقاق</th><th>القسط</th><th>الأصل</th><th>الزيادة</th><th>الرصيد</th></tr></thead><tbody></tbody>
            </table></div>
        </div>
    </div>
</div>
<form id="xlsx-form" method="POST" action="{{ route('calculator.export') }}" style="display:none">@csrf</form>
@endsection

@push('scripts')
<script>
(function () {
  const $ = (id) => document.getElementById(id);
  const csrf = document.querySelector('meta[name=csrf-token]').content;
  const url = @json(route('calculator.calculate'));
  const cfg = @json($cfg);
  let t, last, product = null;

  function params() {
    const price = +$('c-price').value || 0; let down = +$('c-down').value || 0;
    if ($('c-down-mode').value === 'pct') down = price * down / 100;
    const type = $('c-type').value;
    return { price, down, plan: $('c-plan').value, months: $('c-months').value, rate: type === 'table' ? 0 : ($('c-rate').value || 0), type,
      fees: type === 'table' ? ($('c-fees').value || 0) : ($('c-fees-o').value || 0), first_due: $('c-first').value };
  }
  function offerText(d) {
    const L = [cfg.title, ''];
    if (product) L.push('المنتج: ' + product.name);
    L.push('السعر الكاش: ' + fmt(d.price, 0) + ' جنيه');
    L.push(d.down > 0 ? 'المقدم: ' + fmt(d.down, 0) + ' جنيه' : 'بدون مقدم');
    L.push('نظام: ' + d.months + ' شهر' + (d.type === 'table' && d.plan ? ' (' + d.plan + ')' : ''));
    if (d.type === 'table') L.push('المقدم + القسط الأول + المصاريف الإدارية: ' + fmt(d.upfront, 0) + ' جنيه');
    L.push('القسط الشهري: ' + fmt(d.monthly, 0) + ' جنيه');
    L.push('الإجمالي الكلي للنظام: ' + fmt(d.grand_total, 0) + ' جنيه');
    if (d.type === 'table') L.push('أول قسط يُدفع مع الاستلام');
    if (product && product.req) L.push('', 'الضمانات:', product.req);
    if (product && product.warranty) L.push('', 'الضمان على المنتج: ' + product.warranty);
    if (cfg.phone) L.push('', 'للتواصل: ' + cfg.phone);
    if (cfg.hours) L.push(cfg.hours);
    if (cfg.address) L.push('العنوان: ' + cfg.address);
    return L.join('\n');
  }
  function sync() {
    const tb = $('c-type').value === 'table';
    $('plan-box').style.display = tb ? 'block' : 'none'; $('rate-box').style.display = tb ? 'none' : 'grid'; $('fees-box').style.display = tb ? 'block' : 'none';
    $('first-hint').textContent = tb ? 'في نظام الشركة يُدفع أول قسط مع المقدم عند الاستلام.' : 'اتركه لبدء أول قسط بعد شهر من اليوم.';
  }
  function run() {
    sync(); $('c-months-lbl').textContent = $('c-months').value;
    clearTimeout(t); t = setTimeout(async () => {
      try {
        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: new URLSearchParams(params()) });
        const d = await r.json(); last = d; if (!r.ok) return;
        const tb = d.type === 'table';
        $('r-monthly').textContent = fmt(d.monthly); $('r-fin').textContent = fmt(d.financed); $('r-total').textContent = fmt(d.total_to_pay);
        $('r-grand').textContent = fmt(d.grand_total); $('r-fees').textContent = fmt(d.fees); $('r-upfront').textContent = tb ? fmt(d.upfront) : fmt(d.down + d.fees);
        $('r-inc').textContent = fmt(d.grand_total - d.price);
        $('r-sub').textContent = d.financed > 0 ? ('لمدة ' + d.months + ' شهر — آخر قسط ' + d.last_due + (tb ? ' — المضاعف ' + d.multiplier : '')) : 'لا يوجد مبلغ ممول';
        $('sched').tBodies[0].innerHTML = d.schedule.map(s => '<tr><td>' + s.number + '</td><td>' + s.due_date + '</td><td class="num"><b>' + fmt(s.amount) + '</b></td><td class="num">' + fmt(s.principal) + '</td><td class="num">' + fmt(s.interest) + '</td><td class="num">' + fmt(s.balance) + '</td></tr>').join('');
        $('btn-pdf').href = @json(route('documents.create', 'quote')) + '?' + new URLSearchParams({ price: d.price, product: product ? product.name : '' });
        const txt = offerText(d); $('offer').textContent = txt; $('btn-wa').href = 'https://wa.me/?text=' + encodeURIComponent(txt);
      } catch (e) {}
    }, 180);
  }
  function chips() {
    const o = $('c-plan').selectedOptions[0], tbl = JSON.parse(o.dataset.table || '{}'), box = $('month-chips');
    $('c-fees').placeholder = 'تلقائي: ' + o.dataset.fp + '% + ' + o.dataset.ff;
    box.innerHTML = Object.keys(tbl).map(m => '<button type="button" class="btn btn-xs btn-ghost" data-m="' + m + '">' + m + ' شهر</button>').join('');
    box.querySelectorAll('[data-m]').forEach(b => b.addEventListener('click', () => { $('c-months').value = b.dataset.m; run(); }));
  }
  $('c-plan').addEventListener('change', () => { chips(); run(); });
  $('c-product').addEventListener('change', (e) => {
    const o = e.target.selectedOptions[0];
    if (o && o.value) { product = { name: o.dataset.name, warranty: o.dataset.warranty, req: o.dataset.req }; $('c-price').value = o.dataset.price; } else product = null;
    run();
  });
  document.querySelectorAll('[data-down]').forEach(b => b.addEventListener('click', () => { const v = +b.dataset.down; $('c-down-mode').value = v ? 'pct' : 'amt'; $('c-down').value = v; run(); }));
  document.querySelectorAll('#calc-form input, #calc-form select').forEach(e => { e.addEventListener('input', run); e.addEventListener('change', run); });
  $('btn-xlsx').addEventListener('click', () => { const f = $('xlsx-form'); f.querySelectorAll('input:not([name=_token])').forEach(i => i.remove()); const p = params(); Object.keys(p).forEach(k => { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = p[k]; f.appendChild(i); }); f.submit(); });
  $('btn-copy').addEventListener('click', () => { if (!last) return; navigator.clipboard.writeText(offerText(last)).then(() => toast('تم نسخ عرض التقسيط')).catch(() => toast('تعذر النسخ', 'error')); });
  chips(); run();
})();
</script>
<style>@media (max-width: 960px) { #calc-grid { grid-template-columns: 1fr !important; } }</style>
@endpush
