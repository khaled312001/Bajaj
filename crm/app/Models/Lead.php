<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A public lead-capture form submission, triaged by the admin before it becomes a real customer. */
class Lead extends Model
{
    public const STATUSES = ['new', 'assigned', 'converted', 'rejected'];

    protected $fillable = [
        'name', 'phone', 'vehicle', 'governorate', 'district', 'address',
        'status', 'assigned_to', 'customer_id', 'ip', 'converted_at',
    ];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
