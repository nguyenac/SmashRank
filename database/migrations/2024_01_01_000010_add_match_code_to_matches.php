<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Match Code: nghiệp vụ Elo giao lưu — 2 bên xác nhận qua mã chống gian lận
        Schema::table('matches', function (Blueprint $table) {
            $table->string('match_code', 8)->nullable()->unique()->after('id');
            $table->string('status')->default('confirmed')->after('walkover'); // pending | confirmed | cancelled
            $table->foreignId('created_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['confirmed_by_user_id']);
            $table->dropColumn(['match_code', 'status', 'created_by_user_id', 'confirmed_by_user_id']);
        });
    }
};
