<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $q = ActivityLog::with('user:id,name,role')->latest('created_at')->latest('id');

        if ($v = $request->query('user')) {
            $q->where('user_id', $v);
        }
        if ($v = $request->query('action')) {
            $q->where('action', $v);
        }
        if ($v = $request->query('from')) {
            $q->whereDate('created_at', '>=', $v);
        }
        if ($v = $request->query('to')) {
            $q->whereDate('created_at', '<=', $v);
        }
        if (! $request->boolean('with_views')) {
            $q->where('action', '!=', 'customer.view');
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where('description', 'like', '%' . $s . '%');
        }

        return view('logs.index', [
            'logs' => $q->paginate(40)->withQueryString(),
            'staff' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::LABELS,
            'filters' => $request->query(),
            'alertCount' => ActivityLog::where('action', 'security.alert')->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }
}
