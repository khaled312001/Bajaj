<?php

use App\Models\Deal;
use App\Services\DealService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what the finance entity actually transfers, when typed by hand (else financed minus the entity's deductions)
        Schema::table('deals', function (Blueprint $t) {
            $t->decimal('entity_net', 12, 2)->nullable()->after('entity_amount');
        });

        // entity debts become the net transfer
        Deal::query()->entityFinanced()->get()->each(fn (Deal $d) => $d->recalculate());

        // deals entered before: their chassis is held / sold in stock
        $service = app(DealService::class);
        Deal::query()->where(fn ($q) => $q->where('chassis', '!=', '')->orWhere('motor', '!=', ''))->orderBy('id')->get()
            ->each(fn (Deal $d) => $service->syncInventory($d));
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $t) {
            $t->dropColumn('entity_net');
        });
    }
};
