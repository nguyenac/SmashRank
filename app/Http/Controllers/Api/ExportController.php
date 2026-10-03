<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Brand;
use App\Models\CourtUsage;
use App\Models\EquipmentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 'Xuất dữ liệu' tại Admin:
 *  - GET /admin/export/athletes.csv       → CSV danh sách VĐV + thống kê đầy đủ
 *  - GET /admin/export/all.json           → JSON toàn bộ trạng thái (VĐV, trang thiết bị, CLB, thương hiệu)
 *  - GET /admin/export/athlete/{id}.pdf   → PDF hồ sơ VĐV để lưu trữ / in ấn
 */
class ExportController extends Controller
{
    public function athletesCsv(): StreamedResponse
    {
        $filename = 'smashrank-athletes-'.date('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // BOM để Excel mở tiếng Việt đúng
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID', 'Ho ten', 'Quoc tich', 'Nuoc', 'Noi dung', 'Hang TG', 'Diem BWF',
                'Elo', 'Trinh do', 'Hang phong trao', 'Thang', 'Thua', 'Ty le thang (%)',
                'Tay thuan', 'CLB', 'Vot', 'Giay', 'Diem GOAT', 'Danh hieu', 'Ngay sinh',
            ]);

            Athlete::query()->with(['details', 'racket', 'shoes'])->chunk(200, function ($athletes) use ($out) {
                foreach ($athletes as $a) {
                    fputcsv($out, [
                        $a->id, $a->full_name, $a->nationality, $a->country_code, $a->category,
                        $a->world_rank, $a->ranking_points, $a->elo_rating, $a->skill_level,
                        $a->grassroots_rank, $a->win_count, $a->loss_count, $a->win_rate,
                        $a->dominant_hand, $a->club,
                        $a->racket?->name, $a->shoes?->name,
                        $a->details?->goat_points, $a->details?->titles,
                        $a->details?->birth_date?->format('Y-m-d'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function allJson(): StreamedResponse
    {
        $data = [
            'exported_at' => now()->toIso8601String(),
            'athletes' => Athlete::with(['details', 'yearStats', 'racket', 'shoes'])->get(),
            'equipment' => EquipmentItem::with('brand')->get(),
            'brands' => Brand::all(),
            // Danh sách câu lạc bộ duy nhất từ hồ sơ VĐV
            'clubs' => Athlete::whereNotNull('club')->distinct()->orderBy('club')->pluck('club'),
        ];

        $filename = 'smashrank-full-export-'.date('Ymd-His').'.json';

        return response()->streamDownload(
            fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /** Báo cáo PDF tổng hợp: thống kê VĐV + thiết bị + giải đấu (cho AdminReports). */
    public function reportPdf()
    {
        $data = [
            'totalAthletes' => \App\Models\Athlete::count(),
            'totalUsers' => \App\Models\User::count(),
            'topAthletes' => \App\Models\Athlete::orderByDesc('elo_rating')->limit(10)->get(['id', 'full_name', 'country_code', 'elo_rating', 'win_count', 'loss_count']),
            'equipmentByType' => \App\Models\EquipmentItem::selectRaw('type, COUNT(*) as total')->groupBy('type')->pluck('total', 'type'),
            'equipmentByBrand' => \App\Models\Brand::withCount('products')->orderByDesc('products_count')->get(['id', 'name', 'products_count']),
            'tournaments' => \App\Models\Tournament::orderByDesc('start_date')->limit(10)->get(['id', 'name', 'level', 'start_date', 'has_live_scores']),
        ];

        $options = new \Dompdf\Options();
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(view('pdf.report', $data)->render());
        $dompdf->render();

        return response()->streamDownload(
            fn () => print $dompdf->output(),
            'smashrank-report-'.date('Ymd-His').'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /** Xuất toàn bộ CSDL dạng file .sql (mysqldump stream) — để lưu trữ/khôi phục. */
    public function dbSql()
    {
        $mysqldump = config('services.backup.mysqldump_path', 'mysqldump');
        $cmd = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s %s',
            escapeshellcmd($mysqldump),
            escapeshellarg(config('database.connections.mariadb.host', '127.0.0.1')),
            escapeshellarg((string) config('database.connections.mariadb.port', '3306')),
            escapeshellarg(config('database.connections.mariadb.username')),
            escapeshellarg(config('database.connections.mariadb.password')),
            escapeshellarg(config('database.connections.mariadb.database'))
        );

        $filename = 'smashrank-db-'.date('Ymd-His').'.sql';

        return response()->streamDownload(function () use ($cmd) {
            // Stream trực tiếp output của mysqldump ra body phản hồi
            passthru($cmd);
        }, $filename, ['Content-Type' => 'application/sql']);
    }

    public function athletePdf(Athlete $athlete)
    {
        $athlete->load(['details', 'histories', 'racket', 'shoes']);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(view('pdf.athlete', ['athlete' => $athlete])->render());
        $dompdf->render();

        $filename = 'smashrank-athlete-'.$athlete->id.'.pdf';

        return response()->streamDownload(
            fn () => print $dompdf->output(),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
