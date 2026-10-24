@extends('layouts.app')
@section('title', $def['list_title'] ?? $def['title'])
@section('heading', $def['list_title'] ?? $def['title'])
@section('sub', 'راجع البيانات ثم أصدر المستند')

@section('content')
@if($log)
<div class="alert alert-info mb"><i class="fa-solid fa-pen"></i><div>بتعدّل المستند <b dir="ltr">{{ \App\Services\DocumentService::docNo($log->type, $log->serial) }}</b> — هيتحفظ ويتطبع بنفس الرقم.</div></div>
@endif
<div class="card mb" id="doc-picker" @if($log) style="display:none" @endif data-lookup="{{ route('documents.customers') }}" data-base="{{ route('documents.create', $type) }}" data-vehicles="{{ route('vehicles.lookup') }}">
    <div class="card-head"><h3><i class="fa-solid fa-user-check"></i> العميل والمركبة</h3>
        @if($customer)<a class="btn btn-xs btn-ghost" href="{{ route('documents.create', $type) }}">تغيير العميل</a>@endif</div>
    <div class="card-body"><div class="form-grid">
        <div class="field span-2"><label>اختيار العميل (بالاسم أو الهاتف أو الكود) — بياناته تتعبأ تلقائياً</label>
            <div style="position:relative">
                <input class="input" id="doc-cust-q" autocomplete="off" placeholder="ابحث عن العميل..." value="{{ $customer ? $customer->name . ' — ' . $customer->code : '' }}">
                <div id="doc-cust-res" style="display:none;position:absolute;z-index:30;inset-inline:0;top:100%;background:#fff;border:1px solid var(--line-2);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.12);max-height:280px;overflow:auto"></div>
            </div></div>
        @if($customerDeals->isNotEmpty() && in_array('chassis', array_column($def['fields'], 0), true))
            <div class="field"><label>مركبات العميل (الشاسيه والموتور)</label>
                <select class="input" id="doc-deal"><option value="">— اختر المركبة —</option>
                    @foreach($customerDeals as $cd)<option value="{{ route('documents.create', [$type, 'deal' => $cd->id]) }}" @selected($deal?->id === $cd->id)>{{ $cd->vehicle ?: 'مركبة' }}{{ $cd->model ? ' ' . $cd->model : '' }} — شاسيه {{ $cd->chassis ?: ($cd->vehicleUnit?->chassis ?: 'غير مسجل') }}</option>@endforeach
                </select></div>
        @endif
    </div>
    @if(in_array('chassis', array_column($def['fields'], 0), true))<div class="hint">أو اكتب جزء من رقم الشاسيه في خانة «الشاسيه» واختره من المخزن، فيتعبأ الموتور والنوع واللون تلقائياً.</div>@endif
    </div>
</div>
<datalist id="dl-chassis"></datalist>

<form method="POST" action="{{ $log ? route('documents.update', $log) : route('documents.store', $type) }}" class="card">
    @csrf @if($log) @method('PUT') @endif
    <input type="hidden" name="deal_id" value="{{ $deal?->id }}"><input type="hidden" name="customer_id" value="{{ $customer?->id }}">
    <div class="card-head"><h3><i class="fa-solid fa-file-pen"></i> بيانات المستند</h3>
        @if($customer)<span class="badge blue">{{ $customer->name }} — {{ $customer->code }}</span>@endif</div>
    <div class="card-body">
        @if($errors->has('deal_id'))<div class="alert alert-error mb"><i class="fa-solid fa-circle-xmark"></i><div>{{ $errors->first('deal_id') }}</div></div>@endif
        <div class="form-grid">
        @foreach($def['fields'] as $f)
            @php([$name, $label, $kind] = $f)
            @php($val = old($name, $values[$name] ?? ''))
            @if($kind === 'check')
                <div class="field span-2"><label class="check"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $values[$name] ?? false))> {{ $label }}</label></div>
            @elseif($kind === 'select')
                <div class="field"><label>{{ $label }}</label><select class="input" name="{{ $name }}">
                    @if($val !== '' && ! in_array($val, $f[4], true))<option selected>{{ $val }}</option>@endif
                    @foreach($f[4] as $opt)<option @selected($val === $opt)>{{ $opt }}</option>@endforeach</select></div>
            @elseif($kind === 'textarea')
                <div class="field span-3"><label>{{ $label }}</label><textarea class="input" rows="4" name="{{ $name }}">{{ $val }}</textarea></div>
            @else
                <div class="field"><label>{{ $label }}</label>
                    <input class="input" name="{{ $name }}" value="{{ $val }}" type="{{ in_array($kind, ['money', 'number']) ? 'number' : ($kind === 'date' ? 'date' : 'text') }}" @if($kind === 'money') step="0.01" min="0" @endif @if($name === 'chassis') list="dl-chassis" autocomplete="off" dir="ltr" style="text-align:right" @endif></div>
            @endif
        @endforeach
        </div>
        <div class="form-actions"><button class="btn btn-primary"><i class="fa-solid fa-file-pdf"></i> {{ $log ? 'حفظ التعديل بنفس الرقم' : 'إصدار المستند (PDF)' }}</button>
            <a class="btn btn-ghost" href="{{ route('documents.index') }}">إلغاء</a></div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  const box = document.getElementById('doc-picker'); if (!box) return;
  const q = document.getElementById('doc-cust-q'), res = document.getElementById('doc-cust-res');
  let t;
  q.addEventListener('focus', () => q.select());
  q.addEventListener('input', () => {
    clearTimeout(t);
    const term = q.value.trim();
    if (term.length < 2) { res.style.display = 'none'; return; }
    t = setTimeout(async () => {
      try {
        const r = await fetch(box.dataset.lookup + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } });
        const rows = r.ok ? await r.json() : [];
        res.replaceChildren();
        if (!rows.length) { const d = document.createElement('div'); d.className = 'muted small'; d.style.padding = '10px 14px'; d.textContent = 'لا يوجد عميل مطابق'; res.append(d); }
        rows.forEach((c) => {
          const a = document.createElement('a');
          a.href = box.dataset.base + '?customer=' + c.id;
          a.style.cssText = 'display:block;padding:9px 14px;border-bottom:1px solid var(--line-2);color:inherit';
          a.textContent = c.name + ' — ' + (c.code || '') + ' — ' + (c.phone || '');
          res.append(a);
        });
        res.style.display = 'block';
      } catch (e) {}
    }, 250);
  });
  document.addEventListener('click', (e) => { if (!box.contains(e.target)) res.style.display = 'none'; });

  const deal = document.getElementById('doc-deal');
  if (deal) deal.addEventListener('change', () => { if (deal.value) location.href = deal.value; });

  // chassis from the stock -> motor / type / model / color
  const form = box.parentElement.querySelector('form[action*="documents"]') || document.querySelector('form[method=POST]');
  const ch = form && form.querySelector('[name=chassis]'), dl = document.getElementById('dl-chassis');
  if (!ch) return;
  let found = [], t2;
  ch.addEventListener('input', () => {
    clearTimeout(t2);
    const v = ch.value.trim();
    const hit = found.find((x) => x.chassis === v.toUpperCase().replace(/\s+/g, ''));
    if (hit) {
      ch.value = hit.chassis;
      ['motor', 'product', 'model', 'color'].forEach((k) => { const f = form.querySelector('[name=' + k + ']'); if (f && hit[k]) f.value = hit[k]; });
      return;
    }
    if (v.length < 3) return;
    t2 = setTimeout(async () => {
      try {
        const r = await fetch(box.dataset.vehicles + '?q=' + encodeURIComponent(v), { headers: { Accept: 'application/json' } });
        found = r.ok ? await r.json() : [];
        dl.replaceChildren(...found.map((x) => { const o = document.createElement('option'); o.value = x.chassis; o.label = [x.product, x.color, x.motor, x.status].filter(Boolean).join(' — '); return o; }));
      } catch (e) {}
    }, 250);
  });
})();
</script>
@endpush
