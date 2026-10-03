<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Thanh toán gói tập.
 *
 * Hiện tại: tạo hóa đơn + xác nhận (admin) + liệt kê — "stub" an toàn.
 * Tích hợp thật VNPay/MoMo: cấu hình VNPAY_TMN_CODE/... trong .env,
 * tạo URL thanh toán tại create() (để sẵn hook createGatewayUrl).
 */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->isAdmin()
            ? Payment::query()->with('user:id,name')
            : Payment::where('user_id', $request->user()->id);

        $payments = $query->orderByDesc('created_at')->limit(50)->get();

        return response()->json(['data' => $payments->map(fn (Payment $p) => [
            'id' => $p->id,
            'user' => $p->user?->name ?? 'Tôi',
            'package_name' => $p->package_name,
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'status' => $p->status,
            'reference' => $p->reference,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'created_at' => $p->created_at->toIso8601String(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:10000'],
            'method' => ['required', 'in:bank,vnpay,momo,cash'],
        ]);

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'package_name' => $data['package_name'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'status' => 'pending',
        ]);

        $gatewayUrl = $this->createGatewayUrl($payment);

        return response()->json([
            'data' => $payment,
            'gateway_url' => $gatewayUrl,
            'message' => $gatewayUrl
                ? 'Hóa đơn đã tạo — chuyển đến cổng thanh toán.'
                : 'Hóa đơn đã tạo (chờ xác nhận từ HLV/quản trị viên).',
        ], 201);
    }

    /** Admin xác nhận đã nhận tiền / hủy hóa đơn. */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Chỉ quản trị viên xác nhận thanh toán.'], 403);
        }

        $data = $request->validate(['status' => ['required', 'in:paid,cancelled']]);
        $payment->update([...$data, 'paid_at' => $data['status'] === 'paid' ? now() : null, 'reference' => $payment->reference ?? 'ADM-'.strtoupper(Str::random(8))]);

        return response()->json(['data' => $payment->fresh(), 'message' => 'Đã cập nhật hóa đơn.']);
    }

    /** Hook cổng thanh toán: trả URL khi có credentials, null khi chưa cấu hình. */
    private function createGatewayUrl(Payment $payment): ?string
    {
        if ($payment->method === 'vnpay' && config('services.vnpay.tmn_code')) {
            // TODO: build URL theo spec VNPay (hash HMAC-SHA512) — trả null ở bản stub
            return null;
        }
        if ($payment->method === 'momo' && config('services.momo.partner_code')) {
            // TODO: MoMo ephemeral key signature
            return null;
        }

        return null;
    }
}
