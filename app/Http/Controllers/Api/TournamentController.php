<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tournament::query()->orderByDesc('start_date');

        // Tìm kiếm theo tên giải / hiệp hội / cấp độ
        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('association', 'like', "%{$q}%");
            });
        }
        if ($level = $request->query('level')) {
            $query->where('level', $level);
        }
        if ($assoc = $request->query('association')) {
            $query->where('association', 'like', "%{$assoc}%");
        }

        $tournaments = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => collect($tournaments->items())->map(fn (Tournament $t) => $this->format($t)),
            'meta' => [
                'current_page' => $tournaments->currentPage(),
                'last_page' => $tournaments->lastPage(),
                'total' => $tournaments->total(),
            ],
        ]);
    }

    public function show(Tournament $tournament): JsonResponse
    {
        // Tìm kiếm trong giải theo hiệp hội tay vợt (?association=...)
        $winners = $tournament->winners();
        if ($assoc = request()->query('association')) {
            $winners->whereHas('athlete', fn ($q) => $q->where('athletes.association', 'like', "%{$assoc}%")
                ->orWhere('athlete_details.association', 'like', "%{$assoc}%"));
        }

        return response()->json([
            'data' => [
                ...$this->format($tournament),
                'created_by' => $tournament->created_by,
                'winners' => $winners->get()->map(fn ($w) => [
                    'athlete_id' => $w->athlete_id,
                    'athlete_name' => $w->athlete?->full_name,
                    'country_code' => $w->athlete?->country_code,
                    'category' => $w->category,
                    'placement' => $w->placement,
                    'achieved_at' => $w->achieved_at?->format('Y-m-d'),
                ]),
            ],
        ]);
    }

    /**
     * Người dùng tự tạo giải đấu (Sportnet-style): tên, cấp độ, thời gian,
     * có/không real-time. Tạo xong gửi push "có giải đấu mới" cho mọi người dùng.
     */
    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'level' => ['nullable', 'in:super1000,super750,super500,super300,super100,other'],
            'association' => ['nullable', 'string', 'max:150'],
            'host_country' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'has_live_scores' => ['nullable', 'boolean'],
            'is_team_event' => ['nullable', 'boolean'],
        ]);

        $tournament = Tournament::create([
            ...$data,
            'level' => $data['level'] ?? 'other',
            'created_by' => $request->user()->id,
            'has_live_scores' => $data['has_live_scores'] ?? false,
        ]);

        // Push thông báo có giải mới (không bỏ lỡ hạn đăng ký)
        $sent = $notifications->push('🎾 Giải đấu mới!', $tournament->name.' — mở đăng ký', "/tournaments/{$tournament->id}");

        return response()->json([
            'data' => $this->format($tournament),
            'message' => "Đã tạo giải đấu. Push đã gửi tới {$sent} thiết bị.",
        ], 201);
    }

    private function format(Tournament $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'level' => $t->level,
            'is_asian_games' => $t->is_asian_games,
            'is_team_event' => $t->is_team_event,
            // Điều kiện hiển thị nút "Live Score": chỉ khi giải có cập nhật tỷ số real-time
            'has_live_scores' => $t->has_live_scores,
            'association' => $t->association,
            'host_country' => $t->host_country,
            'start_date' => $t->start_date?->format('Y-m-d'),
            'end_date' => $t->end_date?->format('Y-m-d'),
        ];
    }
}
