<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('nickname')->nullable();
            $table->string('nationality');
            $table->string('country_code', 3); // VNM, DEN, JPN...
            $table->enum('category', ['MS', 'WS', 'MD', 'WD', 'XD'])->default('MS');
            $table->unsignedInteger('ranking_points')->default(0);
            $table->unsignedInteger('world_rank')->nullable();
            $table->unsignedInteger('career_high_rank')->nullable();
            $table->unsignedInteger('win_count')->default(0);
            $table->unsignedInteger('loss_count')->default(0);
            $table->enum('dominant_hand', ['right', 'left'])->default('right');
            $table->enum('skill_level', ['pro', 'advanced', 'intermediate', 'beginner'])->default('beginner');
            $table->foreignId('racket_id')->nullable()->constrained('equipment_items')->nullOnDelete();
            $table->foreignId('shoes_id')->nullable()->constrained('equipment_items')->nullOnDelete();
            $table->string('avatar_url')->nullable();
            $table->string('club')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('grassroots_rank')->nullable(); // Newbie, TB, Khá, Giỏi, Xuất sắc
            $table->unsignedInteger('elo_rating')->default(1000);
            $table->boolean('verified')->default(false);

            // Chỉ số kỹ năng cho Radar Chart (0-100)
            $table->unsignedTinyInteger('skill_agility')->default(50);
            $table->unsignedTinyInteger('skill_power')->default(50);
            $table->unsignedTinyInteger('skill_stamina')->default(50);
            $table->unsignedTinyInteger('skill_technique')->default(50);
            $table->unsignedTinyInteger('skill_defense')->default(50);
            $table->unsignedTinyInteger('skill_mentality')->default(50);

            $table->timestamps();

            // Tìm kiếm toàn văn (MariaDB FULLTEXT)
            $table->fullText(['full_name', 'nickname', 'club']);
        });

        Schema::create('ranking_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_month'); // đầu tháng ghi nhận
            $table->unsignedInteger('points');
            $table->unsignedInteger('elo_rating');
            $table->decimal('win_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['athlete_id', 'recorded_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_histories');
        Schema::dropIfExists('athletes');
    }
};
