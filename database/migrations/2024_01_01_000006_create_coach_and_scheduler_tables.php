<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mở rộng loại thiết bị: vợt, giày, túi, quấn cán, cước, tất...
        DB::statement("ALTER TABLE equipment_items MODIFY type VARCHAR(20) NOT NULL");

        // Giải đấu do người dùng tự tạo
        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        // Địa điểm thi đấu cho lịch sử đặt sân
        Schema::table('matches', function (Blueprint $table) {
            $table->string('venue')->nullable()->after('played_at');
        });

        // Cân nặng theo giờ trong ngày (cho heatmap) — thêm cột hour
        Schema::table('court_usages', function (Blueprint $table) {
            $table->unsignedTinyInteger('hour')->default(19)->after('usage_date'); // 6-22
        });

        // XP & Danh hiệu cho người dùng
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('xp')->default(0)->after('role');
            $table->json('badges')->nullable()->after('xp');
        });

        Schema::create('weight_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->date('measured_at');
            $table->decimal('weight_kg', 5, 1);
            $table->timestamps();
            $table->unique(['athlete_id', 'measured_at']);
        });

        Schema::create('injuries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('type');            // VD: Cổ tay, Bàn chân, Lưng...
            $table->text('description')->nullable();
            $table->date('occurred_at');
            $table->date('expected_recovery')->nullable();
            $table->string('status')->default('recovering'); // recovering | recovered
            $table->timestamps();
        });

        Schema::create('coach_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');              // ghi chú chuyên sâu / nhận xét chiến thuật
            $table->timestamps();
        });

        Schema::create('training_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedTinyInteger('day_of_week'); // 1-7 (T2-CN)
            $table->string('drill');
            $table->text('detail')->nullable();        // bộ pháp, kỹ thuật động tác, tư duy...
            $table->boolean('done')->default(false);
            $table->timestamps();

            $table->index(['athlete_id', 'week_start']);
        });

        Schema::create('drills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('detail');            // mô tả cụ thể: bộ pháp di chuyển, kỹ thuật động tác, tư duy đánh cầu...
            $table->string('category');        // attack | defense | footwork | technique | stamina
            $table->string('difficulty');      // easy | medium | hard
            $table->unsignedSmallInteger('duration_min'); // thời gian hoàn thành (phút)
            $table->string('level_hint')->nullable(); // trình độ phù hợp
            $table->boolean('is_reference')->default(true); // dữ liệu để tham khảo
            $table->timestamps();

            $table->index(['category', 'difficulty']);
        });

        Schema::create('skill_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_at');
            $table->unsignedTinyInteger('power');     // Sức mạnh
            $table->unsignedTinyInteger('speed');     // Tốc độ
            $table->unsignedTinyInteger('defense');   // Phòng thủ
            $table->unsignedTinyInteger('net_play');  // Kỹ thuật trên lưới
            $table->unsignedTinyInteger('stamina');   // Thể lực
            $table->timestamps();
        });

        // Điểm lối chơi do HLV gán (tấn công cuối sân, cắt lưới, phòng thủ, điều cầu)
        Schema::table('athlete_details', function (Blueprint $table) {
            $table->json('playstyle_scores')->nullable()->after('goat_points');
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('open_hour')->default(6);
            $table->unsignedTinyInteger('close_hour')->default(22);
            $table->timestamps();
        });

        Schema::create('video_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');          // nhãn kỹ thuật tại mốc thời gian
            $table->unsignedInteger('timestamp_ms');
            $table->string('file_path')->nullable(); // video đã upload (tùy chọn)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('athlete_details', fn (Blueprint $t) => $t->dropColumn('playstyle_scores'));
        Schema::dropIfExists('video_tags');
        Schema::dropIfExists('courts');
        Schema::dropIfExists('skill_snapshots');
        Schema::dropIfExists('drills');
        Schema::dropIfExists('training_schedules');
        Schema::dropIfExists('coach_notes');
        Schema::dropIfExists('injuries');
        Schema::dropIfExists('weight_entries');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['xp', 'badges']));
        Schema::table('court_usages', fn (Blueprint $t) => $t->dropColumn('hour'));
        Schema::table('matches', fn (Blueprint $t) => $t->dropColumn('venue'));
        Schema::table('tournaments', function (Blueprint $t) {
            $t->dropForeign(['created_by']);
            $t->dropColumn('created_by');
        });
    }
};
