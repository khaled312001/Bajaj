<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = request()->user();
        if (! $user->allows('dashboard')) {
            foreach ([['customers', 'customers.index'], ['followups', 'followups.index'], ['calculator', 'calculator'], ['vehicles', 'vehicles.index'], ['documents', 'documents.index'], ['reports', 'reports.index']] as [$m, $r]) {
                if ($user->allows($m)) {
                    return redirect()->route($r);
                }
            }
            return redirect()->route('password.edit');
        }

        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $customers = Customer::query()->visibleTo($user);

        $stats = [
            'customers' => (clone $customers)->count(),
            'new_month' => (clone $customers)->where('customers.created_at', '>=', now()->startOfMonth())->count(),
            'done' => (clone $customers)->where('customers.status', 'منفذة')->count(),
            'following_today' => Followup::visibleTo($user)->pending()->whereDate('due_date', today())->count(),
            'overdue_followups' => Followup::visibleTo($user)->pending()->whereDate('due_date', '<', today())->count(),
        ];
        if ($isAdmin) {
            $stats += [
                'debt' => (float) Deal::sum('balance'),
                'collected_month' => (float) Payment::where('paid_on', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
                'overdue_installments' => Installment::where('due_date', '<', today()->toDateString())->whereColumn('paid_amount', '<', 'amount')->count(),
                'overdue_amount' => (float) Installment::where('due_date', '<', today()->toDateString())->whereColumn('paid_amount', '<', 'amount')->sum(DB::raw('amount - paid_amount')),
            ];
        } else {
            $stats += [
                'my_created' => Customer::where('created_by', $user->id)->count(),
                'done_followups_month' => Followup::where('completed_by', $user->id)->where('completed_at', '>=', now()->startOfMonth())->count(),
            ];
        }

        $todayFollowups = Followup::visibleTo($user)->pending()->with('customer:id,name,phone,code', 'assignee:id,name')
            ->whereDate('due_date', '<=', today())->orderBy('due_date')->orderByRaw("priority = 'high' desc")->limit(8)->get();

        $recent = (clone $customers)->with('creator:id,name')->latest('customers.created_at')->limit(7)->get();

        // charts
        $statusData = (clone $customers)->select('customers.status', DB::raw('COUNT(*) n'))->groupBy('customers.status')->pluck('n', 'status');
        $days = collect(range(13, 0))->map(fn ($i) => today()->subDays($i));
        $perDay = (clone $customers)->where('customers.created_at', '>=', today()->subDays(13))
            ->select(DB::raw('DATE(customers.created_at) d'), DB::raw('COUNT(*) n'))->groupBy('d')->pluck('n', 'd');

        $byEmployee = $isAdmin ? User::withCount(['customersCreated as created' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth())])
            ->orderByDesc('created')->limit(8)->get(['id', 'name']) : collect();

        $dueInstallments = $isAdmin ? Installment::with('deal.customer:id,name,phone')
            ->whereBetween('due_date', [today()->toDateString(), today()->addDays(7)->toDateString()])
            ->whereColumn('paid_amount', '<', 'amount')->orderBy('due_date')->limit(6)->get() : collect();

        $alerts = $isAdmin ? ActivityLog::with('user:id,name')->where('action', 'security.alert')->where('created_at', '>=', now()->subDays(3))->latest('created_at')->limit(5)->get() : collect();

        return view('dashboard', [
            'stats' => $stats, 'todayFollowups' => $todayFollowups, 'recent' => $recent,
            'statusChart' => ['labels' => $statusData->keys()->all(), 'data' => $statusData->values()->all()],
            'dayChart' => ['labels' => $days->map->format('m/d')->all(), 'data' => $days->map(fn ($d) => (int) ($perDay[$d->toDateString()] ?? 0))->all()],
            'byEmployee' => $byEmployee, 'dueInstallments' => $dueInstallments, 'alerts' => $alerts,
        ]);
    }

    /** Employee's own activity & performance. */
    public function myActivity(Request $request)
    {
        $user = $request->user();
        $logs = ActivityLog::where('user_id', $user->id)->whereNotIn('action', ['customer.view'])->latest('created_at')->paginate(30);

        return view('profile.activity', [
            'logs' => $logs,
            'stats' => [
                'created' => Customer::where('created_by', $user->id)->count(),
                'created_month' => Customer::where('created_by', $user->id)->where('created_at', '>=', now()->startOfMonth())->count(),
                'followups_done' => Followup::where('completed_by', $user->id)->count(),
                'followups_month' => Followup::where('completed_by', $user->id)->where('completed_at', '>=', now()->startOfMonth())->count(),
                'pending' => Followup::where('assigned_to', $user->id)->pending()->count(),
            ],
        ]);
    }
}
