<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One vehicle a customer has expressed interest in (a customer may have several, newest first). */
class CustomerVehicle extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_id', 'vehicle', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
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
