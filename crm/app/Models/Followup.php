<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Followup extends Model
{
    protected $fillable = [
        'customer_id', 'deal_id', 'assigned_to', 'created_by', 'reason', 'priority', 'notes',
        'due_date', 'due_time', 'status', 'completed_at', 'completed_by', 'outcome',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by')->withTrashed();
    }

    /** Followups an agent may see: assigned/created by them, or belonging to customers they can see. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $q;
        }

        return $q->where(function ($w) use ($user) {
            $w->where('followups.assigned_to', $user->id)->orWhere('followups.created_by', $user->id);
        });
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('followups.status', 'pending');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date->lt(today());
    }

    public function getIsTodayAttribute(): bool
    {
        return $this->due_date->isToday();
    }
}
