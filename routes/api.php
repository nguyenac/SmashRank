<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AthleteController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\BirthdayController;
use App\Http\Controllers\Api\RecordsController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\CrawlController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\PushController;
use App\Http\Controllers\Api\TrainingGoalController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\TournamentMatchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AthleteExtrasController;
use App\Http\Controllers\Api\DrillController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\NutritionController;
use App\Http\Controllers\Api\AthleteInsightsController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\DuplicatesController;
use App\Http\Controllers\Api\DemographicsController;
use App\Http\Controllers\Api\MyDashboardController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes - SmashRank
|--------------------------------------------------------------------------
| Bảo mật: các route xác thực/nhạy cảm được áp dụng rate limiting (throttle)
| để chống brute-force và spam.
*/

// ---------- Public: Authentication (rate-limited) ----------
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/2fa/email-code', [AuthController::class, 'sendEmailCode']);
    Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ---------- Public: Athletes & Products ----------
Route::get('/athletes', [AthleteController::class, 'index']);
Route::get('/athletes/{athlete}', [AthleteController::class, 'show']);
Route::get('/products', [EquipmentController::class, 'publicIndex']);
Route::get('/products/{item}', [EquipmentController::class, 'publicShow']);

// ---------- Public: Sinh nhật / Kỷ lục / Giải đấu ----------
Route::get('/birthdays', [BirthdayController::class, 'index']);       // ?date=YYYY-MM-DD (mặc định: hôm nay)
Route::get('/records', [RecordsController::class, 'index']);          // Danh sách Kỷ lục
Route::get('/tournaments', [TournamentController::class, 'index']);   // ?q= &level= &association=
Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']); // ?association= tìm theo hiệp hội
Route::get('/tournaments/{tournament}/matches', [TournamentMatchController::class, 'index']);
Route::get('/tournaments/{tournament}/matches/live', [TournamentMatchController::class, 'live']);

// ---------- Public: Leaderboard (tổng / tuần / tháng) ----------
Route::get('/leaderboards', [LeaderboardController::class, 'index']);

// ---------- Global Search (VĐV / CLB / thiết bị / giải đấu) ----------
Route::get('/search', [SearchController::class, 'index']);

// ---------- Smart Court Scheduler ----------
Route::get('/courts', [CourtController::class, 'index']);
Route::get('/courts/suggest', [CourtController::class, 'suggest']);

// ---------- Thư viện bài tập (lọc độ khó / thời gian / kỹ thuật) ----------
Route::get('/drills', [DrillController::class, 'index']);
Route::get('/drills/skill-progress', [DrillController::class, 'skillProgress'])->middleware('auth:sanctum');
Route::post('/drills', [DrillController::class, 'store'])->middleware('auth:sanctum');
Route::delete('/drills/{drill}', [DrillController::class, 'destroy'])->middleware('auth:sanctum');

// ---------- Public: Tin tức + bình luận (xem) ----------
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{news}', [NewsController::class, 'show']);
Route::get('/comments', [CommentController::class, 'index']); // ?type=athlete|news&id=...

// ---------- Hồ sơ VĐV mở rộng ----------
Route::get('/athletes/{athlete}/weight-entries', [AthleteExtrasController::class, 'weightIndex']);
Route::post('/athletes/{athlete}/weight-entries', [AthleteExtrasController::class, 'weightStore'])->middleware('auth:sanctum');
Route::get('/athletes/{athlete}/injuries', [AthleteExtrasController::class, 'injuriesIndex']);
Route::post('/athletes/{athlete}/injuries', [AthleteExtrasController::class, 'injuriesStore'])->middleware('auth:sanctum');
Route::put('/injuries/{injury}', [AthleteExtrasController::class, 'injuriesUpdate'])->middleware('auth:sanctum');
Route::get('/athletes/{athlete}/coach-notes', [AthleteExtrasController::class, 'notesIndex']);
Route::post('/athletes/{athlete}/coach-notes', [AthleteExtrasController::class, 'notesStore'])->middleware(['auth:sanctum', 'admin']);
Route::get('/athletes/{athlete}/schedule', [AthleteExtrasController::class, 'scheduleIndex']);
Route::post('/athletes/{athlete}/schedule', [AthleteExtrasController::class, 'scheduleStore'])->middleware(['auth:sanctum', 'admin']);
Route::put('/schedule/{schedule}', [AthleteExtrasController::class, 'scheduleUpdate'])->middleware('auth:sanctum');
Route::get('/athletes/{athlete}/match-history', [AthleteExtrasController::class, 'matchHistory']);
Route::get('/athletes/{athlete}/racket-suggestions', [AthleteExtrasController::class, 'racketSuggestions']);
Route::get('/athletes/{athlete}/coach-bot', [AthleteExtrasController::class, 'coachBot']);
Route::post('/athletes/{athlete}/playstyle', [AthleteExtrasController::class, 'playstyle'])->middleware(['auth:sanctum', 'admin']);
Route::get('/athletes/{athlete}/playstyle', [AthleteExtrasController::class, 'playstyle']);
Route::post('/athletes/{athlete}/veo-highlight', [AthleteExtrasController::class, 'veoHighlight'])->middleware('auth:sanctum');

// ---------- Dinh dưỡng VĐV (nhật ký theo ngày) ----------
Route::get('/athletes/{athlete}/nutrition', [NutritionController::class, 'index']);
Route::post('/athletes/{athlete}/nutrition', [NutritionController::class, 'store'])->middleware('auth:sanctum');
Route::delete('/nutrition/{log}', [NutritionController::class, 'destroy'])->middleware('auth:sanctum');

// ---------- Insights: cột mốc / heatmap phong độ / vùng sân / thiết bị / ghi chú trận ----------
Route::get('/athletes/{athlete}/milestones', [AthleteInsightsController::class, 'milestones']);
Route::get('/athletes/{athlete}/peak-heatmap', [AthleteInsightsController::class, 'peakHeatmap']);
Route::get('/athletes/{athlete}/zones', [AthleteInsightsController::class, 'zones']);
Route::post('/athletes/{athlete}/zones', [AthleteInsightsController::class, 'zones'])->middleware(['auth:sanctum', 'admin']);
Route::get('/athletes/{athlete}/equipment-fit', [AthleteInsightsController::class, 'equipmentFit']);
Route::put('/matches/{match}/note', [AthleteInsightsController::class, 'matchNote'])->middleware('auth:sanctum');
Route::get('/athletes/{athlete}/match-notes-summary', [AthleteInsightsController::class, 'matchNotesSummary']);

// ---------- Nhóm tập luyện ----------
Route::get('/training-groups', [GroupController::class, 'index']);
Route::post('/training-groups', [GroupController::class, 'store'])->middleware('auth:sanctum');
Route::post('/training-groups/{group}/join', [GroupController::class, 'join'])->middleware('auth:sanctum');
Route::delete('/training-groups/{group}/leave', [GroupController::class, 'leave'])->middleware('auth:sanctum');

// ---------- Analytics Hub (nhân khẩu học) ----------
Route::get('/demographics', [DemographicsController::class, 'index']);

// ---------- Public: Thương hiệu (xem) ----------
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{brand}', [BrandController::class, 'show']);

// ---------- Authenticated ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    // 2FA configuration (allowed even when 2FA not yet enabled)
    Route::post('/2fa/setup', [AuthController::class, 'setupTwoFactor']);
    Route::post('/2fa/enable', [AuthController::class, 'enableTwoFactor']);
    Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor']);

    // Ghi kết quả trận đấu (AddMatchResultModal) → cập nhật Elo + thắng/thua
    Route::post('/matches', [MatchController::class, 'store'])->middleware('throttle:10,1');

    // Bình luận (chống spam 15s/lần)
    Route::post('/comments', [CommentController::class, 'store']);

    // Push notifications (PWA) + theo dõi VĐV
    Route::get('/push/vapid-public-key', [PushController::class, 'vapidPublicKey']);
    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
    Route::get('/follows', [PushController::class, 'myFollows']);
    Route::post('/follows/{athlete}', [PushController::class, 'follow']);
    Route::delete('/follows/{athlete}', [PushController::class, 'unfollow']);

    // Người dùng tự tạo giải đấu (push thông báo giải mới)
    Route::post('/tournaments', [TournamentController::class, 'store']);

    // User Dashboard: lịch sử cá nhân (chỉ chủ tài khoản/admin) + tiến độ + xuất dữ liệu
    Route::get('/my/matches', [MyDashboardController::class, 'matches']);
    Route::get('/my/progress', [MyDashboardController::class, 'progress']);
    Route::get('/my/matches/export', [MyDashboardController::class, 'export']);

    // Chat học viên ↔ HLV (polling; nâng cấp Reverb/WebSocket sau)
    Route::get('/chat/threads', [ChatController::class, 'threads']);
    Route::get('/chat/messages', [ChatController::class, 'messages']);
    Route::post('/chat/send', [ChatController::class, 'send'])->middleware('throttle:30,1');

    // Thanh toán gói tập
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);

    // Lộ trình huấn luyện (Training Path)
    Route::get('/training-goals', [TrainingGoalController::class, 'index']);
    Route::post('/training-goals', [TrainingGoalController::class, 'store']);
    Route::put('/training-goals/{goal}', [TrainingGoalController::class, 'update']);
    Route::delete('/training-goals/{goal}', [TrainingGoalController::class, 'destroy']);
    Route::get('/training-goals/smart-progress', [TrainingGoalController::class, 'smartProgress']);

    // AI Smart Coach: gợi ý bài tập theo điểm yếu (dữ liệu tham khảo)
    Route::get('/coach/drill-suggestions', function (Request $request, \App\Services\SuggestionService $service) {
        $athlete = \App\Models\Athlete::where('user_id', $request->user()->id)->first();
        if (! $athlete) {
            return response()->json(['data' => [], 'message' => 'Lập hồ sơ VĐV để nhận gợi ý.']);
        }

        return response()->json([
            'data' => $service->suggestDrills($athlete),
            'message' => '⚠️ Dữ liệu THAM KHẢO — hãy điều chỉnh theo HLV chuyên môn.',
        ]);
    });
});

// ---------- Admin ----------
Route::middleware(['auth:sanctum', 'admin', 'twofactor'])
    ->prefix('admin')->group(function () {
        // Sản phẩm (vợt/giày thuộc thương hiệu)
        Route::post('/products', [EquipmentController::class, 'store']);
        Route::put('/products/{item}', [EquipmentController::class, 'update']);
        Route::delete('/products/{item}', [EquipmentController::class, 'destroy']);

        // Thương hiệu CRUD
        Route::post('/brands', [BrandController::class, 'store']);
        Route::put('/brands/{brand}', [BrandController::class, 'update']);
        Route::delete('/brands/{brand}', [BrandController::class, 'destroy']);

        // Analytics: dashboard tổng hợp + dashboard nhanh
        Route::get('/analytics/summary', [AnalyticsController::class, 'summary']);
        Route::get('/statistics', [StatsController::class, 'index']); // scatter + heatmap
        Route::get('/duplicates', [DuplicatesController::class, 'index']); // toàn vẹn dữ liệu
        Route::post('/athletes-merge', [DuplicatesController::class, 'mergeAthletes']);
        Route::get('/analytics/quick', [AnalyticsController::class, 'quick']);

        // Báo cáo: sản phẩm bán chạy, đăng ký thành viên, tần suất sử dụng sân
        Route::get('/reports', [ReportController::class, 'index']);

        // Xuất dữ liệu: CSV (VĐV), JSON (toàn bộ), SQL (CSDL), PDF (hồ sơ VĐV)
        Route::get('/export/db.sql', [ExportController::class, 'dbSql']);
        Route::get('/export/report.pdf', [ExportController::class, 'reportPdf']);
        Route::put('/payments/{payment}/confirm', [PaymentController::class, 'confirm']);
        Route::get('/export/athletes.csv', [ExportController::class, 'athletesCsv']);
        Route::get('/export/all.json', [ExportController::class, 'allJson']);
        Route::get('/export/athlete/{athlete}.pdf', [ExportController::class, 'athletePdf']);

        // Tin tức (đăng + push)
        Route::post('/news', [NewsController::class, 'store']);

        // Nhánh đấu + live score
        Route::post('/tournaments/{tournament}/bracket', [TournamentMatchController::class, 'createBracket']);
        Route::put('/tournaments/{tournament}/matches/{match}/score', [TournamentMatchController::class, 'updateScore']);

        // Crawl & đồng bộ dữ liệu
        Route::post('/crawl/athletes', [CrawlController::class, 'crawlAthletes']);
        Route::post('/crawl/products', [CrawlController::class, 'crawlProducts']);
        Route::get('/crawl/logs', [CrawlController::class, 'logs']);

        // Sao lưu
        Route::get('/backups/settings', [BackupController::class, 'getSettings']);
        Route::put('/backups/settings', [BackupController::class, 'updateSettings']);
        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups/run', [BackupController::class, 'run']);
        Route::post('/backups/{backup}/restore', [BackupController::class, 'restore']);
    });
