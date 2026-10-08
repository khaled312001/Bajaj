<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Installment calculator.
 *  - flat     : interest = financed * rate% * (months / 12), spread evenly (fixed-rate, common for car financing).
 *  - reducing : classic amortisation (PMT) on the declining balance.
 */
class InstallmentCalculator
{
    /**
     * @param array{price:float,down:float,months:int,rate:float,type?:string,fees?:float,fees_type?:string,fees_financed?:bool,first_due?:string|null} $in
     */
    public static function calculate(array $in): array
    {
        $price = max(0, (float) ($in['price'] ?? 0));
        $down = min($price, max(0, (float) ($in['down'] ?? 0)));
        $months = max(1, min(120, (int) ($in['months'] ?? 12)));
        $rate = max(0, (float) ($in['rate'] ?? 0));
        $type = in_array($in['type'] ?? 'flat', ['reducing', 'table'], true) ? $in['type'] : 'flat';
        $feesInput = max(0, (float) ($in['fees'] ?? 0));
        $feesType = ($in['fees_type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $feesFinanced = (bool) ($in['fees_financed'] ?? false);

        $base = round($price - $down, 2);
        $fees = round($feesType === 'percent' ? $base * $feesInput / 100 : $feesInput, 2);
        $financed = round($base + ($feesFinanced ? $fees : 0), 2);

        // company table: first installment is collected with the down payment (at delivery)
        $first = ! empty($in['first_due']) ? Carbon::parse($in['first_due'])->startOfDay()
            : ($type === 'table' ? now()->startOfDay() : now()->startOfDay()->addMonthNoOverflow());

        if ($type === 'table') {
            return self::companyTable($price, $down, $months, $first, $feesInput > 0 ? $feesInput : null, $in['plan'] ?? null);
        }

        if ($financed <= 0) {
            return self::emptyResult($price, $down, $months, $rate, $type, $fees, $feesFinanced, $first);
        }

        $schedule = [];
        $balance = $financed;

        if ($type === 'flat') {
            $interestTotal = round($financed * ($rate / 100) * ($months / 12), 2);
            $totalToPay = $financed + $interestTotal;
            $monthly = round($totalToPay / $months, 2);
            $principalEach = round($financed / $months, 2);
            $interestEach = round($interestTotal / $months, 2);

            for ($i = 1; $i <= $months; $i++) {
                $isLast = $i === $months;
                $principal = $isLast ? round($balance, 2) : $principalEach;
                $interest = $isLast ? round($interestTotal - $interestEach * ($months - 1), 2) : $interestEach;
                $amount = round($principal + $interest, 2);
                $balance = round($balance - $principal, 2);
                $schedule[] = self::row($i, $first, $amount, $principal, $interest, max(0, $balance));
            }
        } else {
            $r = $rate / 100 / 12;
            $pmt = $r > 0 ? $financed * $r / (1 - pow(1 + $r, -$months)) : $financed / $months;
            $pmt = round($pmt, 2);

            for ($i = 1; $i <= $months; $i++) {
                $interest = round($balance * $r, 2);
                $isLast = $i === $months;
                $principal = $isLast ? round($balance, 2) : round($pmt - $interest, 2);
                $amount = round($principal + $interest, 2);
                $balance = round($balance - $principal, 2);
                $schedule[] = self::row($i, $first, $amount, $principal, $interest, max(0, $balance));
            }
        }

        $totalToPay = round(array_sum(array_column($schedule, 'amount')), 2);
        $interestTotal = round(array_sum(array_column($schedule, 'interest')), 2);

        return [
            'price' => $price,
            'down' => $down,
            'financed' => $financed,
            'fees' => $fees,
            'fees_financed' => $feesFinanced,
            'months' => $months,
            'rate' => $rate,
            'type' => $type,
            'monthly' => $schedule[0]['amount'],
            'interest_total' => $interestTotal,
            'total_to_pay' => $totalToPay,
            'grand_total' => round($down + $totalToPay + ($feesFinanced ? 0 : $fees), 2),
            'effective_cost_pct' => $financed > 0 ? round($interestTotal / $financed * 100, 2) : 0,
            'first_due' => $first->toDateString(),
            'last_due' => end($schedule)['due_date'],
            'schedule' => $schedule,
        ];
    }

    /** Default markup table of the dealership (editable in Settings). */
    public const DEFAULT_TABLE = [6 => 1.125, 12 => 1.22, 18 => 1.33, 20 => 1.36, 24 => 1.44, 36 => 1.66];

    /** Built-in plans (from the company's Excel sheets); admin can edit them in Settings. */
    public const DEFAULT_PLANS = [
        ['name' => 'نظام الشركة', 'fees_percent' => 3, 'fees_fixed' => 500, 'table' => [6 => 1.125, 12 => 1.22, 18 => 1.33, 20 => 1.36, 24 => 1.44, 36 => 1.66]],
        ['name' => 'أمان', 'fees_percent' => 3, 'fees_fixed' => 650, 'table' => [6 => 1.12, 12 => 1.24, 18 => 1.36, 24 => 1.48, 36 => 1.71]],
        ['name' => 'ريفي', 'fees_percent' => 3, 'fees_fixed' => 650, 'table' => [6 => 1.11, 12 => 1.22, 18 => 1.33, 24 => 1.44, 36 => 1.66]],
        ['name' => 'تساهيل', 'fees_percent' => 0, 'fees_fixed' => 900, 'table' => [6 => 1.12, 12 => 1.24, 18 => 1.36, 24 => 1.48, 36 => 1.72]],
    ];

    /** @return array<int,array{name:string,fees_percent:float,fees_fixed:float,table:array<int,float>}> */
    public static function plans(): array
    {
        $raw = Settings::get('finance_plans');
        $list = $raw ? json_decode($raw, true) : null;
        if (! is_array($list) || ! $list) {
            $list = self::DEFAULT_PLANS;
            // honour a legacy single table / fee settings for the first plan
            if (($legacy = Settings::get('markup_table')) && is_array($t = json_decode($legacy, true)) && $t) {
                $list[0]['table'] = $t;
                $list[0]['fees_percent'] = (float) Settings::get('fees_percent', 3);
                $list[0]['fees_fixed'] = (float) Settings::get('fees_fixed', 500);
            }
        }
        $out = [];
        foreach ($list as $pl) {
            $t = [];
            foreach ($pl['table'] ?? [] as $m => $x) {
                $t[(int) $m] = (float) $x;
            }
            ksort($t);
            if ($t && ! empty($pl['name'])) {
                $out[] = ['name' => (string) $pl['name'], 'fees_percent' => (float) ($pl['fees_percent'] ?? 0), 'fees_fixed' => (float) ($pl['fees_fixed'] ?? 0), 'table' => $t];
            }
        }

        return $out ?: self::DEFAULT_PLANS;
    }

    /** Plan by name (finance entity); falls back to the first (company) plan. */
    public static function plan(?string $name = null): array
    {
        $plans = self::plans();
        $name = trim((string) $name);
        foreach ($plans as $pl) {
            if ($name !== '' && mb_strtolower($pl['name']) === mb_strtolower($name)) {
                return $pl;
            }
        }

        return $plans[0];
    }

    public static function markupTable(?string $plan = null): array
    {
        return self::plan($plan)['table'];
    }

    /** Multiplier for any month count (exact, else linear interpolation / extrapolation). */
    public static function multiplierFor(int $months, ?string $plan = null): float
    {
        $t = self::markupTable($plan);
        if (isset($t[$months])) {
            return $t[$months];
        }
        $keys = array_keys($t);
        if (count($keys) === 1) {
            return $t[$keys[0]];
        }
        $lo = null;
        $hi = null;
        foreach ($keys as $k) {
            if ($k < $months) {
                $lo = $k;
            } elseif ($hi === null) {
                $hi = $k;
            }
        }
        if ($lo === null) { $lo = $keys[0]; $hi = $keys[1]; }
        elseif ($hi === null) { $hi = $lo; $lo = $keys[count($keys) - 2]; }

        return round($t[$lo] + ($t[$hi] - $t[$lo]) * ($months - $lo) / ($hi - $lo), 4);
    }

    /**
     * Dealership system: remaining = price - down; monthly = remaining * multiplier / months;
     * admin fees = remaining * fee% + fixed fee; down + fees + FIRST installment are paid at delivery.
     */
    private static function companyTable(float $price, float $down, int $months, Carbon $first, ?float $feesOverride, ?string $planName = null): array
    {
        $remaining = round($price - $down, 2);
        if ($remaining <= 0) {
            return self::emptyResult($price, $down, $months, 0, 'table', 0, false, $first);
        }
        $plan = self::plan($planName);
        $fees = $feesOverride ?? round($remaining * $plan['fees_percent'] / 100 + $plan['fees_fixed'], 2);
        $mult = self::multiplierFor($months, $planName);
        $total = round($remaining * $mult, 2);
        $monthly = round($total / $months, 2);
        $principalEach = round($remaining / $months, 2);
        $schedule = [];
        $balance = $remaining;
        $paidSoFar = 0;
        for ($i = 1; $i <= $months; $i++) {
            $isLast = $i === $months;
            $amount = $isLast ? round($total - $paidSoFar, 2) : $monthly;
            $principal = $isLast ? round($balance, 2) : $principalEach;
            $balance = round($balance - $principal, 2);
            $paidSoFar += $amount;
            $schedule[] = self::row($i, $first, $amount, $principal, round($amount - $principal, 2), max(0, $balance));
        }
        $grand = round($down + $fees + $total, 2);

        return [
            'price' => $price, 'down' => $down, 'financed' => $remaining, 'fees' => $fees, 'fees_financed' => false,
            'months' => $months, 'rate' => 0, 'type' => 'table', 'plan' => $plan['name'], 'multiplier' => $mult, 'monthly' => $monthly,
            'interest_total' => round($total - $remaining, 2), 'total_to_pay' => $total, 'grand_total' => $grand,
            'upfront' => round($down + $fees + $monthly, 2), 'increase' => round($grand - $price, 2),
            'effective_cost_pct' => round(($mult - 1) * 100, 2),
            'first_due' => $first->toDateString(), 'last_due' => end($schedule)['due_date'], 'schedule' => $schedule,
        ];
    }

    private static function row(int $n, Carbon $first, float $amount, float $principal, float $interest, float $balance): array
    {
        return [
            'number' => $n,
            'due_date' => $first->copy()->addMonthsNoOverflow($n - 1)->toDateString(),
            'amount' => $amount,
            'principal' => $principal,
            'interest' => $interest,
            'balance' => $balance,
        ];
    }

    private static function emptyResult($price, $down, $months, $rate, $type, $fees, $feesFinanced, Carbon $first): array
    {
        return [
            'price' => $price, 'down' => $down, 'financed' => 0, 'fees' => $fees, 'fees_financed' => $feesFinanced,
            'months' => $months, 'rate' => $rate, 'type' => $type, 'monthly' => 0, 'interest_total' => 0,
            'total_to_pay' => 0, 'grand_total' => round($down + $fees, 2), 'effective_cost_pct' => 0,
            'first_due' => $first->toDateString(), 'last_due' => $first->toDateString(), 'schedule' => [],
        ];
    }
}
