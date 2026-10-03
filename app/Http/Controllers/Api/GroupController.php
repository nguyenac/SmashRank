<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\TrainingGroup;
use App\Models\TrainingGroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tab 'Nhóm tập luyện': tạo/tham gia nhóm, theo dõi tiến độ chung,
 * bảng xếp hạng nội bộ (theo XP thành viên).
 */
class GroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = TrainingGroup::with(['coach:id,name', 'members.user:id,name,xp', 'members.athlete:id,full_name,elo_rating'])
            ->withCount('members')
            ->orderByDesc('members_count')
            ->get();

        return response()->json(['data' => $groups->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'description' => $g->description,
            'coach' => $g->coach?->name,
            'members_count' => $g->members_count,
            'leaderboard' => $g->members
                ->sortByDesc(fn ($m) => $m->user?->xp ?? 0)
                ->values()
                ->map(fn ($m, $i) => [
                    'rank' => $i + 1,
                    'name' => $m->user?->name ?? '—',
                    'athlete' => $m->athlete?->full_name,
                    'elo' => $m->athlete?->elo_rating,
                    'xp' => $m->user?->xp ?? 0,
                ]),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $group = TrainingGroup::create([
            ...$data,
            'creator_id' => $request->user()->id,
            'coach_id' => $request->user()->isAdmin() ? $request->user()->id : null,
        ]);
        $group->members()->create(['user_id' => $request->user()->id]);

        return response()->json(['data' => $group, 'message' => 'Đã tạo nhóm tập luyện.'], 201);
    }

    public function join(Request $request, TrainingGroup $group): JsonResponse
    {
        $athlete = Athlete::where('user_id', $request->user()->id)->first();
        $group->members()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['athlete_id' => $athlete?->id]
        );

        return response()->json(['message' => "Đã tham gia nhóm {$group->name}!"]);
    }

    public function leave(Request $request, TrainingGroup $group): JsonResponse
    {
        $group->members()->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Đã rời nhóm.']);
    }
}
