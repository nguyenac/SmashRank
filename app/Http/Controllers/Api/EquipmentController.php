<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EquipmentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends Controller
{
    // ------------------------------------------------------------------
    // Public (mọi người xem)
    // ------------------------------------------------------------------

    public function publicIndex(Request $request): JsonResponse
    {
        $query = EquipmentItem::query();

        if ($type = $request->query('type')) {
            $query->where('type', $type); // racket | shoes
        }
        if ($brand = $request->query('brand')) {
            $query->where('brand', 'like', "%{$brand}%");
        }
        // Lọc khoảng giá tối đa
        if ($maxPrice = $request->query('max_price')) {
            $query->where('price', '<=', (float) $maxPrice);
        }
        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return response()->json([
            'data' => $query->orderBy('brand')->orderBy('name')
                ->get(['id', 'name', 'type', 'brand', 'model', 'description', 'price', 'image_url', 'specifications']),
        ]);
    }

    public function publicShow(EquipmentItem $item): JsonResponse
    {
        // Liên kết hai chiều: sản phẩm -> các VĐV đang sử dụng
        $users = $item->type === 'racket'
            ? $item->athletesUsingRacket()->get(['id', 'full_name', 'country_code', 'world_rank', 'elo_rating', 'avatar_url'])
            : $item->athletesUsingShoes()->get(['id', 'full_name', 'country_code', 'world_rank', 'elo_rating', 'avatar_url']);

        return response()->json([
            'data' => [
                ...$item->toArray(),
                'associated_athletes' => $users,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Admin CRUD (chỉ quản trị viên)
    // ------------------------------------------------------------------

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['image_url'] = $this->handleImage($request);

        $item = EquipmentItem::create($data);

        return response()->json(['data' => $item, 'message' => 'Đã tạo sản phẩm.'], 201);
    }

    public function update(Request $request, EquipmentItem $item): JsonResponse
    {
        $data = $this->validated($request, partial: true);

        if ($request->hasFile('image')) {
            if ($item->image_url) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $item->image_url));
            }
            $data['image_url'] = $this->handleImage($request);
        }

        $item->update($data);

        return response()->json(['data' => $item->fresh(), 'message' => 'Đã cập nhật sản phẩm.']);
    }

    public function destroy(EquipmentItem $item): JsonResponse
    {
        if ($item->image_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $item->image_url));
        }
        $item->delete();

        return response()->json(['message' => 'Đã xóa sản phẩm.']);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:racket,shoes'],
            'brand' => ['required', 'string', 'max:80'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'model' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'specifications' => ['nullable', 'array'],
        ];

        if ($partial) {
            $rules = collect($rules)
                ->map(fn ($r) => array_map(fn ($rule) => str_replace('required', 'sometimes', $rule), $r))
                ->all();
        }

        return $request->validate($rules);
    }

    private function handleImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $request->validate(['image' => ['image', 'max:4096']]);

        $path = $request->file('image')->store('products', 'public');

        return '/storage/'.$path;
    }
}
