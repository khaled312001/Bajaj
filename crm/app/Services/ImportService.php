<?php

namespace App\Services;

use App\Exports\Schemas;
use App\Exports\TemplateBuilder;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Import;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Activity;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

class ImportService
{
    public const MAX_ROWS = 5000;

    public function __construct(private DealService $deals)
    {
    }

    /* ------------------------------------------------------------------ parsing */

    /** @return array{rows:array<int,array>, unknown:array<int,string>, missing:array<int,string>} */
    public function parse(string $type, string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $maxRow = min($sheet->getHighestDataRow(), self::MAX_ROWS + 1);

        $map = [];
        $unknown = [];
        for ($c = 1; $c <= $maxCol; $c++) {
            $h = trim((string) $sheet->getCell([$c, 1])->getValue());
            if ($h === '') {
                continue;
            }
            $key = Schemas::matchHeader($type, $h);
            $key ? $map[$c] = $key : $unknown[] = $h;
        }

        $required = array_keys(array_filter(Schemas::columns($type), fn ($c) => $c[4]));
        $missing = array_map(fn ($k) => Schemas::columns($type)[$k][0], array_values(array_diff($required, $map)));

        $rows = [];
        for ($r = 2; $r <= $maxRow; $r++) {
            $row = [];
            $any = false;
            foreach ($map as $c => $key) {
                $cell = $sheet->getCell([$c, $r]);
                $val = $cell->getValue();
                if (is_numeric($val) && XlDate::isDateTime($cell) && in_array($key, ['first_due_date', 'delivery_date', 'due_date', 'paid_on'], true)) {
                    $val = XlDate::excelToDateTimeObject($val)->format('Y-m-d');
                } elseif ($val instanceof \Stringable || is_object($val)) {
                    $val = (string) $val;
                } elseif (is_float($val) && floor($val) == $val && ! in_array($key, ['total_price', 'down_payment', 'monthly_installment', 'amount', 'interest_rate'], true)) {
                    $val = (string) (int) $val; // phone/id stored as number in Excel
                }
                $val = is_string($val) ? trim($val) : $val;
                $row[$key] = $val;
                if ($val !== null && $val !== '') {
                    $any = true;
                }
            }
            if ($any) {
                $rows[$r] = $row;
            }
        }

        return ['rows' => $rows, 'unknown' => $unknown, 'missing' => $missing];
    }

    /* ------------------------------------------------------------------ validation */

    /**
     * @return array{clean:array<int,array>, errors:array<int,array{row:int,message:string}>}
     */
    public function validate(string $type, array $rows): array
    {
        $lists = TemplateBuilder::lists();
        $users = [];
        foreach (User::all(['id', 'username', 'name']) as $u) {
            $users[$this->normalizeName($u->username)] = $u->id;
            $users[$this->normalizeName($u->name)] = $u->id;
        }
        $clean = [];
        $errors = [];

        foreach ($rows as $line => $row) {
            $errs = [];
            $d = match ($type) {
                'customers' => $this->cleanCustomer($row, $lists, $users, $errs),
                'followups' => $this->cleanFollowup($row, $users, $errs),
                'payments' => $this->cleanPayment($row, $errs),
            };
            if ($errs) {
                foreach ($errs as $e) {
                    $errors[] = ['row' => $line, 'message' => $e];
                }
            } else {
                $clean[$line] = $d;
            }
        }

        return ['clean' => $clean, 'errors' => $errors];
    }

    private function cleanCustomer(array $r, array $lists, array $users, array &$errs): array
    {
        $d = [];
        $d['code'] = $this->str($r['code'] ?? null, 30);
        $d['name'] = $this->str($r['name'] ?? null, 150);
        if (! $d['name']) {
            $errs[] = 'الاسم مطلوب.';
        }
        $d['phone'] = Customer::normalizePhone($this->str($r['phone'] ?? null));
        if (! $d['phone'] || ! preg_match('/^\d{8,15}$/', $d['phone'])) {
            $errs[] = 'رقم الهاتف مطلوب وغير صالح.';
        }
        foreach (['alt_phone', 'whatsapp'] as $f) {
            $d[$f] = Customer::normalizePhone($this->str($r[$f] ?? null));
            if ($d[$f] && ! preg_match('/^\d{8,15}$/', $d[$f])) {
                $errs[] = 'رقم ' . ($f === 'alt_phone' ? 'الهاتف البديل' : 'الواتساب') . ' غير صالح.';
            }
        }
        $nat = preg_replace('/\D/', '', (string) ($r['nat_id'] ?? ''));
        $d['nat_id'] = $nat ?: null;
        if ($nat && strlen($nat) !== 14) {
            $errs[] = 'الرقم القومي يجب أن يكون 14 رقماً.';
        }
        foreach (['job' => 120, 'governorate' => 100, 'district' => 120, 'address' => 250, 'channel' => 100, 'interest' => 150, 'notes' => 3000, 'seriousness' => 60, 'previous_vehicle' => 120, 'branch' => 80, 'loss_reason' => 1000] as $f => $max) {
            $d[$f] = $this->str($r[$f] ?? null, $max);
        }
        $age = $this->str($r['age'] ?? null);
        $d['age'] = is_numeric($age) && (int) $age > 0 && (int) $age < 120 ? (int) $age : null;
        $d['status'] = $this->str($r['status'] ?? null);
        if ($d['status'] && ! in_array($d['status'], Customer::STATUSES, true)) {
            $errs[] = 'حالة العميل غير معروفة (' . $d['status'] . ').';
        }
        $d['assigned_to'] = null;
        if ($u = $this->str($r['assigned_username'] ?? null)) {
            $d['assigned_to'] = $users[$this->normalizeName($u)] ?? null;
            if (! $d['assigned_to']) {
                $errs[] = "الموظف '{$u}' غير موجود.";
            }
        }

        // deal block
        $deal = [];
        $deal['vehicle'] = $this->str($r['vehicle'] ?? null, 150);
        $deal['pay_method'] = $this->str($r['pay_method'] ?? null);
        $deal['finance_entity'] = $this->str($r['finance_entity'] ?? null, 120);
        $deal['chassis'] = $this->str($r['chassis'] ?? null, 100);
        $deal['motor'] = $this->str($r['motor'] ?? null, 100);
        $deal['model'] = $this->str($r['model'] ?? null, 100);
        $deal['color'] = $this->str($r['color'] ?? null, 100);
        $deal['dealer'] = $this->str($r['dealer'] ?? null, 150);
        $deal['mobaya_no'] = $this->str($r['mobaya_no'] ?? null, 40);
        foreach (['total_price', 'down_payment', 'interest_rate', 'monthly_installment'] as $f) {
            $deal[$f] = $this->num($r[$f] ?? null, $f, $errs);
        }
        $deal['months'] = (int) ($this->num($r['months'] ?? null, 'months', $errs) ?? 0);
        foreach (['first_due_date', 'delivery_date', 'sale_date'] as $f) {
            $deal[$f] = $this->date($r[$f] ?? null, $f, $errs);
        }
        if ($deal['pay_method'] && ! in_array($deal['pay_method'], ['كاش', 'تقسيط'], true)) {
            $errs[] = 'طريقة الدفع يجب أن تكون كاش أو تقسيط.';
        }
        if ($deal['pay_method'] === 'تقسيط' && ($deal['months'] ?? 0) < 1 && ($deal['total_price'] ?? 0) > 0) {
            $errs[] = 'عدد الأشهر مطلوب لصفقة التقسيط.';
        }
        $hasDeal = collect($deal)->filter(fn ($v) => $v !== null && $v !== '' && $v !== 0)->isNotEmpty();
        $d['deal'] = $hasDeal ? $deal : null;

        return $d;
    }

    private function cleanFollowup(array $r, array $users, array &$errs): array
    {
        $d = ['customer' => $this->str($r['customer'] ?? null)];
        if (! $d['customer']) {
            $errs[] = 'كود أو هاتف العميل مطلوب.';
        }
        $d['reason'] = $this->str($r['reason'] ?? null, 120);
        if (! $d['reason']) {
            $errs[] = 'سبب المتابعة مطلوب.';
        }
        $d['due_date'] = $this->date($r['due_date'] ?? null, 'due_date', $errs);
        if (! $d['due_date']) {
            $errs[] = 'تاريخ المتابعة مطلوب.';
        }
        $t = $this->str($r['due_time'] ?? null);
        $d['due_time'] = null;
        if ($t) {
            if (is_numeric($t) && (float) $t < 1) {
                $secs = (int) round((float) $t * 86400);
                $d['due_time'] = gmdate('H:i', $secs);
            } elseif (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) {
                $d['due_time'] = sprintf('%02d:%02d', $m[1], $m[2]);
            } else {
                $errs[] = 'صيغة الوقت غير صحيحة.';
            }
        }
        $p = $this->str($r['priority'] ?? null);
        $d['priority'] = in_array($p, ['عالية', 'high'], true) ? 'high' : 'normal';
        $d['notes'] = $this->str($r['notes'] ?? null, 2000);
        $d['assigned_to'] = null;
        if ($u = $this->str($r['assigned_username'] ?? null)) {
            $d['assigned_to'] = $users[$this->normalizeName($u)] ?? null;
            if (! $d['assigned_to']) {
                $errs[] = "الموظف '{$u}' غير موجود.";
            }
        }

        return $d;
    }

    /** Case/diacritics/alef-variant-insensitive key for matching a username or an Arabic display name. */
    private function normalizeName(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s); // tashkeel/diacritics
        $s = preg_replace('/[إأآٱ]/u', 'ا', $s); // alef variants -> bare alef
        $s = str_replace('ى', 'ي', $s);
        $s = str_replace('ة', 'ه', $s);

        return mb_strtolower($s);
    }

    private function cleanPayment(array $r, array &$errs): array
    {
        $d = ['customer' => $this->str($r['customer'] ?? null)];
        if (! $d['customer']) {
            $errs[] = 'كود أو هاتف العميل مطلوب.';
        }
        $d['chassis'] = $this->str($r['chassis'] ?? null, 100);
        $d['amount'] = $this->num($r['amount'] ?? null, 'amount', $errs);
        if (! $d['amount'] || $d['amount'] <= 0) {
            $errs[] = 'المبلغ مطلوب ويجب أن يكون أكبر من صفر.';
        }
        $d['paid_on'] = $this->date($r['paid_on'] ?? null, 'paid_on', $errs);
        if (! $d['paid_on']) {
            $errs[] = 'تاريخ الدفع مطلوب.';
        } elseif (Carbon::parse($d['paid_on'])->isFuture()) {
            $errs[] = 'تاريخ الدفع في المستقبل.';
        }
        $d['method'] = $this->str($r['method'] ?? null, 30);
        $d['note'] = $this->str($r['note'] ?? null, 200);

        return $d;
    }

    /* ------------------------------------------------------------------ commit */

    /** @return array{created:int,updated:int,skipped:int,failed:int,errors:array} */
    public function commit(Import $import, string $mode, User $by): array
    {
        $parsed = $this->parse($import->type, Storage::disk('local')->path($import->path));
        $v = $this->validate($import->type, $parsed['rows']);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $errors = $v['errors'];
        $stats['failed'] = count(array_unique(array_column($errors, 'row')));
        $seen = [];

        foreach ($v['clean'] as $line => $d) {
            try {
                DB::transaction(function () use ($import, $d, $mode, $by, &$stats, &$seen) {
                    match ($import->type) {
                        'customers' => $this->commitCustomer($d, $mode, $by, $stats, $seen),
                        'followups' => $this->commitFollowup($d, $by, $stats),
                        'payments' => $this->commitPayment($d, $by, $stats),
                    };
                });
            } catch (\Throwable $e) {
                $stats['failed']++;
                $errors[] = ['row' => $line, 'message' => $e->getMessage() ?: 'خطأ غير متوقع.'];
            }
        }

        $import->update([
            'status' => 'done', 'created' => $stats['created'], 'updated' => $stats['updated'],
            'failed' => $stats['failed'], 'errors' => array_slice($errors, 0, 500),
        ]);
        Activity::log('import', $import, "استيراد {$import->filename}: جديد {$stats['created']} • محدّث {$stats['updated']} • فشل {$stats['failed']}");

        return $stats + ['errors' => $errors];
    }

    private function commitCustomer(array $d, string $mode, User $by, array &$stats, array &$seen): void
    {
        $deal = $d['deal'];
        unset($d['deal']);
        $fileKey = $d['phone'];

        $customer = isset($seen[$fileKey]) ? Customer::find($seen[$fileKey]) : null;
        $sameFileRow = (bool) $customer;

        if (! $customer) {
            $customer = ($d['code'] ? Customer::where('code', $d['code'])->first() : null)
                ?? Customer::where('phone', $d['phone'])->orWhere('alt_phone', $d['phone'])->first();
        }

        if ($customer && ! $sameFileRow) {
            if ($mode === 'skip') {
                $stats['skipped']++;
                $seen[$fileKey] = $customer->id;

                return;
            }
            $fill = array_filter(Arr::except($d, ['code']), fn ($v) => $v !== null && $v !== '');
            $customer->fill($fill)->save();
            $stats['updated']++;
            Activity::customer($customer, 'import', 'تحديث البيانات من ملف Excel بواسطة ' . $by->name);
        } elseif (! $customer) {
            $attrs = array_filter($d, fn ($v) => $v !== null && $v !== '');
            $attrs['status'] = $attrs['status'] ?? 'مفتوحة';
            $attrs['created_by'] = $by->id;
            $attrs['assigned_to'] = $attrs['assigned_to'] ?? $by->id;
            if (! empty($attrs['code']) && Customer::withTrashed()->where('code', $attrs['code'])->exists()) {
                unset($attrs['code']);
            }
            $customer = Customer::create($attrs);
            $stats['created']++;
            Activity::customer($customer, 'import', 'تم إدخال العميل عبر ملف Excel بواسطة ' . $by->name);
        }
        $seen[$fileKey] = $customer->id;

        if ($deal) {
            $exists = $customer->deals()->where(function ($q) use ($deal) {
                if (! empty($deal['chassis'])) {
                    $q->where('chassis', $deal['chassis']);
                } else {
                    $q->where('vehicle', $deal['vehicle'])->where('total_price', $deal['total_price'] ?? 0)->where('down_payment', $deal['down_payment'] ?? 0);
                }
            })->exists();
            if (! $exists) {
                $deal['vehicle'] = $deal['vehicle'] ?: $customer->interest;
                $deal['pay_method'] = $deal['pay_method'] ?: (($deal['months'] ?? 0) > 0 || ! empty($deal['finance_entity']) ? 'تقسيط' : 'كاش');
                $deal['status'] = $customer->status;
                $this->deals->save($customer, $deal, null, $by);
                Activity::customer($customer, 'deal', 'صفقة مستوردة من Excel: ' . ($deal['vehicle'] ?: 'غير محدد'));
            }
        }
    }

    private function commitFollowup(array $d, User $by, array &$stats): void
    {
        $customer = $this->findCustomer($d['customer']);
        if (! $customer) {
            throw new \RuntimeException("العميل '{$d['customer']}' غير موجود.");
        }
        $f = Followup::create([
            'customer_id' => $customer->id, 'reason' => $d['reason'], 'due_date' => $d['due_date'], 'due_time' => $d['due_time'],
            'notes' => $d['notes'], 'created_by' => $by->id,
        ]);
        $f->forceFill(['assigned_to' => $d['assigned_to'] ?? $customer->assigned_to ?? $by->id, 'priority' => $d['priority']])->save();
        Activity::customer($customer, 'followup', "متابعة مستوردة ({$d['reason']}) بتاريخ {$d['due_date']}");
        $stats['created']++;
    }

    private function commitPayment(array $d, User $by, array &$stats): void
    {
        $customer = $this->findCustomer($d['customer']);
        if (! $customer) {
            throw new \RuntimeException("العميل '{$d['customer']}' غير موجود.");
        }
        $q = $customer->deals();
        $deal = $d['chassis']
            ? (clone $q)->where('chassis', $d['chassis'])->first()
            : (clone $q)->where('pay_method', 'تقسيط')->where('balance', '>', 0)->latest('id')->first();
        if (! $deal) {
            throw new \RuntimeException('لا توجد صفقة تقسيط بها رصيد متبقٍ لهذا العميل.');
        }
        $this->deals->addPayment($deal, (float) $d['amount'], $d['paid_on'], $d['method'], $d['note'], $by);
        Activity::customer($customer, 'payment', 'دفعة مستوردة بقيمة ' . number_format($d['amount'], 2) . ' ج.م');
        $stats['created']++;
    }

    public function findCustomer(string $key): ?Customer
    {
        $phone = Customer::normalizePhone($key);

        return Customer::where('code', strtoupper(trim($key)))->first()
            ?? ($phone ? Customer::where('phone', $phone)->orWhere('alt_phone', $phone)->first() : null);
    }

    /* ------------------------------------------------------------------ cell helpers */

    private function str($v, int $max = 255): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim(preg_replace('/\s+/u', ' ', (string) $v));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private function num($v, string $field, array &$errs): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = strtr((string) $v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',']);
        $s = str_replace([',', ' ', 'ج', '.م', '%'], '', $s);
        if (! is_numeric($s) || (float) $s < 0) {
            $errs[] = 'قيمة رقمية غير صحيحة في عمود ' . (Schemas::columns($this->fieldOwner($field))[$field][0] ?? $field) . '.';

            return null;
        }

        return (float) $s;
    }

    private function fieldOwner(string $field): string
    {
        return in_array($field, ['amount', 'paid_on'], true) ? 'payments' : 'customers';
    }

    private function date($v, string $field, array &$errs): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = trim((string) $v);
        try {
            if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $s, $m)) {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            }
            if (is_numeric($s) && (float) $s > 20000) {
                return XlDate::excelToDateTimeObject((float) $s)->format('Y-m-d');
            }

            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            $errs[] = 'تاريخ غير صالح (' . $s . ').';

            return null;
        }
    }
}
