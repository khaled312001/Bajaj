<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A customer handed to an employee by someone else stays "new" for them until they open the file. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->timestamp('assignment_seen_at')->nullable()->after('assigned_at');
            $table->index(['assigned_to', 'assignment_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['assigned_to', 'assignment_seen_at']);
            $table->dropColumn(['assigned_at', 'assignment_seen_at']);
        });
    }
};
