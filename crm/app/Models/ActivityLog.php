<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'ip', 'user_agent', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public const LABELS = [
        'login' => 'تسجيل دخول', 'logout' => 'تسجيل خروج', 'login_failed' => 'محاولة دخول فاشلة',
        'customer.create' => 'إضافة عميل', 'customer.update' => 'تعديل عميل', 'customer.delete' => 'حذف عميل',
        'customer.view' => 'عرض ملف عميل', 'customer.assign' => 'نقل عميل', 'deal.create' => 'إضافة صفقة',
        'deal.update' => 'تعديل صفقة', 'deal.delete' => 'حذف صفقة', 'payment.create' => 'تسجيل دفعة',
        'payment.delete' => 'حذف دفعة', 'followup.create' => 'إضافة متابعة', 'followup.done' => 'إتمام متابعة',
        'followup.update' => 'تعديل متابعة', 'followup.delete' => 'حذف متابعة', 'export' => 'تصدير بيانات',
        'import' => 'استيراد بيانات', 'user.create' => 'إضافة موظف', 'user.update' => 'تعديل موظف',
        'user.delete' => 'حذف موظف', 'settings' => 'تعديل الإعدادات', 'security.alert' => 'تنبيه أمني',
        'password.change' => 'تغيير كلمة المرور', 'lead.assign' => 'تعيين ليدز',
        'reassign.request' => 'طلب نقل عميل', 'reassign.approve' => 'الموافقة على نقل عميل', 'reassign.reject' => 'رفض نقل عميل',
    ];

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
