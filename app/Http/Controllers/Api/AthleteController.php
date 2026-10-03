<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API tìm kiếm vận động viên.
 *
 * Hỗ trợ:
 *  - Tìm kiếm toàn văn (full-text) trên full_name, nickname, club (chỉ mục FULLTEXT MariaDB)
 *  - Lọc theo Họ Tên, Quốc Tịch, Trình độ, Nội dung, Tay thuận, Vợt/Giày
 * Xem chi tiết ví dụ & định dạng trả về tại docs/API_ATHLETE_SEARCH.md
 */
class AthleteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'skill_level' => ['nullable', 'in:pro,advanced,intermediate,beginner'],
            'category' => ['nullable', 'in:MS,WS,MD,WD,XD'],
            'dominant_hand' => ['nullable', 'in:right,left'],
            // Lọc nhóm tuổi: U18 / U21 / Open (dựa trên năm sinh)
            'age_group' => ['nullable', 'in:U18,U21,Open'],
            // Lọc khoảng Elo (rank range)
            'min_elo' => ['nullable', 'integer', 'min:0'],
            'max_elo' => ['nullable', 'integer', 'min:0'],
            'racket' => ['nullable', 'integer'],
            'shoes' => ['nullable', 'integer'],
            'sort' => ['nullable', 'in:elo,points,world_rank,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Athlete::query()
            ->with(['racket:id,name,brand,model', 'shoes:id,name,brand,model']);

        // ---- Tìm kiếm toàn văn: không dấu / có dấu / một phần từ ----
        if ($q = trim((string) $request->query('q'))) {
            if (str_contains($q, ' ')) {
                // Nhiều từ: chế độ natural language FULLTEXT
                $query->whereFullText(['full_name', 'nickname', 'club'], $q);
            } else {
                // Một từ: dùng wildcard prefix (LIKE) cho gợi ý nhanh
                $query->where(function ($sub) use ($q) {
                    $sub->where('full_name', 'like', "%{$q}%")
                        ->orWhere('nickname', 'like', "%{$q}%")
                        ->orWhere('club', 'like', "%{$q}%")
                        ->orWhere('nationality', 'like', "%{$q}%");
                });
            }
        }

        // ---- Bộ lọc đa tiêu chí ----
        if ($name = $request->query('name')) {
            $query->where('full_name', 'like', "%{$name}%");
        }
        if ($nationality = $request->query('nationality')) {
            $query->where('nationality', 'like', "%{$nationality}%");
        }
        if ($skill = $request->query('skill_level')) {
            $query->where('skill_level', $skill);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($hand = $request->query('dominant_hand')) {
            $query->where('dominant_hand', $hand);
        }
        if ($racket = $request->query('racket')) {
            $query->where('racket_id', $racket);
        }

        // Khoảng Elo (rank range) cho quản lý dữ liệu
        if ($minElo = $request->query('min_elo')) {
            $query->where('elo_rating', '>=', (int) $minElo);
        }
        if ($maxElo = $request->query('max_elo')) {
            $query->where('elo_rating', '<=', (int) $maxElo);
        }

        // Nhóm tuổi qua năm sinh: U18 (≤18 tuổi), U21 (≤21), Open (mọi lứa tuổi)
        if ($age = $request->query('age_group')) {
            $currentYear = (int) now()->year;
            match ($age) {
                'U18' => $query->where('birth_year', '>=', $currentYear - 18),
                'U21' => $query->where('birth_year', '>=', $currentYear - 21),
                default => null,
            };
        }
        if ($shoes = $request->query('shoes')) {
            $query->where('shoes_id', $shoes);
        }

        $sort = $request->query('sort', 'elo');
        $query->orderBy(match ($sort) {
            'points' => 'ranking_points',
            'world_rank' => 'world_rank',
            'name' => 'full_name',
            default => 'elo_rating',
        }, $sort === 'world_rank' ? 'asc' : 'desc');

        $perPage = (int) $request->query('per_page', 20);
        $results = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => collect($results->items())->map(fn (Athlete $a) => $this->formatListItem($a)),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function show(Athlete $athlete): JsonResponse
    {
        $athlete->load(['histories', 'racket', 'shoes', 'details']);
        $details = $athlete->details;

        return response()->json([
            'data' => [
                'id' => $athlete->id,
                'full_name' => $athlete->full_name,
                'nickname' => $athlete->nickname,
                'nationality' => $athlete->nationality,
                'country_code' => $athlete->country_code,
                'category' => $athlete->category,
                'ranking_points' => $athlete->ranking_points,
                'world_rank' => $athlete->world_rank,
                'career_high_rank' => $athlete->career_high_rank,
                'win_count' => $athlete->win_count,
                'loss_count' => $athlete->loss_count,
                'win_rate' => $athlete->win_rate,
                'dominant_hand' => $athlete->dominant_hand,
                'skill_level' => $athlete->skill_level,
                'club' => $athlete->club,
                'birth_year' => $athlete->birth_year,
                'grassroots_rank' => $athlete->grassroots_rank,
                'elo_rating' => $athlete->elo_rating,
                'verified' => $athlete->verified,
                'avatar_url' => $athlete->avatar_url,
                'racket' => $athlete->racket?->only(['id', 'name', 'brand', 'model']),
                'shoes' => $athlete->shoes?->only(['id', 'name', 'brand', 'model']),
                'skills' => [
                    'agility' => $athlete->skill_agility,
                    'power' => $athlete->skill_power,
                    'stamina' => $athlete->skill_stamina,
                    'technique' => $athlete->skill_technique,
                    'defense' => $athlete->skill_defense,
                    'mentality' => $athlete->skill_mentality,
                ],
                'association' => $athlete->association,
                'ranking_history' => $athlete->histories->map(fn ($h) => [
                    'month' => $h->recorded_month->format('Y-m'),
                    'points' => $h->points,
                    'elo_rating' => $h->elo_rating,
                    'win_rate' => (float) $h->win_rate,
                ]),
                'details' => $details ? [
                    'birth_date' => $details->birth_date?->format('Y-m-d'),
                    'height_cm' => $details->height_cm,
                    'weight_kg' => $details->weight_kg,
                    'playing_style' => $details->playing_style,
                    'coach' => $details->coach,
                    'association' => $details->association,
                    'titles' => $details->titles,
                    'finals' => $details->finals,
                    'total_matches' => $details->total_matches,
                    'total_wins' => $details->total_wins,
                    'goat_points' => $details->goat_points,
                    'not_played_matches' => $details->not_played_matches,
                    // Chuỗi trận thắng: 2 phiên bản cho thẻ [Chuỗi trận thắng]
                    // (tùy chọn "W.O. có làm ngắt chuỗi hay không")
                    'win_streak_current' => $details->win_streak_current,
                    'win_streak_career' => $details->win_streak_career,
                    'win_streak_career_excl_wo' => $details->win_streak_career_excl_wo,
                    'super_streak' => $details->super_streak,
                ] : null,
            ],
        ]);
    }

    private function formatListItem(Athlete $a): array
    {
        return [
            'id' => $a->id,
            'full_name' => $a->full_name,
            'nickname' => $a->nickname,
            'nationality' => $a->nationality,
            'country_code' => $a->country_code,
            'category' => $a->category,
            'world_rank' => $a->world_rank,
            'ranking_points' => $a->ranking_points,
            'elo_rating' => $a->elo_rating,
            'skill_level' => $a->skill_level,
            'win_rate' => $a->win_rate,
            'dominant_hand' => $a->dominant_hand,
            'avatar_url' => $a->avatar_url,
            'racket' => $a->racket?->name,
            'shoes' => $a->shoes?->name,
        ];
    }
}
