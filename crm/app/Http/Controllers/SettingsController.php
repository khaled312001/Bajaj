<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $lookups = Lookup::orderBy('type')->orderBy('sort')->orderBy('id')->get()->groupBy('type');

        return view('settings.index', ['lookups' => $lookups, 'types' => Lookup::TYPES, 'settings' => Settings::all()]);
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'company_phone' => ['nullable', 'string', 'max:40'],
            'agent_scope' => ['required', Rule::in(['own', 'all'])],
            'agent_idle_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'agent_ip_allowlist' => ['nullable', 'string', 'max:500', 'regex:/^[0-9a-fA-F:.,\s]*$/'],
            'view_alert_threshold' => ['required', 'integer', 'min:5', 'max:1000'],
            'view_block_threshold' => ['required', 'integer', 'min:10', 'max:2000', 'gte:view_alert_threshold'],
            'default_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'plans' => ['required', 'array', 'max:12'],
            'plans.*.name' => ['nullable', 'string', 'max:60'],
            'plans.*.fees_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'plans.*.fees_fixed' => ['nullable', 'numeric', 'min:0'],
            'plans.*.table' => ['nullable', 'string', 'max:600'],
            'offer_title' => ['nullable', 'string', 'max:80'],
            'working_hours' => ['nullable', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:200'],
            'company_legal_name' => ['nullable', 'string', 'max:120'], 'commercial_register' => ['nullable', 'string', 'max:40'],
            'tax_card' => ['nullable', 'string', 'max:40'], 'bank_account' => ['nullable', 'string', 'max:120'], 'manager_name' => ['nullable', 'string', 'max:80'],
        ], ['agent_ip_allowlist.regex' => 'اكتب عناوين IP مفصولة بفاصلة.']);

        // each plan: lines like "12=1.24" -> {12:1.24}
        $plans = [];
        foreach ($data['plans'] as $pl) {
            $name = trim((string) ($pl['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $table = [];
            foreach (preg_split('/\R+/', trim((string) ($pl['table'] ?? ''))) as $line) {
                if (preg_match('/^\s*(\d{1,3})\s*[=:،,]\s*([0-9]+(?:\.[0-9]+)?)\s*$/u', $line, $m) && (float) $m[2] >= 1) {
                    $table[(int) $m[1]] = (float) $m[2];
                } elseif (trim($line) !== '') {
                    return back()->withInput()->withErrors(['plans' => "سطر غير صحيح في جدول «{$name}»: {$line} (الصيغة: 12=1.22)"]);
                }
            }
            if (! $table) {
                return back()->withInput()->withErrors(['plans' => "أدخل جدول نسب للنظام «{$name}»."]);
            }
            ksort($table);
            $plans[] = ['name' => $name, 'fees_percent' => (float) ($pl['fees_percent'] ?? 0), 'fees_fixed' => (float) ($pl['fees_fixed'] ?? 0), 'table' => $table];
        }
        if (! $plans) {
            return back()->withInput()->withErrors(['plans' => 'يلزم نظام تقسيط واحد على الأقل.']);
        }
        unset($data['plans']);
        $data['finance_plans'] = json_encode($plans, JSON_UNESCAPED_UNICODE);

        foreach ($data as $k => $v) {
            Settings::set($k, $v);
        }
        Activity::log('settings', null, 'تعديل الإعدادات العامة والأمان');

        return back()->with('success', 'تم حفظ الإعدادات.');
    }

    public function storeLookup(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Lookup::TYPES))],
            'name' => ['required', 'string', 'max:150'],
        ]);
        $name = trim($data['name']);
        if (Lookup::where('type', $data['type'])->where('name', $name)->exists()) {
            return back()->withErrors(['name' => 'القيمة موجودة بالفعل.']);
        }
        Lookup::create(['type' => $data['type'], 'name' => $name, 'sort' => (int) Lookup::where('type', $data['type'])->max('sort') + 1]);
        Activity::log('settings', null, 'إضافة "' . $name . '" إلى ' . Lookup::TYPES[$data['type']]);

        return back()->with('success', 'تمت الإضافة.')->withFragment($data['type']);
    }

    public function toggleLookup(Lookup $lookup)
    {
        $lookup->update(['is_active' => ! $lookup->is_active]);

        return back()->withFragment($lookup->type);
    }

    public function destroyLookup(Lookup $lookup)
    {
        $lookup->delete();
        Activity::log('settings', null, 'حذف "' . $lookup->name . '" من ' . (Lookup::TYPES[$lookup->type] ?? $lookup->type));

        return back()->with('success', 'تم الحذف.')->withFragment($lookup->type);
    }
}
