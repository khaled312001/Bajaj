<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\DocumentLog;
use App\Models\Followup;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Day / week / month / year digest with comparison to the previous period. Non-admins only see their own numbers. */
class SummaryService
{
    public const PERIODS = ['day' => 'اليوم', 'week' => 'الأسبوع', 'month' => 'الشهر', 'year' => 'السنة'];

    public static function range(string $period, Carbon $anchor): array
    {
        $anchor = $anchor->copy();
        switch ($period) {
            case 'day':
                return [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()];
            case 'week': // Egypt week: Saturday → Friday
                $start = $anchor->copy()->startOfDay();
                while ($start->dayOfWeek !== Carbon::SATURDAY) {
                    $start->subDay();
                }

                return [$start, $start->copy()->addDays(6)->endOfDay()];
            case 'year':
                return [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()];
            default:
                return [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()];
        }
    }

    public static function previous(string $period, Carbon $from): array
    {
        $anchor = match ($period) {
            'day' => $from->copy()->subDay(), 'week' => $from->copy()->subWeek(),
            'year' => $from->copy()->subYear(), default => $from->copy()->subMonthNoOverflow(),
        };

        return self::range($period, $anchor);
    }

    public static function metrics(Carbon $from, Carbon $to, ?int $uid): array
    {
        $cust = Customer::query()->whereBetween('created_at', [$from, $to]);
        $deals = Deal::query()->whereBetween('created_at', [$from, $to]);
        $sold = Deal::query()->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
        $pay = Payment::query()->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()]);
        $fuDone = Followup::query()->where('status', 'done')->whereBetween('completed_at', [$from, $to]);
        $fuNew = Followup::query()->whereBetween('created_at', [$from, $to]);
        $inst = Installment::query()->whereBetween('due_date', [$from->toDateString(), $to->toDateString()]);
        $docs = DocumentLog::query()->whereBetween('printed_at', [$from, $to]);

        if ($uid) {
            $cust->where('created_by', $uid);
            $deals->where('created_by', $uid);
            $sold->where('created_by', $uid);
            $pay->where('received_by', $uid);
            $fuDone->where('completed_by', $uid);
            $fuNew->where('created_by', $uid);
            $inst->whereHas('deal', fn ($d) => $d->where('created_by', $uid));
            $docs->where('user_id', $uid);
        }

        return [
            'customers' => (clone $cust)->count(),
            'serious' => (clone $cust)->whereIn('seriousness', ['جاد للشراء'])->count(),
            'lost' => (clone $cust)->where('status', 'مفقودة')->count(),
            'followups_done' => (clone $fuDone)->count(),
            'followups_new' => (clone $fuNew)->count(),
            'deals' => (clone $deals)->count(),
            'sales' => (clone $sold)->count(),
            'sales_value' => (float) (clone $sold)->sum('total_price'),
            'collected' => (float) (clone $pay)->sum('amount'),
            'payments' => (clone $pay)->count(),
            'inst_due_count' => (clone $inst)->count(),
            'inst_due_amount' => (float) (clone $inst)->sum('amount'),
            'inst_paid_amount' => (float) (clone $inst)->sum('paid_amount'),
            'documents' => (clone $docs)->count(),
            'vehicles_in' => $uid ? null : Vehicle::whereBetween('arrived_at', [$from->toDateString(), $to->toDateString()])->count(),
            'vehicles_sold' => $uid ? null : Vehicle::where('status', 'sold')->whereBetween('sold_at', [$from->toDateString(), $to->toDateString()])->count(),
        ];
    }

    /** Daily series for the chart. */
    public static function series(Carbon $from, Carbon $to, ?int $uid): array
    {
        $unit = $from->diffInDays($to) > 62 ? 'month' : 'day';
        $fmt = $unit === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $c = Customer::query()->whereBetween('created_at', [$from, $to])->when($uid, fn ($q) => $q->where('created_by', $uid))
            ->selectRaw("DATE_FORMAT(created_at,'{$fmt}') k, COUNT(*) v")->groupBy('k')->pluck('v', 'k');
        $p = Payment::query()->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->when($uid, fn ($q) => $q->where('received_by', $uid))
            ->selectRaw("DATE_FORMAT(paid_on,'{$fmt}') k, SUM(amount) v")->groupBy('k')->pluck('v', 'k');
        $f = Followup::query()->where('status', 'done')->whereBetween('completed_at', [$from, $to])->when($uid, fn ($q) => $q->where('completed_by', $uid))
            ->selectRaw("DATE_FORMAT(completed_at,'{$fmt}') k, COUNT(*) v")->groupBy('k')->pluck('v', 'k');

        $labels = $cust = $coll = $fu = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $k = $unit === 'month' ? $cursor->format('Y-m') : $cursor->format('Y-m-d');
            $labels[] = $unit === 'month' ? $cursor->format('Y/m') : $cursor->format('m/d');
            $cust[] = (int) ($c[$k] ?? 0);
            $coll[] = (float) ($p[$k] ?? 0);
            $fu[] = (int) ($f[$k] ?? 0);
            $unit === 'month' ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        return ['labels' => $labels, 'customers' => $cust, 'collected' => $coll, 'followups' => $fu];
    }

    /** Per-employee leaderboard (admin only). */
    public static function employees(Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach (User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']) as $u) {
            $m = self::metrics($from, $to, $u->id);
            $out[] = ['name' => $u->name, 'customers' => $m['customers'], 'followups' => $m['followups_done'], 'deals' => $m['deals'], 'sales' => $m['sales'], 'collected' => $m['collected'], 'documents' => $m['documents']];
        }
        usort($out, fn ($a, $b) => [$b['sales'], $b['customers'], $b['followups']] <=> [$a['sales'], $a['customers'], $a['followups']]);

        return $out;
    }

    public static function topProducts(Carbon $from, Carbon $to, ?int $uid): array
    {
        return Deal::query()->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])->when($uid, fn ($q) => $q->where('created_by', $uid))
            ->whereNotNull('vehicle')->selectRaw('vehicle, COUNT(*) c, SUM(total_price) s')->groupBy('vehicle')->orderByDesc('c')->limit(6)->get()
            ->map(fn ($r) => ['name' => $r->vehicle, 'count' => (int) $r->c, 'value' => (float) $r->s])->all();
    }

    public static function build(string $period, Carbon $anchor, ?User $user): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'day';
        $uid = $user && ! $user->isAdmin() ? $user->id : null;
        [$from, $to] = self::range($period, $anchor);
        [$pf, $pt] = self::previous($period, $from);
        $cur = self::metrics($from, $to, $uid);
        $prev = self::metrics($pf, $pt, $uid);

        $label = match ($period) {
            'day' => $from->translatedFormat('l j F Y'),
            'week' => 'من ' . $from->format('Y/m/d') . ' إلى ' . $to->format('Y/m/d'),
            'year' => $from->format('Y'),
            default => $from->translatedFormat('F Y'),
        };

        return [
            'period' => $period, 'from' => $from, 'to' => $to, 'label' => $label, 'cur' => $cur, 'prev' => $prev,
            'series' => self::series($from, $to, $uid), 'employees' => $uid ? [] : self::employees($from, $to),
            'products' => self::topProducts($from, $to, $uid), 'scope' => $uid ? 'ملخص أعمالك' : 'ملخص الشركة',
            'prev_label' => $pf->format('Y/m/d') . ($period === 'day' ? '' : ' — ' . $pt->format('Y/m/d')),
        ];
    }
}
