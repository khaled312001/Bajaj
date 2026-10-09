<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An employee's request to take over a customer whose follow-up has gone unactioned, pending admin approval. */
class ReassignmentRequest extends Model
{
    protected $fillable = ['customer_id', 'requested_by', 'previous_assignee', 'reason', 'status', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function previousAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_assignee');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
