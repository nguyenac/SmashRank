<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtUsage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Smart Court Scheduler — phân tích dữ liệu đặt sân lịch sử
 * để gợi ý khung giờ trống tối ưu cho trận giao lưu mới.
 *
 * Điểm "tối ưu": giờ có mật độ sử dụng THẤP trong khung mở cửa
 * (ít đông → dễ book sân) nhưng vẫn nằm trong khung giờ vàng 17-21h
 * được ưu tiên nếu mật độ không quá cao.
 */
class CourtController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Court::orderBy('name')->get()]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $date = Carbon::parse($request->query('date', now()->toDateString()));
        $weekday = $date->dayOfWeekIso - 1; // 0=T2 ... 6=CN

        // Mật độ trung bình theo giờ cho thứ tương ứng trong 8 tuần qua
        $density = CourtUsage::query()
            ->whereRaw('WEEKDAY(usage_date) = ?', [$weekday])
            ->where('usage_date', '>=', now()->subWeeks(8)->toDateString())
            ->selectRaw('hour, AVG(hours) as avg_hours, SUM(bookings) as total_bookings')
            ->groupBy('hour')
            ->pluck('total_bookings', 'hour');

        $courts = Court::all();
        $suggestions = [];

        foreach ($courts as $court) {
            $slots = [];
            for ($h = $court->open_hour; $h < $court->close_hour; $h++) {
                $busy = (int) ($density[$h] ?? 0);
                $primeTime = $h >= 17 && $h <= 20;

                $slots[] = [
                    'hour' => sprintf('%02d:00', $h),
                    'busy_score' => $busy,
                    // Điểm gợi ý: càng ít người càng tốt; khung giờ vàng được cộng nhẹ
                    'score' => max(0, 100 - $busy * 5) + ($primeTime ? 10 : 0),
                    'prime_time' => $primeTime,
                ];
            }

            usort($slots, fn ($a, $b) => $b['score'] <=> $a['score']);
            $suggestions[] = [
                'court' => $court->only(['id', 'name', 'open_hour', 'close_hour']),
                'best_slots' => array_slice($slots, 0, 3),
            ];
        }

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'weekday' => ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'][$weekday],
                'suggestions' => $suggestions,
                'note' => 'Gợi ý dựa trên tần suất đặt sân 8 tuần gần nhất — chỉ mang tính tham khảo.',
            ],
        ]);
    }
}
