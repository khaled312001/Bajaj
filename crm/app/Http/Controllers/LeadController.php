<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'vehicles' => self::publicVehicles(),
        ]);
    }

    /** Admin: edit what the public form shows (on/off, title, intro, which vehicles). */
    public function formSettings()
    {
        $picked = json_decode((string) Settings::get('lead_form_vehicles', ''), true);

        return view('leads.form', [
            'enabled' => Settings::get('lead_form_enabled', '1') === '1',
            'title' => Settings::get('lead_form_title', 'اطلب مركبتك الآن'),
            'intro' => Settings::get('lead_form_intro', 'املأ بياناتك وسيتواصل معك فريق المبيعات في أقرب وقت.'),
            'vehicles' => Lookup::list('vehicle'),
            'picked' => is_array($picked) ? $picked : [],
            'prices' => \App\Models\Product::where('is_active', true)->where('price', '>', 0)->pluck('price', 'name'),
        ]);
    }

    public function updateFormSettings(Request $request)
    {
        $data = $request->validate([
            'lead_form_enabled' => ['required', Rule::in(['0', '1'])],
            'lead_form_title' => ['nullable', 'string', 'max:120'],
            'lead_form_intro' => ['nullable', 'string', 'max:400'],
            'lead_form_vehicles' => ['nullable', 'array'], 'lead_form_vehicles.*' => ['string', 'max:150'],
        ]);
        // none ticked = the public form lists every vehicle
        $data['lead_form_vehicles'] = empty($data['lead_form_vehicles']) ? '' : json_encode(array_values($data['lead_form_vehicles']), JSON_UNESCAPED_UNICODE);
        foreach ($data as $k => $v) {
            Settings::set($k, $v);
        }
        Activity::log('settings', null, 'تعديل محتوى نموذج طلب العملاء (الليدز)');

        return back()->with('success', 'تم حفظ محتوى النموذج.');
    }

    /** Vehicles offered on the public form: the ones the admin ticked in settings, or every vehicle when none is ticked. */
    public static function publicVehicles(): array
    {
        $all = Lookup::list('vehicle');
        $picked = json_decode((string) Settings::get('lead_form_vehicles', ''), true);
        $shown = is_array($picked) ? array_values(array_intersect($all, $picked)) : [];

        return $shown ?: $all;
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

    /**
     * Admin: assign the selected leads to one sales employee — each becomes a customer owned by that employee
     * right away (shows in their customers). A phone that already belongs to a customer is linked, not duplicated.
     */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ], ['ids.required' => 'حدد طلب واحد على الأقل.', 'assigned_to.required' => 'اختار الموظف.']);
        $employee = User::findOrFail($data['assigned_to']);
        $admin = $request->user();
        $created = 0;
        $existing = [];

        foreach (Lead::whereIn('id', $data['ids'])->where('status', '!=', 'converted')->get() as $lead) {
            DB::transaction(function () use ($lead, $employee, $admin, &$created, &$existing) {
                $customer = Customer::where(fn ($w) => $w->where('phone', $lead->phone)->orWhere('alt_phone', $lead->phone)->orWhere('whatsapp', $lead->phone))->first();
                if ($customer) {
                    $existing[] = $lead->name . ' (كود ' . $customer->code . ')';
                } else {
                    $customer = Customer::create([
                        'name' => $lead->name, 'phone' => $lead->phone, 'interest' => $lead->vehicle,
                        'governorate' => $lead->governorate, 'district' => $lead->district, 'address' => $lead->address,
                        'status' => 'مفتوحة', 'notes' => 'من طلبات النموذج العام بتاريخ ' . $lead->created_at->format('Y/m/d'),
                        'created_by' => $admin->id, 'assigned_to' => $employee->id,
                    ]);
                    $customer->assignTo($employee->id, $admin);
                    if ($customer->interest) {
                        $customer->vehicles()->create(['vehicle' => $customer->interest, 'created_by' => $admin->id]);
                    }
                    Activity::log('customer.create', $customer, 'إضافة العميل ' . $customer->name . ' من الليدز');
                    Activity::customer($customer, 'created', "تم إدخال العميل من طلبات النموذج العام وتعيينه إلى {$employee->name} بواسطة {$admin->name}");
                    $created++;
                }
                $lead->update(['status' => 'converted', 'assigned_to' => $employee->id, 'customer_id' => $customer->id, 'converted_at' => now()]);
            });
        }
        Activity::log('lead.assign', null, "تحويل {$created} من الليدز لعملاء عند {$employee->name}");

        $msg = "تم تحويل {$created} طلب لعملاء عند {$employee->name} وظهروا في عملائه.";
        if ($existing) {
            $msg .= ' وفيه ' . count($existing) . ' رقمهم مسجل قبل كده كعميل فاتربطوا بيه من غير تكرار: ' . implode('، ', $existing) . '.';
        }

        return back()->with('success', $msg);
    }

    /** Admin: delete the selected leads (the customers made from them stay). */
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'حدد طلب واحد على الأقل.']);
        $count = Lead::whereIn('id', $data['ids'])->delete();
        Activity::log('lead.delete', null, "حذف {$count} من الليدز");

        return back()->with('success', "تم حذف {$count} طلب.");
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
