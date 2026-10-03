<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Quản lý danh mục thương hiệu cầu lông (Admin).
 * Mỗi thương hiệu chứa danh sách vợt/giày thuộc về nó.
 */
class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()->withCount(['rackets', 'shoes']);

        if ($q = $request->query('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        return response()->json([
            'data' => $query->orderBy('name')->get()->map(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'country' => $b->country,
                'logo_url' => $b->logo_url,
                'description' => $b->description,
                'rackets_count' => $b->rackets_count,
                'shoes_count' => $b->shoes_count,
            ]),
        ]);
    }

    public function show(Brand $brand): JsonResponse
    {
        $brand->load(['products:id,name,type,brand_id,model,price,image_url']);

        return response()->json(['data' => [
            ...$brand->only(['id', 'name', 'slug', 'country', 'logo_url', 'description']),
            'products' => $brand->products,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:brands,name'],
            'country' => ['nullable', 'string', 'max:60'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
        ]);

        $brand = Brand::create([...$data, 'slug' => Str::slug($data['name'])]);

        return response()->json(['data' => $brand, 'message' => 'Đã thêm thương hiệu.'], 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', 'unique:brands,name,'.$brand->id],
            'country' => ['nullable', 'string', 'max:60'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $brand->update($data);

        return response()->json(['data' => $brand->fresh(), 'message' => 'Đã cập nhật thương hiệu.']);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa: thương hiệu vẫn còn sản phẩm. Hãy chuyển sản phẩm sang thương hiệu khác trước.',
            ], 422);
        }
        $brand->delete();

        return response()->json(['message' => 'Đã xóa thương hiệu.']);
    }
}
