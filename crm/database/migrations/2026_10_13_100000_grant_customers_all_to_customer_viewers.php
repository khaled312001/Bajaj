<?php

use App\Models\RoleProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * "View customers" used to mean only the employee's own customers. Roles and employees that were given
 * view on customers now also get the new "see all customers" permission; the admin can untick it per role
 * or per employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        $grant = function (?array $perms): ?array {
            if ($perms === null || ! in_array('view', $perms['customers'] ?? [], true)) {
                return null;
            }
            $perms['customers_all'] = ['view'];

            return $perms;
        };

        foreach (RoleProfile::all() as $role) {
            if ($perms = $grant($role->permissions)) {
                $role->forceFill(['permissions' => $perms])->saveQuietly();
            }
        }
        foreach (User::whereNotNull('permissions_override')->get() as $user) {
            if ($perms = $grant($user->permissions_override)) {
                $user->forceFill(['permissions_override' => $perms])->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        foreach (RoleProfile::all() as $role) {
            $perms = $role->permissions ?? [];
            unset($perms['customers_all']);
            $role->forceFill(['permissions' => $perms])->saveQuietly();
        }
        foreach (User::whereNotNull('permissions_override')->get() as $user) {
            $perms = $user->permissions_override;
            unset($perms['customers_all']);
            $user->forceFill(['permissions_override' => $perms])->saveQuietly();
        }
    }
};
