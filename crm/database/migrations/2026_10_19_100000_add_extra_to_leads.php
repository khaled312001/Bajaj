<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Answers to the custom fields the admin adds to the public lead form. */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->json('extra')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('extra');
        });
    }
};
