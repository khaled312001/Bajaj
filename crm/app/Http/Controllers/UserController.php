<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Followup;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $users = User::withCount([
            'customersCreated as created_count',
            'customersAssigned as assigned_count',
            'followups as pending_followups' => fn ($q) => $q->where('status', 'pending'),
        ])->orderBy('role')->orderBy('name')->get();

        return view('users.index', ['users' => $users]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'agent', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($data['role'] === User::ROLE_ADMIN) {
            $data['role_profile_id'] = null;
        }
        $user = User::create($data + ['must_change_password' => $request->boolean('must_change_password', true)]);
        Activity::log('user.create', $user, 'إضافة الموظف ' . $user->name . ' (' . $user->role_label . ')');

        return redirect()->route('users.index')->with('success', 'تمت إضافة الموظف.');
    }

    public function show(User $user)
    {
        $logs = ActivityLog::where('user_id', $user->id)->latest('created_at')->limit(40)->get();
        $recent = Customer::where('created_by', $user->id)->latest()->limit(10)->get();

        return view('users.show', [
            'u' => $user, 'logs' => $logs, 'recent' => $recent,
            'stats' => [
                'created' => Customer::where('created_by', $user->id)->count(),
                'assigned' => Customer::where('assigned_to', $user->id)->count(),
                'followups_done' => Followup::where('completed_by', $user->id)->count(),
                'pending' => Followup::where('assigned_to', $user->id)->pending()->count(),
                'views_today' => ActivityLog::where('user_id', $user->id)->where('action', 'customer.view')->whereDate('created_at', today())->count(),
            ],
        ]);
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (($data['role'] ?? $user->role) === User::ROLE_ADMIN) {
            $data['role_profile_id'] = null;
        }
        \App\Support\Permissions::flush();
        if ($user->id === $request->user()->id) {
            unset($data['role'], $data['is_active']);
        }
        $user->fill($data);
        if ($request->boolean('unlock')) {
            $user->forceFill(['locked_until' => null, 'failed_attempts' => 0]);
        }
        if (! empty($data['password'])) {
            $user->must_change_password = $request->boolean('must_change_password', true);
        }
        $user->save();
        Activity::log('user.update', $user, 'تعديل الموظف ' . $user->name);

        return redirect()->route('users.index')->with('success', 'تم تحديث بيانات الموظف.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'لا يمكنك حذف حسابك.');
        // hand over open work so nothing is orphaned
        Customer::where('assigned_to', $user->id)->update(['assigned_to' => $request->user()->id]);
        Followup::where('assigned_to', $user->id)->where('status', 'pending')->update(['assigned_to' => $request->user()->id]);
        $user->tokens()->delete();
        $user->delete();
        Activity::log('user.delete', $user, 'حذف الموظف ' . $user->name . ' ونقل عملائه إلى ' . $request->user()->name);

        return redirect()->route('users.index')->with('success', 'تم حذف الموظف ونقل عملائه ومتابعاته إليك.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $id = $user?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_AGENT])],
            'role_profile_id' => ['nullable', 'integer', 'exists:role_profiles,id'],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ], ['username.regex' => 'اسم المستخدم حروف إنجليزية وأرقام فقط.', 'username.unique' => 'اسم المستخدم مستخدم بالفعل.']);
    }
}
