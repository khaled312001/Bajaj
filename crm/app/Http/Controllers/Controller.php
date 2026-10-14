<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /** Abort 404 when the signed-in user may not see this customer (agent scope). */
    protected function ensureCanSee(Customer $customer): void
    {
        $user = request()->user();
        $visible = Customer::query()->visibleTo($user)->whereKey($customer->id)->exists();
        abort_unless($visible, 404);
    }

    /** Abort 403 when the customer belongs to a colleague and the user has no permission to change colleagues' customers. */
    protected function ensureCanEdit(Customer $customer): void
    {
        abort_unless($customer->editableBy(request()->user()), 403, 'العميل ده مسجل باسم زميل — تقدر تضيف متابعة أو تطلب نقله ليك.');
    }
}
