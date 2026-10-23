<?php

use App\Models\Deal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a dealer (تاجر / معرض) buys vehicles in quantity, not a retail customer
        Schema::table('customers', function (Blueprint $t) {
            $t->boolean('is_dealer')->default(false)->after('status');
            $t->string('shop_name', 150)->nullable()->after('is_dealer');
        });

        // a batch deal: the other chassis (one per line) besides the deal's own chassis
        Schema::table('deals', function (Blueprint $t) {
            $t->text('units')->nullable()->after('motor');
        });

        // entity debts no longer deduct the percentage and نثريات twice when the entity also keeps the admin fees
        Deal::query()->entityFinanced()->get()->each(fn (Deal $d) => $d->recalculate());
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn(['is_dealer', 'shop_name']);
        });
        Schema::table('deals', function (Blueprint $t) {
            $t->dropColumn('units');
        });
    }
};
