<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'client'])->default('client');
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_image')->nullable();
            $table->enum('account_status', ['active', 'disabled'])->default('active');
            $table->string('verification_code')->nullable();
            $table->timestamp('verification_expiry')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'phone',
                'whatsapp',
                'bio',
                'profile_image',
                'account_status',
                'verification_code',
                'verification_expiry',
            ]);
        });
    }
};
