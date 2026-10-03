<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['racket', 'shoes']);
            $table->string('brand');
            $table->string('model');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->json('specifications')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->unsignedBigInteger('size_kb')->default(0);
            $table->string('drive_file_id')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'failed', 'local_only'])->default('pending');
            $table->string('type')->default('scheduled'); // scheduled | manual
            $table->timestamps();
        });

        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('daily');
            $table->boolean('enabled')->default(true);
            $table->string('drive_folder_id')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('equipment_items');
    }
};
