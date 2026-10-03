<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Thương hiệu (Yonex, Victor, Li-Ning...) — sản phẩm thuộc về 1 brand
        // ------------------------------------------------------------------
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('country')->nullable();
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->nullable();      // nguồn crawl: shopvnb | badmintoncn
            $table->string('source_id')->nullable();   // ID bản ghi trên nguồn
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Thông tin chi tiết vận động viên (1-1 với athletes)
        // ------------------------------------------------------------------
        Schema::create('athlete_details', function (Blueprint $table) {
            $table->unsignedBigInteger('athlete_id')->primary();
            $table->foreign('athlete_id')->references('id')->on('athletes')->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('weight_kg')->nullable();
            $table->string('playing_style')->nullable(); // Tấn công uy lực, phòng thủ phản tạt...
            $table->string('coach')->nullable();
            $table->string('association')->nullable();   // Hiệp hội (Liên đoàn cầu lông quốc gia)
            $table->string('team')->nullable();

            // Thành tích sự nghiệp
            $table->unsignedInteger('titles')->default(0);
            $table->unsignedInteger('finals')->default(0);
            $table->unsignedInteger('total_matches')->default(0);
            $table->unsignedInteger('total_wins')->default(0);

            // Chuỗi trận thắng
            $table->unsignedInteger('win_streak_current')->default(0);
            $table->unsignedInteger('win_streak_career')->default(0);         // bao gồm W.O.
            $table->unsignedInteger('win_streak_career_excl_wo')->default(0); // không tính W.O.
            $table->unsignedInteger('super_streak')->default(0);              // chuỗi xuyên các giải Super 1000→100
            $table->unsignedInteger('not_played_matches')->default(0);        // trận không tham gia (giải đồng đội)

            // Điểm GOAT
            $table->unsignedInteger('goat_points')->default(0);

            // Nguồn dữ liệu crawl
            $table->string('source')->nullable();
            $table->string('source_id')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Giải đấu & kết quả (dùng cho kỷ lục VĐ trẻ nhất/lớn tuổi nhất...)
        // ------------------------------------------------------------------
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('level')->default('other'); // super1000|super750|super500|super300|super100|other
            $table->boolean('is_asian_games')->default(false);
            $table->boolean('is_team_event')->default(false);
            $table->boolean('has_live_scores')->default(false); // false => không hiển thị nút Live Score
            $table->string('association')->nullable();
            $table->string('host_country')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('source')->nullable();
            $table->string('source_id')->nullable();
            $table->timestamps();

            $table->index(['level', 'start_date']);
            $table->index('association');
        });

        Schema::create('tournament_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('category', 2)->default('MS'); // MS|WS|MD|WD|XD
            $table->string('placement')->default('champion'); // champion|finalist|semifinal
            $table->date('achieved_at')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'athlete_id', 'category', 'placement'], 'tw_unique');
        });

        // ------------------------------------------------------------------
        // Thống kê theo năm — nguồn cho kỷ lục "trong năm dương lịch"
        // ------------------------------------------------------------------
        Schema::create('athlete_year_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('matches')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('finals')->default(0);
            $table->unsignedInteger('titles')->default(0);
            $table->timestamps();

            $table->unique(['athlete_id', 'year']);
        });

        // ------------------------------------------------------------------
        // Bảng Kỷ lục (được rebuild bằng lệnh records:rebuild)
        // ------------------------------------------------------------------
        Schema::create('records', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();  // win_rate_year | streak_career | streak_super | youngest_champion | oldest_champion | titles_year | finals_year | matches_year | wins_year
            $table->string('title');          // Tên kỷ lục hiển thị
            $table->foreignId('athlete_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('value');          // Giá trị kỷ lục (VD: "87.3%", "31 trận", "17 tuổi")
            $table->string('period')->nullable(); // VD: "2023", "Sự nghiệp"
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Nhật ký crawl/sync
        // ------------------------------------------------------------------
        Schema::create('crawl_logs', function (Blueprint $table) {
            $table->id();
            $table->string('target'); // athletes | products
            $table->string('source'); // badmintonranks | bwfbadminton | shopvnb | badmintoncn
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('items_upserted')->default(0);
            $table->string('status')->default('success'); // success | failed
            $table->text('message')->nullable();
            $table->timestamps();
        });

        // Sản phẩm gắn thương hiệu + nguồn crawl
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('source_id')->nullable();
            $table->index(['brand_id', 'type']);
        });

        // VĐV: hiệp hội + tối ưu tìm kiếm
        Schema::table('athletes', function (Blueprint $table) {
            $table->string('association')->nullable()->after('club');
            $table->index('nationality');
            $table->index('skill_level');
            $table->index('elo_rating');
            $table->index('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropIndex(['elo_rating']);
            $table->dropIndex(['skill_level']);
            $table->dropIndex(['nationality']);
            $table->dropIndex(['source_id']);
            $table->dropColumn('association');
        });
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->dropIndex(['brand_id', 'type']);
            $table->dropForeign(['brand_id']);
            $table->dropColumn(['brand_id', 'source', 'source_id']);
        });
        Schema::dropIfExists('crawl_logs');
        Schema::dropIfExists('records');
        Schema::dropIfExists('athlete_year_stats');
        Schema::dropIfExists('tournament_winners');
        Schema::dropIfExists('tournaments');
        Schema::dropIfExists('athlete_details');
        Schema::dropIfExists('brands');
    }
};
