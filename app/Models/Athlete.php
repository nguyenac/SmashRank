<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Athlete extends Model
{
    protected $fillable = [
        'user_id', 'full_name', 'nickname', 'nationality', 'country_code',
        'category', 'ranking_points', 'world_rank', 'career_high_rank',
        'win_count', 'loss_count', 'dominant_hand', 'skill_level',
        'racket_id', 'shoes_id', 'avatar_url', 'club', 'birth_year',
        'grassroots_rank', 'elo_rating', 'verified', 'association',
        'skill_agility', 'skill_power', 'skill_stamina',
        'skill_technique', 'skill_defense', 'skill_mentality',
    ];

    protected $casts = [
        'verified' => 'boolean',
    ];

    public function histories(): HasMany
    {
        return $this->hasMany(RankingHistory::class)->orderBy('recorded_month');
    }

    public function details()
    {
        return $this->hasOne(AthleteDetail::class, 'athlete_id');
    }

    public function yearStats()
    {
        return $this->hasMany(AthleteYearStat::class);
    }

    public function weightEntries()
    {
        return $this->hasMany(WeightEntry::class);
    }

    public function injuries()
    {
        return $this->hasMany(Injury::class);
    }

    public function coachNotes()
    {
        return $this->hasMany(CoachNote::class);
    }

    public function trainingSchedules()
    {
        return $this->hasMany(TrainingSchedule::class);
    }

    public function skillSnapshots()
    {
        return $this->hasMany(SkillSnapshot::class)->orderBy('recorded_at');
    }

    public function videoTags()
    {
        return $this->hasMany(VideoTag::class);
    }

    public function nutritionLogs()
    {
        return $this->hasMany(NutritionLog::class);
    }

    public function racket(): BelongsTo
    {
        return $this->belongsTo(EquipmentItem::class, 'racket_id');
    }

    public function shoes(): BelongsTo
    {
        return $this->belongsTo(EquipmentItem::class, 'shoes_id');
    }

    public function getWinRateAttribute(): float
    {
        $total = $this->win_count + $this->loss_count;

        return $total > 0 ? round($this->win_count / $total * 100, 1) : 0.0;
    }
}
