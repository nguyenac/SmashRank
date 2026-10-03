<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tính năng "Sinh nhật hôm nay" trên trang chủ.
 * Hỗ trợ tra cứu sinh nhật tay vợt vào BẤT KỲ ngày nào qua ?date=YYYY-MM-DD
 * (mặc định là ngày hiện tại).
 */
class BirthdayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : now();

        $athletes = Athlete::query()
            ->join('athlete_details', 'athlete_details.athlete_id', '=', 'athletes.id')
            ->whereMonth('athlete_details.birth_date', $date->month)
            ->whereDay('athlete_details.birth_date', $date->day)
            ->orderByDesc('athletes.elo_rating')
            ->get([
                'athletes.id', 'athletes.full_name', 'athletes.country_code',
                'athletes.category', 'athletes.elo_rating', 'athletes.avatar_url',
                'athlete_details.birth_date',
            ])
            ->map(function ($a) use ($date) {
                $birth = Carbon::parse($a->birth_date);

                return [
                    'id' => $a->id,
                    'full_name' => $a->full_name,
                    'country_code' => $a->country_code,
                    'category' => $a->category,
                    'elo_rating' => $a->elo_rating,
                    'avatar_url' => $a->avatar_url,
                    'birth_date' => $a->birth_date,
                    'turning_age' => $date->year - $birth->year,
                ];
            });

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'athletes' => $athletes,
            ],
        ]);
    }
}
