<?php

namespace App\Console\Commands;

use App\Models\CustomerEvent;
use App\Models\Deal;
use App\Models\DocumentLog;
use App\Models\Lookup;
use App\Models\Product;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Loads the sales register, warehouse stock and quote print-log workbooks (no duplicates: matched by chassis / serial / name). */
class ImportSalesWorkbook extends ImportBajajWorkbook
{
    protected $signature = 'bajaj:import-sales {sales : sales workbook .xlsm} {--quotes= : quote workbook .xlsm (print log sheet)} {--prices= : installments workbook .xlsm (products sheet)} {--owner=anwar} {--assign=}';

    protected $description = 'Import sales register, warehouse stock, quote print log and extra products';

    public function handle(): int
    {
        $this->owner = User::where('username', $this->option('owner'))->first() ?? User::where('role', 'admin')->first();
        $this->assignId = $this->option('assign') ? User::where('username', $this->option('assign'))->value('id') : null;

        DB::transaction(function () {
            $this->loadBook($this->argument('sales'), ['المخزن', 'المبيعات']);
            $this->stock();
            $this->sales();
            if ($f = $this->option('quotes')) {
                $this->loadBook($f, ['سجل الطباعة']);
                $this->quoteLog();
            }
            if ($f = $this->option('prices')) {
                $this->loadBook($f, ['اسعار المنتاجات']);
                $this->products();
            }
        });

        foreach ($this->stats as $k => $v) {
            $this->line(str_pad($k, 28) . $v);
        }

        return 0;
    }

    protected function loadBook(string $file, array $only = []): void
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        if ($only) {
            $reader->setLoadSheetsOnly($only);
        }
        $this->sheets = [];
        foreach ($reader->load($file)->getWorksheetIterator() as $ws) {
            $this->sheets[trim($ws->getTitle())] = $ws->toArray(null, true, false, false);
        }
    }

    protected function serial(mixed $v): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $v));
    }

    protected function stock(): void
    {
        foreach (array_slice($this->sheets['المخزن'] ?? [], 1) as $r) {
            $chassis = $this->serial($r[1] ?? null);
            if ($chassis === '') {
                continue;
            }
            $sold = $this->date($r[12] ?? null) || $this->s($r[10] ?? null);
            $v = Vehicle::firstOrNew(['chassis' => $chassis]);
            $v->fill([
                'motor' => $this->s($r[2] ?? null) ?: $v->motor, 'source_store' => $this->s($r[3] ?? null), 'branch_store' => $this->s($r[4] ?? null),
                'cost_price' => $this->money($r[5] ?? null), 'transport_cost' => $this->money($r[6] ?? null), 'other_cost' => $this->money($r[7] ?? null),
                'arrived_at' => $this->date($r[9] ?? null)?->toDateString(), 'status' => $sold ? 'sold' : 'in_stock',
                'sold_at' => $this->date($r[12] ?? null)?->toDateString(), 'sale_price' => ($r[14] ?? null) ? $this->money($r[14]) : null,
                'created_by' => $this->owner->id,
            ]);
            $v->save();
            $this->tick('stock units');
        }
    }

    protected function sales(): void
    {
        foreach (array_slice($this->sheets['المبيعات'] ?? [], 1) as $r) {
            if (! $this->s($r[1] ?? null)) {
                continue;
            }
            $chassis = $this->serial($r[11] ?? null);
            $c = $this->customer([
                'name' => $r[1], 'governorate' => $r[2] ?? null, 'district' => $r[3] ?? null, 'address' => $r[4] ?? null,
                'phones' => [$r[5] ?? null], 'whatsapp' => $r[6] ?? null, 'nat_id' => $r[7] ?? null, 'interest' => $r[10] ?? null,
                'status' => 'منفذة',
            ]);
            $pay = str_contains((string) $this->s($r[27] ?? null), 'قسط') ? 'تقسيط' : 'كاش';
            $entity = $pay === 'تقسيط' ? $this->normEntity($r[28] ?? null) : null;
            $data = [
                'customer_id' => $c->id, 'vehicle' => $this->s($r[10] ?? null), 'model' => $this->s($r[13] ?? null), 'chassis' => $chassis ?: null,
                'motor' => $this->s($r[12] ?? null), 'color' => $this->s($r[14] ?? null), 'sale_date' => $this->date($r[15] ?? null)?->toDateString(),
                'delivery_date' => $this->date($r[15] ?? null)?->toDateString(), 'total_price' => $this->money($r[19] ?? null),
                'pay_method' => $pay, 'finance_entity' => $entity, 'status' => 'منفذة', 'stage' => 'تم التسليم',
                'po_number' => $this->s($r[8] ?? null), 'sales_order' => $this->s($r[16] ?? null), 'invoice_no' => $this->s($r[17] ?? null),
                'treasury_receipt' => $this->s($r[18] ?? null), 'mobaya_no' => $this->s($r[21] ?? null),
                'mobaya_arrived_at' => $this->date($r[20] ?? null)?->toDateString(), 'mobaya_received_at' => $this->date($r[24] ?? null)?->toDateString(),
                'customer_notified' => (bool) $this->s($r[22] ?? null), 'notes' => $this->s($r[26] ?? null),
            ];
            $deal = $chassis ? Deal::where('chassis', $chassis)->first() : null;
            $deal ??= Deal::where('customer_id', $c->id)->where('vehicle', $data['vehicle'])->whereNull('chassis')->first();
            if ($deal) {
                $deal->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''))->save();
                $this->tick('sales merged');
            } else {
                $deal = Deal::create($data + ['created_by' => $this->owner->id]);
                $deal->recalculate();
                $this->tick('sales deals');
                CustomerEvent::create(['customer_id' => $c->id, 'user_id' => $this->owner->id, 'type' => 'deal', 'description' => 'بيع مركبة ' . $deal->vehicle . ' — شاسيه ' . $chassis, 'meta' => ['deal' => $deal->id], 'created_at' => $deal->sale_date ?? now()]);
            }
            $c->update(['status' => 'منفذة']);

            if ($chassis) {
                $v = Vehicle::firstOrNew(['chassis' => $chassis]);
                $v->fill([
                    'motor' => $data['motor'] ?: $v->motor, 'type' => $data['vehicle'], 'model_year' => $data['model'], 'color' => $data['color'],
                    'status' => 'sold', 'deal_id' => $deal->id, 'sold_at' => $data['sale_date'], 'sale_price' => $data['total_price'] ?: $v->sale_price,
                    'created_by' => $v->created_by ?? $this->owner->id,
                ])->save();
            }
        }
    }

    protected function quoteLog(): void
    {
        foreach (array_slice($this->sheets['سجل الطباعة'] ?? [], 1) as $r) {
            $name = $this->s($r[0] ?? null);
            $ref = $this->s($r[4] ?? null);
            if (! $name || ! $ref || DocumentLog::where('legacy_ref', $ref)->exists()) {
                continue;
            }
            DocumentLog::create([
                'type' => 'quote', 'legacy_ref' => $ref, 'customer_name' => $name, 'product_name' => $this->s($r[1] ?? null),
                'amount' => $this->money($r[2] ?? null), 'printed_at' => $this->date($r[3] ?? null) ?? now(), 'user_id' => $this->owner->id,
            ]);
            $this->tick('quote log');
        }
    }

    protected function products(): void
    {
        $existing = Product::pluck('price', 'name')->mapWithKeys(fn ($p, $n) => [$this->norm($n) => $p])->all();
        $sort = (int) Product::max('sort');
        foreach (array_slice($this->sheets['اسعار المنتاجات'] ?? [], 1) as $r) {
            $name = $this->s($r[1] ?? null);
            if (! $name) {
                continue;
            }
            $key = $this->norm($name);
            if (isset($existing[$key])) {
                if ((float) $existing[$key] !== (float) $r[2]) {
                    $this->warn("سعر مختلف: {$name} الحالي {$existing[$key]} / الملف {$r[2]} (تم الإبقاء على الحالي)");
                }
                continue;
            }
            $existing[$key] = $r[2];
            Product::create(['name' => $name, 'price' => $this->money($r[2] ?? null), 'warranty' => $this->s($r[3] ?? null), 'requirements' => trim((string) ($r[4] ?? '')) ?: null, 'sort' => ++$sort, 'is_active' => true]);
            $this->tick('products added');
        }
    }

    protected function norm(string $v): string
    {
        return preg_replace('/\s+/u', '', mb_strtolower($v));
    }

    protected function normEntity(mixed $v): ?string
    {
        $v = $this->s($v) ?? '';
        foreach (['ريفي' => 'ريفي', 'امان' => 'أمان', 'أمان' => 'أمان', 'تساهيل' => 'تساهيل', 'مايلو' => 'مايلو'] as $needle => $name) {
            if (str_contains($v, $needle)) {
                return $name;
            }
        }

        return $v ?: null;
    }
}
