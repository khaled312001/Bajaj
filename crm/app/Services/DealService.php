<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\User;
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
            foreach (['color', 'dealer', 'po_number', 'sales_order', 'invoice_no', 'treasury_receipt', 'mobaya_no'] as $f) {
                if (array_key_exists($f, $data)) {
                    $deal->{$f} = $data[$f] !== '' ? $data[$f] : null;
                }
            }
            foreach (['sale_date', 'mobaya_arrived_at', 'mobaya_received_at'] as $f) {
                if (array_key_exists($f, $data)) {
                    $deal->{$f} = ! empty($data[$f]) ? $data[$f] : null;
                }
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
                }
                if (! $deal->first_due_date && $schedule) {
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
            }

            return $payment;
        });
    }

    public function deletePayment(Payment $payment): void
    {
        $deal = $payment->deal;
        DB::transaction(function () use ($payment, $deal) {
            $payment->delete();
            $deal->reallocatePayments();
            $deal->recalculate();
        });
    }
}
