<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Activity;
use App\Support\EgyptGeo;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index()
    {
        $lookups = Lookup::orderBy('type')->orderBy('sort')->orderBy('id')->get()->groupBy('type');

        return view('settings.index', ['lookups' => $lookups, 'types' => Lookup::TYPES, 'settings' => Settings::all(), 'geo' => EgyptGeo::data()]);
    }

    /** Add / remove a governorate or one of its مراكز. */
    public function geo(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['add_gov', 'del_gov', 'add_center', 'del_center'])],
            'gov' => ['required', 'string', 'max:100'],
            'center' => ['nullable', 'string', 'max:120'],
        ]);
        $geo = EgyptGeo::data();
        $gov = trim(preg_replace('/\s+/u', ' ', $data['gov']));
        $center = trim(preg_replace('/\s+/u', ' ', (string) ($data['center'] ?? '')));

        if ($data['action'] !== 'add_gov' && ! array_key_exists($gov, $geo)) {
            return back()->withErrors(['geo' => 'المحافظة غير موجودة.'])->withFragment('geo');
        }
        switch ($data['action']) {
            case 'add_gov':
                if (array_key_exists($gov, $geo)) {
                    return back()->withErrors(['geo' => "محافظة {$gov} موجودة بالفعل."])->withFragment('geo');
                }
                $geo[$gov] = [];
                $msg = "تمت إضافة محافظة {$gov}.";
                break;
            case 'del_gov':
                unset($geo[$gov]);
                $msg = "تم حذف محافظة {$gov}.";
                break;
            case 'add_center':
                if ($center === '') {
                    return back()->withErrors(['geo' => 'اكتب اسم المركز.'])->with('geo_open', $gov)->withFragment('geo');
                }
                if (in_array($center, $geo[$gov], true)) {
                    return back()->withErrors(['geo' => "مركز {$center} موجود بالفعل في {$gov}."])->with('geo_open', $gov)->withFragment('geo');
                }
                $geo[$gov][] = $center;
                $msg = "تمت إضافة مركز {$center} إلى {$gov}.";
                break;
            default:
                $geo[$gov] = array_values(array_filter($geo[$gov], fn ($c) => $c !== $center));
                $msg = "تم حذف مركز {$center} من {$gov}.";
        }
        EgyptGeo::save($geo);
        Activity::log('settings', null, $msg);

        return back()->with('success', $msg)->with('geo_open', $data['action'] === 'del_gov' ? null : $gov)->withFragment('geo');
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

    /**
     * What each finance entity keeps before transferring the financed amount, and how long its transfer stays frozen.
     * A new entity typed here joins the finance entities list; every entity debt follows the new rules.
     */
    public function updateDeductions(Request $request)
    {
        $data = $request->validate([
            'rules' => ['required', 'array', 'max:60'],
            'rules.*.entity' => ['nullable', 'string', 'max:120'],
            'rules.*.percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'rules.*.fixed' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'rules.*.first' => ['nullable', 'boolean'],
            'rules.*.fees' => ['nullable', 'boolean'],
            'rules.*.days' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [], ['rules.*.percent' => 'النسبة', 'rules.*.days' => 'مدة التجميد']);
        $rules = [];
        $added = [];
        foreach ($data['rules'] as $r) {
            $name = trim((string) ($r['entity'] ?? ''));
            if ($name === '') {
                continue;   // an empty "new entity" row
            }
            if (! \App\Models\Deal::isOutsideEntity($name)) {
                return back()->withInput()->withErrors(['rules' => "«{$name}» تقسيط مع الشركة نفسها، مش جهة تقسيط خارجية."]);
            }
            $rules[$name] = ['percent' => (float) ($r['percent'] ?? 0), 'fixed' => (float) ($r['fixed'] ?? 0),
                'first' => (bool) ($r['first'] ?? false), 'fees' => (bool) ($r['fees'] ?? false), 'days' => (int) ($r['days'] ?? \App\Models\Deal::ENTITY_DAYS)];
            if (! Lookup::where('type', 'finance_entity')->where('name', $name)->exists()) {
                Lookup::create(['type' => 'finance_entity', 'name' => $name, 'sort' => (int) Lookup::where('type', 'finance_entity')->max('sort') + 1]);
                $added[] = $name;
            }
        }
        Settings::set('entity_deductions', json_encode($rules, JSON_UNESCAPED_UNICODE));
        $n = 0;
        \App\Models\Deal::query()->entityFinanced()->get()->each(function ($d) use (&$n) {
            $d->recalculate();
            $n++;
        });
        Activity::log('settings', null, 'تعديل خصومات جهات التقسيط' . ($added ? ' وإضافة: ' . implode('، ', $added) : ''));

        return back()->with('success', ($added ? 'تمت إضافة ' . implode('، ', $added) . ' لجهات التقسيط. ' : '') . "تم حفظ شروط جهات التقسيط وإعادة حساب مديونية {$n} صفقة.")
            ->withFragment('entity-deductions');
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
