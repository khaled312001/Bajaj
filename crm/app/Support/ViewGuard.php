<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;

/**
 * Anti-scraping guard: every customer file opened by an agent is logged; unusually fast
 * browsing raises a security alert for the admin and finally blocks the session temporarily.
 */
class ViewGuard
{
    public static function record(User $user, Customer $customer): void
    {
        Activity::log('customer.view', $customer, 'عرض ملف العميل ' . $customer->name);

        if ($user->isAdmin()) {
            return;
        }

        $alertAt = (int) Settings::get('view_alert_threshold', 40);
        $blockAt = (int) Settings::get('view_block_threshold', 90);
        $views = ActivityLog::where('user_id', $user->id)->where('action', 'customer.view')
            ->where('created_at', '>=', now()->subMinutes(10))->count();

        if ($views >= $alertAt) {
            $recentAlert = ActivityLog::where('user_id', $user->id)->where('action', 'security.alert')
                ->where('created_at', '>=', now()->subMinutes(10))->exists();
            if (! $recentAlert) {
                Activity::log('security.alert', $user, "نشاط تصفح غير معتاد: {$views} ملف عميل خلال 10 دقائق بواسطة {$user->name}", ['views' => $views]);
            }
        }

        abort_if($views >= $blockAt, 429, 'تم إيقاف التصفح مؤقتاً لعدد الملفات المفتوحة غير المعتاد. تواصل مع المدير.');
    }
}
