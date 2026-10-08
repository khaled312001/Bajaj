<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('chassis', 60)->unique();
            $t->string('motor', 60)->nullable()->index();
            $t->string('type')->nullable()->index();          // Boxer BM / Qute ...
            $t->string('model_year', 10)->nullable();
            $t->string('color', 60)->nullable();
            $t->string('source_store')->nullable();           // من مخزن (طوبيا إكسبريس / جي بي أوتو)
            $t->string('branch_store')->nullable();           // إلى مخزن الشركة
            $t->decimal('cost_price', 14, 2)->default(0);
            $t->decimal('transport_cost', 14, 2)->default(0);
            $t->decimal('other_cost', 14, 2)->default(0);
            $t->date('arrived_at')->nullable();
            $t->string('status', 15)->default('in_stock')->index();   // in_stock | reserved | sold
            $t->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $t->date('sold_at')->nullable();
            $t->decimal('sale_price', 14, 2)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('deals', function (Blueprint $t) {
            $t->string('po_number', 40)->nullable();
            $t->string('sales_order', 40)->nullable();
            $t->string('invoice_no', 40)->nullable();
            $t->string('treasury_receipt', 40)->nullable();
            $t->string('mobaya_no', 40)->nullable()->index();
            $t->date('mobaya_arrived_at')->nullable();
            $t->date('mobaya_received_at')->nullable();
            $t->boolean('customer_notified')->default(false);
        });

        Schema::create('document_logs', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30)->index();
            $t->unsignedInteger('serial')->default(0);
            $t->string('legacy_ref', 40)->nullable();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('customer_name')->nullable();
            $t->string('product_name')->nullable();
            $t->decimal('amount', 14, 2)->nullable();
            $t->text('payload')->nullable();              // encrypted JSON of the printed values (for re-print)
            $t->timestamp('printed_at')->useCurrent()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_logs');
        Schema::table('deals', function (Blueprint $t) {
            $t->dropColumn(['po_number', 'sales_order', 'invoice_no', 'treasury_receipt', 'mobaya_no', 'mobaya_arrived_at', 'mobaya_received_at', 'customer_notified']);
        });
        Schema::dropIfExists('vehicles');
    }
};
