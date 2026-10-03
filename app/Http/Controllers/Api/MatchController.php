<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Services\MatchResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ghi kết quả trận đấu (AddMatchResultModal) — tự cập nhật
 * thắng/thua + điểm Elo của hai VĐV.
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
}
