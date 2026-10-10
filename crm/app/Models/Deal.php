<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    public const PAY_METHODS = ['كاش', 'تقسيط'];

    protected $fillable = [
        'customer_id', 'vehicle', 'model', 'chassis', 'motor', 'color', 'sale_date', 'dealer', 'pay_method', 'finance_entity', 'stage',
        'status', 'total_price', 'down_payment', 'financed_amount', 'months', 'interest_rate',
        'interest_type', 'admin_fees', 'monthly_installment', 'total_payable', 'paid_total', 'balance',
        'first_due_date', 'delivery_date', 'notes', 'created_by',
        'po_number', 'sales_order', 'invoice_no', 'treasury_receipt', 'mobaya_no', 'mobaya_arrived_at', 'mobaya_received_at', 'customer_notified',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'float', 'down_payment' => 'float', 'financed_amount' => 'float',
            'interest_rate' => 'float', 'admin_fees' => 'float', 'monthly_installment' => 'float',
            'total_payable' => 'float', 'paid_total' => 'float', 'balance' => 'float',
            'first_due_date' => 'date', 'delivery_date' => 'date', 'sale_date' => 'date',
            'mobaya_arrived_at' => 'date', 'mobaya_received_at' => 'date', 'customer_notified' => 'boolean',
        ];
    }

    public function vehicleUnit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_on')->latest('id');
    }

    public function isInstallment(): bool
    {
        return $this->pay_method === 'تقسيط';
    }

    /** Plain-text installment breakdown for pasting into WhatsApp, e.g. to send to the financing company. */
    public function installmentCopyText(): ?string
    {
        if (! $this->isInstallment() || $this->monthly_installment <= 0) {
            return null;
        }

        $fmt = fn ($v) => number_format((float) $v, 0, '.', ',');
        $firstTotal = $this->down_payment + $this->monthly_installment + $this->admin_fees;
        $remainingMonths = max(0, $this->months - 1);
        $grandTotal = $firstTotal + $this->monthly_installment * $remainingMonths;

        return implode("\n", [
            '*تفاصيل التقسيط*',
            '=================================',
            '*المنتج:* ' . trim($this->vehicle . ' ' . $this->model),
            '*سعر الكاش:* ' . $fmt($this->total_price) . ' جنيه',
            '*المقدم الأساسي:* ' . $fmt($this->down_payment) . ' جنيه',
            '*الباقي:* ' . $fmt($this->financed_amount) . ' جنيه',
            '*المصاريف الإدارية:* ' . $fmt($this->admin_fees) . ' جنيه',
            '*القسط الأول:* ' . $fmt($this->monthly_installment) . ' جنيه',
            '*إجمالي المقدم (شامل القسط الاول والمصاريف):* ' . $fmt($firstTotal) . ' جنيه',
            '=================================',
            '',
            '*نظام التقسيط (' . $this->months . ' شهر)*',
            '   > باقي الأقساط: ' . $fmt($this->monthly_installment) . ' جنيه × (' . $remainingMonths . ' شهر)',
            '   > الإجمالي الكلي: ' . $fmt($grandTotal) . ' جنيه',
            '---------------------------------',
        ]);
    }

    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            'منفذة' => 'green', 'جاري التقسيط' => 'amber', 'مفتوحة' => 'blue', 'مفقودة' => 'red', default => 'gray',
        };
    }

    /** Keep paid_total / balance / total_payable in sync with installments & payments. */
    public function recalculate(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $scheduled = (float) $this->installments()->sum('amount');

        if ($scheduled > 0) {
            $this->total_payable = $scheduled;
            $balance = $scheduled - $paid;
        } else {
            $this->total_payable = max(0, $this->total_price - $this->down_payment);
            $balance = $this->total_payable - $paid;
        }

        $this->paid_total = $paid;
        $this->balance = max(0, round($balance, 2));
        $this->saveQuietly();
    }

    /** Spread payments across installments, oldest first. */
    public function reallocatePayments(): void
    {
        $remaining = (float) $this->payments()->sum('amount');
        foreach ($this->installments()->get() as $inst) {
            $apply = min($remaining, (float) $inst->amount);
            $inst->paid_amount = round($apply, 2);
            $inst->paid_at = $apply >= (float) $inst->amount - 0.009 ? ($inst->paid_at ?? now()) : null;
            $inst->save();
            $remaining -= $apply;
        }
    }
}
