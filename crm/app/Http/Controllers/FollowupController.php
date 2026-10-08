<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Followup;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;

class FollowupController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $base = Followup::query()->visibleTo($user);

        if ($user->isAdmin() && ($e = $request->query('employee'))) {
            $base->where('followups.assigned_to', $e);
        }
        if ($r = $request->query('reason')) {
            $base->where('followups.reason', $r);
        }
        if ($p = $request->query('priority')) {
            $base->where('followups.priority', $p);
        }
        if ($s = trim((string) $request->query('q'))) {
            $base->whereHas('customer', fn ($c) => $c->search($s));
        }

        $today = today();
        $counts = [
            'overdue' => (clone $base)->pending()->whereDate('due_date', '<', $today)->count(),
            'today' => (clone $base)->pending()->whereDate('due_date', $today)->count(),
            'upcoming' => (clone $base)->pending()->whereDate('due_date', '>', $today)->count(),
            'done' => (clone $base)->where('status', 'done')->count(),
        ];

        $tab = in_array($request->query('tab'), ['overdue', 'today', 'upcoming', 'done', 'all'], true)
            ? $request->query('tab') : ($counts['overdue'] > 0 && ! $counts['today'] ? 'overdue' : 'today');

        $q = (clone $base)->with(['customer:id,name,phone,code,whatsapp', 'assignee:id,name', 'creator:id,name', 'completer:id,name']);
        match ($tab) {
            'overdue' => $q->pending()->whereDate('due_date', '<', $today)->orderBy('due_date')->orderBy('due_time'),
            'today' => $q->pending()->whereDate('due_date', $today)->orderByRaw("priority = 'high' desc")->orderBy('due_time'),
            'upcoming' => $q->pending()->whereDate('due_date', '>', $today)->orderBy('due_date')->orderBy('due_time'),
            'done' => $q->where('status', 'done')->orderByDesc('completed_at'),
            default => $q->orderByRaw("status = 'pending' desc")->orderBy('due_date'),
        };
        $items = $q->paginate(25)->withQueryString();

        return view('followups.index', [
            'items' => $items, 'counts' => $counts, 'tab' => $tab,
            'staff' => $user->isAdmin() ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'reasons' => Lookup::list('followup_reason'),
            'filters' => $request->query(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'deal_id' => ['nullable', 'exists:deals,id'],
            'reason' => ['required', 'string', 'max:120'],
            'priority' => ['nullable', 'in:normal,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ], ['due_date.after_or_equal' => 'تاريخ المتابعة يجب ألا يكون في الماضي.']);

        $customer = Customer::findOrFail($data['customer_id']);
        $this->ensureCanSee($customer);
        $user = $request->user();

        $assignee = $user->isAdmin() && ! empty($data['assigned_to']) ? (int) $data['assigned_to'] : ($customer->assigned_to ?: $user->id);
        if ($user->isAgent()) {
            $assignee = $user->id;
        }

        $followup = Followup::create($data + ['created_by' => $user->id]);
        $followup->forceFill(['assigned_to' => $assignee, 'priority' => $data['priority'] ?? 'normal'])->save();

        Activity::log('followup.create', $followup, 'متابعة جديدة للعميل ' . $customer->name . ' بتاريخ ' . $followup->due_date->format('Y/m/d'));
        Activity::customer($customer, 'followup', "جدولة متابعة ({$followup->reason}) بتاريخ " . $followup->due_date->format('Y/m/d'));

        return back()->with('success', 'تمت جدولة المتابعة.');
    }

    /** Mark done (optionally schedule the next one). */
    public function complete(Request $request, Followup $followup)
    {
        $this->authorizeFollowup($followup);
        $data = $request->validate([
            'outcome' => ['required', 'string', 'max:2000'],
            'next_date' => ['nullable', 'date', 'after_or_equal:today'],
            'next_reason' => ['nullable', 'string', 'max:120'],
        ], ['outcome.required' => 'اكتب نتيجة المتابعة.']);

        $followup->update([
            'status' => 'done', 'outcome' => $data['outcome'],
            'completed_at' => now(), 'completed_by' => $request->user()->id,
        ]);
        Activity::log('followup.done', $followup, 'إتمام متابعة العميل ' . $followup->customer->name);
        Activity::customer($followup->customer, 'followup_done', 'تمت المتابعة (' . $followup->reason . '): ' . $data['outcome']);

        if (! empty($data['next_date'])) {
            $next = Followup::create([
                'customer_id' => $followup->customer_id, 'deal_id' => $followup->deal_id,
                'assigned_to' => $followup->assigned_to ?: $request->user()->id, 'created_by' => $request->user()->id,
                'reason' => ($data['next_reason'] ?? null) ?: $followup->reason, 'due_date' => $data['next_date'], 'priority' => $followup->priority,
            ]);
            Activity::customer($followup->customer, 'followup', 'جدولة متابعة تالية بتاريخ ' . $next->due_date->format('Y/m/d'));
        }

        return back()->with('success', 'تم إنهاء المتابعة.');
    }

    public function update(Request $request, Followup $followup)
    {
        $this->authorizeFollowup($followup);
        $data = $request->validate([
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', 'in:normal,high'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $old = $followup->due_date->format('Y/m/d');
        $followup->update(array_filter($data, fn ($v) => $v !== null) + ['due_time' => $data['due_time'] ?? null]);

        Activity::log('followup.update', $followup, 'تعديل/تأجيل متابعة العميل ' . $followup->customer->name);
        if ($old !== $followup->due_date->format('Y/m/d')) {
            Activity::customer($followup->customer, 'followup', "تأجيل المتابعة من {$old} إلى " . $followup->due_date->format('Y/m/d'));
        }

        return back()->with('success', 'تم تحديث المتابعة.');
    }

    public function destroy(Followup $followup)
    {
        $this->authorizeFollowup($followup);
        abort_unless(auth()->user()->isAdmin() || $followup->status === 'pending', 403);
        $followup->update(['status' => 'cancelled']);
        Activity::log('followup.delete', $followup, 'إلغاء متابعة العميل ' . $followup->customer->name);

        return back()->with('success', 'تم إلغاء المتابعة.');
    }

    private function authorizeFollowup(Followup $followup): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $followup->assigned_to === $user->id || $followup->created_by === $user->id, 403);
    }
}
