<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    protected $fillable = ['deal_id', 'number', 'due_date', 'amount', 'principal', 'interest', 'paid_amount', 'paid_at'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date', 'paid_at' => 'datetime',
            'amount' => 'float', 'principal' => 'float', 'interest' => 'float', 'paid_amount' => 'float',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function getRemainingAttribute(): float
    {
        return max(0, round($this->amount - $this->paid_amount, 2));
    }

    /** paid | partial | overdue | due_today | upcoming */
    public function getStateAttribute(): string
    {
        if ($this->remaining <= 0.009) {
            return 'paid';
        }
        if ($this->due_date->isPast() && ! $this->due_date->isToday()) {
            return 'overdue';
        }
        if ($this->due_date->isToday()) {
            return 'due_today';
        }

        return $this->paid_amount > 0 ? 'partial' : 'upcoming';
    }

    public function getStateLabelAttribute(): string
    {
        return ['paid' => 'مدفوع', 'partial' => 'سداد جزئي', 'overdue' => 'متأخر', 'due_today' => 'مستحق اليوم', 'upcoming' => 'قادم'][$this->state];
    }

    public function getStateClassAttribute(): string
    {
        return ['paid' => 'green', 'partial' => 'amber', 'overdue' => 'red', 'due_today' => 'amber', 'upcoming' => 'gray'][$this->state];
    }
}
