<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\BackupSetting;
use App\Services\GoogleDriveBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(private GoogleDriveBackupService $service)
    {
    }

    public function getSettings(): JsonResponse
    {
        return response()->json([
            'data' => [
                ...BackupSetting::current()->toArray(),
                'drive_configured' => $this->service->isConfigured(),
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frequency' => ['required', 'in:daily,weekly,monthly'],
            'enabled' => ['required', 'boolean'],
            'drive_folder_id' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = BackupSetting::current();
        $settings->update($data);

        return response()->json(['data' => $settings->fresh(), 'message' => 'Đã lưu cấu hình sao lưu.']);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Backup::latest()->limit(50)
                ->get(['id', 'filename', 'size_kb', 'drive_file_id', 'status', 'type', 'created_at']),
        ]);
    }

    public function run(): JsonResponse
    {
        try {
            $settings = BackupSetting::current();
            $path = $this->service->dumpDatabase();
            $sizeKb = (int) round(filesize($path) / 1024);

            $backup = Backup::create([
                'filename' => basename($path),
                'size_kb' => $sizeKb,
                'status' => 'local_only',
                'type' => 'manual',
            ]);

            if ($this->service->isConfigured()) {
                $driveId = $this->service->uploadToDrive($path, $settings->drive_folder_id);
                $backup->update(['drive_file_id' => $driveId, 'status' => 'uploaded']);
            }

            $settings->update(['last_run_at' => now()]);

            return response()->json([
                'data' => $backup->fresh(),
                'message' => $backup->status === 'uploaded'
                    ? 'Sao lưu thành công và đã upload lên Google Drive.'
                    : 'Sao lưu thành công (chỉ lưu cục bộ — Google Drive chưa cấu hình).',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Sao lưu thất bại: '.$e->getMessage()], 500);
        }
    }

    public function restore(Request $request, Backup $backup): JsonResponse
    {
        $request->validate(['confirmation' => ['required', 'in:yes']]);

        try {
            if (! $backup->drive_file_id) {
                return response()->json(['message' => 'Bản sao lưu này không có trên Google Drive.'], 422);
            }

            $gzPath = $this->service->downloadFromDrive($backup->drive_file_id);
            $this->service->restoreFromDump($gzPath, $request->input('confirmation'));

            return response()->json(['message' => 'Đã phục hồi CSDL từ bản sao lưu '.$backup->filename]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Phục hồi thất bại: '.$e->getMessage()], 500);
        }
    }
}
