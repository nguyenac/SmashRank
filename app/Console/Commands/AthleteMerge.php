<?php

namespace App\Console\Commands;

use App\Models\Athlete;
use App\Models\AthleteDetail;
use App\Models\AthleteYearStat;
use App\Models\RankingHistory;
use App\Models\TournamentWinner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gộp hồ sơ vận động viên trùng lặp: chuyển toàn bộ dữ liệu liên quan
 * (histories, year stats, tournament winners, chi tiết) sang hồ sơ giữ lại
 * rồi xóa hồ sơ trùng.
 */
class AthleteMerge extends Command
{
    protected $signature = 'athletes:merge {keep : ID hồ sơ giữ lại} {duplicate : ID hồ sơ trùng cần gộp}';

    protected $description = 'Gộp hai hồ sơ vận động viên trùng lặp thành một';

    public function handle(): int
    {
        $keepId = (int) $this->argument('keep');
        $dupId = (int) $this->argument('duplicate');

        $keep = Athlete::find($keepId);
        $dup = Athlete::find($dupId);

        if (! $keep || ! $dup || $keepId === $dupId) {
            $this->error('ID không hợp lệ hoặc hai ID trùng nhau.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($keep, $dup) {
            // Chuyển dữ liệu liên quan sang hồ sơ giữ lại
            RankingHistory::where('athlete_id', $dup->id)
                ->update(['athlete_id' => $keep->id]);
            AthleteYearStat::where('athlete_id', $dup->id)
                ->update(['athlete_id' => $keep->id]);
            TournamentWinner::where('athlete_id', $dup->id)
                ->update(['athlete_id' => $keep->id]);

            // Gộp thống kê tổng
            $keep->win_count += $dup->win_count;
            $keep->loss_count += $dup->loss_count;
            if ($dup->elo_rating > $keep->elo_rating) {
                $keep->elo_rating = $dup->elo_rating;
            }
            $keep->ranking_points = max($keep->ranking_points, $dup->ranking_points);
            if (empty($keep->avatar_url) && $dup->avatar_url) {
                $keep->avatar_url = $dup->avatar_url;
            }
            foreach (['club', 'association', 'racket_id', 'shoes_id'] as $field) {
                if (empty($keep->$field) && ! empty($dup->$field)) {
                    $keep->$field = $dup->$field;
                }
            }
            $keep->save();

            // Gộp chi tiết (1-1)
            $dupDetail = AthleteDetail::find($dup->id);
            if ($dupDetail) {
                $keepDetail = AthleteDetail::firstOrNew(['athlete_id' => $keep->id]);
                foreach ([
                    'birth_date', 'height_cm', 'weight_kg', 'playing_style', 'coach',
                    'association', 'team', 'titles', 'finals', 'total_matches', 'total_wins',
                    'win_streak_current', 'win_streak_career', 'win_streak_career_excl_wo',
                    'super_streak', 'not_played_matches', 'goat_points',
                ] as $field) {
                    if (empty($keepDetail->$field) && ! empty($dupDetail->$field)) {
                        $keepDetail->$field = $dupDetail->$field;
                    }
                }
                $keepDetail->save();
                $dupDetail->delete();
            }

            $dup->delete(); // FK cascade dọn phần còn lại
        });

        $this->info("Đã gộp hồ sơ #{$dup->id} ({$dup->full_name}) vào #{$keep->id} ({$keep->full_name}).");

        return self::SUCCESS;
    }
}
