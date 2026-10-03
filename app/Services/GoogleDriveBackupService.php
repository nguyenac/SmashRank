<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sao lưu & phục hồi CSDL MariaDB lên Google Drive.
 *
 * Cơ chế: dùng OAuth2 refresh token (không cần Google SDK).
 * mysqldump xuất file .sql.gz -> upload multipart lên Drive API v3.
 * Phục hồi: tải file .sql.gz về -> gunzip -> mysql import.
 *
 * Xem hướng dẫn cấp quyền tại docs/BACKUP_GOOGLE_DRIVE.md
 */
class GoogleDriveBackupService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'))
            && ! empty(config('services.google.refresh_token'));
    }

    /** Tạo file dump .sql.gz trong storage/app/backups, trả về đường dẫn tuyệt đối. */
    public function dumpDatabase(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'smashrank-backup-'.date('Ymd-His').'.sql.gz';
        $target = $dir.'/'.$filename;

        $mysqldump = config('services.backup.mysqldump_path', 'mysqldump');
        $mysql = config('services.backup.mysql_path', 'mysql');

        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s %s | gzip > %s',
            escapeshellcmd($mysqldump),
            escapeshellarg(config('database.connections.mariadb.host', '127.0.0.1')),
            escapeshellarg((string) config('database.connections.mariadb.port', '3306')),
            escapeshellarg(config('database.connections.mariadb.username')),
            escapeshellarg(config('database.connections.mariadb.password')),
            escapeshellarg(config('database.connections.mariadb.database')),
            escapeshellarg($target)
        );

        // Restore sẽ dùng biến $mysql (mysql CLI) — tham chiếu để tránh unused
        unset($mysql);

        exec($cmd.' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($target)) {
            Log::error('Backup dump thất bại', ['output' => implode("\n", $output)]);
            throw new \RuntimeException('Không thể tạo bản sao lưu CSDL: '.implode('; ', array_slice($output, -3)));
        }

        return $target;
    }

    /** Upload file lên Google Drive, trả về file ID. */
    public function uploadToDrive(string $filePath, ?string $folderId = null): string
    {
        $accessToken = $this->getAccessToken();

        $metadata = ['name' => basename($filePath)];
        if ($folderId) {
            $metadata['parents'] = [$folderId];
        }

        $boundary = 'smashrank'.bin2hex(random_bytes(8));
        $metaJson = json_encode($metadata);
        $fileData = file_get_contents($filePath);
        $size = strlen($fileData);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metaJson."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: application/octet-stream\r\n\r\n";

        $tail = "\r\n--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => "multipart/related; boundary={$boundary}"])
            ->withBody(
                (function () use ($body, $fileData, $tail, &$size) {
                    $stream = fopen('php://temp', 'r+');
                    fwrite($stream, $body.$fileData.$tail);
                    rewind($stream);

                    return $stream;
                })(),
                $size + strlen($body) + strlen($tail)
            )
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');

        if (! $response->successful()) {
            Log::error('Upload Google Drive thất bại', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Upload lên Google Drive thất bại (HTTP '.$response->status().').');
        }

        return $response->json('id');
    }

    /** Tải file sao lưu từ Drive về storage/app/backups/restore.sql.gz */
    public function downloadFromDrive(string $driveFileId): string
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$driveFileId}", ['alt' => 'media']);

        if (! $response->successful()) {
            throw new \RuntimeException('Tải file từ Google Drive thất bại (HTTP '.$response->status().').');
        }

        $target = storage_path('app/backups/restore-'.date('Ymd-His').'.sql.gz');
        file_put_contents($target, $response->body());

        return $target;
    }

    /** Phục hồi CSDL từ file .sql.gz (có xác nhận "yes" để chống gọi nhầm). */
    public function restoreFromDump(string $gzPath, string $confirmation): void
    {
        if ($confirmation !== 'yes') {
            throw new \RuntimeException('Phải truyền confirmation=yes để phục hồi dữ liệu.');
        }

        $sqlPath = $gzPath.'.sql';
        exec('gzip -dc '.escapeshellarg($gzPath).' > '.escapeshellarg($sqlPath), $o, $code);
        if ($code !== 0) {
            throw new \RuntimeException('Không thể giải nén file sao lưu.');
        }

        $mysql = config('services.backup.mysql_path', 'mysql');
        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s %s < %s',
            escapeshellcmd($mysql),
            escapeshellarg(config('database.connections.mariadb.host', '127.0.0.1')),
            escapeshellarg((string) config('database.connections.mariadb.port', '3306')),
            escapeshellarg(config('database.connections.mariadb.username')),
            escapeshellarg(config('database.connections.mariadb.password')),
            escapeshellarg(config('database.connections.mariadb.database')),
            escapeshellarg($sqlPath)
        );

        exec($cmd.' 2>&1', $output, $exitCode);
        @unlink($sqlPath);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Phục hồi thất bại: '.implode('; ', array_slice($output, -3)));
        }
    }

    private function getAccessToken(): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => config('services.google.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Không lấy được access token Google (kiểm tra GOOGLE_CLIENT_ID/SECRET/REFRESH_TOKEN).');
        }

        return $response->json('access_token');
    }
}
