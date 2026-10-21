<?php

use App\Models\Deal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a deal financed by an outside entity (ريفي، أمان...): the entity pays the company the remainder in one transfer
        Schema::table('deals', function (Blueprint $t) {
            $t->string('entity_branch', 120)->nullable()->after('finance_entity');
            $t->decimal('entity_amount', 12, 2)->nullable();
            $t->string('entity_ref', 80)->nullable();
            $t->date('entity_deposited_at')->nullable();
            $t->date('entity_collected_at')->nullable();
            $t->string('entity_channel', 40)->nullable();
            $t->string('entity_account', 120)->nullable();
            $t->string('entity_note', 250)->nullable();
            $t->unsignedBigInteger('entity_payment_id')->nullable();
        });

        // a requested vehicle remembers its installment calculation and where the customer was sent
        Schema::table('customer_vehicles', function (Blueprint $t) {
            $t->string('finance_entity', 120)->nullable()->after('vehicle');
            $t->string('entity_branch', 120)->nullable()->after('finance_entity');
            $t->string('stage', 120)->nullable()->after('entity_branch');
            $t->json('calc')->nullable()->after('stage');
            $t->timestamp('entity_sent_at')->nullable();
            $t->index('finance_entity');
        });

        // the customer pays the entity, not us: entity deals drop the monthly schedule and owe the financed amount
        Deal::query()->entityFinanced()->get()->each(function (Deal $d) {
            if (! $d->payments()->exists()) {
                $d->installments()->delete();
                $d->recalculate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_vehicles', function (Blueprint $t) {
            $t->dropIndex(['finance_entity']);
            $t->dropColumn(['finance_entity', 'entity_branch', 'stage', 'calc', 'entity_sent_at']);
        });
        Schema::table('deals', function (Blueprint $t) {
            $t->dropColumn(['entity_branch', 'entity_amount', 'entity_ref', 'entity_deposited_at', 'entity_collected_at', 'entity_channel', 'entity_account', 'entity_note', 'entity_payment_id']);
        });
    }
};
