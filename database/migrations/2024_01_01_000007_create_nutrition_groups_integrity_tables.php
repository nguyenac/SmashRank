<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nhật ký dinh dưỡng (Athlete Nutrition Diary)
        Schema::create('nutrition_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->string('meal')->default('breakfast'); // breakfast|lunch|dinner|snack|pre_match
            $table->string('description')->nullable();
            $table->unsignedInteger('calories')->default(0);
            $table->unsignedSmallInteger('protein_g')->default(0);
            $table->unsignedSmallInteger('carbs_g')->default(0);
            $table->unsignedSmallInteger('water_ml')->default(0);
            $table->timestamps();

            $table->index(['athlete_id', 'log_date']);
        });

        // Nhóm tập luyện (Training Groups) + bảng xếp hạng nội bộ
        Schema::create('training_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('training_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        // Vùng sân mạnh/yếu (phân tích video): 6 vùng (trước/giữa/sau x trái/giữa/phải)
        Schema::table('athlete_details', function (Blueprint $table) {
            $table->json('zone_marks')->nullable()->after('playstyle_scores');
        });

        // Ghi chú thi đấu + thẻ yếu tố thắng/thua cho từng trận
        Schema::table('matches', function (Blueprint $table) {
            $table->json('result_tags')->nullable()->after('venue'); // ["tactic","mental","technique_error"]
            $table->text('result_note')->nullable()->after('result_tags');
        });

        // Mục tiêu thông minh (Smart Goals): chỉ số + baseline + đích
        Schema::table('training_goals', function (Blueprint $table) {
            $table->string('metric')->nullable()->after('title'); // elo | win_rate
            $table->decimal('baseline_value', 8, 2)->nullable()->after('metric');
            $table->decimal('target_value', 8, 2)->nullable()->after('baseline_value');
        });
    }

    public function down(): void
    {
        Schema::table('training_goals', fn (Blueprint $t) => $t->dropColumn(['metric', 'baseline_value', 'target_value']));
        Schema::table('matches', fn (Blueprint $t) => $t->dropColumn(['result_tags', 'result_note']));
        Schema::table('athlete_details', fn (Blueprint $t) => $t->dropColumn('zone_marks'));
        Schema::dropIfExists('training_group_members');
        Schema::dropIfExists('training_groups');
        Schema::dropIfExists('nutrition_logs');
    }
};
