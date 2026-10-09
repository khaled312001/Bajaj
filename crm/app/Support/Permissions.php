<?php

namespace App\Support;

use App\Models\RoleProfile;
use App\Models\User;

/** Module/action permission matrix. Admin accounts can do everything; sensitive modules are admin-only and never grantable. */
class Permissions
{
    public const ACTIONS = ['view' => 'عرض', 'create' => 'إضافة', 'edit' => 'تعديل', 'delete' => 'حذف'];

    /** Grantable modules => [label, icon, applicable actions]. */
    public const MODULES = [
        'dashboard' => ['لوحة التحكم', 'fa-chart-pie', ['view']],
        'summary' => ['ملخص اليوم / الأسبوع / الشهر / السنة', 'fa-chart-line', ['view']],
        'customers' => ['العملاء', 'fa-users', ['view', 'create', 'edit', 'delete']],
        'followups' => ['المتابعات', 'fa-calendar-check', ['view', 'create', 'edit', 'delete']],
        'deals' => ['الصفقات والأقساط', 'fa-handshake', ['view', 'create', 'edit', 'delete']],
        'payments' => ['الدفعات والتحصيل', 'fa-money-bill-wave', ['create', 'delete']],
        'calculator' => ['حاسبة الأقساط', 'fa-calculator', ['view']],
        'vehicles' => ['المخزن والمركبات', 'fa-warehouse', ['view', 'create', 'edit', 'delete']],
        'documents' => ['المستندات والطباعة (عروض وكشوف)', 'fa-file-pdf', ['view', 'create']],
        'legal_documents' => ['الإقرارات والمبايعات والفواتير', 'fa-file-signature', ['view', 'create']],
        'reports' => ['التقارير', 'fa-chart-column', ['view']],
        'products' => ['المنتجات والأسعار', 'fa-tags', ['view', 'create', 'edit', 'delete']],
    ];

    /** Never grantable: users, roles, settings, logs, backups, Excel import/export. */
    public const ADMIN_ONLY = ['users', 'roles', 'settings', 'logs', 'backups', 'data'];

    public static function defaultProfiles(): array
    {
        return [
            'خدمة عملاء' => ['desc' => 'إدخال العملاء ومتابعتهم وحاسبة الأقساط وعروض الأسعار', 'is_default' => true, 'perms' => [
                'dashboard' => ['view'], 'summary' => ['view'], 'customers' => ['view', 'create', 'edit'], 'followups' => ['view', 'create', 'edit', 'delete'],
                'deals' => ['view', 'create', 'edit'], 'payments' => ['create'], 'calculator' => ['view'], 'vehicles' => ['view'], 'documents' => ['view', 'create'],
            ]],
            'مبيعات' => ['desc' => 'خدمة العملاء + المستندات القانونية والمخزن والتقارير', 'is_default' => false, 'perms' => [
                'dashboard' => ['view'], 'summary' => ['view'], 'customers' => ['view', 'create', 'edit'], 'followups' => ['view', 'create', 'edit', 'delete'],
                'deals' => ['view', 'create', 'edit'], 'payments' => ['create'], 'calculator' => ['view'], 'vehicles' => ['view', 'create', 'edit'],
                'documents' => ['view', 'create'], 'legal_documents' => ['view', 'create'], 'reports' => ['view'], 'products' => ['view'],
            ]],
            'مشاهدة فقط' => ['desc' => 'عرض العملاء والمتابعات دون أي تعديل', 'is_default' => false, 'perms' => [
                'dashboard' => ['view'], 'customers' => ['view'], 'followups' => ['view'], 'deals' => ['view'], 'calculator' => ['view'],
            ]],
        ];
    }

    private static function store(): \ArrayObject
    {
        return app()->bound('perm.cache') ? app('perm.cache') : app()->instance('perm.cache', new \ArrayObject()) ?? app('perm.cache');
    }

    public static function flush(): void
    {
        app()->instance('perm.cache', new \ArrayObject());
    }

    public static function profileFor(User $user): ?RoleProfile
    {
        $id = $user->role_profile_id ?: 0;

        $store = self::store();
        if (! isset($store[$id])) {
            $store[$id] = ($id ? RoleProfile::find($id) : null) ?? RoleProfile::where('is_default', true)->first() ?? false;
        }

        return $store[$id] ?: null;
    }

    public static function allows(User $user, string $module, string $action = 'view'): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if (in_array($module, self::ADMIN_ONLY, true) || ! isset(self::MODULES[$module])) {
            return false;
        }
        $perms = $user->permissions_override ?? self::profileFor($user)?->permissions;
        if ($perms === null) {
            $perms = self::defaultProfiles()['خدمة عملاء']['perms'];
        }

        return in_array($action, $perms[$module] ?? [], true);
    }

    /** The permission set actually in effect for a user: their personal override, or their role profile's. */
    public static function effectiveFor(User $user): array
    {
        return $user->permissions_override ?? self::profileFor($user)?->permissions ?? self::defaultProfiles()['خدمة عملاء']['perms'];
    }
}
