@extends('layouts.app')
@section('title', $customer->name)
@section('heading', 'ملف العميل')
@section('sub', $customer->code)

@section('content')
@php
    $me = auth()->user(); $isAdmin = $me->isAdmin();
    $canEdit = $customer->editableBy($me);
    $totalDebt = $deals->sum('balance');
@endphp

<div class="profile-head mb">
    <div class="avatar lg">{{ mb_substr($customer->name, 0, 1) }}</div>
    <div class="grow">
        <h2>{{ $customer->name }}@if($customer->is_dealer) <span class="badge purple" style="font-size:12px;vertical-align:middle"><i class="fa-solid fa-store"></i> تاجر{{ $customer->shop_name && ! str_contains($customer->name, $customer->shop_name) ? ' — ' . $customer->shop_name : '' }}</span>@endif</h2>
        <div class="meta">
            <span><span class="code">{{ $customer->code }}</span></span>
            <span><i class="fa-solid fa-phone"></i> <span class="num">{{ $customer->phone }}</span></span>
            @if($customer->governorate)<span><i class="fa-solid fa-location-dot"></i> {{ $customer->governorate }}{{ $customer->district ? ' — ' . $customer->district : '' }}</span>@endif
            <span><i class="fa-solid fa-user-pen"></i> أدخله: <b>{{ $customer->creator?->name ?? '—' }}</b> · {{ $customer->created_at->format('Y/m/d H:i') }}</span>
            <span><i class="fa-solid fa-user-check"></i> المسؤول: <b>{{ $customer->assignee?->name ?? '—' }}</b></span>
        </div>
    </div>
    <div style="text-align:center"><span class="badge dot {{ $customer->status_class }}" style="font-size:13px;padding:6px 16px">{{ $customer->status }}</span>
        @if($totalDebt > 0)<div style="margin-top:8px;font-weight:800">المديونية: <span class="num">@money($totalDebt)</span> ج.م</div>@endif</div>
</div>

@unless($canEdit)<div class="alert alert-info no-print"><i class="fa-solid fa-circle-info"></i><div>العميل ده مسجل باسم <b>{{ $customer->assignee?->name ?? $customer->creator?->name ?? 'زميل' }}</b> — تقدر تتصل بيه وتضيف متابعة أو تطلب نقله ليك، والتعديل في ملفه محتاج صلاحية من المدير.</div></div>@endunless
<div class="btn-group mb no-print">
    <a class="btn btn-ghost" href="tel:{{ $customer->phone }}"><i class="fa-solid fa-phone" style="color:var(--blue-600)"></i> اتصال</a>
    @if($customer->whatsappLink())<a class="btn btn-ghost" target="_blank" rel="noopener noreferrer" href="{{ $customer->whatsappLink() }}"><i class="fa-brands fa-whatsapp" style="color:#16a34a"></i> واتساب</a>@endif
    <button type="button" class="btn btn-ghost no-print" data-copy-text="{{ $customer->basicInfoCopyText($isAdmin) }}"><i class="fa-solid fa-copy" style="color:var(--blue-600)"></i> نسخ بيانات العميل</button>
    @if(auth()->user()->allows('followups', 'create'))<button class="btn btn-primary" data-open="m-fu-add"><i class="fa-solid fa-calendar-plus"></i> متابعة جديدة</button>@endif
    @if($canEdit && auth()->user()->allows('deals', 'create'))<a class="btn btn-soft" href="{{ route('deals.create', $customer) }}"><i class="fa-solid fa-car"></i> صفقة جديدة</a>@endif
    @if($canEdit && auth()->user()->allows('customers', 'edit'))<a class="btn btn-ghost" href="{{ route('customers.edit', $customer) }}"><i class="fa-solid fa-pen"></i> تعديل</a>@endif
    @if(auth()->user()->allows('customers', 'delete'))<form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="حذف العميل نهائياً مع كل بياناته؟">@csrf @method('DELETE')<button class="btn btn-danger-soft"><i class="fa-solid fa-trash"></i> حذف</button></form>@endif
    @if($isAdmin)
        <button class="btn btn-ghost" data-open="m-assign"><i class="fa-solid fa-right-left" style="color:var(--purple)"></i> نقل لموظف</button>
    @elseif((int) $customer->assigned_to !== $me->id)
        @php($myRequest = $customer->reassignmentRequests()->where('requested_by', auth()->id())->where('status', 'pending')->first())
        @if($myRequest)
            <span class="badge amber"><i class="fa-solid fa-hourglass-half"></i> طلب النقل قيد المراجعة</span>
        @else
            <form method="POST" action="{{ route('reassignments.store', $customer) }}" data-confirm="طلب نقل هذا العميل إليك؟ الطلب هيروح للمدير للموافقة">@csrf<button class="btn btn-ghost"><i class="fa-solid fa-hand" style="color:var(--amber)"></i> طلب نقل العميل لي</button></form>
        @endif
    @endif
</div>

<div class="grid g-main">
<div class="section-gap">
    <div class="card">
        <div class="card-head"><h3><i class="fa-solid fa-id-card"></i> البيانات</h3></div>
        <div class="card-body"><dl class="info-grid">
            <div class="info"><dt>الهاتف البديل</dt><dd class="num">{{ $customer->alt_phone ?: '—' }}</dd></div>
            <div class="info"><dt>واتساب</dt><dd class="num">{{ $customer->whatsapp ?: '—' }}</dd></div>
            <div class="info"><dt>الرقم القومي</dt><dd class="num">{{ $isAdmin ? ($customer->nat_id ?: '—') : ($customer->maskedNatId() ?: '—') }}</dd></div>
            <div class="info"><dt>المهنة</dt><dd>{{ $customer->job ?: '—' }}</dd></div>
            <div class="info"><dt>قناة التواصل</dt><dd>{{ $customer->channel ?: '—' }}</dd></div>
            <div class="info"><dt>المركبة المطلوبة</dt><dd>{{ $customer->interest ?: '—' }}</dd></div>
            <div class="info"><dt>جدية العميل</dt><dd>{{ $customer->seriousness ?: '—' }}</dd></div>
            <div class="info"><dt>الفرع</dt><dd>{{ $customer->branch ?: '—' }}</dd></div>
            <div class="info"><dt>السن</dt><dd>{{ $customer->age ?: '—' }}</dd></div>
            <div class="info"><dt>المركبة السابقة</dt><dd>{{ $customer->previous_vehicle ?: '—' }}</dd></div>
            <div class="info"><dt>أول تواصل</dt><dd>{{ $customer->contacted_at?->format('Y/m/d H:i') ?: '—' }}</dd></div>
            @if($customer->loss_reason)<div class="info" style="grid-column:1/-1"><dt>سبب عدم إتمام البيع</dt><dd style="font-weight:600;white-space:pre-line">{{ $customer->loss_reason }}</dd></div>@endif
            <div class="info" style="grid-column:1/-1"><dt>العنوان</dt><dd>{{ $customer->address ?: '—' }}</dd></div>
            @if($customer->notes)<div class="info" style="grid-column:1/-1"><dt>ملاحظات</dt><dd style="font-weight:600;white-space:pre-line">{{ $customer->notes }}</dd></div>@endif
        </dl></div>
    </div>

    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h3><i class="fa-solid fa-motorcycle"></i> المركبات المطلوبة ({{ $vehicles->count() }})</h3>
            <div class="row" style="gap:8px">
                <span class="badge {{ $customer->status_class }}">{{ $customer->status === 'منفذة' ? 'تم التنفيذ' : ($customer->status === 'مفقودة' ? 'لم يتم التنفيذ' : 'قيد المتابعة') }}</span>
                @if($canEdit && auth()->user()->allows('customers', 'edit'))<button class="btn btn-sm btn-primary no-print" data-open="m-vehicle-add"><i class="fa-solid fa-plus"></i> إضافة مركبة</button>@endif
            </div>
        </div>
        <div class="card-body">
            @php($vehiclePrices = \App\Models\Product::where('is_active', true)->pluck('price', 'name'))
            @php($canVehicle = $canEdit && auth()->user()->allows('customers', 'edit'))
            @forelse($vehicles as $v)
                @php($calc = $v->calc)
                <div style="padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--line-2)' : '' }}">
                <div class="row between wrap" style="gap:10px">
                    <div><b style="font-size:15px">{{ $v->vehicle }}</b>
                        @if($loop->first)<span class="badge blue" style="margin-right:8px">الأحدث</span>@endif
                        @if(isset($vehiclePrices[$v->vehicle]))<span class="badge green" style="margin-right:6px">{{ number_format($vehiclePrices[$v->vehicle]) }} ج.م</span>@endif
                        @if($v->finance_entity)<span class="badge purple" style="margin-right:6px"><i class="fa-solid fa-building-columns"></i> {{ $v->finance_entity }}{{ $v->entity_branch ? ' — فرع ' . $v->entity_branch : '' }}</span>@endif
                        @if($v->stage)<span class="badge amber" style="margin-right:6px">{{ $v->stage }}</span>@endif
                    </div>
                    <div class="row wrap" style="gap:6px">
                        @if((isset($vehiclePrices[$v->vehicle]) || $calc) && auth()->user()->allows('calculator'))<button type="button" class="btn btn-xs btn-soft no-print" data-open="m-quick-calc" data-qc data-qc-product="{{ $v->vehicle }}" @if($calc) data-qc-saved="{{ json_encode(['price' => $calc['price'], 'down' => $calc['down'], 'months' => $calc['months'], 'plan' => $calc['plan']], JSON_UNESCAPED_UNICODE) }}" @endif @if($canVehicle) data-qc-save="{{ route('customers.vehicles.calc', [$customer, $v]) }}" @endif><i class="fa-solid fa-calculator"></i> احسب القسط</button>@endif
                        @if($canVehicle)<button type="button" class="btn btn-xs btn-ghost no-print" data-open="m-vehicle-edit" data-action="{{ route('customers.vehicles.update', [$customer, $v]) }}" data-fill-vehicle-name="{{ $v->vehicle }}" data-fill-finance-entity="{{ $v->finance_entity }}" data-fill-entity-branch="{{ $v->entity_branch }}" data-fill-stage="{{ $v->stage }}" data-fill-notes="{{ $v->notes }}"><i class="fa-solid fa-pen"></i> جهة التقسيط والمرحلة</button>@endif
                        <span class="muted small">{{ $v->creator?->name ?? '—' }} · {{ $v->created_at->format('Y/m/d') }}</span>
                        @if(auth()->user()->isAdmin() || $v->created_by === auth()->id())
                        <form method="POST" action="{{ route('customers.vehicles.destroy', [$customer, $v]) }}" data-confirm="حذف هذه المركبة؟" class="no-print">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form>
                        @endif
                    </div>
                </div>
                @if($calc)
                    <div class="veh-calc">
                        <span><i class="fa-solid fa-calculator" style="color:var(--blue-600)"></i> <b>الحساب المحفوظ</b></span>
                        <span>السعر <b class="num">@money($calc['price'])</b></span><span>المقدم <b class="num">@money($calc['down'])</b></span>
                        <span>النظام <b>{{ $calc['months'] }} شهر{{ $calc['plan'] ? ' — ' . $calc['plan'] : '' }}</b></span>
                        <span>القسط الشهري <b class="num" style="color:var(--blue-600)">@money($calc['monthly'])</b></span>
                        <span>المصاريف الإدارية <b class="num">@money($calc['fees'])</b></span>
                        <span>عند الاستلام <b class="num">@money($calc['upfront'])</b></span>
                        <span class="muted">{{ $calc['saved_by'] ?? '' }} · {{ $calc['saved_at'] ?? '' }}</span>
                    </div>
                @endif
                @if($v->notes)<div class="muted small mt">{{ $v->notes }}</div>@endif
                </div>
            @empty
                <div class="muted small">لا توجد مركبات مسجلة بعد.</div>
            @endforelse
            @if($customer->seriousness || $customer->previous_vehicle)
                <div class="mt">
                    @if($customer->seriousness)<span class="badge amber">{{ $customer->seriousness }}</span>@endif
                    @if($customer->previous_vehicle)<span class="muted small" style="margin-right:8px">المركبة السابقة: {{ $customer->previous_vehicle }}</span>@endif
                </div>
            @endif
            @if($customer->status === 'مفقودة' && $customer->loss_reason)<div class="alert alert-warning mt"><i class="fa-solid fa-circle-info"></i><div>سبب عدم التنفيذ: {{ $customer->loss_reason }}</div></div>@endif
        </div>
    </div>

    <div id="deals">
    @if($customer->is_dealer && $deals->count())
        {{-- the dealer's wholesale account: every batch he took, what he paid and what he still owes --}}
        @php($liveDeals = $deals->where('status', '!=', 'مفقودة'))
        <div class="card mb dealer-sum"><div class="card-body grid g4">
            <div class="kv" style="display:block"><span>صفقات التاجر</span><br><b class="num">{{ $liveDeals->count() }}</b> <span class="muted small">({{ $liveDeals->sum(fn ($x) => $x->quantity()) }} مكنة)</span></div>
            <div class="kv" style="display:block"><span>إجمالي الصفقات</span><br><b class="num">@money($liveDeals->sum('total_price'))</b></div>
            <div class="kv" style="display:block"><span>المدفوع</span><br><b class="num" style="color:var(--green)">@money($liveDeals->sum(fn ($x) => $x->down_payment + $x->paid_total))</b></div>
            <div class="kv" style="display:block"><span>الباقي عليه (المديونية)</span><br><b class="num" style="color:{{ $totalDebt > 0 ? 'var(--red)' : 'var(--green)' }}">@money($totalDebt)</b></div>
        </div></div>
    @endif
    @forelse($deals as $d)
        @php($paidPct = $d->total_payable > 0 ? min(100, $d->paid_total / $d->total_payable * 100) : 0)
        @php($installmentCopyText = $d->installmentCopyText())
        @php($entityDeal = $d->isEntityFinanced())
        <div class="card" style="margin-bottom:18px">
            <div class="card-head">
                <h3><i class="fa-solid fa-car"></i> {{ $d->vehicle ?: 'صفقة' }} {{ $d->model }}@if($d->quantity() > 1) <span class="badge purple">{{ $d->quantity() }} مكن</span>@endif <span class="badge {{ $d->status_class }}">{{ $d->status }}</span> <span class="badge gray">{{ $d->pay_method }}</span></h3>
                <div class="btn-group no-print">
                    @if($installmentCopyText)<button type="button" class="btn btn-xs btn-ghost" data-copy-text="{{ $installmentCopyText }}"><i class="fa-solid fa-copy" style="color:var(--blue-600)"></i> نسخ للتقسيط</button>@endif
                    @if($canEdit && $d->installments->count())<a class="btn btn-xs btn-ghost" href="{{ route('documents.create', ['type' => 'statement', 'deal' => $d->id]) }}"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i> كشف PDF</a>@endif
                    @if($canEdit && ($d->quantity() > 1 || $customer->is_dealer) && auth()->user()->allows('legal_documents', 'create'))<a class="btn btn-xs btn-ghost" href="{{ route('documents.create', ['type' => 'bulk_receipt', 'deal' => $d->id]) }}"><i class="fa-solid fa-boxes-stacked" style="color:#dc2626"></i> إقرار استلام كمية</a>@endif
                    @if($canEdit)<a class="btn btn-xs btn-ghost" href="{{ route('documents.index', ['deal' => $d->id]) }}"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i> مستندات</a>@endif
                    @if($d->installments->count())<a class="btn btn-xs btn-ghost" href="{{ route('deals.schedule', $d) }}"><i class="fa-solid fa-table-list"></i> جدول الأقساط</a>@endif
                    @if($canEdit && auth()->user()->allows('deals', 'edit'))<a class="btn btn-xs btn-ghost" href="{{ route('deals.edit', $d) }}"><i class="fa-solid fa-pen"></i></a>@endif
                    @if(auth()->user()->allows('deals', 'delete'))<form method="POST" action="{{ route('deals.destroy', $d) }}" data-confirm="حذف الصفقة وجدول أقساطها؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-trash"></i></button></form>@endif
                </div>
            </div>
            <div class="card-body">
                <div class="grid g4 mb">
                    <div class="kv" style="display:block"><span>السعر</span><br><b class="num">@money($d->total_price)</b></div>
                    <div class="kv" style="display:block"><span>المقدم</span><br><b class="num">@money($d->down_payment)</b></div>
                    @if($entityDeal)
                        <div class="kv" style="display:block"><span>تحويل {{ $d->finance_entity }}</span><br><span class="badge {{ $d->entity_state_class }}" style="margin-top:4px">{{ $d->entity_state_label }}</span></div>
                        <div class="kv" style="display:block"><span>المتبقي (المديونية على {{ $d->finance_entity }})</span><br><b class="num" style="color:{{ $d->balance > 0 ? 'var(--red)' : 'var(--green)' }}">@money($d->balance)</b></div>
                    @else
                    <div class="kv" style="display:block"><span>{{ $d->isInstallment() ? 'القسط الشهري' : 'المتبقي' }}</span><br><b class="num">@money($d->isInstallment() ? $d->monthly_installment : $d->balance)</b></div>
                    <div class="kv" style="display:block"><span>المتبقي (المديونية)</span><br><b class="num" style="color:{{ $d->balance > 0 ? 'var(--red)' : 'var(--green)' }}">@money($d->balance)</b></div>
                    @endif
                </div>
                <div class="small muted mb-s">
                    @if($d->finance_entity)الجهة: <b>{{ $d->finance_entity }}{{ $d->entity_branch ? ' — فرع ' . $d->entity_branch : '' }}</b> · @endif
                    @if($d->stage)المرحلة: <b>{{ $d->stage }}</b> · @endif
                    @if($d->color)اللون: <b>{{ $d->color }}</b> · @endif
                    @if($d->sale_date)تاريخ البيع: <b>{{ $d->sale_date->format('Y/m/d') }}</b> · @endif
                    @if($d->dealer)التاجر: <b>{{ $d->dealer }}</b> · @endif
                    @if($d->chassis)الشاسيه: <b class="num">{{ $d->chassis }}</b> · @endif
                    @if($d->vehicleUnit)المخزن: <span class="badge {{ $d->vehicleUnit->status_class }}">{{ $d->vehicleUnit->status_label }}</span> · @endif
                    @if($d->motor)الموتور: <b class="num">{{ $d->motor }}</b> · @endif
                    @if($d->delivery_date)التسليم: <b>{{ $d->delivery_date->format('Y/m/d') }}</b> · @endif
                    @if($entityDeal)@elseif($d->interest_type === 'table' && $d->installments->count())نظام الشركة · @elseif($d->interest_rate > 0)فائدة {{ rtrim(rtrim(number_format($d->interest_rate, 2), '0'), '.') }}% ({{ $d->interest_type === 'flat' ? 'ثابتة' : 'متناقصة' }}) · @endif
                    @if($d->admin_fees > 0)مصاريف إدارية: <b class="num">@money($d->admin_fees)</b> · @endif
                    @if($d->mobaya_no)مبايعة: <b class="num">{{ $d->mobaya_no }}</b> · @endif
                    @if($d->invoice_no)فاتورة: <b class="num">{{ $d->invoice_no }}</b> · @endif
                    @if($d->po_number)PO: <b class="num">{{ $d->po_number }}</b> · @endif
                    أدخلها: {{ $d->creator?->name ?? '—' }}
                </div>
                @if($d->quantity() > 1)
                    {{-- every chassis of the batch and where it stands in stock --}}
                    <div class="table-wrap mb-s"><table class="tbl small">
                        <thead><tr><th>#</th><th>الشاسيه</th><th>الموتور</th><th>النوع</th><th>المخزن</th></tr></thead>
                        <tbody>@foreach($d->serials() as $i => [$ch, $mo])
                            @php($u = \App\Models\Deal::matchUnit($d->vehicleUnits, $ch, $mo))
                            <tr><td>{{ $i + 1 }}</td><td class="num" dir="ltr">{{ $u?->chassis ?? $ch }}</td><td class="num" dir="ltr">{{ $u?->motor ?? $mo ?: '—' }}</td><td>{{ $u?->type ?? '—' }}</td>
                                <td>@if($u)<span class="badge {{ $u->status_class }}">{{ $u->status_label }}</span>@else<span class="badge gray">مش في المخزن</span>@endif</td></tr>
                        @endforeach</tbody>
                    </table></div>
                @endif

                @if($entityDeal && ($d->financed_amount > 0 || $d->entity_state !== 'pending'))
                    <div class="entity-box">
                        @if($d->entity_state === 'pending')
                            <span><i class="fa-solid fa-building-columns"></i> العميل بيسدد الأقساط لـ <b>{{ $d->finance_entity }}</b> — والجهة تحوّل لنا <b class="num">@money($d->balance)</b> (بعد الإيداع بيتجمد {{ $d->entityDays() }} يوم وبعدها يتحصل)</span>
                            <span class="muted"><i class="fa-solid fa-calculator"></i> {{ $d->entityNetText() }}</span>
                        @else
                            <span><i class="fa-solid fa-building-columns"></i> المبلغ <b class="num">@money($d->entity_amount)</b></span>
                            @if($d->entity_ref)<span>رقم المرجع <b class="num">{{ $d->entity_ref }}</b></span>@endif
                            @if($d->entity_deposited_at)<span>الإيداع <b>{{ $d->entity_deposited_at->format('Y/m/d') }}</b></span>@endif
                            @if($d->entity_collected_at)<span>التحصيل <b>{{ $d->entity_collected_at->format('Y/m/d') }}</b></span>@else<span>موعد التحصيل <b>{{ $d->entity_due_date->format('Y/m/d') }}</b></span>@endif
                            @if($d->entity_channel || $d->entity_account)<span>إلى <b>{{ trim($d->entity_channel . ' ' . $d->entity_account) }}</b></span>@endif
                            @if($d->entity_note)<span class="muted">{{ $d->entity_note }}</span>@endif
                            <span class="muted"><i class="fa-solid fa-calculator"></i> المستحق: {{ $d->entityNetText() }}</span>
                        @endif
                    </div>
                @endif
                @if($d->installments->count())
                    <div class="row between small mb-s"><b>سداد الأقساط</b><span class="muted">{{ $d->installments->where('state', 'paid')->count() }} / {{ $d->installments->count() }} قسط — {{ round($paidPct) }}%</span></div>
                    <div class="progress green mb"><span style="width:{{ $paidPct }}%"></span></div>
                    <details {{ $d->installments->contains(fn ($i) => in_array($i->state, ['overdue', 'due_today'])) ? 'open' : '' }}>
                        <summary class="btn btn-sm btn-soft" style="list-style:none;display:inline-flex"><i class="fa-solid fa-list"></i> عرض الأقساط</summary>
                        <div class="table-wrap" style="margin-top:12px"><table class="tbl">
                            <thead><tr><th>#</th><th>الاستحقاق</th><th>القسط</th><th>المسدد</th><th>المتبقي</th><th>الحالة</th></tr></thead>
                            <tbody>@foreach($d->installments as $i)<tr><td>{{ $i->number }}</td><td>{{ $i->due_date->format('Y/m/d') }}</td><td class="num">@money($i->amount)</td><td class="num">@money($i->paid_amount)</td><td class="num">@money($i->remaining)</td><td><span class="badge {{ $i->state_class }}">{{ $i->state_label }}</span></td></tr>@endforeach</tbody>
                        </table></div>
                    </details>
                @endif

                <div class="divider"></div>
                <div class="row between mb-s">
                    <b class="small"><i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> الدفعات ({{ $d->payments->count() }}) — المحصّل <span class="num">@money($d->paid_total)</span></b>
                    @if($entityDeal && $canEdit && auth()->user()->allows('payments', 'create'))
                        <button class="btn btn-sm btn-green no-print" data-open="m-entity" data-action="{{ route('deals.entity', $d) }}" data-fill-entity-label="{{ $d->finance_entity }} — {{ $d->vehicle }}" data-fill-entity-days="{{ $d->entityDays() }}" data-fill-entity-due="{{ number_format($d->total_payable, 2) }} ج.م — {{ $d->entityNetText() }}"
                            data-fill-entity-amount="{{ $d->entity_amount ?: $d->balance }}" data-fill-entity-ref="{{ $d->entity_ref }}" data-fill-entity-deposited-at="{{ $d->entity_deposited_at?->format('Y-m-d') }}"
                            data-fill-entity-collected-at="{{ $d->entity_collected_at?->format('Y-m-d') }}" data-fill-entity-channel="{{ $d->entity_channel ?: 'تحويل بنكي' }}" data-fill-entity-account="{{ $d->entity_account }}" data-fill-entity-note="{{ $d->entity_note }}">
                            <i class="fa-solid fa-building-columns"></i> {{ $d->entity_state === 'pending' ? 'تسجيل تحويل ' . $d->finance_entity : 'تعديل تحويل ' . $d->finance_entity }}</button>
                    @elseif($canEdit && $d->balance > 0 && auth()->user()->allows('payments', 'create'))<button class="btn btn-sm btn-green no-print" data-open="m-pay" data-action="{{ route('deals.payments.store', $d) }}" data-fill-deal-label="{{ $d->vehicle }}" data-fill-amount="{{ $d->isInstallment() && $d->monthly_installment ? min($d->monthly_installment, $d->balance) : $d->balance }}"><i class="fa-solid fa-plus"></i> تسجيل دفعة</button>@endif
                </div>
                @foreach($d->payments as $p)
                    <div class="row small" style="padding:7px 0;border-bottom:1px dashed var(--line)">
                        <span class="muted" style="width:90px">{{ $p->paid_on->format('Y/m/d') }}</span><b class="num">@money($p->amount)</b>
                        <span class="muted grow">{{ $p->method }} {{ $p->note ? '— ' . $p->note : '' }} · {{ $p->receiver?->name }}</span>
                        @if($canEdit && auth()->user()->allows('legal_documents', 'create'))<a class="btn btn-xs btn-ghost" title="إيصال PDF" href="{{ route('documents.create', ['type' => 'receipt', 'payment' => $p->id]) }}"><i class="fa-solid fa-file-pdf" style="color:#dc2626"></i></a>@endif
                        @if(auth()->user()->allows('payments', 'delete'))<form method="POST" action="{{ route('payments.destroy', $p) }}" data-confirm="حذف هذه الدفعة؟">@csrf @method('DELETE')<button class="btn btn-xs btn-danger-soft"><i class="fa-solid fa-xmark"></i></button></form>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="card"><div class="empty"><i class="fa-solid fa-car-side"></i><b>لم تُسجَّل صفقة فعلية بعد</b>@if($canEdit && auth()->user()->allows('deals', 'create'))<a class="btn btn-primary mt" href="{{ route('deals.create', $customer) }}"><i class="fa-solid fa-plus"></i> إضافة صفقة</a>@endif</div></div>
    @endforelse
    </div>

    <div class="card" id="followups">
        <div class="card-head"><h3><i class="fa-solid fa-calendar-check"></i> المتابعات ({{ $followups->count() }})</h3>@if(auth()->user()->allows('followups', 'create'))<button class="btn btn-sm btn-primary no-print" data-open="m-fu-add"><i class="fa-solid fa-plus"></i> جديدة</button>@endif</div>
        @forelse($followups as $f)
            <div class="fu">
                <div class="fu-date {{ $f->is_overdue ? 'overdue' : ($f->status === 'pending' && $f->is_today ? 'today' : '') }}"><b>{{ $f->due_date->format('d') }}</b><span>{{ $f->due_date->translatedFormat('M') }}</span></div>
                <div class="fu-body">
                    <div class="title">{{ $f->reason }}
                        @if($f->status === 'done')<span class="badge green">تمت</span>@elseif($f->status === 'cancelled')<span class="badge gray">ملغاة</span>@elseif($f->is_overdue)<span class="badge red">متأخرة</span>@else<span class="badge blue">قادمة</span>@endif
                        @if($f->priority === 'high')<span class="badge purple">مهمة</span>@endif</div>
                    @if($f->notes)<div class="notes">{{ $f->notes }}</div>@endif
                    @if($f->outcome)<div class="notes" style="color:var(--green)"><i class="fa-solid fa-check"></i> {{ $f->outcome }}</div>@endif
                    <div class="meta"><span><i class="fa-solid fa-user"></i> {{ $f->assignee?->name }}</span>@if($f->completed_at)<span>أنجزها {{ $f->completer?->name }} · {{ $f->completed_at->format('Y/m/d H:i') }}</span>@endif</div>
                </div>
                @if($f->status === 'pending')
                <div class="fu-actions no-print">
                    <button class="btn btn-sm btn-green" data-open="m-fu-done" data-action="{{ route('followups.complete', $f) }}" data-fill-customer-name="{{ $customer->name }}"><i class="fa-solid fa-check"></i> تم</button>
                    <button class="btn btn-sm btn-ghost" data-open="m-fu-move" data-action="{{ route('followups.update', $f) }}"><i class="fa-regular fa-clock"></i></button>
                </div>
                @endif
            </div>
        @empty<div class="empty" style="padding:34px"><i class="fa-regular fa-calendar" style="font-size:32px"></i>لا توجد متابعات</div>@endforelse
    </div>
</div>

<div class="section-gap">
    <div class="card" style="position:sticky;top:84px">
        <div class="card-head"><h3><i class="fa-solid fa-route"></i> تتبّع العميل</h3></div>
        <div class="card-body" style="max-height:78vh;overflow-y:auto">
            <div class="timeline">
                @foreach($events as $e)
                    @php([$ic, $cl] = $e->icon)
                    <div class="tl-item"><div class="tl-dot {{ $cl }}"><i class="fa-solid {{ $ic }}"></i></div>
                        <div class="tl-body">{{ $e->description }}</div>
                        <div class="tl-meta">{{ $e->user?->name ?? 'النظام' }} · {{ $e->created_at->format('Y/m/d H:i') }}</div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
</div>

{{-- add vehicle modal --}}
<div class="modal" id="m-vehicle-add"><div class="modal-box" style="max-width:460px">
    <div class="modal-head"><h3><i class="fa-solid fa-motorcycle" style="color:var(--blue-600)"></i> إضافة مركبة مطلوبة</h3><button class="modal-x" data-close="m-vehicle-add"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="{{ route('customers.vehicles.store', $customer) }}">@csrf
        <div class="modal-body">
            <div class="field"><label>المركبة <span class="req">*</span></label><select class="input" name="vehicle" id="f-vehicle-add" required data-price-hint>@include('partials.vehicle_options', ['selected' => ''])</select>
                <div class="row between wrap" style="gap:6px;margin-top:6px"><b class="price-hint small" style="color:var(--green)"></b>@if(auth()->user()->allows('calculator'))<button type="button" class="btn btn-xs btn-soft" data-open="m-quick-calc" data-qc data-qc-from="#f-vehicle-add"><i class="fa-solid fa-calculator"></i> احسب القسط</button>@endif</div></div>
            @include('customers._vehicle_entity_fields')
            <div class="field mt"><label>ملاحظة</label><input class="input" name="notes" maxlength="250"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-vehicle-add">إلغاء</button><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> إضافة</button></div>
    </form>
</div></div>

{{-- where a requested vehicle was sent --}}
<div class="modal" id="m-vehicle-edit"><div class="modal-box" style="max-width:460px">
    <div class="modal-head"><h3><i class="fa-solid fa-building-columns" style="color:var(--purple)"></i> <span data-label="vehicle_name"></span></h3><button class="modal-x" data-close="m-vehicle-edit"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="">@csrf @method('PUT')
        <div class="modal-body">
            @include('customers._vehicle_entity_fields')
            <div class="field mt"><label>ملاحظة</label><input class="input" name="notes" maxlength="250"></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-vehicle-edit">إلغاء</button><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> حفظ</button></div>
    </form>
</div></div>
<datalist id="entity-branches">@foreach(\App\Models\Deal::entityBranches() as $b)<option value="{{ $b }}">@endforeach</datalist>

{{-- the finance entity's transfer for a deal --}}
<div class="modal" id="m-entity"><div class="modal-box" style="max-width:560px">
    <div class="modal-head"><h3><i class="fa-solid fa-building-columns" style="color:var(--green)"></i> تحويل جهة التقسيط — <span data-label="entity_label"></span></h3><button class="modal-x" data-close="m-entity"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="">@csrf
        <div class="modal-body">
            <div class="alert alert-info" style="margin-bottom:12px"><i class="fa-solid fa-circle-info"></i><div>المستحق من الجهة: <b data-label="entity_due"></b><br>تقدر ترجع تعدل أي بيانات هنا أو تمسح تاريخ التحصيل في أي وقت من نفس الزرار.</div></div>
            <div class="form-grid cols-2">
            <div class="field"><label>المبلغ المحوّل (ج.م)</label><input class="input" type="number" step="0.01" min="0" name="entity_amount"></div>
            <div class="field"><label>رقم المرجع</label><input class="input" name="entity_ref" maxlength="80" dir="ltr" style="text-align:right"></div>
            <div class="field"><label>تاريخ الإيداع</label><input class="input" type="date" name="entity_deposited_at" max="{{ today()->format('Y-m-d') }}"><div class="hint">بيتجمد <span data-label="entity_days"></span> يوم وبعدها يتحصل.</div></div>
            <div class="field"><label>تاريخ التحصيل</label><input class="input" type="date" name="entity_collected_at" max="{{ today()->format('Y-m-d') }}"><div class="hint">سيبه فاضي لو الفلوس لسه مجمدة.</div></div>
            <div class="field"><label>اتحصلت على</label><select class="input" name="entity_channel">@foreach(\App\Models\Deal::ENTITY_CHANNELS as $c)<option>{{ $c }}</option>@endforeach</select></div>
            <div class="field"><label>البنك / الحساب</label><input class="input" name="entity_account" maxlength="120" list="entity-accounts" placeholder="مثلاً بنك مصر — حساب الشركة"></div>
            <div class="field span-2" style="grid-column:1/-1"><label>ملاحظة</label><input class="input" name="entity_note" maxlength="250"></div>
        </div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-entity">إلغاء</button><button class="btn btn-green" type="submit"><i class="fa-solid fa-check"></i> حفظ</button></div>
    </form>
</div></div>
<datalist id="entity-accounts">@foreach(\App\Models\Deal::whereNotNull('entity_account')->distinct()->pluck('entity_account') as $a)<option value="{{ $a }}">@endforeach</datalist>

{{-- payment modal --}}
<div class="modal" id="m-pay"><div class="modal-box" style="max-width:480px">
    <div class="modal-head"><h3><i class="fa-solid fa-money-bill-wave" style="color:var(--green)"></i> تسجيل دفعة — <span data-label="deal_label"></span></h3><button class="modal-x" data-close="m-pay"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="">@csrf
        <div class="modal-body"><div class="form-grid cols-2">
            <div class="field"><label>المبلغ (ج.م) <span class="req">*</span></label><input class="input" type="number" step="0.01" min="0.01" name="amount" required></div>
            <div class="field"><label>تاريخ الدفع <span class="req">*</span></label><input class="input" type="date" name="paid_on" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></div>
            <div class="field"><label>طريقة السداد</label><select class="input" name="method">@foreach(\App\Exports\TemplateBuilder::PAYMENT_METHODS as $m)<option>{{ $m }}</option>@endforeach</select></div>
            <div class="field"><label>ملاحظة</label><input class="input" name="note" maxlength="200"></div>
        </div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-pay">إلغاء</button><button class="btn btn-green" type="submit"><i class="fa-solid fa-check"></i> تسجيل</button></div>
    </form>
</div></div>

@if($isAdmin)
<div class="modal" id="m-assign"><div class="modal-box" style="max-width:460px">
    <div class="modal-head"><h3><i class="fa-solid fa-right-left" style="color:var(--purple)"></i> نقل العميل لموظف</h3><button class="modal-x" data-close="m-assign"><i class="fa-solid fa-xmark"></i></button></div>
    <form method="POST" action="{{ route('customers.assign', $customer) }}">@csrf
        <div class="modal-body"><div class="field"><label>الموظف المسؤول الجديد</label><select class="input" name="assigned_to" required>@foreach($staff as $u)<option value="{{ $u->id }}" @selected($customer->assigned_to == $u->id)>{{ $u->name }}</option>@endforeach</select><div class="hint">ستُنقل معه المتابعات المعلّقة، ويُسجَّل النقل في سجل تتبع العميل.</div></div></div>
        <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close="m-assign">إلغاء</button><button class="btn btn-primary">نقل</button></div>
    </form>
</div></div>
@endif

@include('followups._modals', ['customer' => $customer])
@if(auth()->user()->allows('calculator'))@include('customers._quick_calc')@endif
@endsection
