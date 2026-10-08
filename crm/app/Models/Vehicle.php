<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public const STATUSES = ['in_stock' => 'متاحة بالمخزن', 'reserved' => 'محجوزة', 'sold' => 'مباعة'];

    protected $fillable = [
        'chassis', 'motor', 'type', 'model_year', 'color', 'source_store', 'branch_store', 'cost_price', 'transport_cost',
        'other_cost', 'arrived_at', 'status', 'deal_id', 'sold_at', 'sale_price', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['arrived_at' => 'date', 'sold_at' => 'date'];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->cost_price + (float) $this->transport_cost + (float) $this->other_cost;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusClassAttribute(): string
    {
        return ['in_stock' => 'green', 'reserved' => 'amber', 'sold' => 'gray'][$this->status] ?? 'gray';
    }

    public static function cleanSerial(?string $v): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $v));
    }
}
