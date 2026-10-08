<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerEvent;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Lookup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/** One-off loader for the company's "تقرير متابعة العملاء" workbook (all sheets). */
class ImportBajajWorkbook extends Command
{
    protected $signature = 'bajaj:import {file : path to the .xlsm} {--owner=anwar : username that owns/created the imported records} {--assign= : username to assign customers to} {--fresh : delete existing customers/deals/follow-ups first}';

    protected $description = 'Import every sheet of the follow-up workbook (customers, follow-ups, financing, deliveries, cash bookings)';

    protected array $stats = [];
    protected ?User $owner = null;
    protected ?int $assignId = null;
    protected array $sheets = [];

    public function handle(): int
    {
        $this->owner = User::where('username', $this->option('owner'))->first() ?? User::where('role', 'admin')->first();
        if (! $this->owner) {
            $this->error('No owner user found. Run the seeder first.');

            return 1;
        }
        $this->assignId = $this->option('assign') ? User::where('username', $this->option('assign'))->value('id') : null;

        $reader = IOFactory::createReaderForFile($this->argument('file'));
        $reader->setReadDataOnly(true);
        $book = $reader->load($this->argument('file'));
        foreach ($book->getWorksheetIterator() as $ws) {
            $this->sheets[trim($ws->getTitle())] = $ws->toArray(null, true, false, false);
        }
        $this->info('Sheets: ' . implode(' | ', array_keys($this->sheets)));

        DB::transaction(function () {
            if ($this->option('fresh')) {
                foreach (['payments', 'installments', 'followups', 'customer_events', 'deals'] as $t) {
                    DB::table($t)->delete();
                }
                Customer::withTrashed()->forceDelete();
            }
            $this->main();
            $this->misc();
            $this->reminders();
            foreach (['تساهيل', 'بنك اسكندرية', 'ريفي', 'امان', 'مايلو'] as $name) {
                $this->financing($name);
            }
            $this->cash();
            $this->deliveries();
        });

        foreach ($this->stats as $k => $v) {
            $this->line(str_pad($k, 28) . $v);
        }
        $this->info('Customers total: ' . Customer::count() . ' | deals: ' . Deal::count() . ' | follow-ups: ' . Followup::count());

        return 0;
    }

    /* ---------------- sheets ---------------- */

    protected function main(): void
    {
        foreach (array_slice($this->sheets['تقرير مبيعات التفصيلى'] ?? [], 4) as $r) {
            if (! $this->s($r[1] ?? null)) {
                continue;
            }
            $status = match ($this->s($r[14] ?? null)) {
                'منفذة' => 'منفذة', 'مفقودة' => 'مفقودة', default => 'مفتوحة',
            };
            $c = $this->customer([
                'name' => $r[1], 'phones' => [$r[2] ?? null], 'whatsapp' => $r[3] ?? null,
                'governorate' => $r[4] ?? null, 'district' => $r[5] ?? null, 'address' => $r[6] ?? null,
                'channel' => $r[7] ?? null, 'contacted_at' => $this->date($r[8] ?? null),
                'interest' => $r[9] ?? null, 'previous_vehicle' => $r[10] ?? null, 'job' => $r[11] ?? null,
                'age' => $r[12] ?? null, 'seriousness' => $r[0] ?? null, 'status' => $status,
                'loss_reason' => $status === 'مفقودة' ? ($r[15] ?? null) : null,
            ]);
            if (($r[13] ?? null) && ! $c->notes) {
                $c->update(['notes' => 'أسلوب الدفع: ' . $this->s($r[13])]);
            }
            for ($i = 16; $i + 1 < count($r) + 1; $i += 2) {
                $this->followupDone($c, $r[$i] ?? null, $r[$i + 1] ?? null, 'main');
            }
            if ($status !== 'مفقودة' && $this->s($r[15] ?? null)) {
                $this->followupDone($c, $r[15], $r[8] ?? null, 'main');
            }
        }
    }

    /** "تغير افراج" has no header; free-text in several columns. */
    protected function misc(): void
    {
        foreach ($this->sheets['تغير افراج'] ?? [] as $r) {
            if (! $this->s($r[1] ?? null)) {
                continue;
            }
            $notes = collect(array_slice($r, 9, 8))->map(fn ($v) => $this->s($v))->filter(fn ($v) => $v && ! is_numeric($v) && ! $this->looksDate($v))->implode(' — ');
            $c = $this->customer([
                'name' => $r[1], 'phones' => [$r[2] ?? null], 'whatsapp' => $r[3] ?? null, 'governorate' => $r[4] ?? null,
                'district' => $r[5] ?? null, 'address' => $r[6] ?? null, 'channel' => $r[7] ?? null,
                'contacted_at' => $this->date($r[8] ?? null), 'seriousness' => 'تغيير إفراج', 'interest' => 'تغيير إفراج',
            ]);
            if ($notes) {
                $this->followupDone($c, $notes, $r[8] ?? null, 'misc');
            }
        }
    }

    protected function reminders(): void
    {
        foreach (array_slice($this->sheets['Reminders'] ?? [], 1) as $r) {
            if (! $this->s($r[0] ?? null)) {
                continue;
            }
            $c = $this->customer([
                'name' => $r[0], 'phones' => [$r[1] ?? null], 'whatsapp' => $r[2] ?? null,
                'governorate' => $r[3] ?? null, 'interest' => $r[4] ?? null,
            ]);
            $due = $this->date($r[6] ?? null) ?? now();
            $state = $this->s($r[9] ?? null) ?? '';
            $done = str_contains($state, 'تمت');
            $this->tick('reminders');
            Followup::create([
                'customer_id' => $c->id, 'assigned_to' => $this->assignId ?? $c->assigned_to ?? $this->owner->id, 'created_by' => $this->owner->id,
                'reason' => 'تذكير', 'notes' => $this->s($r[8] ?? null) ?: ($this->s($r[5] ?? null) ? 'الدفع: ' . $this->s($r[5]) : null),
                'due_date' => $due->toDateString(), 'due_time' => $this->time($r[7] ?? null),
                'status' => $done ? 'done' : 'pending', 'completed_at' => $done ? $due : null, 'completed_by' => $done ? $this->owner->id : null,
                'outcome' => $done ? ($this->s($r[8] ?? null) ?: $state) : null,
            ]);
        }
    }

    protected function financing(string $sheet): void
    {
        $entity = match ($sheet) { 'امان' => 'أمان', default => $sheet };
        foreach (array_slice($this->sheets[$sheet] ?? [], 1) as $r) {
            if (! $this->s($r[1] ?? null)) {
                continue;
            }
            $c = $this->customer([
                'name' => $r[1], 'nat_id' => $r[2] ?? null, 'address' => $r[3] ?? null, 'phones' => [$r[4] ?? null, $r[5] ?? null],
                'branch' => $this->s($r[0] ?? null), 'interest' => $r[9] ?? null, 'status' => 'جاري التقسيط',
            ]);
            $stageCol = in_array($sheet, ['تساهيل', 'مايلو'], true) ? null : ($r[10] ?? null);
            $color = in_array($sheet, ['تساهيل', 'مايلو'], true) ? ($r[10] ?? null) : null;
            $down = $this->money($r[6] ?? null);
            $notes = [];
            if ($sheet === 'ريفي') {
                $c->update(['branch' => $this->s($r[11] ?? null) ?: $c->branch]);
                foreach ([12, 14] as $i) {
                    $this->followupDone($c, $r[$i] ?? null, $r[$i + 1] ?? null, 'finance');
                }
                if ($this->s($r[16] ?? null)) {
                    $notes[] = 'جهة: ' . $this->s($r[16]);
                }
            }
            $deal = Deal::create([
                'customer_id' => $c->id, 'vehicle' => $this->s($r[9] ?? null), 'color' => $this->s($color),
                'pay_method' => 'تقسيط', 'finance_entity' => $entity, 'stage' => $this->s($stageCol),
                'status' => $this->s($stageCol) && str_contains($this->s($stageCol), 'رفض') ? 'مفقودة' : 'جاري التقسيط',
                'down_payment' => $down, 'months' => (int) $this->money($r[7] ?? null),
                'sale_date' => null, 'first_due_date' => null, 'notes' => $notes ? implode(' | ', $notes) : null,
                'created_by' => $this->owner->id, 'created_at' => $this->date($r[8] ?? null) ?? now(),
            ]);
            $this->tick("deals:$entity");
            CustomerEvent::create(['customer_id' => $c->id, 'user_id' => $this->owner->id, 'type' => 'deal', 'description' => "طلب تقسيط عبر {$entity}", 'meta' => ['deal' => $deal->id], 'created_at' => now()]);
        }
    }

    protected function cash(): void
    {
        foreach (array_slice($this->sheets['حجز نقدي'] ?? [], 2) as $r) {
            if (! $this->s($r[1] ?? null)) {
                continue;
            }
            $c = $this->customer([
                'name' => $r[1], 'phones' => [$r[2] ?? null, $r[3] ?? null], 'governorate' => $r[4] ?? null,
                'district' => $r[5] ?? null, 'address' => $r[6] ?? null, 'interest' => $r[7] ?? null,
                'contacted_at' => $this->date($r[9] ?? null),
            ]);
            Deal::create([
                'customer_id' => $c->id, 'vehicle' => $this->s($r[7] ?? null), 'color' => $this->s($r[8] ?? null), 'pay_method' => 'كاش',
                'status' => 'مفتوحة', 'stage' => 'حجز نقدي', 'delivery_date' => $this->date($r[11] ?? null)?->toDateString(),
                'created_by' => $this->owner->id, 'created_at' => $this->date($r[9] ?? null) ?? now(),
            ]);
            $this->tick('deals:cash');
            foreach ([[12, 13], [14, 15]] as [$a, $b]) {
                $this->followupDone($c, $r[$a] ?? null, $r[$b] ?? null, 'cash');
            }
        }
    }

    protected function deliveries(): void
    {
        foreach (array_slice($this->sheets['التسليمات'] ?? [], 1) as $r) {
            if (! $this->s($r[1] ?? null)) {
                continue;
            }
            $c = $this->customer([
                'name' => $r[1], 'nat_id' => $r[2] ?? null, 'phones' => [$r[3] ?? null], 'whatsapp' => $r[4] ?? null,
                'governorate' => $r[5] ?? null, 'district' => $r[6] ?? null, 'address' => $r[7] ?? null, 'channel' => $r[8] ?? null,
                'contacted_at' => $this->date($r[9] ?? null), 'interest' => $r[10] ?? null, 'status' => 'منفذة',
            ]);
            $installment = str_contains((string) $this->s($r[11] ?? null), 'قسط');
            $deal = Deal::create([
                'customer_id' => $c->id, 'vehicle' => $this->s($r[10] ?? null), 'chassis' => $this->s($r[12] ?? null), 'motor' => $this->s($r[13] ?? null),
                'color' => $this->s($r[14] ?? null), 'sale_date' => $this->date($r[15] ?? null)?->toDateString(),
                'delivery_date' => $this->date($r[15] ?? null)?->toDateString(), 'dealer' => $this->s($r[17] ?? null),
                'pay_method' => $installment ? 'تقسيط' : 'كاش', 'finance_entity' => $installment ? $this->entity($r[18] ?? null) : null,
                'status' => 'منفذة', 'stage' => 'تم التسليم', 'notes' => $this->s($r[19] ?? null) ? 'المبايعة: ' . $this->s($r[19]) : null,
                'created_by' => $this->owner->id,
            ]);
            $this->tick('deals:delivered');
            CustomerEvent::create(['customer_id' => $c->id, 'user_id' => $this->owner->id, 'type' => 'deal', 'description' => 'تسليم مركبة ' . $deal->vehicle, 'meta' => ['deal' => $deal->id], 'created_at' => $deal->sale_date ?? now()]);
        }
    }

    /* ---------------- helpers ---------------- */

    /** Find by phone (merge) or create. */
    protected function customer(array $d): Customer
    {
        $phones = collect($d['phones'] ?? [])
            ->flatMap(fn ($p) => preg_split('/[\s\/\\\\,،;\-]+/u', (string) $p) ?: [])
            ->map(fn ($p) => Customer::normalizePhone($p))->filter(fn ($p) => $p && strlen($p) >= 9)->unique()->values();
        $wa = Customer::normalizePhone($this->s($d['whatsapp'] ?? null));
        $name = $this->s($d['name']);

        $c = null;
        foreach ($phones as $p) {
            $c = Customer::where('phone', $p)->orWhere('alt_phone', $p)->first();
            if ($c) {
                break;
            }
        }
        $nat = preg_replace('/\D/', '', (string) ($d['nat_id'] ?? ''));
        $nat = strlen($nat) === 14 ? $nat : null;

        $age = is_numeric($d['age'] ?? null) ? (int) $d['age'] : null;
        $fields = [
            'nat_id' => $nat, 'job' => $this->s($d['job'] ?? null), 'whatsapp' => $wa, 'governorate' => $this->s($d['governorate'] ?? null),
            'district' => $this->s($d['district'] ?? null), 'address' => $this->s($d['address'] ?? null), 'channel' => $this->s($d['channel'] ?? null),
            'interest' => $this->s($d['interest'] ?? null), 'age' => $age, 'seriousness' => $this->s($d['seriousness'] ?? null),
            'previous_vehicle' => $this->s($d['previous_vehicle'] ?? null), 'branch' => $this->s($d['branch'] ?? null),
            'loss_reason' => $this->s($d['loss_reason'] ?? null), 'contacted_at' => $d['contacted_at'] ?? null,
        ];
        foreach (['channel' => 'channel', 'seriousness' => 'seriousness', 'branch' => 'branch', 'governorate' => 'governorate'] as $f => $type) {
            if ($fields[$f]) {
                Lookup::firstOrCreate(['type' => $type, 'name' => $fields[$f]], ['sort' => (int) Lookup::where('type', $type)->max('sort') + 1]);
            }
        }
        if ($fields['interest'] && ! in_array($fields['interest'], ['Other', 'spaer pats', 'spaer parts'], true)) {
            Lookup::firstOrCreate(['type' => 'vehicle', 'name' => $fields['interest']], ['sort' => (int) Lookup::where('type', 'vehicle')->max('sort') + 1]);
        }

        if ($c) {
            $fill = [];
            foreach ($fields as $k => $v) {
                if ($v !== null && ($c->{$k} === null || $c->{$k} === '')) {
                    $fill[$k] = $v;
                }
            }
            if (($d['status'] ?? null) && in_array($d['status'], ['منفذة'], true)) {
                $fill['status'] = $d['status'];
            }
            if ($phones->count() > 1 && ! $c->alt_phone) {
                $fill['alt_phone'] = $phones->first(fn ($p) => $p !== $c->phone);
            }
            $c->fill($fill)->save();
            $this->tick('customers merged');

            return $c;
        }

        $this->tick('customers created');

        return Customer::create($fields + [
            'name' => $name, 'phone' => $phones[0] ?? '', 'alt_phone' => $phones[1] ?? null, 'status' => $d['status'] ?? 'مفتوحة',
            'created_by' => $this->owner->id, 'assigned_to' => $this->assignId ?? $this->owner->id,
        ]);
    }

    protected function followupDone(Customer $c, mixed $text, mixed $date, string $src): void
    {
        $text = $this->s($text);
        if (! $text) {
            return;
        }
        $d = $this->date($date) ?? $c->contacted_at ?? now();
        Followup::create([
            'customer_id' => $c->id, 'assigned_to' => $c->assigned_to ?? $this->owner->id, 'created_by' => $this->owner->id,
            'reason' => 'متابعة', 'due_date' => $d->toDateString(), 'status' => 'done', 'completed_at' => $d,
            'completed_by' => $this->owner->id, 'outcome' => $text,
        ]);
        $this->tick('followups done');
    }

    protected function tick(string $k): void
    {
        $this->stats[$k] = ($this->stats[$k] ?? 0) + 1;
    }

    protected function s(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string) $v)));

        return $v === '' ? null : $v;
    }

    protected function money(mixed $v): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        preg_match('/\d+(?:[.,]\d+)?/', (string) $v, $m);

        return isset($m[0]) ? (float) str_replace(',', '', $m[0]) : 0.0;
    }

    protected function entity(mixed $v): ?string
    {
        $v = $this->s($v);

        return $v === 'امان' ? 'أمان' : $v;
    }

    protected function looksDate(string $v): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}/', $v);
    }

    protected function date(mixed $v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            if (is_numeric($v)) {
                return (float) $v > 20000 ? Carbon::instance(XlDate::excelToDateTimeObject((float) $v)) : null;
            }

            return Carbon::parse((string) $v);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function time(mixed $v): ?string
    {
        if (is_numeric($v) && (float) $v < 1 && (float) $v > 0) {
            return XlDate::excelToDateTimeObject((float) $v)->format('H:i:s');
        }

        return null;
    }
}
