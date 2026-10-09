<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle');
            $table->string('notes', 250)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
        });

        // Backfill: every customer's existing single `interest` becomes their first vehicle row.
        DB::table('customers')->whereNotNull('interest')->where('interest', '!=', '')
            ->orderBy('id')->get(['id', 'interest', 'created_by', 'created_at'])
            ->each(function ($c) {
                DB::table('customer_vehicles')->insert([
                    'customer_id' => $c->id,
                    'vehicle' => $c->interest,
                    'created_by' => $c->created_by,
                    'created_at' => $c->created_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vehicles');
    }
};
