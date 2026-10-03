<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\RankingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Công cụ tính lại chuỗi điểm ELO với điều chỉnh:
 *
 *  1) Covid decay — VĐV vắng mặt tại các giải đấu trong giai đoạn Covid
 *     (2020-03 → 2021-12) bị giảm điểm CHẬM hơn: chỉ giảm decay_per_month
 *     mỗi tháng vắng mặt (sau grace_months), trần max_total_decay.
 *
 *  2) Post-Covid boost — VĐV thi đấu sau năm 2021 được tăng nhẹ điểm
 *     (boost_per_month/tháng có thi đấu), trần max_total_boost.
 */
class EloService
{
    public function recalcAll(): array
    {
        $stats = ['athletes' => 0, 'decayed' => 0, 'boosted' => 0];

        Athlete::query()->with('histories')->chunkById(100, function ($athletes) use (&$stats) {
            foreach ($athletes as $athlete) {
                $result = $this->recalcAthlete($athlete);
                $stats['decayed'] += $result['decayed'];
                $stats['boosted'] += $result['boosted'];
                $stats['athletes']++;
            }
        });

        return $stats;
    }

    /** @return array{decayed:int, boosted:int} */
    public function recalcAthlete(Athlete $athlete): array
    {
        $histories = $athlete->histories()->orderBy('recorded_month')->get();
        if ($histories->count() < 2) {
            return ['decayed' => 0, 'boosted' => 0];
        }

        $cfg = config('elo');
        $covidStart = Carbon::parse($cfg['covid']['start'].'-01');
        $covidEnd = Carbon::parse($cfg['covid']['end'].'-01')->endOfMonth();
        $postFrom = Carbon::parse($cfg['post_covid']['from'].'-01');

        $decayed = 0;
        $boosted = 0;
        $totalDecayRatio = 0.0;
        $totalBoostRatio = 0.0;

        $months = $histories->map(fn (RankingHistory $h) => Carbon::parse($h->recorded_month)->format('Y-m'))
            ->unique()->values();

        foreach ($histories as $history) {
            $month = Carbon::parse($history->recorded_month)->startOfMonth();

            // #1 IDEMPOTENT: luôn tính lại từ điểm GỐC (raw_elo) — lần chạy đầu
            // ghi nhận raw_elo = elo hiện tại; các lần chạy sau dùng lại raw_elo
            // nên kết quả không bị cộng dồn boost/decay qua nhiều lần chạy.
            if ($history->raw_elo === null) {
                $history->raw_elo = $history->elo_rating;
                $history->save();
            }
            $newElo = (float) $history->raw_elo;

            // ---- 1) Covid decay: kiểm tra các tháng vắng mặt TRƯỚC tháng này ----
            $prevMonth = $month->copy()->subMonth()->format('Y-m');
            $prevIndex = $months->search($prevMonth);
            if ($prevIndex === false && $month->betweenIncluded($covidStart, $covidEnd)) {
                // Vắng mặt: đếm số tháng nghỉ liên tiếp tính tới tháng này
                $gap = $this->countAbsenceMonths($months, $month);
                if ($gap > $cfg['covid']['grace_months']) {
                    $effectiveGap = $gap - $cfg['covid']['grace_months'];
                    $ratio = min(
                        $effectiveGap * $cfg['covid']['decay_per_month'],
                        max(0, $cfg['covid']['max_total_decay'] - $totalDecayRatio)
                    );
                    if ($ratio > 0) {
                        $newElo -= $newElo * $ratio;
                        $totalDecayRatio += $ratio;
                        $decayed++;
                    }
                }
            }

            // ---- 2) Post-2021 boost: tháng có thi đấu (có bản ghi) sau 2021-12 ----
            if ($month->gte($postFrom)) {
                $ratio = min(
                    $cfg['post_covid']['boost_per_month'],
                    max(0, $cfg['post_covid']['max_total_boost'] - $totalBoostRatio)
                );
                if ($ratio > 0) {
                    $newElo += $newElo * $ratio;
                    $totalBoostRatio += $ratio;
                    $boosted++;
                }
            }

            $history->update(['elo_rating' => (int) round($newElo)]);
        }

        $last = $histories->last();
        $athlete->update(['elo_rating' => $last->elo_rating]);

        return ['decayed' => $decayed, 'boosted' => $boosted];
    }

    /** Đếm số tháng nghỉ liên tiếp ngay trước $month (trong/bao gồm giai đoạn Covid). */
    private function countAbsenceMonths($months, Carbon $month): int
    {
        $gap = 0;
        $cursor = $month->copy()->subMonth();
        $covidStart = Carbon::parse(config('elo.covid.start').'-01');
        $covidEnd = Carbon::parse(config('elo.covid.end').'-01')->endOfMonth();

        while ($cursor->gte($covidStart) && !$months->contains($cursor->format('Y-m'))) {
            $gap++;
            $cursor->subMonth();
            if ($cursor->lt($covidStart->copy()->subYears(2))) {
                break; // an toàn
            }
        }

        return $gap;
    }
}
