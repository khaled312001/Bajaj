<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_profiles', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80)->unique();
            $t->string('description')->nullable();
            $t->json('permissions')->nullable();      // {"customers":["view","create"],...}
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_profile_id')->nullable()->after('role')->constrained('role_profiles')->nullOnDelete();
        });

        Schema::create('backups', function (Blueprint $t) {
            $t->id();
            $t->string('filename')->unique();
            $t->unsignedBigInteger('size')->default(0);
            $t->unsignedInteger('tables')->default(0);
            $t->unsignedBigInteger('rows')->default(0);
            $t->string('kind', 12)->default('auto');      // auto | manual
            $t->string('status', 12)->default('ok');
            $t->text('error')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_profile_id'));
        Schema::dropIfExists('role_profiles');
    }
};
