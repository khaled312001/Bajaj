<?php

namespace App\Support;

/** Decorative Arabic typography helpers for PDF titles (matches the company's legacy printed-form look). */
class ArabicTypography
{
    /**
     * Stretch each word of an Arabic title using tatweel (kashida) characters, mimicking the
     * wide letter-spaced titles on the company's legacy printed forms (e.g. "مـــبـــايـــعــــة").
     * Only inserts tatweel between two consecutive Arabic letters within the same word — digits,
     * punctuation and spaces are left untouched so mixed titles don't break.
     */
    public static function kashidaTitle(string $title, int $gap = 3): string
    {
        $tatweel = str_repeat('ـ', $gap);

        return implode(' ', array_map(function ($word) use ($tatweel) {
            $letters = mb_str_split($word);
            $isArabicLetter = fn ($c) => (bool) preg_match('/^[\x{0621}-\x{064A}]$/u', $c);
            $out = '';
            foreach ($letters as $i => $c) {
                $out .= $c;
                $next = $letters[$i + 1] ?? null;
                if ($next !== null && $isArabicLetter($c) && $isArabicLetter($next)) {
                    $out .= $tatweel;
                }
            }

            return $out;
        }, preg_split('/\s+/u', trim($title))));
    }
}
