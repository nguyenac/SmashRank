<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;

/** 'Analytics Hub' — phân bố nhân khẩu học VĐV: tuổi, quốc tịch, trình độ. */
class DemographicsController extends Controller
{
    public function index(): JsonResponse
    {
        $currentYear = (int) now()->year;

        $ageBuckets = [
            ['label' => 'U18', 'min' => $currentYear - 18, 'max' => $currentYear],
            ['label' => '18-25', 'min' => $currentYear - 25, 'max' => $currentYear - 19],
            ['label' => '26-32', 'min' => $currentYear - 32, 'max' => $currentYear - 26],
            ['label' => '33+', 'min' => 1930, 'max' => $currentYear - 33],
        ];

        $ageDistribution = collect($ageBuckets)->map(fn ($b) => [
            'label' => $b['label'],
            'total' => Athlete::whereBetween('birth_year', [$b['min'], $b['max']])->count(),
        ]);

        $nationality = Athlete::query()
            ->selectRaw('nationality, country_code, COUNT(*) as total')
            ->groupBy('nationality', 'country_code')
            ->orderByDesc('total')->limit(15)
            ->get()
            ->map(fn ($r) => ['country' => $r->nationality, 'country_code' => $r->country_code, 'total' => (int) $r->total]);

        $skills = Athlete::query()
            ->selectRaw('skill_level, COUNT(*) as total')
            ->groupBy('skill_level')->pluck('total', 'skill_level');

        $grassroots = Athlete::query()
            ->whereNotNull('grassroots_rank')
            ->selectRaw('grassroots_rank, COUNT(*) as total')
            ->groupBy('grassroots_rank')->pluck('total', 'grassroots_rank');

        return response()->json(['data' => [
            'age_distribution' => $ageDistribution,
            'nationality_distribution' => $nationality,
            'skill_distribution' => $skills,
            'grassroots_distribution' => $grassroots,
        ]]);
    }
}
