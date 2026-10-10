<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A supplier release / receiving permit (إذن صرف): the group of vehicles that arrived together. */
class VehicleBatch extends Model
{
    protected $fillable = ['permit_no', 'sale_order', 'invoice_no', 'received_at', 'source', 'transport_company', 'driver', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['received_at' => 'date'];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'batch_id');
    }

    public function label(): string
    {
        return trim(($this->permit_no ?: 'إذن #' . $this->id) . ' — ' . ($this->received_at?->format('Y/m/d') ?? ''), ' —');
    }
}
