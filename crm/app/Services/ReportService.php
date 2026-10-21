<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every report returns the same shape so the UI (table + chart) and the Excel export share one source:
 * [title, cards[label=>value], columns[], rows[][], money[col idx 1-based], chart?{type,labels,data,label}]
 */
class ReportService
{
    public const REPORTS = [
        'summary' => ['title' => 'ملخص الأداء العام', 'icon' => 'fa-gauge-high', 'desc' => 'عملاء، صفقات، تحصيل ومتابعات خلال الفترة'],
        'employees' => ['title' => 'أداء الموظفين', 'icon' => 'fa-user-tie', 'desc' => 'ما أدخله وأنجزه كل موظف خدمة عملاء'],
        'tracking' => ['title' => 'تتبع العملاء (من أدخل من)', 'icon' => 'fa-route', 'desc' => 'كل عميل والموظف الذي أدخله والمسؤول عنه حالياً'],
        'sales' => ['title' => 'المبيعات والصفقات', 'icon' => 'fa-car', 'desc' => 'الصفقات المسجلة بالأسعار والمقدمات'],
        'installments' => ['title' => 'الأقساط المستحقة', 'icon' => 'fa-calendar-days', 'desc' => 'أقساط تستحق خلال الفترة وغير مسددة'],
        'overdue' => ['title' => 'المتأخرات (أعمار الديون)', 'icon' => 'fa-triangle-exclamation', 'desc' => 'الأقساط المتأخرة مقسّمة حسب مدة التأخر'],
        'collections' => ['title' => 'التحصيل', 'icon' => 'fa-money-bill-trend-up', 'desc' => 'الدفعات المحصّلة يومياً وحسب الموظف'],
        'channels' => ['title' => 'العملاء حسب القناة', 'icon' => 'fa-bullhorn', 'desc' => 'مصادر العملاء ونسب التحويل'],
        'governorates' => ['title' => 'العملاء حسب المحافظة', 'icon' => 'fa-map-location-dot', 'desc' => 'التوزيع الجغرافي للعملاء'],
        'seriousness' => ['title' => 'جدية العملاء', 'icon' => 'fa-fire', 'desc' => 'توزيع العملاء حسب الجدية ونسب التحويل'],
        'branches' => ['title' => 'العملاء حسب الفرع', 'icon' => 'fa-code-branch', 'desc' => 'توزيع العملاء والمبيعات على الفروع'],
        'loss' => ['title' => 'أسباب عدم إتمام البيع', 'icon' => 'fa-user-xmark', 'desc' => 'أكثر أسباب فقدان العملاء تكراراً'],
        'inventory' => ['title' => 'المخزن والمركبات', 'icon' => 'fa-warehouse', 'desc' => 'المتاح والمباع والتكلفة لكل نوع مركبة'],
        'vehicles' => ['title' => 'المركبات الأكثر طلباً', 'icon' => 'fa-motorcycle', 'desc' => 'الطلب والمبيعات لكل مركبة'],
        'finance' => ['title' => 'جهات التقسيط', 'icon' => 'fa-building-columns', 'desc' => 'عدد الصفقات وقيمتها لكل جهة'],
        'entity_dues' => ['title' => 'مستحقات جهات التقسيط', 'icon' => 'fa-building-columns', 'desc' => 'المتبقي على كل جهة: لم يُودع، مجمد، متأخر، وما تم تحصيله', 'filters' => ['entity', 'branch', 'state']],
        'entity_sent' => ['title' => 'العملاء المرسلين لجهات التقسيط', 'icon' => 'fa-share-from-square', 'desc' => 'مين اتبعت لكل جهة وفرع، نفذ ولا لأ، وآخر متابعة', 'filters' => ['entity', 'branch', 'result']],
        'followups' => ['title' => 'أداء المتابعات', 'icon' => 'fa-calendar-check', 'desc' => 'المنجزة والمتأخرة حسب السبب'],
    ];

    public const ENTITY_STATES = ['pending' => 'لم يتم الإيداع', 'deposited' => 'مجمد (تم الإيداع)', 'late' => 'متأخر', 'collected' => 'تم التحصيل'];

    public const SENT_RESULTS = ['done' => 'نفذ مع الجهة', 'not_done' => 'لم ينفذ', 'open' => 'قيد المتابعة'];

    /** $opts: entity / branch / state / result filters of the finance entity reports. */
    public function build(string $key, Carbon $from, Carbon $to, ?int $employee = null, array $opts = []): array
    {
        abort_unless(isset(self::REPORTS[$key]), 404);
        $fn = 'report' . Str::studly($key);

        return ['key' => $key, 'from' => $from, 'to' => $to] + $this->$fn($from->copy()->startOfDay(), $to->copy()->endOfDay(), $employee, array_filter($opts));
    }

    /* ------------------------------------------------------------ individual reports */

    private function reportSummary(Carbon $f, Carbon $t, ?int $emp): array
    {
        $cust = Customer::whereBetween('created_at', [$f, $t])->when($emp, fn ($q) => $q->where('created_by', $emp));
        $deals = Deal::whereBetween('created_at', [$f, $t])->when($emp, fn ($q) => $q->where('created_by', $emp));
        $pay = Payment::whereBetween('paid_on', [$f->toDateString(), $t->toDateString()])->when($emp, fn ($q) => $q->where('received_by', $emp));
        $fu = Followup::whereBetween('due_date', [$f->toDateString(), $t->toDateString()])->when($emp, fn ($q) => $q->where('assigned_to', $emp));

        $days = $this->dailySeries(Customer::class, 'created_at', $f, $t, $emp ? ['created_by', $emp] : null);

        return [
            'title' => 'ملخص الأداء العام',
            'cards' => [
                'عملاء جدد' => (clone $cust)->count(),
                'صفقات جديدة' => (clone $deals)->count(),
                'صفقات منفذة' => (clone $deals)->where('status', 'منفذة')->count(),
                'قيمة الصفقات' => (float) (clone $deals)->sum('total_price'),
                'المحصّل في الفترة' => (float) (clone $pay)->sum('amount'),
                'إجمالي المديونية الحالية' => (float) Deal::sum('balance'),
                'متابعات منجزة' => (clone $fu)->where('status', 'done')->count(),
                'متابعات متأخرة' => Followup::pending()->where('due_date', '<', today())->when($emp, fn ($q) => $q->where('assigned_to', $emp))->count(),
            ],
            'money_cards' => ['قيمة الصفقات', 'المحصّل في الفترة', 'إجمالي المديونية الحالية'],
            'columns' => ['اليوم', 'عملاء جدد'],
            'rows' => array_map(fn ($d, $n) => [$d, $n], array_keys($days), $days),
            'money' => [],
            'chart' => ['type' => 'line', 'labels' => array_keys($days), 'data' => array_values($days), 'label' => 'عملاء جدد'],
        ];
    }

    private function reportEmployees(Carbon $f, Carbon $t, ?int $emp): array
    {
        $users = User::when($emp, fn ($q) => $q->where('id', $emp))->orderBy('name')->get();
        $rows = [];
        foreach ($users as $u) {
            $rows[] = [
                $u->name, $u->role_label,
                Customer::where('created_by', $u->id)->whereBetween('created_at', [$f, $t])->count(),
                Customer::where('assigned_to', $u->id)->count(),
                Deal::where('created_by', $u->id)->whereBetween('created_at', [$f, $t])->count(),
                Deal::where('created_by', $u->id)->where('status', 'منفذة')->whereBetween('created_at', [$f, $t])->count(),
                (float) Payment::where('received_by', $u->id)->whereBetween('paid_on', [$f->toDateString(), $t->toDateString()])->sum('amount'),
                Followup::where('completed_by', $u->id)->where('status', 'done')->whereBetween('completed_at', [$f, $t])->count(),
                Followup::pending()->where('assigned_to', $u->id)->count(),
                Followup::pending()->where('assigned_to', $u->id)->where('due_date', '<', today())->count(),
                $u->last_login_at?->format('Y/m/d H:i') ?? '—',
            ];
        }

        return [
            'title' => 'أداء الموظفين',
            'cards' => [],
            'columns' => ['الموظف', 'الدور', 'عملاء أدخلهم', 'عملاء مسؤول عنهم', 'صفقات', 'صفقات منفذة', 'تحصيل', 'متابعات منجزة', 'متابعات معلّقة', 'متابعات متأخرة', 'آخر دخول'],
            'rows' => $rows, 'money' => [7],
            'chart' => ['type' => 'bar', 'labels' => array_column($rows, 0), 'data' => array_column($rows, 2), 'label' => 'عملاء أدخلهم'],
        ];
    }

    private function reportTracking(Carbon $f, Carbon $t, ?int $emp): array
    {
        $q = Customer::with(['creator:id,name', 'assignee:id,name'])->whereBetween('created_at', [$f, $t])
            ->when($emp, fn ($q) => $q->where('created_by', $emp))->orderByDesc('created_at')->limit(5000)->get();
        $last = DB::table('customer_events')->whereIn('customer_id', $q->pluck('id'))->select('customer_id', DB::raw('MAX(created_at) as last'))->groupBy('customer_id')->pluck('last', 'customer_id');

        $rows = $q->map(fn ($c) => [
            $c->code, $c->name, $c->phone, $c->governorate ?? '—', $c->status,
            $c->creator?->name ?? '—', $c->created_at->format('Y/m/d H:i'), $c->assignee?->name ?? '—',
            isset($last[$c->id]) ? Carbon::parse($last[$c->id])->format('Y/m/d H:i') : '—',
        ])->all();

        $by = $q->groupBy(fn ($c) => $c->creator?->name ?? 'غير معروف')->map->count()->sortDesc();

        return [
            'title' => 'تتبع العملاء — من أدخل كل عميل', 'cards' => ['إجمالي العملاء في الفترة' => count($rows)],
            'columns' => ['الكود', 'العميل', 'الهاتف', 'المحافظة', 'الحالة', 'أدخله', 'تاريخ الإدخال', 'المسؤول حالياً', 'آخر نشاط'],
            'rows' => $rows, 'money' => [],
            'chart' => ['type' => 'bar', 'labels' => $by->keys()->all(), 'data' => $by->values()->all(), 'label' => 'عملاء حسب من أدخلهم'],
        ];
    }

    private function reportSales(Carbon $f, Carbon $t, ?int $emp): array
    {
        $deals = Deal::with(['customer:id,code,name,phone', 'creator:id,name'])->whereBetween('created_at', [$f, $t])
            ->when($emp, fn ($q) => $q->where('created_by', $emp))->orderByDesc('created_at')->limit(5000)->get();
        $rows = $deals->map(fn ($d) => [
            $d->created_at->format('Y/m/d'), $d->customer?->code, $d->customer?->name, $d->vehicle ?? '—', $d->pay_method,
            $d->finance_entity ?? '—', $d->status, $d->total_price, $d->down_payment, $d->financed_amount,
            $d->monthly_installment ?: null, $d->balance, $d->creator?->name ?? '—',
        ])->all();

        $byStatus = $deals->groupBy('status')->map->count();

        return [
            'title' => 'المبيعات والصفقات',
            'cards' => ['عدد الصفقات' => $deals->count(), 'إجمالي القيمة' => (float) $deals->sum('total_price'), 'إجمالي المقدمات' => (float) $deals->sum('down_payment'), 'إجمالي المديونية' => (float) $deals->sum('balance')],
            'money_cards' => ['إجمالي القيمة', 'إجمالي المقدمات', 'إجمالي المديونية'],
            'columns' => ['التاريخ', 'كود العميل', 'العميل', 'المركبة', 'الدفع', 'الجهة', 'الحالة', 'السعر', 'المقدم', 'الممول', 'القسط', 'المتبقي', 'الموظف'],
            'rows' => $rows, 'money' => [8, 9, 10, 11, 12],
            'chart' => ['type' => 'doughnut', 'labels' => $byStatus->keys()->all(), 'data' => $byStatus->values()->all(), 'label' => 'الصفقات حسب الحالة'],
        ];
    }

    private function reportInstallments(Carbon $f, Carbon $t, ?int $emp): array
    {
        $items = Installment::with(['deal.customer:id,code,name,phone'])
            ->whereHas('deal')
            ->whereBetween('due_date', [$f->toDateString(), $t->toDateString()])
            ->whereColumn('paid_amount', '<', 'amount')->orderBy('due_date')->limit(5000)->get();
        $rows = $items->map(fn ($i) => [
            $i->due_date->format('Y/m/d'), $i->deal?->customer?->code, $i->deal?->customer?->name, $i->deal?->customer?->phone,
            $i->deal?->vehicle ?? '—', $i->number . ' / ' . $i->deal?->months, $i->amount, $i->paid_amount, $i->remaining,
            $i->due_date->isPast() && ! $i->due_date->isToday() ? (int) $i->due_date->diffInDays(today()) : 0,
        ])->all();

        $byMonth = $items->groupBy(fn ($i) => $i->due_date->format('Y-m'))->map(fn ($g) => round($g->sum('remaining'), 2));

        return [
            'title' => 'الأقساط المستحقة', 'cards' => ['عدد الأقساط' => $items->count(), 'إجمالي المتبقي' => (float) $items->sum('remaining')], 'money_cards' => ['إجمالي المتبقي'],
            'columns' => ['الاستحقاق', 'كود العميل', 'العميل', 'الهاتف', 'المركبة', 'القسط', 'قيمته', 'المسدد', 'المتبقي', 'أيام التأخير'],
            'rows' => $rows, 'money' => [7, 8, 9],
            'chart' => ['type' => 'bar', 'labels' => $byMonth->keys()->all(), 'data' => $byMonth->values()->all(), 'label' => 'المستحق شهرياً'],
        ];
    }

    private function reportOverdue(Carbon $f, Carbon $t, ?int $emp): array
    {
        $items = Installment::with(['deal.customer:id,code,name,phone,assigned_to', 'deal.customer.assignee:id,name'])
            ->whereHas('deal')
            ->where('due_date', '<', today()->toDateString())->whereColumn('paid_amount', '<', 'amount')->orderBy('due_date')->limit(5000)->get();

        $bucket = fn ($days) => $days <= 30 ? '1–30 يوم' : ($days <= 60 ? '31–60 يوم' : ($days <= 90 ? '61–90 يوم' : 'أكثر من 90 يوم'));
        $rows = $items->map(function ($i) use ($bucket) {
            $days = (int) $i->due_date->diffInDays(today());

            return [$bucket($days), $i->deal?->customer?->code, $i->deal?->customer?->name, $i->deal?->customer?->phone, $i->deal?->vehicle ?? '—',
                $i->number, $i->due_date->format('Y/m/d'), $days, $i->remaining, $i->deal?->customer?->assignee?->name ?? '—'];
        })->all();

        $buckets = ['1–30 يوم' => 0, '31–60 يوم' => 0, '61–90 يوم' => 0, 'أكثر من 90 يوم' => 0];
        foreach ($rows as $r) {
            $buckets[$r[0]] += $r[8];
        }

        return [
            'title' => 'المتأخرات وأعمار الديون',
            'cards' => ['عدد الأقساط المتأخرة' => count($rows), 'إجمالي المتأخر' => round(array_sum($buckets), 2)] + array_map(fn ($v) => round($v, 2), $buckets),
            'money_cards' => array_merge(['إجمالي المتأخر'], array_keys($buckets)),
            'columns' => ['الفئة', 'كود العميل', 'العميل', 'الهاتف', 'المركبة', 'القسط', 'الاستحقاق', 'أيام التأخير', 'المتبقي', 'المسؤول'],
            'rows' => $rows, 'money' => [9],
            'chart' => ['type' => 'bar', 'labels' => array_keys($buckets), 'data' => array_map(fn ($v) => round($v, 2), array_values($buckets)), 'label' => 'قيمة المتأخر'],
        ];
    }

    private function reportCollections(Carbon $f, Carbon $t, ?int $emp): array
    {
        $pays = Payment::with(['deal.customer:id,code,name', 'receiver:id,name'])->whereBetween('paid_on', [$f->toDateString(), $t->toDateString()])
            ->when($emp, fn ($q) => $q->where('received_by', $emp))->orderByDesc('paid_on')->limit(5000)->get();
        $rows = $pays->map(fn ($p) => [$p->paid_on->format('Y/m/d'), $p->deal?->customer?->code, $p->deal?->customer?->name, $p->deal?->vehicle ?? '—', $p->amount, $p->method ?? '—', $p->receiver?->name ?? '—', $p->note ?? ''])->all();
        $byDay = $pays->groupBy(fn ($p) => $p->paid_on->format('m/d'))->map(fn ($g) => round($g->sum('amount'), 2))->reverse();

        return [
            'title' => 'التحصيل', 'cards' => ['عدد الدفعات' => $pays->count(), 'إجمالي المحصّل' => (float) $pays->sum('amount')], 'money_cards' => ['إجمالي المحصّل'],
            'columns' => ['التاريخ', 'كود العميل', 'العميل', 'المركبة', 'المبلغ', 'الطريقة', 'استلمها', 'ملاحظة'],
            'rows' => $rows, 'money' => [5],
            'chart' => ['type' => 'bar', 'labels' => $byDay->keys()->all(), 'data' => $byDay->values()->all(), 'label' => 'المحصّل يومياً'],
        ];
    }

    private function dimension(string $title, string $column, Carbon $f, Carbon $t, ?int $emp, string $label): array
    {
        $rows = DB::table('customers')->leftJoin('deals', fn ($j) => $j->on('deals.customer_id', '=', 'customers.id')->whereNull('deals.deleted_at'))
            ->whereNull('customers.deleted_at')->whereBetween('customers.created_at', [$f, $t])
            ->when($emp, fn ($q) => $q->where('customers.created_by', $emp))
            ->selectRaw("COALESCE(NULLIF(customers.$column,''),'غير محدد') as k, COUNT(DISTINCT customers.id) as customers, COUNT(DISTINCT CASE WHEN customers.status='منفذة' THEN customers.id END) as won, COALESCE(SUM(deals.total_price),0) as value")
            ->groupBy('k')->orderByDesc('customers')->get();

        $out = $rows->map(fn ($r) => [$r->k, (int) $r->customers, (int) $r->won, $r->customers ? round($r->won / $r->customers * 100, 1) . '%' : '0%', (float) $r->value])->all();

        return [
            'title' => $title, 'cards' => ['إجمالي العملاء' => (int) $rows->sum('customers'), 'منفذة' => (int) $rows->sum('won')],
            'columns' => [$label, 'عدد العملاء', 'مبيعات منفذة', 'نسبة التحويل', 'قيمة الصفقات'], 'rows' => $out, 'money' => [5],
            'chart' => ['type' => 'bar', 'labels' => array_column($out, 0), 'data' => array_column($out, 1), 'label' => 'عدد العملاء'],
        ];
    }

    private function reportChannels(Carbon $f, Carbon $t, ?int $emp): array
    {
        return $this->dimension('العملاء حسب القناة', 'channel', $f, $t, $emp, 'القناة');
    }

    private function reportGovernorates(Carbon $f, Carbon $t, ?int $emp): array
    {
        return $this->dimension('العملاء حسب المحافظة', 'governorate', $f, $t, $emp, 'المحافظة');
    }

    private function reportSeriousness(Carbon $f, Carbon $t, ?int $emp): array
    {
        return $this->dimension('جدية العملاء', 'seriousness', $f, $t, $emp, 'الجدية');
    }

    private function reportBranches(Carbon $f, Carbon $t, ?int $emp): array
    {
        return $this->dimension('العملاء حسب الفرع', 'branch', $f, $t, $emp, 'الفرع');
    }

    private function reportLoss(Carbon $f, Carbon $t, ?int $emp): array
    {
        $rows = Customer::where('status', 'مفقودة')->whereBetween('created_at', [$f, $t])->when($emp, fn ($q) => $q->where('created_by', $emp))
            ->selectRaw("COALESCE(NULLIF(loss_reason,''),'غير محدد') as k, COUNT(*) as n")->groupBy('k')->orderByDesc('n')->limit(40)->get();
        $total = (int) $rows->sum('n');
        $out = $rows->map(fn ($r) => [$r->k, (int) $r->n, $total ? round($r->n / $total * 100, 1) . '%' : '0%'])->all();

        return [
            'title' => 'أسباب عدم إتمام البيع', 'cards' => ['عملاء مفقودون' => $total, 'عدد الأسباب' => count($out)],
            'columns' => ['السبب', 'عدد العملاء', 'النسبة'], 'rows' => $out, 'money' => [],
            'chart' => ['type' => 'bar', 'labels' => array_slice(array_column($out, 0), 0, 12), 'data' => array_slice(array_column($out, 1), 0, 12), 'label' => 'عدد العملاء'],
        ];
    }

    private function reportInventory(Carbon $f, Carbon $t, ?int $emp): array
    {
        $rows = \App\Models\Vehicle::selectRaw("COALESCE(NULLIF(type,''),'غير محدد') as k, SUM(status='in_stock') as stock, SUM(status='reserved') as res, SUM(status='sold') as sold, SUM(CASE WHEN status='in_stock' THEN cost_price+transport_cost+other_cost ELSE 0 END) as stock_cost, SUM(CASE WHEN status='sold' THEN COALESCE(sale_price,0) ELSE 0 END) as sold_value")
            ->groupBy('k')->orderByDesc('stock')->get();
        $out = $rows->map(fn ($r) => [$r->k, (int) $r->stock, (int) $r->res, (int) $r->sold, (float) $r->stock_cost, (float) $r->sold_value])->all();

        return [
            'title' => 'المخزن والمركبات', 'cards' => ['متاحة' => (int) $rows->sum('stock'), 'محجوزة' => (int) $rows->sum('res'), 'مباعة' => (int) $rows->sum('sold'), 'تكلفة المخزون' => (float) $rows->sum('stock_cost')],
            'columns' => ['النوع', 'متاحة', 'محجوزة', 'مباعة', 'تكلفة المخزون', 'قيمة المباع'], 'rows' => $out, 'money' => [5, 6],
            'chart' => ['type' => 'bar', 'labels' => array_column($out, 0), 'data' => array_column($out, 1), 'label' => 'متاحة بالمخزن'],
        ];
    }

    private function reportVehicles(Carbon $f, Carbon $t, ?int $emp): array
    {
        $rows = Deal::whereBetween('created_at', [$f, $t])->when($emp, fn ($q) => $q->where('created_by', $emp))
            ->selectRaw("COALESCE(NULLIF(vehicle,''),'غير محدد') as k, COUNT(*) as n, SUM(status='منفذة') as won, SUM(total_price) as value, SUM(balance) as bal")
            ->groupBy('k')->orderByDesc('n')->get();
        $out = $rows->map(fn ($r) => [$r->k, (int) $r->n, (int) $r->won, (float) $r->value, (float) $r->bal])->all();

        return [
            'title' => 'المركبات الأكثر طلباً', 'cards' => ['إجمالي الصفقات' => (int) $rows->sum('n')],
            'columns' => ['المركبة', 'عدد الصفقات', 'منفذة', 'القيمة', 'المديونية'], 'rows' => $out, 'money' => [4, 5],
            'chart' => ['type' => 'bar', 'labels' => array_column($out, 0), 'data' => array_column($out, 1), 'label' => 'عدد الصفقات'],
        ];
    }

    private function reportFinance(Carbon $f, Carbon $t, ?int $emp): array
    {
        $rows = Deal::whereBetween('created_at', [$f, $t])->where('pay_method', 'تقسيط')->when($emp, fn ($q) => $q->where('created_by', $emp))
            ->selectRaw("COALESCE(NULLIF(finance_entity,''),'بدون جهة') as k, COUNT(*) as n, SUM(financed_amount) as fin, SUM(paid_total) as paid, SUM(balance) as bal")
            ->groupBy('k')->orderByDesc('n')->get();
        $out = $rows->map(fn ($r) => [$r->k, (int) $r->n, (float) $r->fin, (float) $r->paid, (float) $r->bal])->all();

        return [
            'title' => 'جهات التقسيط', 'cards' => ['صفقات التقسيط' => (int) $rows->sum('n'), 'إجمالي الممول' => (float) $rows->sum('fin')], 'money_cards' => ['إجمالي الممول'],
            'columns' => ['الجهة', 'عدد الصفقات', 'المبلغ الممول', 'المحصّل', 'المتبقي'], 'rows' => $out, 'money' => [3, 4, 5],
            'chart' => ['type' => 'doughnut', 'labels' => array_column($out, 0), 'data' => array_column($out, 1), 'label' => 'الصفقات'],
        ];
    }

    /** What each finance entity still owes us (whenever it was sold) and what it paid during the period. */
    private function reportEntityDues(Carbon $f, Carbon $t, ?int $emp, array $o = []): array
    {
        $deals = Deal::query()->entityFinanced()->with(['customer:id,code,name,phone', 'creator:id,name'])
            ->whereIn('status', ['جاري التقسيط', 'منفذة'])->where('financed_amount', '>', 0)
            ->when($emp, fn ($q) => $q->where('created_by', $emp))
            ->when($o['entity'] ?? null, fn ($q, $v) => $q->where('finance_entity', $v))
            ->when($o['branch'] ?? null, fn ($q, $v) => $q->where('entity_branch', $v))
            ->where(fn ($w) => $w->whereNull('entity_collected_at')->orWhereBetween('entity_collected_at', [$f->toDateString(), $t->toDateString()]))
            ->orderBy('finance_entity')->orderBy('sale_date')->orderBy('id')->limit(5000)->get();
        $state = fn (Deal $d) => $d->entity_state === 'collected' ? 'collected' : ($d->entity_late_days > 0 ? 'late' : $d->entity_state);
        if ($want = $o['state'] ?? null) {
            $deals = $deals->filter(fn ($d) => $state($d) === $want)->values();
        }

        $rows = $deals->map(fn (Deal $d) => [
            $d->finance_entity, $d->entity_branch ?? '—', $d->customer?->code, $d->customer?->name, trim($d->vehicle . ' ' . $d->model) ?: '—',
            ($d->sale_date ?? $d->created_at)->format('Y/m/d'), $d->entity_amount ?: $d->total_payable,
            $d->entity_deposited_at?->format('Y/m/d') ?? '—', $d->entity_ref ?? '—', $d->entity_collected_at?->format('Y/m/d') ?? '—',
            trim($d->entity_channel . ' ' . $d->entity_account) ?: '—', $d->entity_state_label, $d->balance, $d->creator?->name ?? '—',
        ])->all();
        $byEntity = $deals->groupBy('finance_entity')->map(fn ($g) => round($g->sum('balance'), 2))->sortDesc();

        return [
            'title' => 'مستحقات جهات التقسيط',
            'cards' => [
                'المستحق على الجهات' => round($deals->sum('balance'), 2),
                'منه متأخر' => round($deals->filter(fn ($d) => $state($d) === 'late')->sum('balance'), 2),
                'مجمد (تم الإيداع)' => round($deals->filter(fn ($d) => $state($d) === 'deposited')->sum(fn ($d) => $d->entity_amount ?: $d->balance), 2),
                'تم تحصيله في الفترة' => round($deals->filter(fn ($d) => $d->entity_state === 'collected')->sum('entity_amount'), 2),
            ],
            'money_cards' => ['المستحق على الجهات', 'منه متأخر', 'مجمد (تم الإيداع)', 'تم تحصيله في الفترة'],
            'columns' => ['الجهة', 'الفرع', 'الكود', 'العميل', 'المركبة', 'تاريخ البيع', 'المستحق', 'تاريخ الإيداع', 'رقم المرجع', 'تاريخ التحصيل', 'اتحصلت على', 'الحالة', 'المتبقي', 'أدخلها'],
            'rows' => $rows, 'money' => [7, 13],
            'chart' => ['type' => 'bar', 'labels' => $byEntity->keys()->all(), 'data' => $byEntity->values()->all(), 'label' => 'المتبقي على كل جهة'],
        ];
    }

    /**
     * Customers sent to a finance entity (a requested vehicle sent there, or a deal through it), by entity and branch:
     * did they execute with it, and what the last follow-up says.
     */
    private function reportEntitySent(Carbon $f, Carbon $t, ?int $emp, array $o = []): array
    {
        $entity = $o['entity'] ?? null;
        $branch = $o['branch'] ?? null;
        $mine = fn ($q) => $q->where('assigned_to', $emp);

        $vehicles = CustomerVehicle::query()->whereNotNull('finance_entity')->where('finance_entity', '!=', '')
            ->whereBetween(DB::raw('COALESCE(entity_sent_at, created_at)'), [$f, $t])
            ->when($entity, fn ($q) => $q->where('finance_entity', $entity))->when($branch, fn ($q) => $q->where('entity_branch', $branch))
            ->when($emp, fn ($q) => $q->whereHas('customer', $mine))->orderByDesc('id')->limit(5000)->get();
        $deals = Deal::query()->entityFinanced()->whereBetween('created_at', [$f, $t])
            ->when($entity, fn ($q) => $q->where('finance_entity', $entity))->when($branch, fn ($q) => $q->where('entity_branch', $branch))
            ->when($emp, fn ($q) => $q->whereHas('customer', $mine))->orderByDesc('id')->limit(5000)->get();

        // one row per customer and entity; the deal (if any) has the final word
        $groups = [];
        foreach ($vehicles as $v) {
            $groups[$v->customer_id . '|' . $v->finance_entity]['vehicle'] ??= $v;
        }
        foreach ($deals as $d) {
            $groups[$d->customer_id . '|' . $d->finance_entity]['deal'] ??= $d;
        }
        $ids = array_unique(array_map(fn ($k) => (int) $k, array_keys($groups)));
        $customers = Customer::with('assignee:id,name')->whereIn('id', $ids)->get(['id', 'code', 'name', 'phone', 'status', 'assigned_to'])->keyBy('id');
        // to see a deal for this customer and this entity even if it was entered before the period
        $allDeals = Deal::whereIn('customer_id', $ids)->get(['id', 'customer_id', 'pay_method', 'finance_entity', 'entity_branch', 'status', 'stage', 'vehicle'])->groupBy('customer_id');
        $lastFu = \App\Models\Followup::whereIn('customer_id', $ids)->orderByDesc('due_date')->orderByDesc('id')
            ->get(['customer_id', 'due_date', 'reason', 'outcome', 'status'])->unique('customer_id')->keyBy('customer_id');

        $rows = [];
        $tally = ['done' => 0, 'not_done' => 0, 'open' => 0];
        $byGroup = [];
        foreach ($groups as $key => $g) {
            [$cid, $ent] = explode('|', $key, 2);
            $c = $customers[(int) $cid] ?? null;
            if (! $c) {
                continue;   // deleted customer
            }
            $theirs = $allDeals[$c->id] ?? collect();
            $deal = $g['deal'] ?? $theirs->first(fn ($d) => $d->finance_entity === $ent && $d->pay_method === 'تقسيط');
            $veh = $g['vehicle'] ?? null;
            $other = $theirs->first(fn ($d) => $d->status !== 'مفقودة' && ! ($d->finance_entity === $ent && $d->pay_method === 'تقسيط'));
            $stage = $deal?->stage ?: $veh?->stage;

            if ($deal && $deal->status !== 'مفقودة') {
                [$res, $label] = ['done', 'نفذ — ' . $deal->status];
            } elseif ($other) {
                [$res, $label] = ['not_done', 'لم ينفذ — اشترى ' . ($other->pay_method === 'كاش' ? 'كاش' : 'عن طريق ' . ($other->finance_entity ?: 'الشركة'))];
            } elseif ($c->status === 'مفقودة' || $deal || preg_match('/مرفوض|ملغي|سيئ/u', (string) $stage)) {
                [$res, $label] = ['not_done', 'لم ينفذ'];
            } else {
                [$res, $label] = ['open', 'قيد المتابعة'];
            }
            if (($o['result'] ?? null) && $o['result'] !== $res) {
                continue;
            }
            $tally[$res]++;
            $br = $deal?->entity_branch ?: $veh?->entity_branch;
            $byGroup[$ent . ($br ? ' — ' . $br : '')] = ($byGroup[$ent . ($br ? ' — ' . $br : '')] ?? 0) + 1;
            $fu = $lastFu[$c->id] ?? null;
            $sent = $veh ? ($veh->entity_sent_at ?? $veh->created_at) : $deal->created_at;

            $rows[] = [
                $sent->format('Y/m/d'), $c->code, $c->name, $c->phone, ($deal?->vehicle ?: $veh?->vehicle) ?: '—', $ent, $br ?: '—', $stage ?: '—', $label,
                $fu ? $fu->due_date->format('Y/m/d') . ' — ' . ($fu->outcome ?: $fu->reason) . ($fu->status === 'pending' ? ' (لم تتم)' : '') : '—',
                $c->assignee?->name ?? '—',
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b[0], $a[0]));
        arsort($byGroup);

        return [
            'title' => 'العملاء المرسلين لجهات التقسيط',
            'cards' => ['إجمالي المرسلين' => count($rows), 'نفذوا مع الجهة' => $tally['done'], 'لم ينفذوا' => $tally['not_done'], 'قيد المتابعة' => $tally['open']],
            'columns' => ['تاريخ الإرسال', 'الكود', 'العميل', 'الهاتف', 'المركبة', 'الجهة', 'الفرع', 'المرحلة', 'النتيجة', 'آخر متابعة', 'المسؤول'],
            'rows' => $rows, 'money' => [],
            'chart' => ['type' => 'bar', 'labels' => array_keys($byGroup), 'data' => array_values($byGroup), 'label' => 'العملاء حسب الجهة والفرع'],
        ];
    }

    private function reportFollowups(Carbon $f, Carbon $t, ?int $emp): array
    {
        $rows = Followup::whereBetween('due_date', [$f->toDateString(), $t->toDateString()])->when($emp, fn ($q) => $q->where('assigned_to', $emp))
            ->selectRaw("COALESCE(NULLIF(reason,''),'غير محدد') as k, COUNT(*) as n, SUM(status='done') as done, SUM(status='pending' AND due_date < CURDATE()) as late, SUM(status='pending' AND due_date >= CURDATE()) as open")
            ->groupBy('k')->orderByDesc('n')->get();
        $out = $rows->map(fn ($r) => [$r->k, (int) $r->n, (int) $r->done, (int) $r->open, (int) $r->late, $r->n ? round($r->done / $r->n * 100, 1) . '%' : '0%'])->all();

        return [
            'title' => 'أداء المتابعات', 'cards' => ['إجمالي المتابعات' => (int) $rows->sum('n'), 'منجزة' => (int) $rows->sum('done'), 'متأخرة' => (int) $rows->sum('late')],
            'columns' => ['السبب', 'الإجمالي', 'منجزة', 'قادمة', 'متأخرة', 'نسبة الإنجاز'], 'rows' => $out, 'money' => [],
            'chart' => ['type' => 'bar', 'labels' => array_column($out, 0), 'data' => array_column($out, 2), 'label' => 'منجزة'],
        ];
    }

    /** @return array<string,int> day => count */
    private function dailySeries(string $model, string $col, Carbon $f, Carbon $t, ?array $where): array
    {
        $rows = $model::query()->when($where, fn ($q) => $q->where($where[0], $where[1]))->whereBetween($col, [$f, $t])
            ->selectRaw("DATE($col) as d, COUNT(*) as n")->groupBy('d')->pluck('n', 'd');
        $out = [];
        for ($d = $f->copy(); $d->lte($t) && count($out) < 120; $d->addDay()) {
            $out[$d->format('m/d')] = (int) ($rows[$d->toDateString()] ?? 0);
        }

        return $out;
    }
}
