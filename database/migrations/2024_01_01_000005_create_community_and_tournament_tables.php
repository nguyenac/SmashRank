<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Kết quả trận đấu — nguồn cho Elo, leaderboard tuần/tháng
        // ------------------------------------------------------------------
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 2)->default('MS');
            $table->foreignId('athlete1_id')->constrained('athletes')->cascadeOnDelete();
            $table->foreignId('athlete2_id')->constrained('athletes')->cascadeOnDelete();
            $table->unsignedTinyInteger('score1')->default(0);
            $table->unsignedTinyInteger('score2')->default(0);
            $table->boolean('walkover')->default(false); // W.O.
            $table->unsignedInteger('rating_change')->nullable(); // điểm Elo chuyển giao
            $table->date('played_at');
            $table->timestamps();

            $table->index(['played_at', 'category']);
        });

        // ------------------------------------------------------------------
        // Bình luận (dưới hồ sơ VĐV / tin tức) — hỗ trợ phân tầng (parent_id)
        // ------------------------------------------------------------------
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('commentable_type', 40); // athlete | news
            $table->unsignedBigInteger('commentable_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('body');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id']);
        });

        // ------------------------------------------------------------------
        // Tin tức cầu lông — nguồn thông báo đẩy
        // ------------------------------------------------------------------
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('source')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Push subscriptions (Web Push / PWA) + VĐV theo dõi
        // ------------------------------------------------------------------
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('endpoint')->unique();
            $table->text('p256dh')->nullable();
            $table->text('auth')->nullable();
            $table->timestamps();
        });

        Schema::create('athlete_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'athlete_id']);
        });

        // ------------------------------------------------------------------
        // Lộ trình huấn luyện (Training Path)
        // ------------------------------------------------------------------
        Schema::create('training_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('target')->nullable();
            $table->string('drill')->nullable();      // bài tập chuyên môn
            $table->string('frequency')->nullable();  // tần suất
            $table->date('deadline')->nullable();
            $table->string('status')->default('active'); // active | done | archived
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->text('coach_advice')->nullable(); // gợi ý từ HLV giả lập
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // Nhánh đấu (bracket) + tỷ số trực tiếp
        // ------------------------------------------------------------------
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->unsignedSmallInteger('slot')->default(1);
            $table->foreignId('athlete1_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->foreignId('athlete2_id')->nullable()->constrained('athletes')->nullOnDelete();
            $table->unsignedTinyInteger('score1')->default(0);
            $table->unsignedTinyInteger('score2')->default(0);
            $table->string('status')->default('scheduled'); // scheduled | live | completed
            $table->timestamp('starts_at')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'round', 'slot']);
            $table->index('status');
        });

        // ------------------------------------------------------------------
        // Báo cáo: sản phẩm bán chạy & tần suất sử dụng sân
        // ------------------------------------------------------------------
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->date('sold_at');
            $table->index('sold_at');
        });

        Schema::create('court_usages', function (Blueprint $table) {
            $table->id();
            $table->string('court_name');
            $table->date('usage_date');
            $table->decimal('hours', 5, 1)->default(0);
            $table->unsignedInteger('bookings')->default(0);
            $table->index('usage_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_usages');
        Schema::dropIfExists('product_sales');
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('training_goals');
        Schema::dropIfExists('athlete_follows');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('news');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('matches');
    }
};
