<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\AthleteDetail;
use App\Models\CourtUsage;
use App\Models\CoachNote;
use App\Models\Injury;
use App\Models\TrainingSchedule;
use App\Models\WeightEntry;
use App\Services\SuggestionService;
use App\Services\CoachBotService;
use App\Services\VeoVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hồ sơ VĐV mở rộng — tất cả gom về /athletes/{athlete}/...:
 *  weight-entries (cân nặng) · injuries (chấn thương) · coach-notes (nhật ký HLV)
 *  training-schedules (lịch tập tuần do HLV gán) · radar-playstyle (chỉ số lối chơi)
 *  racket-suggestions · coach-bot · veo-highlight · match-history (lịch sử thi đấu/đặt sân)
 */
class AthleteExtrasController extends Controller
{
    // ---------------- Cân nặng (Weight Tracker) ----------------

    public function weightIndex(Athlete $athlete): JsonResponse
    {
        return response()->json([
            'data' => $athlete->weightEntries()->orderBy('measured_at')->get(),
        ]);
    }

    public function weightStore(Request $request, Athlete $athlete): JsonResponse
    {
        $data = $request->validate([
            'measured_at' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg' => ['required', 'numeric', 'between:30,200'],
            'heart_rate' => ['nullable', 'integer', 'between:40,220'],
        ]);

        $entry = WeightEntry::updateOrCreate(
            ['athlete_id' => $athlete->id, 'measured_at' => $data['measured_at']],
            ['weight_kg' => $data['weight_kg'], 'heart_rate' => $data['heart_rate'] ?? null]
        );

        return response()->json(['data' => $entry, 'message' => 'Đã ghi nhận cân nặng.'], 201);
    }

    // ---------------- Chấn thương (Injury History) ----------------

    public function injuriesIndex(Athlete $athlete): JsonResponse
    {
        return response()->json(['data' => $athlete->injuries()->orderByDesc('occurred_at')->get()]);
    }

    public function injuriesStore(Request $request, Athlete $athlete): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'occurred_at' => ['required', 'date'],
            'expected_recovery' => ['nullable', 'date', 'after:occurred_at'],
        ]);

        $injury = $athlete->injuries()->create([...$data, 'status' => 'recovering']);

        return response()->json(['data' => $injury, 'message' => 'Đã ghi nhận chấn thương.'], 201);
    }

    public function injuriesUpdate(Request $request, Injury $injury): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:recovering,recovered'],
        ]);
        $injury->update($data);

        return response()->json(['data' => $injury->fresh(), 'message' => 'Đã cập nhật.']);
    }

    // ---------------- Ghi chú HLV (timeline log) ----------------

    public function notesIndex(Athlete $athlete): JsonResponse
    {
        return response()->json([
            'data' => $athlete->coachNotes()->with('author:id,name')->orderByDesc('created_at')->get(),
        ]);
    }

    public function notesStore(Request $request, Athlete $athlete): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Chỉ HLV/Quản trị viên được ghi chú chiến thuật.'], 403);
        }

        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:2000']]);
        $note = $athlete->coachNotes()->create([...$data, 'author_id' => $request->user()->id]);

        return response()->json(['data' => $note->load('author:id,name'), 'message' => 'Đã thêm ghi chú.'], 201);
    }

    // ---------------- Lịch tập tuần do HLV gán ----------------

    public function scheduleIndex(Athlete $athlete, Request $request): JsonResponse
    {
        $weekStart = $request->query('week_start', now()->startOfWeek()->toDateString());

        return response()->json([
            'data' => $athlete->trainingSchedules()
                ->where('week_start', $weekStart)
                ->orderBy('day_of_week')->get(),
            'meta' => ['week_start' => $weekStart, 'done_count' => $athlete->trainingSchedules()->where('week_start', $weekStart)->where('done', true)->count()],
        ]);
    }

    public function scheduleStore(Request $request, Athlete $athlete): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Chỉ HLV/Quản trị viên được xếp lịch tập.'], 403);
        }

        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'drill' => ['required', 'string', 'max:200'],
            'detail' => ['nullable', 'string', 'max:1000'],
        ]);

        $schedule = $athlete->trainingSchedules()->create([...$data, 'coach_id' => $request->user()->id]);

        return response()->json(['data' => $schedule, 'message' => 'Đã gán bài tập tuần.'], 201);
    }

    public function scheduleUpdate(Request $request, TrainingSchedule $schedule): JsonResponse
    {
        $data = $request->validate(['done' => ['required', 'boolean']]);
        $schedule->update($data);

        return response()->json(['data' => $schedule->fresh(), 'message' => 'Đã cập nhật tiến độ.']);
    }

    // ---------------- Radar lối chơi chuyên sâu (HLV gán điểm) ----------------

    public function playstyle(Athlete $athlete, Request $request): JsonResponse
    {
        // HLV (admin) gán điểm; mặc định trả về giá trị hiện tại
        if ($request->isMethod('post') && $request->user()?->isAdmin()) {
            $data = $request->validate([
                'attack_smash' => ['integer', 'between:0,100'],   // Tấn công cuối sân
                'net_kill' => ['integer', 'between:0,100'],      // Cắt cầu trên lưới
                'endurance_defense' => ['integer', 'between:0,100'], // Phòng thủ bền bỉ
                'clear_control' => ['integer', 'between:0,100'], // Khả năng điều cầu
            ]);
            $detail = AthleteDetail::firstOrCreate(['athlete_id' => $athlete->id]);
            $detail->update(['playstyle_scores' => $data]);

            return response()->json(['data' => $data, 'message' => 'Đã cập nhật chỉ số lối chơi.']);
        }

        $detail = AthleteDetail::find($athlete->id);

        return response()->json(['data' => $detail?->playstyle_scores ?? [
            'attack_smash' => $athlete->skill_power,
            'net_kill' => $athlete->skill_technique,
            'endurance_defense' => $athlete->skill_defense,
            'clear_control' => $athlete->skill_stamina,
        ]]);
    }

    // ---------------- Gợi ý vợt / Coach Bot / Veo ----------------

    public function racketSuggestions(Athlete $athlete, SuggestionService $service): JsonResponse
    {
        return response()->json(['data' => $service->suggestRackets($athlete)]);
    }

    public function coachBot(Athlete $athlete, CoachBotService $service): JsonResponse
    {
        return response()->json(['data' => ['advices' => $service->advise($athlete)]]);
    }

    public function veoHighlight(Athlete $athlete, VeoVideoService $service): JsonResponse
    {
        return response()->json(['data' => $service->createHighlight($athlete)]);
    }

    // ---------------- Lịch sử thi đấu / đặt sân ----------------

    public function matchHistory(Athlete $athlete): JsonResponse
    {
        $matches = \App\Models\MatchGame::query()
            ->where('athlete1_id', $athlete->id)->orWhere('athlete2_id', $athlete->id)
            ->with(['athlete1:id,full_name', 'athlete2:id,full_name'])
            ->orderByDesc('played_at')->limit(30)->get();

        return response()->json(['data' => $matches->map(fn ($m) => [
            'date' => $m->played_at->format('Y-m-d'),
            'venue' => $m->venue,
            'opponent' => $m->athlete1_id === $athlete->id ? $m->athlete2?->full_name : $m->athlete1?->full_name,
            'score' => $m->athlete1_id === $athlete->id ? "{$m->score1}-{$m->score2}" : "{$m->score2}-{$m->score1}",
            'result' => $m->winnerId() === $athlete->id ? 'win' : 'loss',
            'walkover' => $m->walkover,
        ])]);
    }

    // ---------------- Heatmap tần suất hoạt động (cho thống kê) ----------------

    public static function activityHeatmap(): array
    {
        // Mật độ hoạt động theo ngày trong tuần x giờ (từ dữ liệu đặt sân)
        $rows = CourtUsage::query()
            ->selectRaw('WEEKDAY(usage_date) as weekday, hour, SUM(hours) as density')
            ->groupBy('weekday', 'hour')
            ->get();

        $matrix = array_fill(0, 7, array_fill(6, 17, 0)); // T2-CN x 6h-22h
        foreach ($rows as $r) {
            if ($r->weekday !== null && $r->hour >= 6 && $r->hour <= 22) {
                $matrix[$r->weekday][$r->hour - 6] = round((float) $r->density, 1);
            }
        }

        return $matrix;
    }
}
