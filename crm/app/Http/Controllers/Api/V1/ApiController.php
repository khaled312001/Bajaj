<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerController;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Lookup;
use App\Models\User;
use App\Services\DealService;
use App\Services\ReportService;
use App\Support\Activity;
use App\Support\InstallmentCalculator;
use App\Support\ViewGuard;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** JSON API (token auth). Same permission rules as the web app: agents never get export or admin endpoints. */
class ApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string', 'max:80'],
        ]);
        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])->first();

        if (! $user || $user->isLocked() || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            Activity::log('login_failed', null, 'فشل دخول API: ' . $data['username']);
            throw ValidationException::withMessages(['username' => 'بيانات الدخول غير صحيحة.']);
        }

        $token = $user->createToken($data['device_name'] ?? 'api', $user->isAdmin() ? ['*'] : ['agent'], now()->addDays(14));
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        Activity::log('login', $user, 'تسجيل دخول عبر API', userId: $user->id);

        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at, 'user' => $this->user($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->user($request->user()));
    }

    public function lookups(): JsonResponse
    {
        return response()->json(collect(Lookup::TYPES)->map(fn ($label, $type) => Lookup::list($type))->all() + ['statuses' => Customer::STATUSES, 'pay_methods' => Deal::PAY_METHODS]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $u = $request->user();
        $c = Customer::query()->visibleTo($u);

        return response()->json([
            'customers' => (clone $c)->count(),
            'new_this_month' => (clone $c)->where('customers.created_at', '>=', now()->startOfMonth())->count(),
            'followups_today' => Followup::visibleTo($u)->pending()->whereDate('due_date', today())->count(),
            'followups_overdue' => Followup::visibleTo($u)->pending()->whereDate('due_date', '<', today())->count(),
        ]);
    }

    public function calculator(Request $request): JsonResponse
    {
        $d = $request->validate([
            'price' => ['required', 'numeric', 'min:0'], 'down' => ['nullable', 'numeric', 'min:0'], 'months' => ['required', 'integer', 'min:1', 'max:120'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'], 'type' => ['nullable', 'in:flat,reducing,table'], 'fees' => ['nullable', 'numeric', 'min:0'],
            'fees_type' => ['nullable', 'in:fixed,percent'], 'fees_financed' => ['nullable', 'boolean'], 'first_due' => ['nullable', 'date'],
        ]);

        return response()->json(InstallmentCalculator::calculate($d));
    }

    /* ------------------------------------------------------------ customers */

    public function customers(Request $request): JsonResponse
    {
        $p = app(CustomerController::class)->query($request)->latest('customers.created_at')->paginate(min(50, (int) $request->query('per_page', 20)));

        return response()->json($p->through(fn ($c) => $this->customer($c, $request->user()))->toArray());
    }

    public function showCustomer(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureCanSee($customer);
        ViewGuard::record($request->user(), $customer);
        $customer->load(['creator:id,name', 'assignee:id,name']);

        return response()->json($this->customer($customer, $request->user()) + [
            'followups' => $customer->followups()->latest('due_date')->limit(30)->get(['id', 'reason', 'due_date', 'due_time', 'status', 'notes', 'outcome']),
        ]);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $this->customerData($request);
        if ($dup = Customer::with('creator:id,name')->where('phone', $data['phone'])->orWhere('alt_phone', $data['phone'])->first()) {
            return response()->json(['message' => 'الرقم مسجل بالفعل.', 'code' => $dup->code, 'entered_by' => $dup->creator?->name], 409);
        }
        $u = $request->user();
        $customer = Customer::create($data + ['created_by' => $u->id, 'assigned_to' => $u->id]);
        Activity::log('customer.create', $customer, 'إضافة العميل ' . $customer->name . ' (API)');
        Activity::customer($customer, 'created', 'تم إدخال العميل بواسطة ' . $u->name . ' عبر التطبيق');

        return response()->json($this->customer($customer, $u), 201);
    }

    public function updateCustomer(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureCanSee($customer);
        $customer->update($this->customerData($request));
        Activity::log('customer.update', $customer, 'تعديل العميل ' . $customer->name . ' (API)');
        Activity::customer($customer, 'updated', 'تعديل البيانات بواسطة ' . $request->user()->name);

        return response()->json($this->customer($customer->fresh(), $request->user()));
    }

    public function deals(Customer $customer): JsonResponse
    {
        $this->ensureCanSee($customer);

        return response()->json($customer->deals()->with('installments')->latest()->get());
    }

    public function storeDeal(Request $request, Customer $customer, DealService $service): JsonResponse
    {
        $this->ensureCanSee($customer);
        $rules = CustomerController::dealRules();
        unset($rules['deal_status'], $rules['deal_notes']);
        $data = $request->validate($rules + ['status' => ['nullable', Rule::in(Customer::STATUSES)], 'notes' => ['nullable', 'string', 'max:2000']]);
        $deal = $service->save($customer, $data, null, $request->user());
        Activity::log('deal.create', $deal, 'إضافة صفقة (API)');
        Activity::customer($customer, 'deal', 'تمت إضافة صفقة عبر التطبيق: ' . ($deal->vehicle ?: 'غير محدد'));

        return response()->json($deal->load('installments'), 201);
    }

    /* ------------------------------------------------------------ follow-ups */

    public function followups(Request $request): JsonResponse
    {
        $q = Followup::visibleTo($request->user())->with('customer:id,name,phone,code')->orderBy('due_date');
        $status = $request->query('status', 'pending');
        $status === 'all' ? null : $q->where('status', $status);
        if ($request->query('when') === 'today') {
            $q->whereDate('due_date', today());
        } elseif ($request->query('when') === 'overdue') {
            $q->whereDate('due_date', '<', today());
        }

        return response()->json($q->paginate(30)->toArray());
    }

    public function storeFollowup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'], 'reason' => ['required', 'string', 'max:120'],
            'due_date' => ['required', 'date', 'after_or_equal:today'], 'due_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'], 'priority' => ['nullable', 'in:normal,high'],
        ]);
        $customer = Customer::findOrFail($data['customer_id']);
        $this->ensureCanSee($customer);
        $f = Followup::create($data + ['created_by' => $request->user()->id]);
        $f->forceFill(['assigned_to' => $request->user()->id])->save();
        Activity::log('followup.create', $f, 'متابعة جديدة (API) للعميل ' . $customer->name);
        Activity::customer($customer, 'followup', "جدولة متابعة ({$f->reason}) بتاريخ " . $f->due_date->format('Y/m/d'));

        return response()->json($f, 201);
    }

    public function completeFollowup(Request $request, Followup $followup): JsonResponse
    {
        $u = $request->user();
        abort_unless($u->isAdmin() || $followup->assigned_to === $u->id || $followup->created_by === $u->id, 403);
        $data = $request->validate(['outcome' => ['required', 'string', 'max:2000']]);
        $followup->update(['status' => 'done', 'outcome' => $data['outcome'], 'completed_at' => now(), 'completed_by' => $u->id]);
        Activity::log('followup.done', $followup, 'إتمام متابعة (API)');
        Activity::customer($followup->customer, 'followup_done', 'تمت المتابعة: ' . $data['outcome']);

        return response()->json($followup);
    }

    /* ------------------------------------------------------------ admin */

    public function users(): JsonResponse
    {
        return response()->json(User::orderBy('name')->get(['id', 'name', 'username', 'role', 'is_active', 'last_login_at']));
    }

    public function report(Request $request, string $key, ReportService $reports): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : now();

        return response()->json($reports->build($key, $from, $to, $request->integer('employee') ?: null));
    }

    /* ------------------------------------------------------------ helpers */

    private function user(User $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->role, 'role_label' => $u->role_label];
    }

    private function customer(Customer $c, User $viewer): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'phone' => $c->phone, 'alt_phone' => $c->alt_phone,
            'whatsapp' => $c->whatsapp, 'job' => $c->job, 'governorate' => $c->governorate, 'district' => $c->district,
            'address' => $c->address, 'channel' => $c->channel, 'interest' => $c->interest, 'status' => $c->status, 'notes' => $c->notes,
            'nat_id' => $viewer->isAdmin() ? $c->nat_id : $c->maskedNatId(),
            'entered_by' => $c->creator?->name, 'assigned_to' => $c->assignee?->name, 'created_at' => $c->created_at,
            'debt' => isset($c->debt) ? (float) $c->debt : null,
        ];
    }

    private function customerData(Request $request): array
    {
        $request->merge([
            'phone' => Customer::normalizePhone($request->input('phone')),
            'alt_phone' => Customer::normalizePhone($request->input('alt_phone')),
            'whatsapp' => Customer::normalizePhone($request->input('whatsapp')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'nat_id' => ['nullable', 'digits:14'], 'job' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^\d{8,15}$/'], 'alt_phone' => ['nullable', 'regex:/^\d{8,15}$/'], 'whatsapp' => ['nullable', 'regex:/^\d{8,15}$/'],
            'governorate' => ['nullable', 'string', 'max:100'], 'district' => ['nullable', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:250'],
            'channel' => ['nullable', 'string', 'max:100'], 'interest' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(Customer::STATUSES)], 'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
