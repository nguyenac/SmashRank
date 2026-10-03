<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->text('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->enum('two_factor_method', ['totp', 'email'])->default('totp');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Mã OTP gửi qua email cho xác thực 2 lớp
        Schema::create('email_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->boolean('consumed')->default(false);
            $table->timestamps();

            $table->index(['email', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_codes');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
