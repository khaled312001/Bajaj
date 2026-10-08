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
    public function query(Request $request)
    {
        $user = $request->user();
        $q = Customer::query()->visibleTo($user)->with(['creator:id,name', 'assignee:id,name,username'])
            ->withCount('deals')
            ->withSum('deals as debt', 'balance');

        $q->search($request->query('q'));

        foreach (['status', 'governorate', 'channel', 'seriousness', 'branch'] as $f) {
            if ($v = $request->query($f)) {
                $q->where("customers.$f", $v);
            }
        }
        if ($v = $request->query('vehicle')) {
            $q->where(fn ($w) => $w->where('customers.interest', $v)->orWhereHas('deals', fn ($d) => $d->where('vehicle', $v)));
        }
        if ($v = $request->query('finance_entity')) {
            $q->whereHas('deals', fn ($d) => $d->where('finance_entity', $v));
        }
        if ($v = $request->query('pay_method')) {
            $q->whereHas('deals', fn ($d) => $d->where('pay_method', $v));
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
            $hit = Customer::query()->visibleTo($user)->where(fn ($w) => $w->where('phone', $digits)->orWhere('alt_phone', $digits))->get();
            if ($hit->count() === 1) {
                return redirect()->route('customers.show', $hit->first());
            }
        }
        $q = $this->query($request);

        $sort = in_array($request->query('sort'), ['name', 'created_at', 'status'], true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
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

        return view('customers.form', ['customer' => new Customer(['status' => 'مفتوحة', 'phone' => $phone]), 'staff' => $this->staff()]);
    }

    public function store(Request $request, DealService $deals)
    {
        $data = $this->validated($request);
        $this->assertNotDuplicate($data['phone']);
        $this->assertAltNotDuplicate($data['alt_phone'] ?? null, $data['phone']);

        $user = $request->user();
        $data['created_by'] = $user->id;
        $data['assigned_to'] = $user->isAdmin() && $request->filled('assigned_to') ? (int) $request->input('assigned_to') : $user->id;

        $customer = Customer::create($data);
        Activity::log('customer.create', $customer, 'إضافة العميل ' . $customer->name);
        Activity::customer($customer, 'created', 'تم إدخال العميل بواسطة ' . $user->name);

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
        $this->ensureCanSee($customer);
        ViewGuard::record($request->user(), $customer);

        $customer->load(['creator:id,name,role', 'assignee:id,name,role']);
        $deals = $customer->deals()->with(['installments', 'payments.receiver:id,name', 'creator:id,name'])->latest()->get();
        $followups = $customer->followups()->with(['assignee:id,name', 'completer:id,name', 'creator:id,name'])->orderByRaw("status = 'pending' desc")->orderBy('due_date', 'desc')->get();
        $events = $customer->events()->with('user:id,name')->limit(60)->get();

        return view('customers.show', [
            'customer' => $customer, 'deals' => $deals, 'followups' => $followups, 'events' => $events,
            'staff' => $this->staff(),
        ]);
    }

    public function edit(Customer $customer)
    {
        $this->ensureCanSee($customer);

        return view('customers.form', ['customer' => $customer, 'staff' => $this->staff()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->ensureCanSee($customer);
        $data = $this->validated($request, $customer);
        if ($data['phone'] !== $customer->phone) {
            $this->assertNotDuplicate($data['phone'], $customer->id);
        }
        if (($data['alt_phone'] ?? null) !== $customer->alt_phone) {
            $this->assertAltNotDuplicate($data['alt_phone'] ?? null, $data['phone'], $customer->id);
        }

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
        $customer->update(['assigned_to' => $new->id]);
        $customer->followups()->where('status', 'pending')->update(['assigned_to' => $new->id]);

        Activity::log('customer.assign', $customer, "نقل العميل {$customer->name} من {$old} إلى {$new->name}");
        Activity::customer($customer, 'assigned', "تم نقل العميل من {$old} إلى {$new->name}");

        return back()->with('success', 'تم نقل العميل إلى ' . $new->name);
    }

    /** Autocomplete for pickers (scoped, throttled by route). */
    public function lookup(Request $request)
    {
        $term = trim((string) $request->query('q'));
        abort_if(mb_strlen($term) < 2, 422, 'اكتب حرفين على الأقل');

        $rows = Customer::query()->visibleTo($request->user())->search($term)->orderBy('name')->limit(8)
            ->get(['id', 'name', 'phone', 'code'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'code' => $c->code]);

        return response()->json($rows);
    }

    /** Duplicate check by phone — tells the agent who already owns the customer without leaking the file. */
    public function checkPhone(Request $request)
    {
        $phone = Customer::normalizePhone($request->query('phone'));
        if (! $phone || strlen($phone) < 8) {
            return response()->json(['exists' => false, 'short' => true]);
        }
        $c = Customer::with('creator:id,name')->where(fn ($w) => $w->where('phone', $phone)->orWhere('alt_phone', $phone))->first();

        return response()->json($c ? [
            'exists' => true, 'code' => $c->code, 'by' => $c->creator?->name, 'since' => $c->created_at->format('Y/m/d'),
            'can_open' => $open = Customer::query()->visibleTo($request->user())->whereKey($c->id)->exists(),
            'url' => route('customers.show', $c),
            'name' => $open ? $c->name : null, 'status' => $open ? $c->status : null, 'interest' => $open ? $c->interest : null,
            'governorate' => $open ? $c->governorate : null, 'phone' => $open ? $c->phone : null,
        ] : ['exists' => false, 'new_url' => route('customers.create', ['phone' => $phone])]);
    }

    /* ---------------------------------------------------------------- */

    private function staff()
    {
        return auth()->user()->isAdmin() ? User::where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();
    }

    /** The alternative number must not belong to another customer either. */
    private function assertAltNotDuplicate(?string $alt, string $main, ?int $ignoreId = null): void
    {
        $alt = Customer::normalizePhone($alt);
        if (! $alt || $alt === Customer::normalizePhone($main)) {
            return;
        }
        $q = Customer::with('creator:id,name')->where(fn ($w) => $w->where('phone', $alt)->orWhere('alt_phone', $alt));
        $ignoreId && $q->whereKeyNot($ignoreId);
        if ($dup = $q->first()) {
            abort(back()->withInput()->withErrors([
                'alt_phone' => "الهاتف البديل مسجل بالفعل لعميل آخر (كود {$dup->code}) وأدخله " . ($dup->creator?->name ?? 'النظام') . ' بتاريخ ' . $dup->created_at->format('Y/m/d') . '.',
            ]));
        }
    }

    private function assertNotDuplicate(string $phone, ?int $ignoreId = null): void
    {
        $q = Customer::with('creator:id,name')->where(fn ($w) => $w->where('phone', $phone)->orWhere('alt_phone', $phone));
        if ($ignoreId) {
            $q->whereKeyNot($ignoreId);
        }
        if ($dup = $q->first()) {
            abort(back()->withInput()->withErrors([
                'phone' => "هذا الرقم مسجل بالفعل للعميل (كود {$dup->code}) وأدخله " . ($dup->creator?->name ?? 'النظام') . ' بتاريخ ' . $dup->created_at->format('Y/m/d') . '.',
            ]));
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
            'color' => ['nullable', 'string', 'max:60'],
            'sale_date' => ['nullable', 'date'],
            'dealer' => ['nullable', 'string', 'max:100'],
            'pay_method' => ['required', Rule::in(['كاش', 'تقسيط'])],
            'finance_entity' => ['nullable', 'string', 'max:120'],
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
