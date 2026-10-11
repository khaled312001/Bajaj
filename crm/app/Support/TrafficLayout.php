<?php

namespace App\Support;

use App\Services\PdfService;
use Carbon\Carbon;

/**
 * Traffic letters (مبايعة / مبايعة مع حفظ الملكية / خطاب تجديد / مخالصة) are registered with the traffic
 * department in a fixed layout, so every line is placed at the exact page position (mm, from the top-left
 * corner of A4) measured from the company's approved Excel print-outs.
 */
class TrafficLayout
{
    /**
     * The originals use Arial Bold, which prints at the measured sizes as is. The fallback XB Riyaz has the same
     * look and Latin metrics but sets Arabic a little wider: shrink Arabic text (and the stretched title) to fit.
     */
    private const ARABIC_SCALE = 0.865;
    private const TITLE_SCALE = ['mobaya' => 0.955, 'retain' => 0.955, 'renewal' => 0.846, 'clearance' => 0.90];

    public static function font(): string
    {
        return PdfService::docFont();
    }

    private const GEO = [
        // طباعة مبايعة.pdf + خطاب تجديد.pdf (identical grid)
        'mobaya' => [
            'size' => 13.2, 'serial' => [106.35, 37.5, 10.6], 'title' => [106.35, 47.0, 31.7],
            'date' => [176.1, 70.2, 153.7], 'mgr' => [166.2, 81.3, 131.9], 'greet' => [105.95, 92.7],
            'body' => [167.2, 104.0], 'rows' => [115.6, 126.9, 138.2, 149.6, 160.9, 172.1],
            'k1' => 158.7, 'v1' => 157.0, 'k2' => 80.2, 'v2' => 78.5, 'para' => [],
            'closing' => [[109.2, 183.6], [112.2, 191.8], [112.25, 199.6]], 'sign' => [72.95, 216.8],
            'approval' => ['y' => 250.7, 'label' => 63.4, 'close' => 36.5, 'code' => 29.35, 'open' => 20.7, 'code_size' => 10.6],
        ],
        // مبايعة مع حفظ ملكية.pdf
        'retain' => [
            'size' => 13.4, 'serial' => [104.95, 37.8, 10.7], 'title' => [104.95, 47.4, 32.2], 'sub' => [104.6, 60.9],
            'date' => [175.9, 71.8, 153.4], 'mgr' => [166.0, 83.1, 131.6], 'greet' => [104.55, 94.6],
            'body' => [166.2, 106.2], 'rows' => [117.8, 129.4, 140.9, 152.4, 163.9, 175.3],
            'k1' => 158.2, 'v1' => 156.5, 'k2' => 78.4, 'v2' => 76.7, 'para' => [],
            'closing' => [[108.3, 186.9], [110.95, 195.2], [110.95, 203.2]], 'sign' => [71.1, 220.6],
            'approval' => ['y' => 255.0, 'label' => 61.0, 'close' => 34.0, 'code' => 26.8, 'open' => 18.0, 'code_size' => 10.7],
        ],
        // مخالصة.pdf (printed at a slightly smaller scale)
        'clearance' => [
            'size' => 12.6, 'serial' => [110.15, 36.7, 10.1], 'title' => [110.15, 45.8, 30.2],
            'date' => [176.8, 67.8, 155.4], 'mgr' => [166.9, 78.5, 134.4], 'greet' => [109.8, 89.3],
            'body' => [148.7, 100.2], 'rows' => [111.2, 122.0, 132.8, 143.7, 154.5, 165.2],
            'k1' => 160.2, 'v1' => 158.6, 'k2' => 85.3, 'v2' => 83.7,
            'para' => [[110.25, 176.1], [111.3, 183.2]],
            'closing' => [[113.1, 191.2], [115.8, 198.5], [115.85, 206.0]], 'sign' => [78.35, 222.4],
            'approval' => ['y' => 254.8, 'label' => 69.0, 'close' => 43.5, 'code' => 36.7, 'open' => 28.4, 'code_size' => 10.1],
        ],
    ];

    private const TITLES = [
        'mobaya' => 'مـــبـــايـــعــــة', 'retain' => 'مـــبـــايـــعــــة',
        'renewal' => 'خــطاب تـجـديـد', 'clearance' => 'مـخـالــــصـة',
    ];

    /**
     * Positioned text items: [top, left, width, align, size, text, ltr].
     *
     * @return list<array{0: float, 1: float, 2: float, 3: string, 4: float, 5: string, 6: bool}>
     */
    public static function items(string $type, array $d, ?string $docDate): array
    {
        $variant = $type === 'mobaya' && ! empty($d['retain_title']) ? 'retain' : $type;
        $g = self::GEO[$variant === 'renewal' ? 'mobaya' : $variant];
        $s = $g['size'];
        $items = [];
        $right = function (float $x, float $y, string $t, float $size = 0, bool $ltr = false) use (&$items, $s) {
            $items[] = [$y, max(0, $x - 120), min(120, $x), 'right', $size ?: $s, $t, $ltr];
        };
        $left = function (float $x, float $y, string $t, float $size = 0, bool $ltr = false) use (&$items, $s) {
            $items[] = [$y, $x, 210 - $x, 'left', $size ?: $s, $t, $ltr];
        };
        $center = function (float $c, float $y, string $t, float $size = 0) use (&$items, $s) {
            $items[] = [$y, $c - min($c, 210 - $c), 2 * min($c, 210 - $c), 'center', $size ?: $s, $t, false];
        };

        $serial = trim((string) ($d['mobaya_no'] ?? $d['doc_no'] ?? ''));
        if (ctype_digit($serial)) {
            $serial = str_pad($serial, 7, '0', STR_PAD_LEFT);
        }
        $center($g['serial'][0], $g['serial'][1], $serial, $g['serial'][2]);
        $center($g['title'][0], $g['title'][1], self::TITLES[$variant], $g['title'][2]);
        if (isset($g['sub'])) {
            $center($g['sub'][0], $g['sub'][1], '( مـع الاحـتـفاظ بـحـق الـمـلـكـيـة )');
        }

        $right($g['date'][0], $g['date'][1], 'تحريرا في :');
        $right($g['date'][2], $g['date'][1], self::date($docDate), 0, true);
        $dept = trim((string) ($d['traffic_dept'] ?? '')) ?: 'قنا';
        $right($g['mgr'][0], $g['mgr'][1], 'السيد مدير إدارة مرور');
        $right($g['mgr'][2], $g['mgr'][1], self::stretch($dept));
        $center($g['greet'][0], $g['greet'][1], 'تحية طيبة وبعد...');

        match ($type) {
            'renewal' => [$right(182.8, $g['body'][1], 'ارجو من سيادتكم التكرم بتجديد ترخيص'), $right(44.2, $g['body'][1], 'لمدة سنه')],
            'clearance' => $right($g['body'][0], $g['body'][1], 'نود الإفادة بأن  :'),
            default => $right($g['body'][0], $g['body'][1], 'نفيد سيادتكم علما بأننا قد بعنا'),
        };

        $motor = trim((string) ($d['motor'] ?? ''));
        $rows = [
            ['ماركة :', $d['product'] ?? '', 'اللون :', $d['color'] ?? ''],
            ['شاسيه رقم :', $d['chassis'] ?? '', 'ماتور رقم :', $motor !== '' ? '*' . $motor . '*' : ''],
            ['موديل :', $d['model'] ?? '', 'عدد السلندارت :', $d['cylinders'] ?? '1'],
            ['الي السيد :', $d['customer_name'] ?? '', null, null],
            ['العنوان :', $d['address'] ?? '', null, null],
            ['الرقم القومي :', $d['nat_id'] ?? '', null, null],
        ];
        foreach ($rows as $i => [$k1, $v1, $k2, $v2]) {
            $y = $g['rows'][$i];
            $left($g['k1'], $y, $k1);
            // the national id ends under the date, the other values end next to their labels
            $right($i === 5 ? $g['date'][2] : $g['v1'], $y, (string) $v1, 0, (bool) preg_match('/^[\x20-\x7E]+$/', (string) $v1));
            if ($k2 !== null) {
                $left($g['k2'], $y, $k2);
                $right($g['v2'], $y, (string) $v2, 0, (bool) preg_match('/^[\x20-\x7E]+$/', (string) $v2));
            }
        }

        $para = ['بانه قد قام بسداد كافة المستحقات المالية وسداد كامل الأقساط وليس لدينا مانع من رفع حظر البيع عنه',
            'وتمكين المالك من نقل ملكيتها او اتخاذ كافة الإجراءات المرورية اللازمة باسمه'];
        foreach ($g['para'] as $i => [$c, $y]) {
            $center($c, $y, $para[$i]);
        }
        $closing = ['الرجاء التكرم بإتخاذ اللازم نحو ترخيص المعدة', 'باسم المشتري المذكور وشكرا', 'وتفضلوا سيادتكم بقبول وافر الاحترام'];
        foreach ($g['closing'] as $i => [$c, $y]) {
            $center($c, $y, $closing[$i]);
        }
        $center($g['sign'][0], $g['sign'][1], 'التوقيع');

        $a = $g['approval'];
        $right($a['label'], $a['y'], 'اعتماد مرور ' . $dept);
        $left($a['close'], $a['y'], ')', 0, true);
        $center($a['code'], $a['y'] + 0.6, trim((string) ($d['traffic_code'] ?? '')), $a['code_size']);
        $left($a['open'], $a['y'], '(', 0, true);

        $title = self::TITLES[$variant];
        $arial = self::font() === 'arial';

        return array_map(function ($it) use ($title, $variant, $arial) {
            $size = $it[4] * match (true) {
                $arial => 1.0,
                $it[5] === $title => self::TITLE_SCALE[$variant],
                (bool) preg_match('/\p{Arabic}/u', $it[5]) => self::ARABIC_SCALE,
                default => 1.0,
            };

            // Arial's line box starts ~0.022 mm per pt above the glyph box the originals were measured by
            return [$arial ? round($it[0] + 0.022 * $size, 2) : $it[0], $it[1], $it[2], $it[3], round($size, 2), $it[5], $it[6]];
        }, array_values(array_filter($items, fn ($it) => $it[5] !== '')));
    }

    /** A one-word department is stretched after its first letter like the original (قـــــــــــــــنا). */
    private static function stretch(string $word): string
    {
        $first = mb_substr($word, 0, 1);
        if (mb_strlen($word) < 2 || mb_strlen($word) > 6 || str_contains($word, ' ')
            || ! preg_match('/\p{Arabic}/u', $first) || str_contains('اأإآءؤدذرزوة', $first)) {
            return $word;
        }

        return $first . str_repeat('ـ', 16) . mb_substr($word, 1);
    }

    /** The originals print the date as 14-Sep-2026. */
    private static function date(?string $date): string
    {
        if (! $date) {
            return '';
        }
        try {
            return Carbon::createFromFormat('Y/m/d', $date)->format('d-M-Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
