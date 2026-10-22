<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Activity;
use App\Support\InstallmentCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DealService
{
    /**
     * Create or update a deal and (re)build its installment schedule.
     * $data keys: vehicle, model, chassis, motor, pay_method, finance_entity, stage, status, total_price, down_payment,
     * months, interest_rate, interest_type, admin_fees, monthly_installment, first_due_date, delivery_date, notes
     */
    public function save(Customer $customer, array $data, ?Deal $deal = null, ?User $by = null): Deal
    {
        return DB::transaction(function () use ($customer, $data, $deal, $by) {
            $isNew = ! $deal;
            $deal ??= new Deal(['customer_id' => $customer->id, 'created_by' => $by?->id]);

            $price = (float) ($data['total_price'] ?? 0);
            $down = min($price, (float) ($data['down_payment'] ?? 0));
            $isInstallment = ($data['pay_method'] ?? 'كاش') === 'تقسيط';
            $months = $isInstallment ? max(0, (int) ($data['months'] ?? 0)) : 0;

            $deal->fill([
                'vehicle' => $data['vehicle'] ?? null,
                'model' => $data['model'] ?? null,
                'chassis' => $data['chassis'] ?? null,
                'motor' => $data['motor'] ?? null,
                'pay_method' => $isInstallment ? 'تقسيط' : 'كاش',
                'finance_entity' => $data['finance_entity'] ?? null,
                'stage' => $data['stage'] ?? null,
                'status' => $data['status'] ?? 'مفتوحة',
                'total_price' => $price,
                'down_payment' => $down,
                'financed_amount' => max(0, $price - $down),
                'months' => $months,
                'interest_rate' => (float) ($data['interest_rate'] ?? 0),
                'interest_type' => in_array($data['interest_type'] ?? 'flat', ['reducing', 'table'], true) ? $data['interest_type'] : 'flat',
                'admin_fees' => (float) ($data['admin_fees'] ?? 0),
                'first_due_date' => ! empty($data['first_due_date']) ? $data['first_due_date'] : null,
                'delivery_date' => ! empty($data['delivery_date']) ? $data['delivery_date'] : null,
                'notes' => $data['notes'] ?? null,
            ]);
            foreach (['color', 'dealer', 'entity_branch', 'po_number', 'sales_order', 'invoice_no', 'treasury_receipt', 'mobaya_no'] as $f) {
                if (array_key_exists($f, $data)) {
                    $deal->{$f} = $data[$f] !== '' ? $data[$f] : null;
                }
            }
            foreach (['sale_date', 'mobaya_arrived_at', 'mobaya_received_at'] as $f) {
                if (array_key_exists($f, $data)) {
                    $deal->{$f} = ! empty($data[$f]) ? $data[$f] : null;
                }
            }
            if (array_key_exists('entity_net', $data)) {
                $deal->entity_net = ($data['entity_net'] ?? '') !== '' && $data['entity_net'] !== null ? (float) $data['entity_net'] : null;
            }
            // a chassis from our stock fills what was left empty on the deal
            if ($unit = $this->inventoryUnit($deal)) {
                $deal->motor = $deal->motor ?: $unit->motor;
                $deal->color = $deal->color ?: $unit->color;
                $deal->model = $deal->model ?: $unit->model_year;
            }
            if (array_key_exists('customer_notified', $data)) {
                $deal->customer_notified = (bool) $data['customer_notified'];
            }

            $schedule = [];
            if ($isInstallment && $months > 0 && $deal->financed_amount > 0) {
                $manualMonthly = (float) ($data['monthly_installment'] ?? 0);
                if ($deal->interest_type !== 'table' && $deal->interest_rate <= 0 && $manualMonthly > 0) {
                    $schedule = $this->manualSchedule($deal->financed_amount, $manualMonthly, $months, $deal->first_due_date);
                    $deal->monthly_installment = $manualMonthly;
                } else {
                    $calc = InstallmentCalculator::calculate([
                        'price' => $price, 'down' => $down, 'months' => $months, 'rate' => $deal->interest_rate,
                        'type' => $deal->interest_type, 'plan' => $deal->finance_entity, 'fees' => $deal->admin_fees, 'first_due' => $deal->first_due_date?->toDateString(),
                    ]);
                    $schedule = $calc['schedule'];
                    $deal->monthly_installment = $calc['monthly'];
                    $deal->admin_fees = (float) $calc['fees'];
                }
                if ($deal->isEntityFinanced()) {
                    // the monthly figure is kept for the offer text; the customer pays it to the entity, so no schedule here
                    $schedule = [];
                } elseif (! $deal->first_due_date && $schedule) {
                    $deal->first_due_date = $schedule[0]['due_date'];
                }
            } else {
                $deal->monthly_installment = 0;
            }

            $deal->save();

            $deal->installments()->delete();
            foreach ($schedule as $row) {
                Installment::create([
                    'deal_id' => $deal->id, 'number' => $row['number'], 'due_date' => $row['due_date'],
                    'amount' => $row['amount'], 'principal' => $row['principal'], 'interest' => $row['interest'],
                ]);
            }

            $deal->reallocatePayments();
            $deal->recalculate();
            $this->syncInventory($deal);

            // keep the customer's headline status/vehicle in sync with their latest deal
            if ((int) $customer->deals()->max('id') === (int) $deal->id) {
                $customer->forceFill(['status' => $deal->status])->saveQuietly();
            }

            return $deal;
        });
    }

    private function manualSchedule(float $financed, float $monthly, int $months, $firstDue): array
    {
        $first = $firstDue ? Carbon::parse($firstDue)->startOfDay() : now()->startOfDay()->addMonthNoOverflow();
        $rows = [];
        $principalEach = round($financed / $months, 2);
        $remainingPrincipal = $financed;
        for ($i = 1; $i <= $months; $i++) {
            $principal = $i === $months ? round($remainingPrincipal, 2) : $principalEach;
            $remainingPrincipal -= $principal;
            $rows[] = [
                'number' => $i,
                'due_date' => $first->copy()->addMonthsNoOverflow($i - 1)->toDateString(),
                'amount' => $monthly,
                'principal' => $principal,
                'interest' => max(0, round($monthly - $principal, 2)),
            ];
        }

        return $rows;
    }

    public function addPayment(Deal $deal, float $amount, string $paidOn, ?string $method, ?string $note, ?User $by): Payment
    {
        return DB::transaction(function () use ($deal, $amount, $paidOn, $method, $note, $by) {
            $payment = Payment::create([
                'deal_id' => $deal->id, 'amount' => $amount, 'paid_on' => $paidOn,
                'method' => $method, 'note' => $note, 'received_by' => $by?->id,
            ]);
            $deal->reallocatePayments();
            $deal->recalculate();

            if ($deal->balance <= 0 && $deal->isInstallment() && $deal->status !== 'منفذة') {
                $deal->forceFill(['status' => 'منفذة'])->saveQuietly();
                $this->syncInventory($deal);
            }

            return $payment;
        });
    }

    /**
     * Record the entity's transfer: reference, deposit / collection dates and where the money went.
     * Once collected it becomes a payment on the deal, so the debt and the collections reports follow it.
     */
    public function saveEntityCollection(Deal $deal, array $data, ?User $by = null): Deal
    {
        return DB::transaction(function () use ($deal, $data, $by) {
            foreach (['entity_ref', 'entity_deposited_at', 'entity_collected_at', 'entity_channel', 'entity_account', 'entity_note'] as $f) {
                $deal->{$f} = ($data[$f] ?? '') !== '' ? $data[$f] : null;
            }
            $payment = $deal->entity_payment_id ? Payment::find($deal->entity_payment_id) : null;
            $others = (float) $deal->payments()->when($payment, fn ($q) => $q->where('id', '!=', $payment->id))->sum('amount');
            $deal->entity_amount = ($data['entity_amount'] ?? '') !== '' ? (float) $data['entity_amount'] : max(0, round($deal->total_payable - $others, 2));

            if ($deal->entity_collected_at && $deal->entity_amount > 0) {
                $attrs = [
                    'deal_id' => $deal->id, 'amount' => $deal->entity_amount, 'paid_on' => $deal->entity_collected_at->toDateString(),
                    'method' => $deal->entity_channel ?: 'تحويل بنكي',
                    'note' => mb_substr(implode(' — ', array_filter(['تحصيل من ' . $deal->finance_entity, $deal->entity_ref ? 'مرجع ' . $deal->entity_ref : null, $deal->entity_account])), 0, 200),
                ];
                $payment ? $payment->update($attrs) : $payment = Payment::create($attrs + ['received_by' => $by?->id]);
                $deal->entity_payment_id = $payment->id;
            } else {
                $payment?->delete();
                $deal->entity_payment_id = null;
            }
            $deal->save();
            $deal->reallocatePayments();
            $deal->recalculate();

            if ($deal->entity_collected_at && $deal->balance <= 0 && $deal->status !== 'منفذة') {
                $deal->forceFill(['status' => 'منفذة'])->saveQuietly();
                $this->syncInventory($deal);
            }

            return $deal;
        });
    }

    /* ------------------------------------------------------------ the deal's vehicle in our stock */

    /** The stock vehicle with the deal's chassis (or motor when the chassis is empty). */
    public function inventoryUnit(Deal $deal): ?Vehicle
    {
        $chassis = Vehicle::cleanSerial($deal->chassis);
        $motor = Vehicle::cleanSerial($deal->motor);
        if ($chassis !== '') {
            return Vehicle::where('chassis', $chassis)->first();
        }

        return $motor !== '' ? Vehicle::where('motor', $motor)->first() : null;
    }

    /** "this chassis is held by another customer's deal" (null when free), checked before saving a deal. */
    public function inventoryConflict(?string $chassis, ?string $motor, ?int $exceptDeal = null): ?string
    {
        $unit = $this->inventoryUnit(new Deal(['chassis' => $chassis, 'motor' => $motor]));
        $other = $unit?->deal_id && (int) $unit->deal_id !== (int) $exceptDeal ? Deal::with('customer:id,name,code')->find($unit->deal_id) : null;

        return $other ? 'رقم الشاسيه ' . $unit->chassis . ' ' . ($unit->status === 'sold' ? 'مباع' : 'محجوز') . ' في المخزن للعميل ' . $other->customer?->name . ' (' . $other->customer?->code . ')' : null;
    }

    /**
     * Saving a deal holds its chassis in stock (محجوزة); once the sale is done it leaves stock (مباعة) with the buyer,
     * price and date. A lost or deleted deal, or a changed chassis, puts the vehicle back in stock.
     */
    public function syncInventory(Deal $deal): ?Vehicle
    {
        $unit = $deal->trashed() || $deal->status === 'مفقودة' ? null : $this->inventoryUnit($deal);
        if ($unit && $unit->deal_id && (int) $unit->deal_id !== (int) $deal->id && Deal::whereKey($unit->deal_id)->exists()) {
            $unit = null;   // another live deal holds it
        }
        foreach (Vehicle::where('deal_id', $deal->id)->when($unit, fn ($q) => $q->whereKeyNot($unit->id))->get() as $old) {
            $old->update(['status' => 'in_stock', 'deal_id' => null, 'sold_at' => null, 'sale_price' => null]);
            Activity::log('vehicle', $old, 'رجوع الشاسيه ' . $old->chassis . ' للمخزن (صفقة ' . ($deal->customer?->name ?? '') . ')');
        }
        if (! $unit) {
            return null;
        }

        $sold = $deal->isSold();
        $before = [$unit->status, $unit->deal_id];
        $unit->fill([
            'deal_id' => $deal->id, 'status' => $sold ? 'sold' : 'reserved',
            'sold_at' => $sold ? ($unit->status === 'sold' && $unit->sold_at ? $unit->sold_at : ($deal->sale_date ?? $deal->delivery_date ?? today())) : null,
            'sale_price' => $deal->total_price > 0 ? $deal->total_price : $unit->sale_price,
        ])->save();
        if ($before !== [$unit->status, $unit->deal_id]) {
            Activity::log('vehicle', $unit, 'الشاسيه ' . $unit->chassis . ($sold ? ' مباع' : ' محجوز') . ' للعميل ' . ($deal->customer?->name ?? ''));
        }

        return $unit;
    }

    public function deletePayment(Payment $payment): void
    {
        $deal = $payment->deal;
        DB::transaction(function () use ($payment, $deal) {
            if ((int) $deal->entity_payment_id === (int) $payment->id) {
                // deleting the entity's transfer puts it back to "not collected"
                $deal->forceFill(['entity_payment_id' => null, 'entity_collected_at' => null])->saveQuietly();
            }
            $payment->delete();
            $deal->reallocatePayments();
            $deal->recalculate();
        });
    }
}
