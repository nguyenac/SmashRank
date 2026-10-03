<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhánh đấu (bracket) + tỷ số trực tiếp cho giải đấu.
 *
 *  Public:
 *   - GET /tournaments/{t}/matches          → toàn bộ nhánh đấu (lọc ?association=)
 *   - GET /tournaments/{t}/matches/live     → các trận đang LIVE (polling frontend)
 *  Admin:
 *   - POST /admin/tournaments/{t}/bracket   → bốc thăm tự động từ danh sách VĐV
 *   - PUT  /admin/tournaments/{t}/matches/{m}/score → cập nhật tỷ số / trạng thái
 */
class TournamentMatchController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Tournament $tournament, Request $request): JsonResponse
    {
        $query = $tournament->matches()->with(['athlete1:id,full_name,country_code,elo_rating,avatar_url', 'athlete2:id,full_name,country_code,elo_rating,avatar_url']);

        // Tìm kiếm trong giải theo hiệp hội tay vợt
        if ($assoc = $request->query('association')) {
            $query->where(function ($q) use ($assoc) {
                $q->whereHas('athlete1', fn ($a) => $a->where('association', 'like', "%{$assoc}%")
                    ->orWhereHas('details', fn ($d) => $d->where('association', 'like', "%{$assoc}%")))
                  ->orWhereHas('athlete2', fn ($a) => $a->where('association', 'like', "%{$assoc}%")
                    ->orWhereHas('details', fn ($d) => $d->where('association', 'like', "%{$assoc}%")));
            });
        }

        $matches = $query->orderBy('round')->orderBy('slot')->get();

        return response()->json([
            'data' => $matches->map(fn (TournamentMatch $m) => $this->format($m)),
            'meta' => ['has_live_scores' => $tournament->has_live_scores],
        ]);
    }

    public function live(Tournament $tournament): JsonResponse
    {
        // Chỉ giải có real-time mới có dữ liệu live
        if (! $tournament->has_live_scores) {
            return response()->json([
                'message' => 'Giải này không có tỷ số trực tiếp.',
                'redirect' => "/tournaments/{$tournament->id}",
            ], 302);
        }

        $matches = $tournament->matches()
            ->whereIn('status', ['live', 'completed'])
            ->with(['athlete1:id,full_name,country_code', 'athlete2:id,full_name,country_code'])
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $matches->map(fn (TournamentMatch $m) => $this->format($m)),
        ]);
    }

    /** Bốc thăm tự động: ghép cặp ngẫu nhiên VĐV theo hạt giống Elo (1 vs cuối, 2 vs kế cuối...). */
    public function createBracket(Tournament $tournament, Request $request): JsonResponse
    {
        // Người tạo giải hoặc admin mới được bốc thăm
        if ($request->user()->id !== $tournament->created_by && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Chỉ người tạo giải hoặc quản trị viên được bốc thăm.'], 403);
        }

        $data = $request->validate([
            'athlete_ids' => ['required', 'array', 'min:2'],
            'athlete_ids.*' => ['integer', 'exists:athletes,id'],
        ]);

        if ($tournament->matches()->exists()) {
            return response()->json(['message' => 'Giải đã có nhánh đấu.'], 422);
        }

        $athletes = Athlete::whereIn('id', $data['athlete_ids'])
            ->orderByDesc('elo_rating')->get(); // xếp hạt giống theo Elo

        $matches = [];
        $slot = 1;
        $total = $athletes->count();
        for ($i = 0; $i < intdiv($total, 2); $i++) {
            $matches[] = [
                'tournament_id' => $tournament->id,
                'round' => 1,
                'slot' => $slot++,
                'athlete1_id' => $athletes[$i]->id,                    // hạt giống đầu
                'athlete2_id' => $athletes[$total - 1 - $i]->id,       // đối đỉnh
                'status' => 'scheduled',
            ];
        }
        // Số lẻ: VĐV cuối được miễn đấu vòng 1 (bye)
        if ($total % 2 === 1) {
            $matches[] = [
                'tournament_id' => $tournament->id, 'round' => 1, 'slot' => $slot,
                'athlete1_id' => $athletes[$total - 1]->id, 'athlete2_id' => null,
                'status' => 'completed', 'score1' => 2, 'score2' => 0,
            ];
        }

        TournamentMatch::insert($matches);

        // Thông báo "lịch ghép cặp trận đấu được cập nhật"
        $sent = $this->notifications->notifyPairingUpdated($tournament, ' — nhánh đấu đã bốc thăm xong.');

        return response()->json([
            'data' => TournamentMatch::where('tournament_id', $tournament->id)->get(),
            'message' => "Đã tạo nhánh đấu {$total} VĐV. Push đã gửi tới {$sent} thiết bị.",
        ], 201);
    }

    /** Ghi điểm trực tiếp (trọng tài/admin): +1 điểm, đổi trạng thái, hoàn tất trận. */
    public function updateScore(Tournament $tournament, TournamentMatch $match, Request $request): JsonResponse
    {
        if ($request->user()->id !== $tournament->created_by && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Chỉ người tạo giải hoặc quản trị viên được ghi điểm.'], 403);
        }

        $data = $request->validate([
            'score1' => ['required', 'integer', 'min:0', 'max:3'],
            'score2' => ['required', 'integer', 'min:0', 'max:3'],
            'status' => ['required', 'in:scheduled,live,completed'],
        ]);

        $match->update($data);

        // Hoàn tất trận → thắng/thua vào hồ sơ VĐV
        if ($data['status'] === 'completed' && $match->athlete1_id && $match->athlete2_id) {
            $winnerId = $data['score1'] > $data['score2'] ? $match->athlete1_id : $match->athlete2_id;
            $loserId = $winnerId === $match->athlete1_id ? $match->athlete2_id : $match->athlete1_id;

            Athlete::whereKey($winnerId)->increment('win_count');
            Athlete::whereKey($loserId)->increment('loss_count');
        }

        // Thông báo đẩy khi ghép cặp / tỷ số thay đổi
        $this->notifications->push(
            '🔴 Live Score',
            "{$tournament->name}: ".($match->athlete1?->full_name ?? '?')." {$data['score1']} - {$data['score2']} ".($match->athlete2?->full_name ?? '?'),
            "/tournaments/{$tournament->id}/live"
        );

        return response()->json(['data' => $this->format($match->fresh()), 'message' => 'Đã cập nhật tỷ số.']);
    }

    private function format(TournamentMatch $m): array
    {
        return [
            'id' => $m->id,
            'round' => $m->round,
            'slot' => $m->slot,
            'athlete1' => $m->athlete1?->only(['id', 'full_name', 'country_code', 'elo_rating']),
            'athlete2' => $m->athlete2?->only(['id', 'full_name', 'country_code', 'elo_rating']),
            'score1' => $m->score1,
            'score2' => $m->score2,
            'status' => $m->status,
            'starts_at' => $m->starts_at?->toIso8601String(),
        ];
    }
}
