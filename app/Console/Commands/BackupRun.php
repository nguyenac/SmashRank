<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Models\BackupSetting;
use App\Services\GoogleDriveBackupService;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--force : Bỏ qua kiểm tra tần suất}';

    protected $description = 'Sao lưu CSDL MariaDB và upload lên Google Drive theo lịch đã cấu hình';

    public function handle(GoogleDriveBackupService $service): int
    {
        $settings = BackupSetting::current();

        if (! $settings->enabled && ! $this->option('force')) {
            $this->info('Sao lưu tự động đang tắt (backup_settings.enabled = false).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->isDue($settings)) {
            $this->info('Chưa đến chu kỳ sao lưu ('.$settings->frequency.'). Lần chạy gần nhất: '.$settings->last_run_at);

            return self::SUCCESS;
        }

        $this->info('Bắt đầu sao lưu...');

        try {
            $backup = Backup::create([
                'filename' => 'pending',
                'status' => 'pending',
                'type' => 'scheduled',
            ]);

            $path = $service->dumpDatabase();
            $sizeKb = (int) round(filesize($path) / 1024);

            if ($service->isConfigured()) {
                $driveId = $service->uploadToDrive($path, $settings->drive_folder_id ?: config('services.google.folder_id'));
                $backup->update([
                    'filename' => basename($path),
                    'size_kb' => $sizeKb,
                    'drive_file_id' => $driveId,
                    'status' => 'uploaded',
                ]);
                $this->info("Đã upload lên Google Drive: {$driveId}");
            } else {
                $backup->update([
                    'filename' => basename($path),
                    'size_kb' => $sizeKb,
                    'status' => 'local_only',
                ]);
                $this->warn('Google Drive chưa cấu hình — bản sao lưu chỉ lưu cục bộ.');
            }

            $settings->update(['last_run_at' => now()]);
            $this->info("Hoàn tất: {$backup->filename} ({$sizeKb} KB)");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sao lưu thất bại: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function isDue(BackupSetting $settings): bool
    {
        if (! $settings->last_run_at) {
            return true;
        }

        return match ($settings->frequency) {
            'daily' => $settings->last_run_at->diffInDays(now()) >= 1,
            'weekly' => $settings->last_run_at->diffInDays(now()) >= 7,
            'monthly' => $settings->last_run_at->diffInDays(now()) >= 28,
            default => false,
        };
    }
}
