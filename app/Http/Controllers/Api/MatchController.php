<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Services\MatchResultService;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ghi kết quả trận đấu (AddMatchResultModal) — tự cập nhật
 * thắng/thua + điểm Elo của hai VĐV.
 *
 * 2 luồng:
 *  - store()         : ghi trực tiếp (admin / có quyền)
 *  - challenge()     : tạo trận CHỜ XÁC NHẬN kèm MATCH CODE — đối thủ nhập mã
 *                      mới áp Elo (chống tự công nhận điểm ảo)
 *  - confirmCode()   : xác nhận bằng mã
 */
class MatchController extends Controller
{
    public function __construct(private MatchResultService $service)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'athlete1_id' => ['required', 'integer', 'different:athlete2_id', 'exists:athletes,id'],
            'athlete2_id' => ['required', 'integer', 'exists:athletes,id'],
            'score1' => ['required', 'integer', 'min:0', 'max:3'],
            'score2' => ['required', 'integer', 'min:0', 'max:3'],
            'walkover' => ['nullable', 'boolean'],
            'category' => ['nullable', 'in:MS,WS,MD,WD,XD'],
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'played_at' => ['nullable', 'date'],
        ]);

        if (! ($data['walkover'] ?? false) && $data['score1'] === $data['score2']) {
            return response()->json(['message' => 'Tỷ số không được hòa.'], 422);
        }
        if (($data['walkover'] ?? false) && $data['score1'] === $data['score2']) {
            return response()->json(['message' => 'Trận W.O. cần chỉ rõ bên bỏ cuộc qua tỷ số (VD: 1-0 hoặc 0-1).'], 422);
        }
        if (! ($data['walkover'] ?? false) && max($data['score1'], $data['score2']) < 2) {
            return response()->json(['message' => 'Cần thắng ít nhất 2 game (bo3).'], 422);
        }

        $match = $this->service->record($data);

        $a1 = Athlete::find($data['athlete1_id']);
        $a2 = Athlete::find($data['athlete2_id']);

        return response()->json([
            'data' => $match,
            'message' => "Đã ghi nhận kết quả. Elo mới: {$a1->full_name} {$a1->elo_rating} · {$a2->full_name} {$a2->elo_rating}",
        ], 201);
    }

    /** Tạo thách đấu/giao lưu chờ xác nhận — trả MATCH CODE cho người tạo. */
    public function challenge(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'athlete1_id' => ['required', 'integer', 'different:athlete2_id', 'exists:athletes,id'],
            'athlete2_id' => ['required', 'integer', 'exists:athletes,id'],
            'score1' => ['required', 'integer', 'min:0', 'max:3'],
            'score2' => ['required', 'integer', 'min:0', 'max:3'],
            'walkover' => ['nullable', 'boolean'],
            'venue' => ['nullable', 'string', 'max:150'],
            'played_at' => ['nullable', 'date'],
        ]);

        try {
            $match = $this->service->createPending($data, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Thông báo cho đối thủ (chủ hồ sơ của athlete2) nếu có
        $opponentUserId = $match->athlete2->user_id;
        $sent = 0;
        if ($opponentUserId && $opponentUserId !== $request->user()->id) {
            $sent = $notifications->push(
                '⚔️ Lời thách đấu mới!',
                "{$match->athlete1->full_name} đã ghi kết quả trận với bạn — nhập mã {$match->match_code} để xác nhận & cập nhật Elo.",
                '/dashboard',
                $opponentUserId
            );
        }

        return response()->json([
            'data' => [
                'match_id' => $match->id,
                'match_code' => $match->match_code,
                'status' => $match->status,
            ],
            'message' => "Đã tạo trận chờ xác nhận. Chia sẻ mã {$match->match_code} cho đối thủ".($sent ? " (đã gửi push tới {$sent} thiết bị)." : '.'),
        ], 201);
    }

    /** Xác nhận trận bằng MATCH CODE → áp Elo. */
    public function confirmCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);

        try {
            $match = $this->service->confirmByCode($data['code'], $request->user()->id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Mã không tồn tại hoặc trận đã được xác nhận.'], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json([
            'data' => $match->only(['id', 'match_code', 'status', 'rating_change']),
            'message' => 'Đã xác nhận trận đấu — Elo đã được cập nhật!',
        ]);
    }
}
