<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lookup;
use App\Models\User;
use App\Services\DealService;
use App\Support\Activity;
use App\Support\ViewGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /** Filtered customer query shared by the list screen, the API and the Excel export. */
    public function query(Request $request, bool $searchAll = false)
    {
        $user = $request->user();
        $q = Customer::query()->with(['creator:id,name', 'assignee:id,name,username'])
            ->withCount('deals')
            ->withSum('deals as debt', 'balance');

        // employees list only their own customers; a search (from the list screen) reaches every customer
        if (! $searchAll || mb_strlen(trim((string) $request->query('q'))) < 3) {
            $q->visibleTo($user);
        }
        $q->search($request->query('q'));

        foreach (['status', 'governorate', 'channel', 'seriousness', 'branch'] as $f) {
            if ($v = $request->query($f)) {
                $q->where("customers.$f", $v);
            }
        }
        if ($v = $request->query('vehicle')) {
            $q->where(fn ($w) => $w->where('customers.interest', $v)->orWhereHas('deals', fn ($d) => $d->where('vehicle', $v)));
        }
        // a customer "with" an entity: a deal through it, or a requested vehicle sent to it (executed or not)
        foreach (['finance_entity', 'entity_branch'] as $f) {
            if ($v = $request->query($f)) {
                $q->where(fn ($w) => $w->whereHas('deals', fn ($d) => $d->where($f, $v))->orWhereHas('vehicles', fn ($d) => $d->where($f, $v)));
            }
        }
        // dealers (تجار / معارض) or retail customers only
        if (in_array($request->query('kind'), ['dealer', 'customer'], true)) {
            $q->where('customers.is_dealer', $request->query('kind') === 'dealer');
        }
        if ($v = $request->query('pay_method')) {
            $q->whereHas('deals', fn ($d) => $d->where('pay_method', $v));
        }
        if ($request->boolean('new')) {
            $q->newlyAssignedTo($user);
        }
        if ($request->boolean('has_debt')) {
            $q->whereHas('deals', fn ($d) => $d->where('balance', '>', 0));
        }
        if ($user->isAdmin()) {
            if ($v = $request->query('created_by')) {
                $q->where('customers.created_by', $v);
            }
            if ($v = $request->query('assigned_to')) {
                $v === 'none' ? $q->whereNull('customers.assigned_to') : $q->where('customers.assigned_to', $v);
            }
        }
        if ($v = $request->query('from')) {
            $q->whereDate('customers.created_at', '>=', $v);
        }
        if ($v = $request->query('to')) {
            $q->whereDate('customers.created_at', '<=', $v);
        }

        return $q;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $term = trim((string) $request->query('q'));
        $digits = Customer::normalizePhone($term);
        $isPhone = $digits && preg_match('/^\d{8,15}$/', $digits) && ! preg_match('/[^\d\s+\-()٠-٩]/u', $term);
        if ($isPhone && ! $request->query('page')) {
            $hit = Customer::query()->where(fn ($w) => $w->where('phone', $digits)->orWhere('alt_phone', $digits))->get();
            if ($hit->count() === 1) {
                return redirect()->route('customers.show', $hit->first());
            }
        }
        // the exact code opens the customer's file straight away
        if (Customer::looksLikeCode($term) && ! $request->query('page') && ($hit = Customer::where('code', strtoupper($term))->first())) {
            return redirect()->route('customers.show', $hit);
        }
        $q = $this->query($request, true);

        $sort = in_array($request->query('sort'), ['name', 'created_at', 'status'], true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        // customers just handed to this employee come first until they open them
        $q->orderByRaw('(customers.assigned_to = ? AND customers.assigned_at IS NOT NULL AND customers.assignment_seen_at IS NULL) DESC', [$user->id]);
        $customers = $q->orderBy("customers.$sort", $dir)->paginate(20)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'staff' => $user->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'filters' => $request->query(),
            'searchedPhone' => $isPhone ? $digits : null,
        ]);
    }

    public function create(Request $request)
    {
        $phone = Customer::normalizePhone($request->query('phone'));
        $prefill = $request->only(['name', 'interest', 'governorate', 'district', 'address']);
        $lead = $request->filled('lead_id') ? \App\Models\Lead::find((int) $request->query('lead_id')) : null;
        if ($lead && $lead->extra) {
            $prefill['notes'] = $lead->extraText();
        }

        return view('customers.form', [
            'customer' => new Customer(array_filter($prefill) + ['status' => 'مفتوحة', 'phone' => $phone]),
            'staff' => $this->staff(), 'leadId' => $request->query('lead_id'),
        ]);
    }

    public function store(Request $request, DealService $deals)
    {
        $data = $this->validated($request);
        $this->assertPhoneSlotsNotDuplicate($data);
        if ($request->boolean('with_deal') && ($conflict = $deals->inventoryConflict($request->input('chassis'), $request->input('motor'), null, $request->input('units')))) {
            return back()->withInput()->withErrors(['chassis' => $conflict]);
        }

        $user = $request->user();
        $data['created_by'] = $user->id;
        $data['assigned_to'] = $user->isAdmin() && $request->filled('assigned_to') ? (int) $request->input('assigned_to') : $user->id;

        $customer = Customer::create($data);
        if ($customer->assigned_to !== $user->id) {
            $customer->assignTo($customer->assigned_to, $user);
        }
        Activity::log('customer.create', $customer, 'إضافة العميل ' . $customer->name);
        Activity::customer($customer, 'created', 'تم إدخال العميل بواسطة ' . $user->name);

        if ($customer->interest) {
            $customer->vehicles()->create(['vehicle' => $customer->interest, 'created_by' => $user->id]);
        }

        if ($request->filled('lead_id')) {
            $lead = \App\Models\Lead::find((int) $request->input('lead_id'));
            if ($lead && ($user->isAdmin() || $lead->assigned_to === $user->id)) {
                $lead->update(['status' => 'converted', 'customer_id' => $customer->id, 'converted_at' => now()]);
            }
        }

        if ($request->boolean('with_deal')) {
            $dealData = $request->validate($this->dealRules());
            $deal = $deals->save($customer, array_merge($dealData, [
                'vehicle' => $dealData['vehicle'] ?? $customer->interest,
                'status' => $dealData['deal_status'] ?? $customer->status,
                'notes' => $dealData['deal_notes'] ?? null,
            ]), null, $user);
            Activity::log('deal.create', $deal, 'إضافة صفقة للعميل ' . $customer->name);
            Activity::customer($customer, 'deal', 'تمت إضافة صفقة: ' . ($deal->vehicle ?: 'غير محدد'));
        }

        return redirect()->route('customers.show', $customer)->with('success', 'تم تسجيل العميل. الكود: ' . $customer->code);
    }

    public function show(Request $request, Customer $customer)
    {
        ViewGuard::record($request->user(), $customer);
        if ($customer->isNewFor($request->user())) {
            $customer->forceFill(['assignment_seen_at' => now()])->saveQuietly();
        }

        $customer->load(['creator:id,name,role', 'assignee:id,name,role']);
        $deals = $customer->deals()->with(['installments', 'payments.receiver:id,name', 'creator:id,name', 'vehicleUnit:id,deal_id,chassis,status', 'vehicleUnits:id,deal_id,chassis,motor,type,model_year,color,status'])->latest()->get();
        $followups = $customer->followups()->with(['assignee:id,name', 'completer:id,name', 'creator:id,name'])->latest('created_at')->latest('id')->get();
        $events = $customer->events()->with('user:id,name')->limit(60)->get();
        $vehicles = $customer->vehicles()->with('creator:id,name')->get();

        return view('customers.show', [
            'customer' => $customer, 'deals' => $deals, 'followups' => $followups, 'events' => $events, 'vehicles' => $vehicles,
            'staff' => $this->staff(),
        ]);
    }

    public function edit(Customer $customer)
    {
        $this->ensureCanEdit($customer);

        return view('customers.form', ['customer' => $customer, 'staff' => $this->staff()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->ensureCanEdit($customer);
        $data = $this->validated($request, $customer);
        $this->assertPhoneSlotsNotDuplicate($data, $customer->id);

        $customer->fill($data);
        $changed = collect($customer->getDirty())->except(['updated_at', 'nat_id_hash'])->keys();
        $oldStatus = $customer->getOriginal('status');
        $customer->save();

        if ($changed->isNotEmpty()) {
            Activity::log('customer.update', $customer, 'تعديل بيانات العميل ' . $customer->name, ['fields' => $changed->all()]);
            Activity::customer($customer, 'updated', 'تعديل البيانات بواسطة ' . $request->user()->name, ['fields' => $changed->all()]);
            if ($changed->contains('status')) {
                Activity::customer($customer, 'status', "تغيير الحالة من {$oldStatus} إلى {$customer->status}");
            }
        }

        return redirect()->route('customers.show', $customer)->with('success', 'تم حفظ التعديلات.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        Activity::log('customer.delete', $customer, 'حذف العميل ' . $customer->name);

        return redirect()->route('customers.index')->with('success', 'تم حذف العميل.');
    }

    /** Admin: move a customer to another employee (ownership tracking). */
    public function assign(Request $request, Customer $customer)
    {
        $data = $request->validate(['assigned_to' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')]]);
        $new = User::findOrFail($data['assigned_to']);
        $old = $customer->assignee?->name ?? 'بدون';
        $customer->assignTo($new->id, $request->user());
        $customer->followups()->where('status', 'pending')->update(['assigned_to' => $new->id]);

        Activity::log('customer.assign', $customer, "نقل العميل {$customer->name} من {$old} إلى {$new->name}");
        Activity::customer($customer, 'assigned', "تم نقل العميل من {$old} إلى {$new->name}");

        return back()->with('success', 'تم نقل العميل إلى ' . $new->name);
    }

    /** Add another vehicle interest to the customer file (newest shown first); also becomes the primary `interest`. */
    public function storeVehicle(Request $request, Customer $customer)
    {
        $this->ensureCanEdit($customer);
        $data = $request->validate([
            'vehicle' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:250'],
        ] + self::vehicleEntityRules());
        $customer->vehicles()->create($data + ['created_by' => $request->user()->id, 'entity_sent_at' => ! empty($data['finance_entity']) ? now() : null]);
        $customer->update(['interest' => $data['vehicle']]);
        Activity::customer($customer, 'vehicle', 'إضافة مركبة مطلوبة: ' . $data['vehicle']);

        return back()->with('success', 'تمت إضافة المركبة.');
    }

    /** Where a requested vehicle was sent: finance entity, its branch, the stage there, and notes. */
    public function updateVehicle(Request $request, Customer $customer, \App\Models\CustomerVehicle $vehicle)
    {
        $this->ensureCanEdit($customer);
        abort_unless($vehicle->customer_id === $customer->id, 404);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:250']] + self::vehicleEntityRules());
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data + ['finance_entity' => null, 'entity_branch' => null, 'stage' => null, 'notes' => null]);

        $before = $vehicle->only(['finance_entity', 'entity_branch', 'stage']);
        if ($data['finance_entity'] && $data['finance_entity'] !== $vehicle->finance_entity) {
            $data['entity_sent_at'] = now();
        }
        $vehicle->update($data);
        if ($before !== $vehicle->only(['finance_entity', 'entity_branch', 'stage'])) {
            Activity::customer($customer, 'vehicle', $vehicle->vehicle . ': ' . implode(' · ', array_filter([
                $vehicle->finance_entity ? 'جهة التقسيط ' . $vehicle->finance_entity . ($vehicle->entity_branch ? ' — فرع ' . $vehicle->entity_branch : '') : 'بدون جهة تقسيط',
                $vehicle->stage ? 'المرحلة ' . $vehicle->stage : null,
            ])));
        }

        return back()->with('success', 'تم حفظ بيانات المركبة.');
    }

    /** Save the installment calculation under a requested vehicle (recomputed here, never trusted from the browser). */
    public function saveVehicleCalc(Request $request, Customer $customer, \App\Models\CustomerVehicle $vehicle)
    {
        $this->ensureCanEdit($customer);
        abort_unless($vehicle->customer_id === $customer->id, 404);
        $in = $request->validate([
            'price' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'down' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'months' => ['required', 'integer', 'min:1', 'max:84'],
            'plan' => ['nullable', 'string', 'max:80'],
        ]);
        $r = \App\Support\InstallmentCalculator::calculate(['price' => $in['price'], 'down' => $in['down'] ?? 0, 'months' => $in['months'], 'type' => 'table', 'plan' => $in['plan'] ?? null]);
        abort_unless($r['monthly'] > 0, 422, 'لا يوجد مبلغ للتقسيط — راجع السعر والمقدم.');

        $calc = ['price' => $r['price'], 'down' => $r['down'], 'months' => $r['months'], 'plan' => $r['plan'] ?? ($in['plan'] ?? null), 'monthly' => $r['monthly'],
            'fees' => $r['fees'], 'upfront' => $r['upfront'], 'financed' => $r['financed'], 'grand_total' => $r['grand_total'],
            'saved_at' => now()->format('Y-m-d H:i'), 'saved_by' => $request->user()->name];
        $fill = ['calc' => $calc];
        // the plan names the entity the offer was made for, unless the employee already chose one
        if (! $vehicle->finance_entity && \App\Models\Deal::isOutsideEntity($calc['plan']) && in_array($calc['plan'], Lookup::list('finance_entity'), true)) {
            $fill += ['finance_entity' => $calc['plan'], 'entity_sent_at' => now()];
        }
        $vehicle->update($fill);
        Activity::customer($customer, 'vehicle', 'حفظ حساب القسط لـ ' . $vehicle->vehicle . ': ' . number_format($calc['monthly']) . ' ج.م × ' . $calc['months'] . ' شهر (' . $calc['plan'] . ')');

        return response()->json(['ok' => true, 'calc' => $calc]);
    }

    public static function vehicleEntityRules(): array
    {
        return [
            'finance_entity' => ['nullable', 'string', 'max:120'],
            'entity_branch' => ['nullable', 'string', 'max:120'],
            'stage' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function destroyVehicle(Customer $customer, \App\Models\CustomerVehicle $vehicle)
    {
        $this->ensureCanEdit($customer);
        abort_unless($vehicle->customer_id === $customer->id, 404);
        abort_unless(auth()->user()->isAdmin() || $vehicle->created_by === auth()->id(), 403);
        $vehicle->delete();

        return back()->with('success', 'تم حذف المركبة.');
    }

    /** Autocomplete for pickers (scoped, throttled by route). */
    public function lookup(Request $request)
    {
        $term = trim((string) $request->query('q'));
        abort_if(mb_strlen($term) < 2, 422, 'اكتب حرفين على الأقل');

        $rows = Customer::query()->search($term)->orderByRaw('customers.code = ? DESC', [strtoupper($term)])->orderBy('name')->limit(8)
            ->get(['id', 'name', 'phone', 'code'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'code' => $c->code]);

        return response()->json($rows);
    }

    /** Duplicate check by phone — tells the agent who already owns the customer; any customer found by number can be opened. */
    public function checkPhone(Request $request)
    {
        $phone = Customer::normalizePhone($request->query('phone'));
        if (! $phone || strlen($phone) < 8) {
            return response()->json(['exists' => false, 'short' => true]);
        }
        $c = Customer::with('creator:id,name')->where(fn ($w) => $w->where('phone', $phone)->orWhere('alt_phone', $phone)->orWhere('whatsapp', $phone))->first();

        return response()->json($c ? [
            'exists' => true, 'code' => $c->code, 'by' => $c->creator?->name, 'since' => $c->created_at->format('Y/m/d'),
            'can_open' => true, 'url' => route('customers.show', $c),
            'name' => $c->name, 'status' => $c->status, 'interest' => $c->interest, 'governorate' => $c->governorate, 'phone' => $c->phone,
        ] : ['exists' => false, 'new_url' => route('customers.create', ['phone' => $phone])]);
    }

    /* ---------------------------------------------------------------- */

    private function staff()
    {
        return auth()->user()->isAdmin() ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();
    }

    /**
     * Check all 3 phone/WhatsApp slots (phone, alt_phone, whatsapp) of the submitted customer
     * against all 3 columns of every other existing customer — a hit on any slot, even the
     * 3rd (whatsapp) against a non-primary existing number, must block with a direct link.
     */
    private function assertPhoneSlotsNotDuplicate(array $data, ?int $ignoreId = null): void
    {
        $labels = ['phone' => 'رقم الهاتف', 'alt_phone' => 'الهاتف البديل', 'whatsapp' => 'رقم الواتساب'];
        $checked = [];
        foreach (['phone', 'alt_phone', 'whatsapp'] as $field) {
            $num = $data[$field] ?? null;
            if (! $num || in_array($num, $checked, true)) {
                continue;
            }
            $checked[] = $num;
            $q = Customer::with('creator:id,name')->where(function ($w) use ($num) {
                $w->where('phone', $num)->orWhere('alt_phone', $num)->orWhere('whatsapp', $num);
            });
            $ignoreId && $q->whereKeyNot($ignoreId);
            if ($dup = $q->first()) {
                abort(back()->withInput()->withErrors([
                    $field => "{$labels[$field]} مسجل بالفعل للعميل (كود {$dup->code}) وأدخله " . ($dup->creator?->name ?? 'النظام') . ' بتاريخ ' . $dup->created_at->format('Y/m/d') . '.',
                ]));
            }
        }
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge([
            'phone' => Customer::normalizePhone($request->input('phone')),
            'alt_phone' => Customer::normalizePhone($request->input('alt_phone')),
            'whatsapp' => Customer::normalizePhone($request->input('whatsapp')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'nat_id' => ['nullable', 'digits:14'],
            'job' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^\d{8,15}$/'],
            'alt_phone' => ['nullable', 'regex:/^\d{8,15}$/'],
            'whatsapp' => ['nullable', 'regex:/^\d{8,15}$/'],
            'governorate' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:250'],
            'channel' => ['nullable', 'string', 'max:100'],
            'interest' => ['nullable', 'string', 'max:150'],
            'age' => ['nullable', 'integer', 'min:15', 'max:100'],
            'seriousness' => ['nullable', 'string', 'max:40'],
            'previous_vehicle' => ['nullable', 'string', 'max:150'],
            'branch' => ['nullable', 'string', 'max:100'],
            'is_dealer' => ['nullable', 'boolean'],
            'shop_name' => ['nullable', 'string', 'max:150'],
            'loss_reason' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(Customer::STATUSES)],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'phone.regex' => 'رقم الهاتف غير صالح.',
            'nat_id.digits' => 'الرقم القومي يجب أن يكون 14 رقماً.',
        ]);
    }

    public static function dealRules(): array
    {
        return [
            'vehicle' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:100'],
            'chassis' => ['nullable', 'string', 'max:100'],
            'motor' => ['nullable', 'string', 'max:100'],
            'units' => ['nullable', 'string', 'max:6000'],
            'color' => ['nullable', 'string', 'max:60'],
            'sale_date' => ['nullable', 'date'],
            'dealer' => ['nullable', 'string', 'max:100'],
            'pay_method' => ['required', Rule::in(['كاش', 'تقسيط'])],
            'finance_entity' => ['nullable', 'string', 'max:120'],
            'entity_branch' => ['nullable', 'string', 'max:120'],
            'entity_net' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'stage' => ['nullable', 'string', 'max:120'],
            'total_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'down_payment' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_type' => ['nullable', Rule::in(['flat', 'reducing', 'table'])],
            'admin_fees' => ['nullable', 'numeric', 'min:0'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0'],
            'first_due_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'po_number' => ['nullable', 'string', 'max:40'], 'sales_order' => ['nullable', 'string', 'max:40'], 'invoice_no' => ['nullable', 'string', 'max:40'],
            'treasury_receipt' => ['nullable', 'string', 'max:40'], 'mobaya_no' => ['nullable', 'string', 'max:40'],
            'mobaya_arrived_at' => ['nullable', 'date'], 'mobaya_received_at' => ['nullable', 'date'], 'customer_notified' => ['nullable', 'boolean'],
            'deal_status' => ['nullable', Rule::in(Customer::STATUSES)],
            'deal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
