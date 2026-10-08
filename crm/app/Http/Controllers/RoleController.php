<?php

namespace App\Http\Controllers;

use App\Models\RoleProfile;
use App\Support\Activity;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'profiles' => RoleProfile::withCount('users')->orderByDesc('is_default')->orderBy('id')->get(),
            'modules' => Permissions::MODULES,
            'actions' => Permissions::ACTIONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $profile = RoleProfile::create($data);
        $this->ensureSingleDefault($profile);
        Activity::log('settings', $profile, 'إنشاء دور: ' . $profile->name);

        return back()->with('success', 'تم إنشاء الدور.');
    }

    public function update(Request $request, RoleProfile $role)
    {
        $data = $this->validated($request, $role);
        $role->update($data);
        $this->ensureSingleDefault($role);
        Permissions::flush();
        Activity::log('settings', $role, 'تعديل صلاحيات الدور: ' . $role->name);

        return back()->with('success', 'تم حفظ الصلاحيات.');
    }

    public function destroy(RoleProfile $role)
    {
        if ($role->is_default) {
            return back()->withErrors(['role' => 'لا يمكن حذف الدور الافتراضي. اجعل دوراً آخر افتراضياً أولاً.']);
        }
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'يوجد موظفون على هذا الدور. انقلهم لدور آخر أولاً.']);
        }
        $role->delete();
        Activity::log('settings', null, 'حذف دور: ' . $role->name);

        return back()->with('success', 'تم حذف الدور.');
    }

    private function validated(Request $request, ?RoleProfile $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('role_profiles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'perms' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $perms = [];
        foreach (Permissions::MODULES as $key => [, , $applicable]) {
            $given = array_intersect($applicable, array_keys($data['perms'][$key] ?? []));
            // create/edit/delete are meaningless without view where a view exists
            if ($given && in_array('view', $applicable, true) && ! in_array('view', $given, true)) {
                $given[] = 'view';
            }
            if ($given) {
                $perms[$key] = array_values($given);
            }
        }

        return ['name' => trim($data['name']), 'description' => $data['description'] ?? null, 'permissions' => $perms, 'is_default' => $request->boolean('is_default')];
    }

    private function ensureSingleDefault(RoleProfile $profile): void
    {
        if ($profile->is_default) {
            RoleProfile::whereKeyNot($profile->id)->update(['is_default' => false]);
        } elseif (! RoleProfile::where('is_default', true)->exists()) {
            $profile->update(['is_default' => true]);
        }
        Permissions::flush();
    }
}
