<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // #1 elo:recalc idempotent: lưu điểm ELO GỐC (trước điều chỉnh Covid)
        // để chạy lại bao nhiêu lần kết quả cũng như nhau
        Schema::table('ranking_histories', function (Blueprint $table) {
            $table->unsignedInteger('raw_elo')->nullable()->after('elo_rating');
        });

        // #2 Index cho truy vấn theo VĐV (H2H, match-history, leaderboard kỳ)
        Schema::table('matches', function (Blueprint $table) {
            $table->index('athlete1_id');
            $table->index('athlete2_id');
            $table->index('status');
        });

        // #9 Full-text search cho thiết bị (tên + model/mã sản phẩm)
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->fullText(['name', 'model', 'brand']);
        });
    }

    public function down(): void
    {
        Schema::table('equipment_items', fn (Blueprint $t) => $t->dropFullText(['name', 'model', 'brand']));
        Schema::table('matches', function (Blueprint $t) {
            $t->dropIndex(['athlete1_id']);
            $t->dropIndex(['athlete2_id']);
            $t->dropIndex(['status']);
        });
        Schema::table('ranking_histories', fn (Blueprint $t) => $t->dropColumn('raw_elo'));
    }
};
