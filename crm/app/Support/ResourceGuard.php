<?php

namespace App\Support;

/**
 * Pre-flight check for heavy/unbounded operations (backups, exports) on shared hosting:
 * refuses to start rather than risk filling the disk or exhausting the account's resource quota.
 */
class ResourceGuard
{
    private const MIN_FREE_BYTES = 80 * 1024 * 1024; // 80MB headroom

    public static function ensureHeadroom(string $context = 'هذه العملية'): void
    {
        $free = @disk_free_space(storage_path());
        if ($free !== false && $free < self::MIN_FREE_BYTES) {
            throw new \RuntimeException("مساحة التخزين على السيرفر منخفضة جداً حالياً، فتم إيقاف {$context} حفاظاً على استقرار الموقع. برجاء تحرير مساحة (حذف نسخ احتياطية قديمة) أو التواصل مع الدعم الفني.");
        }
    }
}
