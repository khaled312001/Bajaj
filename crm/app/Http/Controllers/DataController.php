<?php

namespace App\Http\Controllers;

use App\Exports\Schemas;
use App\Exports\SheetWriter;
use App\Exports\TemplateBuilder;
use App\Models\Customer;
use App\Models\Followup;
use App\Models\Import;
use App\Models\Payment;
use App\Services\ImportService;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Admin-only: Excel templates, import and data export. */
class DataController extends Controller
{
    public function index()
    {
        return view('data.index', [
            'types' => Schemas::TYPES,
            'imports' => Import::with('user:id,name')->latest()->limit(15)->get(),
        ]);
    }

    public function template(string $type)
    {
        abort_unless(isset(Schemas::TYPES[$type]), 404);
        Activity::log('export', null, 'تنزيل نموذج ' . Schemas::TYPES[$type]['title']);

        return SheetWriter::stream(TemplateBuilder::template($type), "نموذج-{$type}.xlsx");
    }

    public function upload(Request $request, ImportService $service)
    {
        $request->validate([
            'type' => ['required', 'in:' . implode(',', array_keys(Schemas::TYPES))],
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
            'mode' => ['nullable', 'in:skip,update'],
        ], ['file.mimes' => 'الملف يجب أن يكون Excel (xlsx / xls) أو CSV.', 'file.max' => 'الحد الأقصى لحجم الملف 10 ميجابايت.']);

        $file = $request->file('file');
        $path = $file->storeAs('imports', now()->format('Ymd_His_') . bin2hex(random_bytes(4)) . '.' . $file->getClientOriginalExtension(), 'local');

        try {
            $parsed = $service->parse($request->input('type'), Storage::disk('local')->path($path));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'تعذر قراءة الملف: تأكد أنه ملف Excel سليم.']);
        }

        if ($parsed['missing']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'الملف لا يحتوي الأعمدة الإلزامية: ' . implode('، ', $parsed['missing']) . '. حمّل النموذج الثابت واستخدمه.']);
        }
        if (! $parsed['rows']) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'الملف لا يحتوي بيانات.']);
        }

        $result = $service->validate($request->input('type'), $parsed['rows']);
        $import = Import::create([
            'user_id' => $request->user()->id, 'type' => $request->input('type'), 'filename' => $file->getClientOriginalName(),
            'path' => $path, 'total' => count($parsed['rows']), 'valid' => count($result['clean']),
            'failed' => count(array_unique(array_column($result['errors'], 'row'))), 'errors' => array_slice($result['errors'], 0, 500),
        ]);

        return redirect()->route('data.preview', $import)->with('mode', $request->input('mode', 'skip'))->with('unknown', $parsed['unknown']);
    }

    public function preview(Import $import)
    {
        abort_if($import->status !== 'pending', 404);

        return view('data.preview', [
            'import' => $import, 'meta' => Schemas::TYPES[$import->type],
            'mode' => session('mode', 'skip'), 'unknown' => session('unknown', []),
        ]);
    }

    public function confirm(Request $request, Import $import, ImportService $service)
    {
        abort_if($import->status !== 'pending', 404);
        $mode = $request->input('mode') === 'update' ? 'update' : 'skip';
        @set_time_limit(300);
        $stats = $service->commit($import, $mode, $request->user());
        Storage::disk('local')->delete($import->path);

        return redirect()->route('data.result', $import)->with('stats', $stats);
    }

    public function cancel(Import $import)
    {
        Storage::disk('local')->delete($import->path);
        $import->update(['status' => 'cancelled']);

        return redirect()->route('data.index')->with('success', 'تم إلغاء الاستيراد.');
    }

    public function result(Import $import)
    {
        return view('data.result', ['import' => $import->fresh('user'), 'meta' => Schemas::TYPES[$import->type]]);
    }

    public function errors(Import $import)
    {
        $rows = array_map(fn ($e) => [$e['row'], $e['message']], $import->errors ?? []);

        return SheetWriter::download("اخطاء-الاستيراد-{$import->id}.xlsx", [[
            'title' => 'الأخطاء', 'headers' => ['رقم الصف في الملف', 'سبب الخطأ'], 'rows' => $rows, 'widths' => [18, 80],
        ]]);
    }

    /* ----------------------------------------------------------------- exports */

    public function exportCustomers(Request $request)
    {
        $q = app(CustomerController::class)->query($request)->with(['deals']);
        $rows = (function () use ($q) {
            foreach ($q->lazyById(500, 'customers.id', 'id') as $c) {
                $base = [
                    'code' => $c->code, 'name' => $c->name, 'nat_id' => $c->nat_id, 'job' => $c->job, 'phone' => $c->phone,
                    'alt_phone' => $c->alt_phone, 'whatsapp' => $c->whatsapp, 'governorate' => $c->governorate,
                    'district' => $c->district, 'address' => $c->address, 'channel' => $c->channel, 'interest' => $c->interest,
                    'status' => $c->status, 'assigned_username' => $c->assignee?->username ?? null, 'notes' => $c->notes,
                    'age' => $c->age, 'seriousness' => $c->seriousness, 'previous_vehicle' => $c->previous_vehicle, 'branch' => $c->branch, 'loss_reason' => $c->loss_reason,
                ];
                $deals = $c->deals;
                if ($deals->isEmpty()) {
                    yield $base;
                    continue;
                }
                foreach ($deals as $d) {
                    yield $base + [
                        'vehicle' => $d->vehicle, 'pay_method' => $d->pay_method, 'finance_entity' => $d->finance_entity,
                        'total_price' => $d->total_price, 'down_payment' => $d->down_payment, 'months' => $d->months ?: null,
                        'interest_rate' => $d->interest_rate ?: null, 'monthly_installment' => $d->monthly_installment ?: null,
                        'first_due_date' => $d->first_due_date?->toDateString(), 'delivery_date' => $d->delivery_date?->toDateString(),
                        'chassis' => $d->chassis, 'motor' => $d->motor, 'model' => $d->model,
                        'color' => $d->color, 'sale_date' => $d->sale_date?->toDateString(), 'dealer' => $d->dealer, 'mobaya_no' => $d->mobaya_no,
                    ];
                }
            }
        })();

        Activity::log('export', null, 'تصدير العملاء إلى Excel', ['filters' => $request->query()]);

        return SheetWriter::stream(TemplateBuilder::withData('customers', $rows), 'العملاء-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportFollowups(Request $request)
    {
        $q = Followup::with(['customer:id,code,name', 'assignee:id,username'])->where('status', 'pending')->orderBy('due_date');
        $rows = $q->get()->map(fn ($f) => [
            'customer' => $f->customer?->code, 'reason' => $f->reason, 'due_date' => $f->due_date->toDateString(),
            'due_time' => $f->due_time ? substr($f->due_time, 0, 5) : null, 'priority' => $f->priority === 'high' ? 'عالية' : 'عادية',
            'assigned_username' => $f->assignee?->username, 'notes' => $f->notes,
        ]);
        Activity::log('export', null, 'تصدير المتابعات إلى Excel');

        return SheetWriter::stream(TemplateBuilder::withData('followups', $rows), 'المتابعات-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportPayments(Request $request)
    {
        $rows = Payment::with(['deal.customer:id,code'])->orderBy('paid_on')->get()->map(fn ($p) => [
            'customer' => $p->deal?->customer?->code, 'chassis' => $p->deal?->chassis, 'amount' => $p->amount,
            'paid_on' => $p->paid_on->toDateString(), 'method' => $p->method, 'note' => $p->note,
        ]);
        Activity::log('export', null, 'تصدير الدفعات إلى Excel');

        return SheetWriter::stream(TemplateBuilder::withData('payments', $rows), 'الدفعات-' . now()->format('Y-m-d') . '.xlsx');
    }
}
