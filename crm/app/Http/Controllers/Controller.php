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
}
