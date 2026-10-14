<?php

use App\Models\Lookup;
use App\Models\Product;
use App\Models\RoleProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * 1. Employees list only their own customers again (a search still opens any customer), so the
 *    "colleagues' customers" permission granted yesterday is taken back; the admin grants it per role/employee.
 * 2. Products that were imported without model events never reached the vehicle dropdown — add them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (RoleProfile::all() as $role) {
            if (isset($role->permissions['customers_all'])) {
                $perms = $role->permissions;
                unset($perms['customers_all']);
                $role->forceFill(['permissions' => $perms])->saveQuietly();
            }
        }
        foreach (User::whereNotNull('permissions_override')->get() as $user) {
            if (isset($user->permissions_override['customers_all'])) {
                $perms = $user->permissions_override;
                unset($perms['customers_all']);
                $user->forceFill(['permissions_override' => $perms])->saveQuietly();
            }
        }

        foreach (Product::all() as $p) {
            Lookup::firstOrCreate(['type' => 'vehicle', 'name' => $p->name], ['sort' => 100 + $p->id]);
        }
        \Illuminate\Support\Facades\Cache::forget('lookups.all');
    }

    public function down(): void
    {
    }
};
