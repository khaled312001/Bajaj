<?php

namespace App\Support;

use App\Http\Controllers\LeadController;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The fields of the public lead form, as the admin arranged them: built-in fields map to their own lead column
 * (name and phone can be renamed but never removed), custom fields are saved in the lead's "extra" answers.
 */
class LeadForm
{
    public const BUILTIN = [
        'name' => ['type' => 'text', 'label' => 'الاسم', 'required' => true, 'width' => 'full', 'max' => 150],
        'phone' => ['type' => 'phone', 'label' => 'رقم الهاتف', 'required' => true, 'width' => 'full', 'placeholder' => '01xxxxxxxxx', 'max' => 20],
        'vehicle' => ['type' => 'vehicle', 'label' => 'المركبة المطلوبة', 'width' => 'full', 'max' => 150],
        'governorate' => ['type' => 'governorate', 'label' => 'المحافظة', 'width' => 'half', 'max' => 100],
        'district' => ['type' => 'district', 'label' => 'المركز', 'width' => 'half', 'max' => 120],
        'address' => ['type' => 'text', 'label' => 'العنوان بالتفصيل', 'width' => 'full', 'max' => 250],
    ];

    public const LOCKED = ['name', 'phone'];

    /** Types the admin can pick for a new field. */
    public const TYPES = ['text' => 'نص قصير', 'textarea' => 'نص طويل', 'number' => 'رقم', 'select' => 'قائمة اختيارات', 'date' => 'تاريخ'];

    public const MAX_FIELDS = 30;

    public static function defaults(): array
    {
        return array_map(fn ($key) => self::field($key, []), array_keys(self::BUILTIN));
    }

    /** The saved arrangement, or the original form when nothing was saved yet. */
    public static function fields(): array
    {
        $saved = json_decode((string) Settings::get('lead_form_fields', ''), true);

        return is_array($saved) && $saved ? self::normalize($saved) : self::defaults();
    }

    /** Clean what the editor sent: known keys only, sane lengths, name and phone always present and required. */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach (array_slice($raw, 0, self::MAX_FIELDS + count(self::BUILTIN)) as $f) {
            if (! is_array($f)) {
                continue;
            }
            $key = (string) ($f['key'] ?? '');
            if (! isset(self::BUILTIN[$key])) {
                $key = preg_match('/^f_[a-z0-9]{6,12}$/', $key) ? $key : 'f_' . Str::lower(Str::random(8));
            }
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = self::field($key, $f);
        }
        foreach (array_reverse(self::LOCKED) as $key) {
            if (! isset($out[$key])) {
                $out = [$key => self::field($key, [])] + $out;
            }
        }
        $custom = array_filter($out, fn ($f) => ! $f['builtin']);
        if (count($custom) > self::MAX_FIELDS) {
            $out = array_diff_key($out, array_slice($custom, self::MAX_FIELDS, null, true));
        }

        return array_values($out);
    }

    private static function field(string $key, array $f): array
    {
        $builtin = self::BUILTIN[$key] ?? null;
        $type = $builtin['type'] ?? (array_key_exists($f['type'] ?? '', self::TYPES) ? $f['type'] : 'text');
        $label = trim(Str::limit(strip_tags((string) ($f['label'] ?? '')), 80, ''));
        $options = [];
        if ($type === 'select') {
            $list = is_array($f['options'] ?? null) ? $f['options'] : preg_split('/\R+/', (string) ($f['options'] ?? ''));
            foreach ($list as $o) {
                $o = trim(Str::limit(strip_tags((string) $o), 100, ''));
                if ($o !== '' && ! in_array($o, $options, true)) {
                    $options[] = $o;
                }
            }
            $options = array_slice($options, 0, 40);
        }

        return [
            'key' => $key,
            'type' => $type,
            'label' => $label !== '' ? $label : ($builtin['label'] ?? 'حقل جديد'),
            'placeholder' => trim(Str::limit(strip_tags((string) ($f['placeholder'] ?? $builtin['placeholder'] ?? '')), 120, '')),
            'required' => in_array($key, self::LOCKED, true) || filter_var($f['required'] ?? $builtin['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'width' => in_array($f['width'] ?? null, ['full', 'half'], true) ? $f['width'] : ($builtin['width'] ?? 'full'),
            'options' => $options,
            'builtin' => (bool) $builtin,
        ];
    }

    /** Validation rules and Arabic attribute names for a submission of the given fields. */
    public static function rules(array $fields): array
    {
        $rules = [];
        $names = [];
        $keys = array_column($fields, 'key');
        foreach ($fields as $f) {
            if ($f['key'] === 'district' && ! in_array('governorate', $keys, true)) {
                continue;
            }
            $r = [$f['required'] ? 'required' : 'nullable'];
            $r = array_merge($r, match ($f['type']) {
                'number' => ['numeric', 'max:999999999999'],
                'date' => ['date'],
                'select' => ['string', Rule::in($f['options'])],
                'vehicle' => ['string', Rule::in(LeadController::publicVehicles())],
                'textarea' => ['string', 'max:1000'],
                default => ['string', 'max:' . (self::BUILTIN[$f['key']]['max'] ?? 250)],
            });
            $rules[$f['key']] = $r;
            $names[$f['key']] = $f['label'];
        }

        return [$rules, $names];
    }
}
