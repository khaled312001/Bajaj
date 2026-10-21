<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One vehicle a customer has expressed interest in (a customer may have several, newest first). */
class CustomerVehicle extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_id', 'vehicle', 'finance_entity', 'entity_branch', 'stage', 'calc', 'entity_sent_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'entity_sent_at' => 'datetime', 'calc' => 'array'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
