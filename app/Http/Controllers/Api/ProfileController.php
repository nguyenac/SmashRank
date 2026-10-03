<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $athlete = Athlete::with(['racket', 'shoes', 'histories'])
            ->where('user_id', $request->user()->id)->first();

        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'role', 'two_factor_enabled', 'two_factor_method']),
            'athlete' => $athlete,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'max:3'],
            'club' => ['nullable', 'string', 'max:150'],
            'birth_year' => ['nullable', 'integer', 'min:1930', 'max:'.date('Y')],
            'dominant_hand' => ['nullable', 'in:right,left'],
            'skill_level' => ['nullable', 'in:pro,advanced,intermediate,beginner'],
            'racket_id' => ['nullable', 'exists:equipment_items,id'],
            'shoes_id' => ['nullable', 'exists:equipment_items,id'],
            // Tự đánh giá Việt Vũ — 8 tiêu chí (0-100) — hệ thống tự phân hạng 6 bậc
            'assessment' => ['nullable', 'array'],
            'assessment.serve' => ['integer', 'between:0,100'],
            'assessment.smash' => ['integer', 'between:0,100'],
            'assessment.net_play' => ['integer', 'between:0,100'],
            'assessment.backhand' => ['integer', 'between:0,100'],
            'assessment.defense' => ['integer', 'between:0,100'],
            'assessment.footwork' => ['integer', 'between:0,100'],
            'assessment.endurance' => ['integer', 'between:0,100'],
            'assessment.mentality' => ['integer', 'between:0,100'],
        ]);

        $user = $request->user();
        if (isset($data['name'])) {
            $user->update(['name' => $data['name']]);
        }

        $athlete = Athlete::firstOrNew(['user_id' => $user->id]);
        $athlete->full_name = $athlete->full_name ?? $user->name;
        $athlete->fill(collect($data)->except(['name', 'assessment'])->all());
        $athlete->save();

        // Phân hạng trình độ phong trào (chuẩn Việt Vũ, 8 tiêu chí):
        // Newbie → TBY (Trung bình yếu) → TB (Trung bình) → Khá → Giỏi → Xuất Sắc
        if (isset($data['assessment'])) {
            $athlete->grassroots_rank = $this->classifyGrassroots((float) collect($data['assessment'])->avg());
            $athlete->save();
        }

        $athlete->load(['racket', 'shoes']);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'athlete' => $athlete->fresh(),
            'message' => 'Đã cập nhật hồ sơ.',
        ]);
    }

    /**
     * Phân hạng trình độ phong trào theo điểm trung bình tự đánh giá Việt Vũ (0-100):
     *   <40 Newbie (người mới) | <50 TBY (Trung bình yếu) | <60 TB (Trung bình)
     *   <75 Khá | <85 Giỏi | ≥85 Xuất Sắc
     */
    private function classifyGrassroots(float $avg): string
    {
        return match (true) {
            $avg >= 85 => 'Xuất Sắc',
            $avg >= 75 => 'Giỏi',
            $avg >= 60 => 'Khá',
            $avg >= 50 => 'TB',
            $avg >= 40 => 'TBY',
            default => 'Newbie',
        };
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'max:2048']]);

        $athlete = Athlete::firstOrNew(['user_id' => $request->user()->id]);
        $path = $request->file('avatar')->store('avatars', 'public');
        $athlete->avatar_url = '/storage/'.$path;
        $athlete->full_name = $athlete->full_name ?? $request->user()->name;
        $athlete->save();

        return response()->json(['data' => ['avatar_url' => $athlete->avatar_url]]);
    }
}
