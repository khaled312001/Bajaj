<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $t) {
            $t->id();
            $t->string('type', 40)->index();
            $t->string('name');
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['type', 'name']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name')->index();
            $t->text('nat_id')->nullable();                      // encrypted
            $t->string('nat_id_hash', 64)->nullable()->index();  // blind index for search
            $t->string('job')->nullable();
            $t->string('phone', 20)->index();
            $t->string('alt_phone', 20)->nullable()->index();
            $t->string('whatsapp', 20)->nullable();
            $t->string('governorate')->nullable()->index();
            $t->string('district')->nullable();
            $t->string('address')->nullable();
            $t->string('channel')->nullable()->index();
            $t->string('interest')->nullable();
            $t->unsignedTinyInteger('age')->nullable();
            $t->string('seriousness', 40)->nullable()->index();   // جدية العميل
            $t->string('previous_vehicle')->nullable();           // المركبة السابقة
            $t->string('branch')->nullable()->index();            // الفرع
            $t->text('loss_reason')->nullable();                  // سبب عدم إتمام البيع
            $t->timestamp('contacted_at')->nullable();            // أول تواصل
            $t->string('status', 30)->default('مفتوحة')->index();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index('created_at');
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->decimal('price', 14, 2)->default(0);
            $t->string('warranty')->nullable();
            $t->text('requirements')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('customer_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 40)->index();
            $t->string('description');
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('deals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('vehicle')->nullable()->index();
            $t->string('model')->nullable();
            $t->string('chassis')->nullable();
            $t->string('motor')->nullable();
            $t->string('color')->nullable();
            $t->date('sale_date')->nullable();
            $t->string('dealer')->nullable();               // الشاسيه تابع التاجر
            $t->string('pay_method', 20)->default('كاش');
            $t->string('finance_entity')->nullable()->index();
            $t->string('stage')->nullable();
            $t->string('status', 30)->default('مفتوحة')->index();
            $t->decimal('total_price', 14, 2)->default(0);
            $t->decimal('down_payment', 14, 2)->default(0);
            $t->decimal('financed_amount', 14, 2)->default(0);
            $t->unsignedSmallInteger('months')->default(0);
            $t->decimal('interest_rate', 6, 2)->default(0);
            $t->string('interest_type', 10)->default('flat');
            $t->decimal('admin_fees', 14, 2)->default(0);
            $t->decimal('monthly_installment', 14, 2)->default(0);
            $t->decimal('total_payable', 14, 2)->default(0);
            $t->decimal('paid_total', 14, 2)->default(0);   // collected after down payment
            $t->decimal('balance', 14, 2)->default(0);      // remaining debt
            $t->date('first_due_date')->nullable();
            $t->date('delivery_date')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('number');
            $t->date('due_date')->index();
            $t->decimal('amount', 14, 2);
            $t->decimal('principal', 14, 2)->default(0);
            $t->decimal('interest', 14, 2)->default(0);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['deal_id', 'number']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 14, 2);
            $t->date('paid_on')->index();
            $t->string('method', 30)->nullable();
            $t->string('note')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->string('priority', 10)->default('normal');
            $t->text('notes')->nullable();
            $t->date('due_date')->index();
            $t->time('due_time')->nullable();
            $t->string('status', 15)->default('pending')->index(); // pending | done | cancelled
            $t->timestamp('completed_at')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('outcome')->nullable();
            $t->timestamps();
            $t->index(['assigned_to', 'status', 'due_date']);
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 50)->index();
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('description')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 30);
            $t->string('filename');
            $t->string('path');
            $t->string('status', 20)->default('pending'); // pending | done | cancelled
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('valid')->default(0);
            $t->unsignedInteger('created')->default(0);
            $t->unsignedInteger('updated')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->json('errors')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['products', 'imports', 'activity_logs', 'followups', 'payments', 'installments', 'deals', 'customer_events', 'customers', 'settings', 'lookups'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
