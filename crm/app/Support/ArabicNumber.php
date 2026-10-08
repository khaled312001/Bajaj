<?php

namespace App\Support;

/** Integer / EGP amount to Arabic words (تفقيط). */
class ArabicNumber
{
    private const ONES = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
    private const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    private const HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    public static function words(int $n): string
    {
        if ($n === 0) {
            return 'صفر';
        }
        $parts = [];
        $billions = intdiv($n, 1_000_000_000);
        $millions = intdiv($n % 1_000_000_000, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1000);
        $rest = $n % 1000;

        if ($billions) {
            $parts[] = self::scale($billions, 'مليار', 'ملياران', 'مليارات');
        }
        if ($millions) {
            $parts[] = self::scale($millions, 'مليون', 'مليونان', 'ملايين');
        }
        if ($thousands) {
            $parts[] = self::scale($thousands, 'ألف', 'ألفان', 'آلاف');
        }
        if ($rest) {
            $parts[] = self::below1000($rest);
        }

        return implode(' و', $parts);
    }

    /** "خمسة وسبعون ألف جنيه مصري فقط لا غير" */
    public static function egp(float|int|string|null $amount): string
    {
        $amount = round((float) $amount, 2);
        $pounds = (int) floor($amount);
        $piasters = (int) round(($amount - $pounds) * 100);
        $txt = self::words($pounds) . ' جنيه مصري';
        if ($piasters) {
            $txt .= ' و' . self::words($piasters) . ' قرش';
        }

        return $txt . ' فقط لا غير';
    }

    private static function scale(int $n, string $one, string $two, string $plural): string
    {
        if ($n === 1) {
            return $one;
        }
        if ($n === 2) {
            return $two;
        }
        if ($n >= 3 && $n <= 10) {
            return self::words($n) . ' ' . $plural;
        }

        return self::words($n) . ' ' . $one;
    }

    private static function below1000(int $n): string
    {
        $out = [];
        $h = intdiv($n, 100);
        $r = $n % 100;
        if ($h) {
            $out[] = self::HUNDREDS[$h];
        }
        if ($r) {
            if ($r < 20) {
                $out[] = self::ONES[$r];
            } else {
                $o = $r % 10;
                $t = intdiv($r, 10);
                $out[] = ($o ? self::ONES[$o] . ' و' : '') . self::TENS[$t];
            }
        }

        return implode(' و', $out);
    }
}
