<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Chat học viên ↔ HLV (polling; nâng cấp Laravel Reverb/WebSockets sau)
        // ------------------------------------------------------------------
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable(); // ảnh/clip tập luyện
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'recipient_id', 'created_at']);
        });

        // ------------------------------------------------------------------
        // Thanh toán: hóa đơn gói tập (VNPay/MoMo stub)
        // ------------------------------------------------------------------
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_name');                 // Gói tháng, Gói 10 buổi...
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('bank');      // bank | vnpay | momo | cash
            $table->string('status')->default('pending');   // pending | paid | cancelled
            $table->string('reference')->nullable()->unique(); // mã giao dịch cổng thanh toán
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('chat_messages');
    }
};
