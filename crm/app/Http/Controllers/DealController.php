<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Payment;
use App\Services\DealService;
use App\Support\Activity;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function __construct(private DealService $service)
    {
    }

    public function create(Customer $customer)
    {
        $this->ensureCanEdit($customer);

        return view('deals.form', [
            'customer' => $customer,
            'deal' => new Deal(['pay_method' => 'تقسيط', 'status' => 'مفتوحة', 'vehicle' => $customer->interest, 'interest_type' => 'flat', 'months' => 12]),
        ]);
    }

    public function store(Request $request, Customer $customer)
    {
        $this->ensureCanEdit($customer);
        $data = $this->validated($request);
        $deal = $this->service->save($customer, $data, null, $request->user());

        Activity::log('deal.create', $deal, 'إضافة صفقة للعميل ' . $customer->name);
        Activity::customer($customer, 'deal', 'تمت إضافة صفقة: ' . ($deal->vehicle ?: 'غير محدد') . ' — ' . $deal->pay_method);

        return redirect()->route('customers.show', $customer)->withFragment('deals')->with('success', 'تم حفظ الصفقة وجدول الأقساط.');
    }

    public function edit(Deal $deal)
    {
        $this->ensureCanEdit($deal->customer);

        return view('deals.form', ['customer' => $deal->customer, 'deal' => $deal]);
    }

    public function update(Request $request, Deal $deal)
    {
        $this->ensureCanEdit($deal->customer);
        $data = $this->validated($request);
        $this->service->save($deal->customer, $data, $deal, $request->user());

        Activity::log('deal.update', $deal, 'تعديل صفقة العميل ' . $deal->customer->name);
        Activity::customer($deal->customer, 'deal', 'تم تعديل الصفقة: ' . ($deal->vehicle ?: 'غير محدد'));

        return redirect()->route('customers.show', $deal->customer)->withFragment('deals')->with('success', 'تم تحديث الصفقة.');
    }

    public function destroy(Deal $deal)
    {
        $customer = $deal->customer;
        $deal->delete();
        Activity::log('deal.delete', $deal, 'حذف صفقة العميل ' . $customer->name);
        Activity::customer($customer, 'deal', 'تم حذف صفقة: ' . ($deal->vehicle ?: 'غير محدد'));

        return redirect()->route('customers.show', $customer)->with('success', 'تم حذف الصفقة.');
    }

    public function schedule(Deal $deal)
    {
        $deal->load(['installments', 'customer', 'payments']);
        Activity::log('customer.view', $deal, 'طباعة جدول أقساط ' . $deal->customer->name);

        return view('deals.schedule', ['deal' => $deal]);
    }

    public function addPayment(Request $request, Deal $deal)
    {
        $this->ensureCanEdit($deal->customer);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['paid_on.before_or_equal' => 'لا يمكن تسجيل دفعة بتاريخ مستقبلي.']);

        $payment = $this->service->addPayment($deal, (float) $data['amount'], $data['paid_on'], $data['method'] ?? null, $data['note'] ?? null, $request->user());
        Activity::log('payment.create', $payment, 'تسجيل دفعة ' . number_format($payment->amount, 2) . ' للعميل ' . $deal->customer->name);
        Activity::customer($deal->customer, 'payment', 'تم تسجيل دفعة بقيمة ' . number_format($payment->amount, 2) . ' ج.م');

        return back()->with('success', 'تم تسجيل الدفعة.');
    }

    /** The finance entity's transfer for this deal: deposited (frozen), then collected into our account. */
    public function entityCollection(Request $request, Deal $deal)
    {
        $this->ensureCanEdit($deal->customer);
        abort_unless($deal->isEntityFinanced(), 404);
        $data = $request->validate([
            'entity_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'entity_ref' => ['nullable', 'string', 'max:80'],
            'entity_deposited_at' => ['nullable', 'date', 'before_or_equal:today'],
            'entity_collected_at' => ['nullable', 'date', 'before_or_equal:today'],
            'entity_channel' => ['nullable', 'string', 'max:40'],
            'entity_account' => ['nullable', 'string', 'max:120'],
            'entity_note' => ['nullable', 'string', 'max:250'],
        ], ['entity_deposited_at.before_or_equal' => 'تاريخ الإيداع لا يكون في المستقبل.', 'entity_collected_at.before_or_equal' => 'تاريخ التحصيل لا يكون في المستقبل.']);

        $this->service->saveEntityCollection($deal, $data, $request->user());
        $what = $deal->entity_collected_at ? 'تحصيل ' . number_format($deal->entity_amount, 2) . ' ج.م من ' . $deal->finance_entity
            : ($deal->entity_deposited_at ? 'إيداع ' . $deal->finance_entity . ' (مجمد حتى ' . $deal->entity_due_date->format('Y/m/d') . ')' : 'بيانات تحويل ' . $deal->finance_entity);
        Activity::log('payment.create', $deal, $what . ' — العميل ' . $deal->customer->name);
        Activity::customer($deal->customer, 'payment', 'تم تسجيل ' . $what . ($deal->entity_ref ? ' — مرجع ' . $deal->entity_ref : ''));

        return back()->with('success', $deal->entity_collected_at ? 'تم تسجيل التحصيل من ' . $deal->finance_entity . '.' : 'تم حفظ بيانات التحويل.');
    }

    public function deletePayment(Payment $payment)
    {
        $deal = $payment->deal;
        $this->service->deletePayment($payment);
        Activity::log('payment.delete', $payment, 'حذف دفعة ' . number_format($payment->amount, 2) . ' للعميل ' . $deal->customer->name);

        return back()->with('success', 'تم حذف الدفعة وإعادة احتساب الأقساط.');
    }

    private function validated(Request $request): array
    {
        $rules = CustomerController::dealRules();
        unset($rules['deal_status'], $rules['deal_notes']);
        $rules['status'] = ['required', 'in:' . implode(',', Customer::STATUSES)];
        $rules['notes'] = ['nullable', 'string', 'max:2000'];

        return $request->validate($rules);
    }
}
