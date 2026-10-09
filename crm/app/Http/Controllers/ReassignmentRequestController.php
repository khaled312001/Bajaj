<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ReassignmentRequest;
use App\Support\Activity;
use Illuminate\Http\Request;

class ReassignmentRequestController extends Controller
{
    /** A colleague asks to take over a customer whose follow-up has gone unactioned for 48h+. */
    public function store(Request $request, Customer $customer)
    {
        $this->ensureCanSee($customer);
        $user = $request->user();
        abort_unless($customer->hasStaleFollowup(), 422, 'لا يمكن طلب نقل عميل متابعته غير متأخرة 48 ساعة.');
        abort_if($customer->assigned_to === $user->id, 422, 'هذا العميل مسؤول عنه بالفعل.');
        abort_if($customer->reassignmentRequests()->where('requested_by', $user->id)->where('status', 'pending')->exists(), 422, 'لديك طلب قيد المراجعة لهذا العميل.');

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);
        $r = $customer->reassignmentRequests()->create($data + [
            'requested_by' => $user->id, 'previous_assignee' => $customer->assigned_to, 'status' => 'pending',
        ]);
        Activity::log('reassign.request', $r, "طلب نقل العميل {$customer->name} إلى {$user->name}");
        Activity::customer($customer, 'reassign_request', "طلب {$user->name} نقل العميل إليه (متابعة متأخرة)");

        return back()->with('success', 'تم إرسال طلب النقل للمدير للموافقة.');
    }

    /** Admin: pending (and recently decided) takeover requests. */
    public function index()
    {
        return view('reassignments.index', [
            'pending' => ReassignmentRequest::with(['customer:id,name,code,assigned_to', 'requester:id,name', 'previousAssignee:id,name'])
                ->where('status', 'pending')->latest()->get(),
            'recent' => ReassignmentRequest::with(['customer:id,name,code', 'requester:id,name', 'decider:id,name'])
                ->whereIn('status', ['approved', 'rejected'])->latest('decided_at')->limit(15)->get(),
        ]);
    }

    public function approve(Request $request, ReassignmentRequest $reassignment)
    {
        abort_unless($reassignment->status === 'pending', 422, 'هذا الطلب تم اتخاذ قرار بشأنه بالفعل.');
        $customer = $reassignment->customer;
        $old = $customer->assignee?->name ?? 'بدون';
        $customer->update(['assigned_to' => $reassignment->requested_by]);
        $customer->followups()->where('status', 'pending')->update(['assigned_to' => $reassignment->requested_by]);
        $reassignment->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now()]);

        Activity::log('reassign.approve', $reassignment, "الموافقة على نقل العميل {$customer->name} من {$old} إلى {$reassignment->requester->name}");
        Activity::customer($customer, 'assigned', "تمت الموافقة على نقل العميل من {$old} إلى {$reassignment->requester->name} (طلب نقل)");

        return back()->with('success', 'تم نقل العميل وقبول الطلب.');
    }

    public function reject(Request $request, ReassignmentRequest $reassignment)
    {
        abort_unless($reassignment->status === 'pending', 422, 'هذا الطلب تم اتخاذ قرار بشأنه بالفعل.');
        $reassignment->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        Activity::log('reassign.reject', $reassignment, "رفض طلب نقل العميل {$reassignment->customer->name}");

        return back()->with('success', 'تم رفض الطلب.');
    }
}
