<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    /** Public, unauthenticated lead-capture page (shareable link). */
    public function publicForm()
    {
        abort_if(Settings::get('lead_form_enabled', '1') === '0', 404);

        return view('leads.public', [
            'title' => Settings::get('lead_form_title', 'اطلب مركبتك الآن'),
            'intro' => Settings::get('lead_form_intro', 'املأ بياناتك وسيتواصل معك فريق المبيعات في أقرب وقت.'),
        ]);
    }

    public function publicStore(Request $request)
    {
        abort_if(Settings::get('lead_form_enabled', '1') === '0', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20'],
            'vehicle' => ['nullable', 'string', 'max:150'],
            'governorate' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:250'],
        ], [], [
            'name' => 'الاسم', 'phone' => 'رقم الهاتف',
        ]);
        $phone = Customer::normalizePhone($data['phone']);
        abort_unless($phone && preg_match('/^\d{8,15}$/', $phone), 422, 'رقم الهاتف غير صالح.');
        $data['phone'] = $phone;
        $data['ip'] = $request->ip();

        Lead::create($data);

        return redirect()->route('lead.public')->with('success', 'تم استلام طلبك بنجاح، سنتواصل معك قريبًا.');
    }

    /** Admin: triage inbox for incoming leads. */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = Lead::query()->with(['assignee:id,name', 'customer:id,code'])->latest();
        if ($status && in_array($status, Lead::STATUSES, true)) {
            $q->where('status', $status);
        }

        return view('leads.index', [
            'leads' => $q->paginate(25)->withQueryString(),
            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'counts' => Lead::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'filters' => $request->query(),
            'publicUrl' => route('lead.public'),
        ]);
    }

    /** Admin: assign a batch of selected leads to one sales employee at once. */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        $employee = User::findOrFail($data['assigned_to']);
        $count = Lead::whereIn('id', $data['ids'])->update(['assigned_to' => $employee->id, 'status' => 'assigned']);
        Activity::log('lead.assign', null, "تعيين {$count} من الليدز إلى {$employee->name}");

        return back()->with('success', "تم تعيين {$count} طلب إلى {$employee->name}.");
    }

    /** Open the "new customer" form prefilled from a lead; marks it converted once the customer is saved. */
    public function convert(Request $request, Lead $lead)
    {
        abort_unless($request->user()->isAdmin() || $lead->assigned_to === $request->user()->id, 403);

        return redirect()->route('customers.create', [
            'phone' => $lead->phone, 'name' => $lead->name, 'interest' => $lead->vehicle,
            'governorate' => $lead->governorate, 'district' => $lead->district, 'address' => $lead->address,
            'lead_id' => $lead->id,
        ]);
    }

    public function reject(Lead $lead)
    {
        $lead->update(['status' => 'rejected']);

        return back()->with('success', 'تم تجاهل الطلب.');
    }
}
