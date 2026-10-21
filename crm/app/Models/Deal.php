<?php

namespace App\Models;

use App\Support\InstallmentCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    public const PAY_METHODS = ['كاش', 'تقسيط'];

    /** Installments with the company itself: these keep a monthly schedule. Any other entity pays us the remainder. */
    public const DIRECT_ENTITIES = ['نظام الشركة', 'الشركة', 'مباشر', 'تقسيط مباشر'];

    /** The entity's transfer sits frozen this many days before it can be collected. */
    public const ENTITY_DAYS = 15;

    /** How the entity's money reached us. */
    public const ENTITY_CHANNELS = ['تحويل بنكي', 'إنستاباي', 'نقدي', 'شيك', 'فودافون كاش'];

    protected $fillable = [
        'customer_id', 'vehicle', 'model', 'chassis', 'motor', 'color', 'sale_date', 'dealer', 'pay_method', 'finance_entity', 'entity_branch', 'stage',
        'status', 'total_price', 'down_payment', 'financed_amount', 'months', 'interest_rate',
        'interest_type', 'admin_fees', 'monthly_installment', 'total_payable', 'paid_total', 'balance',
        'first_due_date', 'delivery_date', 'notes', 'created_by',
        'po_number', 'sales_order', 'invoice_no', 'treasury_receipt', 'mobaya_no', 'mobaya_arrived_at', 'mobaya_received_at', 'customer_notified',
        'entity_amount', 'entity_ref', 'entity_deposited_at', 'entity_collected_at', 'entity_channel', 'entity_account', 'entity_note', 'entity_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'float', 'down_payment' => 'float', 'financed_amount' => 'float',
            'interest_rate' => 'float', 'admin_fees' => 'float', 'monthly_installment' => 'float',
            'total_payable' => 'float', 'paid_total' => 'float', 'balance' => 'float',
            'first_due_date' => 'date', 'delivery_date' => 'date', 'sale_date' => 'date',
            'mobaya_arrived_at' => 'date', 'mobaya_received_at' => 'date', 'customer_notified' => 'boolean',
            'entity_amount' => 'float', 'entity_deposited_at' => 'date', 'entity_collected_at' => 'date',
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

    public static function isOutsideEntity(?string $entity): bool
    {
        return trim((string) $entity) !== '' && ! in_array(trim($entity), self::DIRECT_ENTITIES, true);
    }

    /** Financed by an outside entity: the customer pays the entity, the entity owes us the remainder (no schedule here). */
    public function isEntityFinanced(): bool
    {
        return $this->isInstallment() && self::isOutsideEntity($this->finance_entity);
    }

    /** Branch suggestions for an entity (قفط، قوص...): the company branches plus every branch typed before. */
    public static function entityBranches(): array
    {
        return collect(Lookup::list('branch'))
            ->merge(self::query()->whereNotNull('entity_branch')->distinct()->pluck('entity_branch'))
            ->merge(CustomerVehicle::query()->whereNotNull('entity_branch')->distinct()->pluck('entity_branch'))
            ->map(fn ($b) => trim($b))->filter()->unique()->values()->all();
    }

    public function scopeEntityFinanced($q)
    {
        return $q->where('deals.pay_method', 'تقسيط')->whereNotNull('deals.finance_entity')->where('deals.finance_entity', '!=', '')
            ->whereNotIn('deals.finance_entity', self::DIRECT_ENTITIES);
    }

    /** collected | deposited (frozen at the entity) | pending (nothing sent yet) */
    public function getEntityStateAttribute(): string
    {
        return $this->entity_collected_at ? 'collected' : ($this->entity_deposited_at ? 'deposited' : 'pending');
    }

    /** The day the entity's money should be in our hands. */
    public function getEntityDueDateAttribute(): \Carbon\Carbon
    {
        return ($this->entity_deposited_at ?? $this->sale_date ?? $this->delivery_date ?? $this->created_at)->copy()->startOfDay()->addDays(self::ENTITY_DAYS);
    }

    public function getEntityLateDaysAttribute(): int
    {
        return $this->entity_state === 'collected' || ! $this->entity_due_date->isPast() ? 0 : (int) $this->entity_due_date->diffInDays(today());
    }

    public function getEntityStateLabelAttribute(): string
    {
        return match (true) {
            $this->entity_state === 'collected' => 'تم التحصيل',
            $this->entity_late_days > 0 => 'متأخر ' . $this->entity_late_days . ' يوم',
            $this->entity_state === 'deposited' => 'مجمد — يُحصّل ' . $this->entity_due_date->format('Y/m/d'),
            default => 'لم يتم الإيداع',
        };
    }

    public function getEntityStateClassAttribute(): string
    {
        return $this->entity_state === 'collected' ? 'green' : ($this->entity_late_days > 0 ? 'red' : ($this->entity_state === 'deposited' ? 'blue' : 'amber'));
    }

    /** Plain-text installment breakdown for pasting into WhatsApp, e.g. to send to the financing company. */
    public function installmentCopyText(): ?string
    {
        if (! $this->isInstallment() || $this->monthly_installment <= 0) {
            return null;
        }

        return InstallmentCalculator::financeCopyText(trim($this->vehicle . ' ' . $this->model), (float) $this->total_price,
            (float) $this->down_payment, (float) $this->financed_amount, (float) $this->admin_fees, (float) $this->monthly_installment, (int) $this->months);
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
