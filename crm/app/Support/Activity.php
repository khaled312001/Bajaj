<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Activity
{
    /** Write an entry to the global audit log. */
    public static function log(string $action, ?Model $subject = null, ?string $description = null, array $meta = [], ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description ? mb_substr($description, 0, 250) : null,
            'ip' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 250),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    /** Append an entry to a customer's tracking timeline (who did what and when). */
    public static function customer(Customer|int $customer, string $type, string $description, array $meta = []): CustomerEvent
    {
        return CustomerEvent::create([
            'customer_id' => $customer instanceof Customer ? $customer->id : $customer,
            'user_id' => Auth::id(),
            'type' => $type,
            'description' => mb_substr($description, 0, 250),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }
}
