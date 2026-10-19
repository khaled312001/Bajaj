<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A public lead-capture form submission, triaged by the admin before it becomes a real customer. */
class Lead extends Model
{
    public const STATUSES = ['new', 'assigned', 'converted', 'rejected'];

    protected $fillable = [
        'name', 'phone', 'vehicle', 'governorate', 'district', 'address', 'extra',
        'status', 'assigned_to', 'customer_id', 'ip', 'converted_at',
    ];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime', 'extra' => 'array'];
    }

    /** The answers to the admin's custom form fields, one "label: value" per line. */
    public function extraText(): string
    {
        return collect($this->extra ?? [])->map(fn ($e) => ($e['label'] ?? '') . ': ' . ($e['value'] ?? ''))->implode("\n");
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
