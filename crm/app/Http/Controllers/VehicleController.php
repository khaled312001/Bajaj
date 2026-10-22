<?php

namespace App\Http\Controllers;

use App\Exports\SheetWriter;
use App\Models\Vehicle;
use App\Models\VehicleBatch;
use App\Support\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $isAdmin = $request->user()->isAdmin();
        $q = $this->query($request);
        $base = Vehicle::query();
        if (! $isAdmin) {
            $base->where('status', '!=', 'sold');
        }

        return view('vehicles.index', [
            'batches' => $isAdmin ? VehicleBatch::withCount(['vehicles', 'vehicles as sold_count' => fn ($w) => $w->where('status', 'sold')])->latest('received_at')->latest('id')->limit(12)->get() : collect(),
            'batch' => $isAdmin && $request->query('batch') ? VehicleBatch::withCount('vehicles')->find((int) $request->query('batch')) : null,
            'vehicles' => (clone $q)->with(['deal.customer:id,name,code', 'batch:id,permit_no,received_at'])->orderByRaw("FIELD(status,'in_stock','reserved','sold')")->latest('arrived_at')->latest('id')->paginate(25)->withQueryString(),
            'stats' => [
                'in_stock' => (clone $base)->where('status', 'in_stock')->count(),
                'reserved' => (clone $base)->where('status', 'reserved')->count(),
                'sold' => $isAdmin ? Vehicle::where('status', 'sold')->count() : null,
                'stock_cost' => $isAdmin ? (float) Vehicle::where('status', 'in_stock')->selectRaw('SUM(cost_price+transport_cost+other_cost) c')->value('c') : null,
            ],
            'byType' => (clone $base)->where('status', 'in_stock')->selectRaw("COALESCE(NULLIF(type,''),'غير محدد') t, COUNT(*) c")->groupBy('t')->orderByDesc('c')->pluck('c', 't'),
            'types' => Vehicle::whereNotNull('type')->where('type', '!=', '')->distinct()->orderBy('type')->pluck('type'),
            'sources' => Vehicle::whereNotNull('source_store')->where('source_store', '!=', '')->distinct()->pluck('source_store'),
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['chassis'] = Vehicle::cleanSerial($data['chassis']);
        if (Vehicle::withTrashed()->where('chassis', $data['chassis'])->exists()) {
            return back()->withInput()->withErrors(['chassis' => 'رقم الشاسيه مسجل بالفعل.']);
        }
        $v = Vehicle::create($data + ['created_by' => $request->user()->id]);
        Activity::log('vehicle', $v, 'إضافة مركبة للمخزن ' . $v->chassis);

        return back()->with('success', 'تمت إضافة المركبة للمخزن.');
    }

    /** A receiving permit (إذن صرف): many chassis/motor pairs at once (one per line) sharing type/color/store + the permit data. */
    public function bulkStore(Request $request)
    {
        $shared = $request->validate([
            'lines' => ['required', 'string', 'max:60000'], 'type' => ['nullable', 'string', 'max:120'], 'model_year' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:60'], 'source_store' => ['nullable', 'string', 'max:120'], 'branch_store' => ['nullable', 'string', 'max:120'],
            'cost_price' => ['nullable', 'numeric', 'min:0'], 'transport_cost' => ['nullable', 'numeric', 'min:0'], 'arrived_at' => ['nullable', 'date'],
        ] + self::BATCH_RULES, ['lines.required' => 'اكتب رقم شاسيه واحد على الأقل.']);
        $lines = preg_split('/\R/u', $shared['lines']);
        $batchData = $this->batchAttrs(array_intersect_key($shared, self::BATCH_RULES));
        $shared = array_diff_key($shared, self::BATCH_RULES + ['lines' => 1]);
        $shared['cost_price'] ??= 0;
        $shared['transport_cost'] ??= 0;
        $shared['arrived_at'] ??= today()->toDateString();
        $batch = VehicleBatch::create($batchData + ['received_at' => $shared['arrived_at'], 'source' => $shared['source_store'] ?? null, 'created_by' => $request->user()->id]);
        $shared['batch_id'] = $batch->id;

        $added = [];
        $skipped = [];
        $seen = [];
        foreach ($lines as $line) {
            $parts = preg_split('/[\s,;\t|]+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY);
            if (! $parts) {
                continue;
            }
            $chassis = Vehicle::cleanSerial(mb_substr($parts[0], 0, 60));
            $motor = isset($parts[1]) ? Vehicle::cleanSerial(mb_substr($parts[1], 0, 60)) : null;
            if (isset($seen[$chassis]) || Vehicle::withTrashed()->where('chassis', $chassis)->exists()) {
                $skipped[] = $chassis;
                continue;
            }
            $seen[$chassis] = true;
            $added[] = Vehicle::create($shared + ['chassis' => $chassis, 'motor' => $motor, 'status' => 'in_stock', 'created_by' => $request->user()->id]);
        }
        if (! $added) {
            $batch->delete();
        } else {
            Activity::log('vehicle', null, 'إذن استلام ' . $batch->label() . ': إضافة ' . count($added) . ' مركبة للمخزن');
        }

        $msg = 'تمت إضافة ' . count($added) . ' مركبة للمخزن' . ($added && $batch->permit_no ? ' على الإذن ' . $batch->permit_no : '') . '.';
        if ($skipped) {
            $msg .= ' تم تخطي ' . count($skipped) . ' شاسيه مسجل بالفعل أو مكرر: ' . implode('، ', array_slice($skipped, 0, 15)) . (count($skipped) > 15 ? ' …' : '');
        }

        return back()->with($skipped ? 'warning' : 'success', $msg);
    }

    public function updateBatch(Request $request, VehicleBatch $batch)
    {
        $batch->update($this->batchAttrs($request->validate(self::BATCH_RULES + ['received_at' => ['nullable', 'date'], 'source' => ['nullable', 'string', 'max:120']])));
        Activity::log('vehicle', null, 'تعديل بيانات إذن الاستلام ' . $batch->label());

        return back()->with('success', 'تم حفظ بيانات الإذن.');
    }

    /** Chassis search in the stock, for the document forms (fills motor / type / color automatically). */
    public function lookup(Request $request)
    {
        $u = $request->user();
        abort_unless($u->isAdmin() || $u->allows('documents', 'create') || $u->allows('legal_documents', 'create') || $u->allows('vehicles'), 403);
        $s = Vehicle::cleanSerial((string) $request->query('q'));
        if (mb_strlen($s) < 3) {
            return response()->json([]);
        }

        return response()->json(Vehicle::where(fn ($w) => $w->where('chassis', 'like', "%{$s}%")->orWhere('motor', 'like', "%{$s}%"))
            ->orderByRaw("FIELD(status,'in_stock','reserved','sold')")->limit(10)->get(['chassis', 'motor', 'type', 'model_year', 'color', 'status'])
            ->map(fn ($v) => ['chassis' => $v->chassis, 'motor' => $v->motor, 'product' => $v->type, 'model' => $v->model_year, 'color' => $v->color, 'status' => $v->status_label]));
    }

    private function batchAttrs(array $in): array
    {
        $in['notes'] = $in['batch_notes'] ?? null;
        unset($in['batch_notes']);

        return $in;
    }

    private const BATCH_RULES = [
        'permit_no' => ['nullable', 'string', 'max:60'], 'sale_order' => ['nullable', 'string', 'max:60'], 'invoice_no' => ['nullable', 'string', 'max:60'],
        'transport_company' => ['nullable', 'string', 'max:120'], 'driver' => ['nullable', 'string', 'max:120'], 'batch_notes' => ['nullable', 'string', 'max:1000'],
    ];

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $this->validated($request);
        $data['chassis'] = Vehicle::cleanSerial($data['chassis']);
        if (Vehicle::withTrashed()->where('chassis', $data['chassis'])->whereKeyNot($vehicle->id)->exists()) {
            return back()->withErrors(['chassis' => 'رقم الشاسيه مسجل لمركبة أخرى.']);
        }
        $vehicle->update($data);
        Activity::log('vehicle', $vehicle, 'تعديل مركبة ' . $vehicle->chassis);

        return back()->with('success', 'تم الحفظ.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        Activity::log('vehicle', $vehicle, 'حذف مركبة ' . $vehicle->chassis);

        return back()->with('success', 'تم الحذف.');
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->with(['deal.customer:id,name', 'batch'])->orderBy('id')->get()->map(fn (Vehicle $v) => [
            $v->chassis, $v->motor, $v->type, $v->model_year, $v->color, $v->source_store, $v->branch_store, $v->cost_price, $v->transport_cost,
            $v->other_cost, $v->total_cost, $v->arrived_at?->toDateString(), $v->status_label, $v->deal?->customer?->name, $v->deal ? ($v->deal->pay_method === 'كاش' ? 'كاش' : trim('تقسيط ' . $v->deal->finance_entity)) : null, $v->sold_at?->toDateString(), $v->sale_price,
            $v->batch?->permit_no, $v->batch?->sale_order, $v->batch?->invoice_no, $v->batch?->driver,
        ])->all();
        Activity::log('export', null, 'تصدير المخزن (' . count($rows) . ')');

        return SheetWriter::download('المخزن-' . now()->format('Y-m-d') . '.xlsx', [[
            'title' => 'المخزن', 'headers' => ['الشاسيه', 'الموتور', 'النوع', 'السنة', 'اللون', 'من مخزن', 'إلى مخزن', 'سعر المنتج', 'مصاريف نقل', 'أخرى', 'الإجمالي', 'تاريخ الوصول', 'الحالة', 'المشتري', 'طريقة البيع', 'تاريخ البيع', 'سعر البيع', 'رقم الإذن', 'أمر البيع', 'رقم الفاتورة', 'السائق المستلم'],
            'rows' => $rows, 'money' => [8, 9, 10, 11, 17],
        ]]);
    }

    private function query(Request $request): Builder
    {
        $q = Vehicle::query();
        if (! $request->user()->isAdmin()) {
            $q->where('status', '!=', 'sold');
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('chassis', 'like', "%{$s}%")->orWhere('motor', 'like', "%{$s}%")->orWhere('color', 'like', "%{$s}%")
                ->orWhereHas('batch', fn ($b) => $b->where('permit_no', 'like', "%{$s}%")->orWhere('sale_order', 'like', "%{$s}%")->orWhere('invoice_no', 'like', "%{$s}%"))
                ->orWhereHas('deal.customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")));
        }
        foreach (['status', 'type', 'source_store', 'branch_store', 'model_year'] as $col) {
            if ($v = $request->query($col)) {
                $q->where($col, $v);
            }
        }
        if ($b = (int) $request->query('batch')) {
            $q->where('batch_id', $b);
        }
        if ($f = $request->query('from')) {
            $q->whereDate('arrived_at', '>=', $f);
        }
        if ($t = $request->query('to')) {
            $q->whereDate('arrived_at', '<=', $t);
        }

        return $q;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'chassis' => ['required', 'string', 'max:60'], 'motor' => ['nullable', 'string', 'max:60'], 'type' => ['nullable', 'string', 'max:120'],
            'model_year' => ['nullable', 'string', 'max:10'], 'color' => ['nullable', 'string', 'max:60'], 'source_store' => ['nullable', 'string', 'max:120'],
            'branch_store' => ['nullable', 'string', 'max:120'], 'cost_price' => ['nullable', 'numeric', 'min:0'], 'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'], 'arrived_at' => ['nullable', 'date'], 'status' => ['required', 'in:in_stock,reserved,sold'],
            'sold_at' => ['nullable', 'date'], 'sale_price' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (['cost_price', 'transport_cost', 'other_cost'] as $f) {
            $data[$f] ??= 0;
        }

        return $data;
    }
}
