<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\DocumentLog;
use App\Models\Payment;
use App\Services\DocumentService;
use App\Services\PdfService;
use App\Support\Activity;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = DocumentLog::with('user:id,name')->latest('printed_at');
        if (! $user->isAdmin()) {
            $q->where('user_id', $user->id);
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('customer_name', 'like', "%{$s}%")->orWhere('product_name', 'like', "%{$s}%")->orWhere('legacy_ref', 'like', "%{$s}%"));
        }
        if ($f = $request->query('from')) {
            $q->whereDate('printed_at', '>=', $f);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('printed_at', '<=', $to);
        }
        if ($u = $request->query('user')) {
            $user->isAdmin() && $q->where('user_id', (int) $u);
        }

        return view('documents.index', [
            'types' => array_filter(DocumentService::types(), fn ($t, $k) => DocumentService::allowed($user, $k), ARRAY_FILTER_USE_BOTH),
            'logs' => $q->paginate(20)->withQueryString(),
            'allTypes' => DocumentService::types(),
            'staff' => $user->isAdmin() ? \App\Models\User::orderBy('name')->get(['id', 'name']) : collect(),
            'deal' => $request->query('deal'), 'customerId' => $request->query('customer'), 'payment' => $request->query('payment'),
        ]);
    }

    public function create(Request $request, string $type)
    {
        $def = $this->def($request, $type);
        [$deal, $customer, $payment] = $this->context($request);
        $customerDeals = $this->customerDeals($customer);
        // a customer with exactly one vehicle deal: use it straight away (chassis / motor / model filled in)
        if (! $deal && $customerDeals->count() === 1 && in_array('chassis', array_column($def['fields'], 0), true)) {
            $deal = Deal::with('customer')->find($customerDeals->first()->id);
        }
        $values = array_merge(DocumentService::prefill($deal, $customer, $payment), array_filter($request->only(array_column($def['fields'], 0)), fn ($v) => $v !== null));
        foreach ($def['fields'] as $f) {
            if (! isset($values[$f[0]]) && isset($f[3])) {
                $values[$f[0]] = $f[3];
            }
        }
        // a receipt's payment method is how this amount was paid, not the deal's cash / installment type
        if ($type === 'receipt' && ! in_array($values['pay_method'] ?? '', DocumentService::RECEIPT_METHODS, true) && (! $payment || $values['pay_method'] === 'نقدي')) {
            $values['pay_method'] = 'كاش';
        }

        return view('documents.form', ['type' => $type, 'def' => $def, 'values' => $values, 'deal' => $deal, 'customer' => $customer, 'payment' => $payment, 'log' => null, 'customerDeals' => $customerDeals]);
    }

    private function customerDeals(?Customer $customer)
    {
        return $customer ? $customer->deals()->with('vehicleUnit:id,deal_id,chassis,motor')->latest('id')->get(['id', 'customer_id', 'vehicle', 'model', 'chassis', 'motor', 'sale_date', 'pay_method'])
            ->filter(fn ($d) => $d->vehicle || $d->chassis || $d->vehicleUnit)->values() : collect();
    }

    public function edit(Request $request, DocumentLog $document)
    {
        $this->authorizeLog($request, $document);
        $def = $this->def($request, $document->type);

        return view('documents.form', ['type' => $document->type, 'def' => $def, 'values' => $document->data(), 'deal' => $document->deal, 'customer' => $document->customer, 'payment' => null, 'log' => $document, 'customerDeals' => $this->customerDeals($document->customer)]);
    }

    public function store(Request $request, string $type)
    {
        $def = $this->def($request, $type);
        $rules = ['deal_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer']];
        foreach ($def['fields'] as $f) {
            $rules[$f[0]] = in_array($f[2], ['money', 'number'], true) ? ['nullable', 'numeric', 'min:0'] : ($f[2] === 'date' ? ['nullable', 'date'] : ['nullable', 'string', 'max:' . ($f[2] === 'textarea' ? 2000 : 300)]);
        }
        $in = $request->validate($rules);
        $values = [];
        foreach ($def['fields'] as $f) {
            $values[$f[0]] = $f[2] === 'check' ? $request->boolean($f[0]) : ($in[$f[0]] ?? null);
        }

        $deal = ! empty($in['deal_id']) ? Deal::with('customer')->find($in['deal_id']) : null;
        $customer = $deal?->customer ?? (! empty($in['customer_id']) ? Customer::find($in['customer_id']) : null);
        if ($customer) {
            abort_unless($customer->editableBy($request->user()), 403);
        }
        if ($type === 'statement' && ! $deal) {
            return back()->withInput()->withErrors(['deal_id' => 'كشف الأقساط يتطلب اختيار صفقة من ملف العميل.']);
        }

        $log = new DocumentLog([
            'type' => $type, 'serial' => DocumentService::nextSerial($type), 'customer_id' => $customer?->id, 'deal_id' => $deal?->id,
            'user_id' => $request->user()->id, 'customer_name' => $values['customer_name'] ?? ($values['to_name'] ?? null),
            'product_name' => $values['product'] ?? ($values['goods'] ?? null), 'amount' => $values['price'] ?? ($values['amount'] ?? null), 'printed_at' => now(),
        ]);
        $log->setData($values);
        $log->save();
        Activity::log('document', $log, 'إصدار ' . $def['title'] . ' ' . DocumentService::docNo($type, $log->serial) . ($customer ? ' — ' . $customer->code : ''));
        if ($customer) {
            Activity::customer($customer, 'document', 'إصدار ' . $def['title'] . ' ' . DocumentService::docNo($type, $log->serial));
        }

        return redirect()->route('documents.show', $log);
    }

    public function show(Request $request, DocumentLog $document)
    {
        $this->authorizeLog($request, $document);

        return view('documents.show', ['log' => $document, 'def' => DocumentService::type($document->type), 'docNo' => DocumentService::docNo($document->type, $document->serial, $document->legacy_ref)]);
    }

    public function pdf(Request $request, DocumentLog $document)
    {
        $this->authorizeLog($request, $document);
        $def = DocumentService::type($document->type);
        abort_unless($def, 404);
        $docNo = DocumentService::docNo($document->type, $document->serial, $document->legacy_ref);
        $deal = $document->deal_id ? Deal::with(['installments', 'customer'])->find($document->deal_id) : null;
        $d = $document->data();
        if ($document->legacy_ref && ! $d) {
            $d = ['customer_name' => $document->customer_name, 'product' => $document->product_name, 'price' => $document->amount];
        }

        $reefy = $document->type === 'finance_receipt' && str_contains((string) ($d['entity'] ?? ''), 'ريفي');
        $traffic = in_array($document->type, ['mobaya', 'renewal', 'clearance'], true);
        $pdf = PdfService::render($reefy ? 'pdf.reefy_receipt' : ($traffic ? 'pdf.traffic' : 'pdf.document'), [
            'type' => $document->type, 'def' => $def, 'd' => $d, 'deal' => $deal, 'docNo' => $docNo,
            'docDate' => $document->printed_at->format('Y/m/d'), 'zarad' => true,
        ], $def['title'] . ' ' . $docNo, false, $def['title'] . ' — ' . $docNo, match (true) {
            $reefy => ['header' => resource_path('pdf/reefy-header.png'), 'footer' => resource_path('pdf/reefy-footer.png')],
            $traffic => ['paper' => true],
            // the receipt keeps its lines close together in the upper part of the sheet instead of spreading to the footer
            $document->type === 'receipt' => ['company' => true, 'max_gap' => 1.2],
            default => ['company' => true],
        });
        Activity::log('document', $document, 'طباعة PDF ' . $docNo);

        return PdfService::response($pdf, $def['title'] . '-' . $docNo . '.pdf', ! $request->boolean('download'));
    }

    public function destroy(DocumentLog $document)
    {
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'تم حذف السجل.');
    }

    /* ---- helpers ---- */

    private function def(Request $request, string $type): array
    {
        $def = DocumentService::type($type);
        abort_unless($def && DocumentService::allowed($request->user(), $type), 404);

        return $def;
    }

    private function authorizeLog(Request $request, DocumentLog $log): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($log->user_id === $user->id && ($log->legacy_ref || DocumentService::allowed($user, $log->type))), 403);
    }

    private function context(Request $request): array
    {
        $user = $request->user();
        $deal = $request->query('deal') ? Deal::with('customer')->find($request->query('deal')) : null;
        $payment = $request->query('payment') ? Payment::with('deal.customer')->find($request->query('payment')) : null;
        $customer = $request->query('customer') ? Customer::find($request->query('customer')) : null;
        $customer ??= $deal?->customer ?? $payment?->deal?->customer;
        if ($customer && ! $customer->editableBy($user)) {
            abort(403);
        }

        return [$deal ?? $payment?->deal, $customer, $payment];
    }
}
