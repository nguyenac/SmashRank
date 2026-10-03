<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\EquipmentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Toàn vẹn dữ liệu: phát hiện trùng lặp VĐV / sản phẩm trong CSDL
 * để admin xem xét gộp hoặc xóa; hỗ trợ gộp VĐV ngay từ giao diện.
 */
class DuplicatesController extends Controller
{
    public function index(): JsonResponse
    {
        // ---- VĐV trùng: cùng họ tên (không phân biệt hoa thường/khoảng trắng) ----
        $athleteDupes = Athlete::query()
            ->selectRaw('LOWER(REPLACE(full_name, " ", "")) as key_name, COUNT(*) as total')
            ->groupBy('key_name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('total', 'key_name');

        $athletes = collect();
        foreach ($athleteDupes as $key => $total) {
            $group = Athlete::query()
                ->whereRaw('LOWER(REPLACE(full_name, " ", "")) = ?', [$key])
                ->get(['id', 'full_name', 'country_code', 'elo_rating', 'source', 'source_id']);
            $athletes->push([
                'key' => str_replace(' ', '', $key),
                'count' => (int) $total,
                'items' => $group->map(fn ($a) => $a->only(['id', 'full_name', 'country_code', 'elo_rating', 'source', 'source_id'])),
            ]);
        }

        // ---- Sản phẩm trùng: cùng thương hiệu + model ----
        $equipmentDupes = EquipmentItem::query()
            ->selectRaw('LOWER(TRIM(brand)) as brand_key, LOWER(TRIM(model)) as model_key, COUNT(*) as total')
            ->groupBy('brand_key', 'model_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $equipment = collect();
        foreach ($equipmentDupes as $dupe) {
            $group = EquipmentItem::query()
                ->whereRaw('LOWER(TRIM(brand)) = ? AND LOWER(TRIM(model)) = ?', [$dupe->brand_key, $dupe->model_key])
                ->get(['id', 'name', 'type', 'brand', 'model', 'price', 'source', 'source_id']);
            $equipment->push([
                'key' => $dupe->brand_key.' / '.$dupe->model_key,
                'count' => (int) $dupe->total,
                'items' => $group->map(fn ($e) => $e->only(['id', 'name', 'type', 'brand', 'model', 'price', 'source', 'source_id'])),
            ]);
        }

        return response()->json([
            'data' => [
                'athletes' => $athletes->values(),
                'equipment' => $equipment->values(),
                'summary' => [
                    'duplicate_athlete_groups' => $athletes->count(),
                    'duplicate_equipment_groups' => $equipment->count(),
                    'hint' => 'Gộp VĐV: dùng nút "Gộp nhóm này" hoặc php artisan athletes:merge {keep} {duplicate}. Sản phẩm trùng: xóa bản dư, giữ bản đầy đủ nhất.',
                ],
            ],
        ]);
    }

    /** Gộp VĐV trùng trực tiếp từ giao diện (gọi lệnh athletes:merge). */
    public function mergeAthletes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keep' => ['required', 'integer', 'exists:athletes,id'],
            'duplicate' => ['required', 'integer', 'exists:athletes,id', 'different:keep'],
        ]);

        Artisan::call('athletes:merge', [
            'keep' => $data['keep'],
            'duplicate' => $data['duplicate'],
        ]);

        return response()->json(['message' => 'Đã gộp hồ sơ. '.trim(Artisan::output())]);
    }
}
