<?php

namespace App\Http\Controllers;

use App\Exports\SheetWriter;
use App\Models\Vehicle;
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
            'vehicles' => (clone $q)->with('deal.customer:id,name,code')->orderByRaw("FIELD(status,'in_stock','reserved','sold')")->latest('arrived_at')->latest('id')->paginate(25)->withQueryString(),
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
        $rows = $this->query($request)->with('deal.customer:id,name')->orderBy('id')->get()->map(fn (Vehicle $v) => [
            $v->chassis, $v->motor, $v->type, $v->model_year, $v->color, $v->source_store, $v->branch_store, $v->cost_price, $v->transport_cost,
            $v->other_cost, $v->total_cost, $v->arrived_at?->toDateString(), $v->status_label, $v->deal?->customer?->name, $v->sold_at?->toDateString(), $v->sale_price,
        ])->all();
        Activity::log('export', null, 'تصدير المخزن (' . count($rows) . ')');

        return SheetWriter::download('المخزن-' . now()->format('Y-m-d') . '.xlsx', [[
            'title' => 'المخزن', 'headers' => ['الشاسيه', 'الموتور', 'النوع', 'السنة', 'اللون', 'من مخزن', 'إلى مخزن', 'سعر المنتج', 'مصاريف نقل', 'أخرى', 'الإجمالي', 'تاريخ الوصول', 'الحالة', 'المشتري', 'تاريخ البيع', 'سعر البيع'],
            'rows' => $rows, 'money' => [8, 9, 10, 11, 16],
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
                ->orWhereHas('deal.customer', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")));
        }
        foreach (['status', 'type', 'source_store', 'branch_store', 'model_year'] as $col) {
            if ($v = $request->query($col)) {
                $q->where($col, $v);
            }
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
        return $request->validate([
            'chassis' => ['required', 'string', 'max:60'], 'motor' => ['nullable', 'string', 'max:60'], 'type' => ['nullable', 'string', 'max:120'],
            'model_year' => ['nullable', 'string', 'max:10'], 'color' => ['nullable', 'string', 'max:60'], 'source_store' => ['nullable', 'string', 'max:120'],
            'branch_store' => ['nullable', 'string', 'max:120'], 'cost_price' => ['nullable', 'numeric', 'min:0'], 'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'], 'arrived_at' => ['nullable', 'date'], 'status' => ['required', 'in:in_stock,reserved,sold'],
            'sold_at' => ['nullable', 'date'], 'sale_price' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
