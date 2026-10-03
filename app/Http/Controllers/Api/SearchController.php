<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\EquipmentItem;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global Search — tìm nhanh trên toàn ứng dụng.
 * GET /search?q=&types=athletes,clubs,equipment,tournaments
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => ['athletes' => [], 'clubs' => [], 'equipment' => [], 'tournaments' => []]]);
        }

        $types = collect(explode(',', (string) $request->query('types', 'athletes,clubs,equipment,tournaments')))
            ->map(fn ($t) => trim($t))->filter()->all();

        $result = [
            'athletes' => [],
            'clubs' => [],
            'equipment' => [],
            'tournaments' => [],
        ];

        if (in_array('athletes', $types)) {
            // #9 Tận dụng FULLTEXT index có sẵn (nhanh hơn LIKE %q%)
            $result['athletes'] = Athlete::query()
                ->whereFullText(['full_name', 'nickname', 'club'], $q)
                ->limit(6)
                ->get(['id', 'full_name', 'country_code', 'category', 'elo_rating'])
                ->toArray();
        }

        if (in_array('clubs', $types)) {
            $result['clubs'] = Athlete::query()
                ->whereNotNull('club')
                ->where('club', 'like', "%{$q}%")
                ->selectRaw('club, COUNT(*) as members')
                ->groupBy('club')
                ->limit(5)
                ->get()
                ->toArray();
        }

        if (in_array('equipment', $types)) {
            // #9 FULLTEXT index mới thêm (name, model, brand) — tìm theo mã SKU nhanh
            $result['equipment'] = EquipmentItem::query()
                ->whereFullText(['name', 'model', 'brand'], $q)
                ->limit(6)
                ->get(['id', 'name', 'type', 'brand', 'model', 'price'])
                ->toArray();
        }

        if (in_array('tournaments', $types)) {
            $result['tournaments'] = Tournament::query()
                ->where(fn ($sub) => $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('association', 'like', "%{$q}%"))
                ->limit(5)
                ->get(['id', 'name', 'level', 'start_date'])
                ->toArray();
        }

        return response()->json(['data' => $result, 'meta' => ['query' => $q, 'types' => $types]]);
    }
}
