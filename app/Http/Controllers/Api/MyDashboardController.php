<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\MatchGame;
use App\Models\WeightEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User Dashboard — dữ liệu cá nhân của người dùng đăng nhập:
 *  - Lịch sử trận đấu (CHỈ chủ tài khoản hoặc admin xem được)
 *  - Tổng hợp thắng/thua (cho biểu đồ Pie)
 *  - Tiến độ sức khỏe: cân nặng + nhịp tim theo thời gian
 *  - Xuất JSON / CSV
 */
class MyDashboardController extends Controller
{
    public function matches(Request $request): JsonResponse
    {
        $athlete = Athlete::where('user_id', $request->user()->id)->first();

        // Kiểm tra quyền: chỉ chủ tài khoản (có hồ sơ) hoặc admin
        if (! $athlete && ! $request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Bạn chưa có hồ sơ vận động viên — hãy hoàn thiện hồ sơ cá nhân để xem lịch sử thi đấu chi tiết.',
                'code' => 'PROFILE_REQUIRED',
            ], 403);
        }

        $matches = $athlete
            ? MatchGame::query()
                ->where(fn ($q) => $q->where('athlete1_id', $athlete->id)->orWhere('athlete2_id', $athlete->id))
                ->with(['athlete1:id,full_name', 'athlete2:id,full_name'])
                ->orderByDesc('played_at')->limit(50)->get()
            : collect();

        $rows = $matches->map(function (MatchGame $m) use ($athlete) {
            $isP1 = $m->athlete1_id === $athlete?->id;
            return [
                'id' => $m->id,
                'date' => $m->played_at->format('Y-m-d'),
                'venue' => $m->venue,
                'opponent' => $isP1 ? $m->athlete2?->full_name : $m->athlete1?->full_name,
                'my_score' => $isP1 ? $m->score1 : $m->score2,
                'opp_score' => $isP1 ? $m->score2 : $m->score1,
                'result' => $m->winnerId() === $athlete?->id ? 'win' : 'loss',
                'walkover' => $m->walkover,
                'result_tags' => $m->result_tags,
            ];
        });

        $wins = $rows->where('result', 'win')->count();
        $losses = $rows->where('result', 'loss')->count();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'athlete' => $athlete?->only(['id', 'full_name', 'elo_rating', 'world_rank']),
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => ($wins + $losses) > 0 ? round($wins / ($wins + $losses) * 100, 1) : 0,
            ],
        ]);
    }

    /** Xuất lịch sử thi đấu cá nhân: ?format=json|csv (chỉ chủ tài khoản). */
    public function export(Request $request)
    {
        $request->validate(['format' => ['required', 'in:json,csv']]);
        $athlete = Athlete::where('user_id', $request->user()->id)->firstOrFail();
        $matches = MatchGame::query()
            ->where(fn ($q) => $q->where('athlete1_id', $athlete->id)->orWhere('athlete2_id', $athlete->id))
            ->with(['athlete1:id,full_name', 'athlete2:id,full_name'])
            ->orderByDesc('played_at')->get();

        $rows = $matches->map(fn (MatchGame $m) => [
            'date' => $m->played_at->format('Y-m-d'),
            'venue' => $m->venue,
            'me' => $athlete->full_name,
            'opponent' => $m->athlete1_id === $athlete->id ? $m->athlete2?->full_name : $m->athlete1?->full_name,
            'my_score' => $m->athlete1_id === $athlete->id ? $m->score1 : $m->score2,
            'opp_score' => $m->athlete1_id === $athlete->id ? $m->score2 : $m->score1,
            'result' => $m->winnerId() === $athlete->id ? 'win' : 'loss',
            'walkover' => $m->walkover,
        ]);

        if ($request->query('format') === 'json') {
            return response()->streamDownload(
                fn () => print json_encode(['athlete' => $athlete->full_name, 'matches' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                'my-match-history.json',
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ngay', 'Dia diem', 'Doi thu', 'Diem toi', 'Diem doi thu', 'Ket qua', 'W.O.']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['venue'], $r['opponent'], $r['my_score'], $r['opp_score'], $r['result'], $r['walkover'] ? 'yes' : '']);
            }
            fclose($out);
        }, 'my-match-history.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Streak hoạt động: số ngày liên tiếp có trận/thiếu cân/hoàn thành lịch tập.
     * Dùng cho widget giữ chân người chơi + push "đừng bỏ cuộc".
     */
    public function streak(Request $request): JsonResponse
    {
        $athlete = Athlete::where('user_id', $request->user()->id)->first();
        if (! $athlete) {
            return response()->json(['data' => ['current' => 0, 'best' => 0, 'total_active_days' => 0]]);
        }

        $dates = collect();
        $dates = $dates->merge($athlete->matches()->pluck('played_at')); // trận
        $dates = $dates->merge($athlete->weightEntries()->pluck('measured_at')); // đo sức khỏe
        $dates = $dates->merge(
            \App\Models\TrainingSchedule::where('athlete_id', $athlete->id)
                ->where('done', true)->get()
                ->map(fn ($s) => $s->week_start->copy()->addDays($s->day_of_week - 1)->toDateString())
        );

        $set = $dates->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())->unique()->sort()->values();
        if ($set->isEmpty()) {
            return response()->json(['data' => ['current' => 0, 'best' => 0, 'total_active_days' => 0]]);
        }

        // Chuỗi hiện tại: đếm lùi từ hôm nay (hoặc hôm qua nếu hôm nay chưa hoạt động)
        $current = 0;
        $cursor = now()->startOfDay();
        if (! $set->contains($cursor->toDateString())) {
            $cursor->subDay();
        }
        while ($set->contains($cursor->toDateString())) {
            $current++;
            $cursor->subDay();
        }

        // Chuỗi dài nhất
        $best = 1; $run = 1;
        for ($i = 1; $i < $set->count(); $i++) {
            $prev = \Carbon\Carbon::parse($set[$i - 1]);
            $cur = \Carbon\Carbon::parse($set[$i]);
            $run = $prev->diffInDays($cur) === 1 ? $run + 1 : 1;
            $best = max($best, $run);
        }

        return response()->json(['data' => [
            'current' => $current,
            'best' => $best,
            'total_active_days' => $set->count(),
        ]]);
    }

    /** Tiến độ học viên: cân nặng + nhịp tim + kỹ năng theo thời gian. */
    public function progress(Request $request): JsonResponse
    {
        $athlete = Athlete::where('user_id', $request->user()->id)->first();

        return response()->json([
            'data' => [
                'health' => $athlete?->weightEntries()
                    ->orderBy('measured_at')->get()
                    ->map(fn (WeightEntry $e) => [
                        'date' => $e->measured_at->format('Y-m-d'),
                        'weight_kg' => (float) $e->weight_kg,
                        'heart_rate' => $e->heart_rate,
                    ]),
                'skills' => $athlete?->skillSnapshots()->orderBy('recorded_at')->get()
                    ->map(fn ($s) => [
                        'date' => $s->recorded_at->format('Y-m-d'),
                        'power' => $s->power, 'speed' => $s->speed, 'defense' => $s->defense,
                        'net_play' => $s->net_play, 'stamina' => $s->stamina,
                    ]),
            ],
        ]);
    }
}
