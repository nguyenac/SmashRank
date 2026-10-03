<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Theo dõi tiến độ học viên: nhịp tim đi kèm cân nặng
        Schema::table('weight_entries', function (Blueprint $table) {
            $table->unsignedSmallInteger('heart_rate')->nullable()->after('weight_kg'); // bpm trung bình buổi tập
        });
    }

    public function down(): void
    {
        Schema::table('weight_entries', fn (Blueprint $t) => $t->dropColumn('heart_rate'));
    }
};
