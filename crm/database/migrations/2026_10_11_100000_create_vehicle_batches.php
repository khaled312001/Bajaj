<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one supplier release / receiving permit (إذن صرف) = a group of chassis received together
        Schema::create('vehicle_batches', function (Blueprint $t) {
            $t->id();
            $t->string('permit_no', 60)->nullable()->index();   // رقم الإذن  CIO-RWH-009660
            $t->string('sale_order', 60)->nullable();            // أمر البيع
            $t->string('invoice_no', 60)->nullable();            // رقم الفاتورة
            $t->date('received_at')->nullable();
            $t->string('source')->nullable();                    // المورد / من مخزن
            $t->string('transport_company')->nullable();         // شركة النقل
            $t->string('driver')->nullable();                    // السائق المستلم
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('vehicles', function (Blueprint $t) {
            $t->foreignId('batch_id')->nullable()->after('id')->constrained('vehicle_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $t) {
            $t->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('vehicle_batches');
    }
};
