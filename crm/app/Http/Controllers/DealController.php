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
        $this->ensureCanSee($customer);

        return view('deals.form', [
            'customer' => $customer,
            'deal' => new Deal(['pay_method' => 'تقسيط', 'status' => 'مفتوحة', 'vehicle' => $customer->interest, 'interest_type' => 'flat', 'months' => 12]),
        ]);
    }

    public function store(Request $request, Customer $customer)
    {
        $this->ensureCanSee($customer);
        $data = $this->validated($request);
        $deal = $this->service->save($customer, $data, null, $request->user());

        Activity::log('deal.create', $deal, 'إضافة صفقة للعميل ' . $customer->name);
        Activity::customer($customer, 'deal', 'تمت إضافة صفقة: ' . ($deal->vehicle ?: 'غير محدد') . ' — ' . $deal->pay_method);

        return redirect()->route('customers.show', $customer)->withFragment('deals')->with('success', 'تم حفظ الصفقة وجدول الأقساط.');
    }

    public function edit(Deal $deal)
    {
        $this->ensureCanSee($deal->customer);

        return view('deals.form', ['customer' => $deal->customer, 'deal' => $deal]);
    }

    public function update(Request $request, Deal $deal)
    {
        $this->ensureCanSee($deal->customer);
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
        $this->ensureCanSee($deal->customer);
        $deal->load(['installments', 'customer', 'payments']);
        Activity::log('customer.view', $deal, 'طباعة جدول أقساط ' . $deal->customer->name);

        return view('deals.schedule', ['deal' => $deal]);
    }

    public function addPayment(Request $request, Deal $deal)
    {
        $this->ensureCanSee($deal->customer);
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
