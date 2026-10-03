<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\AthleteDetail;
use App\Models\AthleteYearStat;
use App\Models\Record;
use App\Models\TournamentWinner;
use Illuminate\Support\Facades\DB;

/**
 * Tính lại toàn bộ bảng [Kỷ lục] + điểm GOAT.
 *
 * Kỷ lục hỗ trợ:
 *  - Tỷ lệ thắng trong năm dương lịch (tối thiểu 50 trận)
 *  - Chuỗi trận thắng trong sự nghiệp (có/không tính W.O.)
 *  - Chuỗi trận thắng xuyên suốt các giải Super 1000 → Super 100
 *  - Nhà vô địch trẻ nhất / lớn tuổi nhất tại giải Super 1000 → Super 300
 *  - Danh hiệu / chung kết / trận đấu / trận thắng cá nhân nhiều nhất trong 1 năm
 *
 * Điểm GOAT: tổng điểm vô địch theo cấp độ giải (Asian Games = 40),
 * cộng bonus kỷ lục. KHÔNG còn H2H Goat Points / Winning Percent Points.
 */
class RecordsService
{
    public function rebuild(): array
    {
        $count = 0;

        DB::transaction(function () use (&$count) {
            Record::query()->delete();

            // ---- 1. Tỷ lệ thắng trong năm dương lịch (≥ 50 trận) ----
            $best = AthleteYearStat::query()
                ->where('matches', '>=', 50)
                ->selectRaw('athlete_id, year, wins, matches, ROUND(wins * 100.0 / matches, 1) as rate')
                ->orderByDesc('rate')->first();
            if ($best) {
                $this->save('win_rate_year', 'Tỷ lệ thắng cao nhất trong năm dương lịch (tối thiểu 50 trận)',
                    $best->athlete_id, $best->rate.'%', (string) $best->year,
                    $best->wins.' thắng / '.$best->matches.' trận');
                $count++;
            }

            // ---- 2. Chuỗi trận thắng sự nghiệp (bao gồm W.O.) ----
            $streak = AthleteDetail::query()->orderByDesc('win_streak_career')->first();
            if ($streak) {
                $this->save('streak_career', 'Chuỗi trận thắng dài nhất trong sự nghiệp (bao gồm W.O.)',
                    $streak->athlete_id, $streak->win_streak_career.' trận', 'Sự nghiệp');
                $count++;
            }

            // ---- 3. Chuỗi trận thắng xuyên các giải Super 1000 → Super 100 ----
            $superStreak = AthleteDetail::query()->orderByDesc('super_streak')->first();
            if ($superStreak) {
                $this->save('streak_super', 'Chuỗi trận thắng xuyên suốt các giải Super 1000 đến Super 100',
                    $superStreak->athlete_id, $superStreak->super_streak.' trận', 'Sự nghiệp');
                $count++;
            }

            // ---- 4. VĐ vô địch trẻ nhất / lớn tuổi nhất (Super 1000 → Super 300) ----
            $champions = TournamentWinner::query()
                ->where('placement', 'champion')
                ->whereHas('tournament', fn ($q) => $q->whereIn('level', ['super1000', 'super750', 'super500', 'super300']))
                ->with(['athlete.details', 'tournament'])
                ->get()
                ->filter(fn (TournamentWinner $w) => $w->athlete->details?->birth_date && $w->achieved_at);

            if ($champions->isNotEmpty()) {
                $withAge = $champions->map(fn (TournamentWinner $w) => [
                    'winner' => $w,
                    'age_years' => $w->athlete->details->birth_date->diffInYears($w->achieved_at),
                ]);

                $youngest = $withAge->sortBy('age_years')->first();
                $this->save('youngest_champion',
                    'Nhà vô địch trẻ nhất tại giải Super 1000 đến Super 300',
                    $youngest['winner']->athlete_id,
                    $youngest['age_years'].' tuổi',
                    optional($youngest['winner']->tournament)->name);

                $oldest = $withAge->sortByDesc('age_years')->first();
                $this->save('oldest_champion',
                    'Nhà vô địch lớn tuổi nhất tại giải Super 1000 đến Super 300',
                    $oldest['winner']->athlete_id,
                    $oldest['age_years'].' tuổi',
                    optional($oldest['winner']->tournament)->name);
                $count += 2;
            }

            // ---- 5. Nhiều nhất trong 1 năm dương lịch: danh hiệu / chung kết / trận / thắng ----
            foreach ([
                ['titles', 'Số danh hiệu nhiều nhất trong một năm dương lịch', 'danh hiệu'],
                ['finals', 'Số trận chung kết nhiều nhất trong một năm dương lịch', 'trận chung kết'],
                ['matches', 'Số trận đấu nhiều nhất trong một năm dương lịch', 'trận đấu'],
                ['wins', 'Số trận thắng nhiều nhất trong một năm dương lịch', 'trận thắng'],
            ] as [$column, $title, $unit]) {
                $best = AthleteYearStat::query()->orderByDesc($column)->first();
                if ($best && $best->$column > 0) {
                    $this->save($column.'_year', $title, $best->athlete_id,
                        $best->$column.' '.$unit, (string) $best->year);
                    $count++;
                }
            }
        });

        $this->rebuildGoat();

        return ['records' => $count];
    }

    /** Tính lại điểm GOAT cho toàn bộ VĐV (không gồm H2H / Win% points). */
    public function rebuildGoat(): void
    {
        $cfg = config('goat');

        // Điểm vô địch/á quân theo cấp độ giải
        $points = TournamentWinner::query()
            ->join('tournaments', 'tournaments.id', '=', 'tournament_winners.tournament_id')
            ->select(
                'athlete_id',
                'placement',
                'level',
                DB::raw('CASE WHEN tournaments.is_asian_games = 1 THEN 1 ELSE 0 END as is_asian_games')
            )
            ->whereIn('placement', ['champion', 'finalist'])
            ->get()
            ->groupBy('athlete_id')
            ->map(function ($group) use ($cfg) {
                $sum = 0;
                foreach ($group as $row) {
                    $base = $row->is_asian_games
                        ? $cfg['asian_games_champion_points']
                        : ($cfg['champion_points'][$row->level] ?? $cfg['champion_points']['other']);

                    $sum += $row->placement === 'champion'
                        ? $base
                        : (int) round($base * $cfg['finalist_factor']);
                }

                return (int) $sum;
            });

        // Bonus kỷ lục đang nắm giữ
        $bonuses = Record::query()->get()->groupBy('athlete_id')
            ->map(function ($records) use ($cfg) {
                return (int) $records->sum(fn ($r) => $cfg['record_bonus'][$r->type] ?? 0);
            });

        $allIds = collect($points->keys())->merge($bonuses->keys())->unique();
        foreach ($allIds as $athleteId) {
            AthleteDetail::updateOrCreate(
                ['athlete_id' => $athleteId],
                ['goat_points' => ($points[$athleteId] ?? 0) + ($bonuses[$athleteId] ?? 0)]
            );
        }
    }

    private function save(string $type, string $title, ?int $athleteId, string $value, ?string $period, ?string $description = null): void
    {
        Record::create([
            'type' => $type,
            'title' => $title,
            'athlete_id' => $athleteId,
            'value' => $value,
            'period' => $period,
            'description' => $description,
        ]);
    }
}
