<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Trang [Danh sách Kỷ lục] — dữ liệu do lệnh `php artisan records:rebuild` tính lại.
 */
class RecordsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Record::query()->with('athlete:id,full_name,country_code,avatar_url,elo_rating');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $records = $query->orderBy('id')->get();

        return response()->json([
            'data' => $records->map(fn (Record $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'title' => $r->title,
                'value' => $r->value,
                'period' => $r->period,
                'description' => $r->description,
                'athlete' => $r->athlete ? [
                    'id' => $r->athlete->id,
                    'full_name' => $r->athlete->full_name,
                    'country_code' => $r->athlete->country_code,
                    'avatar_url' => $r->athlete->avatar_url,
                    'elo_rating' => $r->athlete->elo_rating,
                ] : null,
            ]),
        ]);
    }
}
