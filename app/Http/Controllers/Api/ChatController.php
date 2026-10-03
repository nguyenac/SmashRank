<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Chat học viên ↔ HLV.
 *
 * Cơ chế: REST + polling 3s ở frontend (đơn giản, chạy mọi hosting).
 * Nâng cấp real-time thực: cài Laravel Reverb (WebSocket) và broadcast
 * event ChatMessageSent — schema dữ liệu giữ nguyên.
 */
class ChatController extends Controller
{
    /** Danh sách hội thoại (người từng nhắn) + tin nhắn chưa đọc. */
    public function threads(Request $request): JsonResponse
    {
        $me = $request->user()->id;

        $partnerIds = DB::table('chat_messages')
            ->where('sender_id', $me)->orWhere('recipient_id', $me)
            ->selectRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END as partner_id', [$me])
            ->distinct()
            ->pluck('partner_id');

        $threads = User::whereIn('id', $partnerIds)
            ->get(['id', 'name'])
            ->map(function ($partner) use ($me) {
                $last = DB::table('chat_messages')
                    ->where(fn ($q) => $q->where('sender_id', $me)->where('recipient_id', $partner->id)
                        ->orWhere('sender_id', $partner->id)->where('recipient_id', $me))
                    ->orderByDesc('id')->first();

                $unread = DB::table('chat_messages')
                    ->where('sender_id', $partner->id)->where('recipient_id', $me)
                    ->whereNull('read_at')->count();

                return [
                    'user' => $partner->only(['id', 'name']),
                    'last_message' => $last?->body,
                    'last_at' => $last?->created_at,
                    'unread' => $unread,
                ];
            })
            ->sortByDesc('last_at')
            ->values();

        // Gợi ý người có thể chat: admin (HLV) nếu học viên, ngược lại
        if ($threads->isEmpty() && ! $request->user()->isAdmin()) {
            $admins = User::where('role', 'admin')->get(['id', 'name'])
                ->map(fn ($u) => ['user' => $u->only(['id', 'name']), 'last_message' => null, 'last_at' => null, 'unread' => 0]);
            $threads = $admins;
        }

        return response()->json(['data' => $threads]);
    }

    /** Tin nhắn của 1 hội thoại (?with=<user_id>&after=<id> cho polling). */
    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'with' => ['required', 'integer', 'exists:users,id'],
            'after' => ['nullable', 'integer'],
        ]);
        $me = $request->user()->id;

        $query = DB::table('chat_messages')
            ->join('users as s', 's.id', '=', 'chat_messages.sender_id')
            ->where(function ($q) use ($me, $data) {
                $q->where('sender_id', $me)->where('recipient_id', $data['with'])
                  ->orWhere('sender_id', $data['with'])->where('recipient_id', $me);
            })
            ->when($data['after'] ?? null, fn ($q) => $q->where('chat_messages.id', '>', $data['after']))
            ->orderBy('chat_messages.id')
            ->limit(200)
            ->get(['chat_messages.id', 'chat_messages.sender_id', 'chat_messages.body', 'chat_messages.created_at', 's.name as sender_name']);

        // Đánh dấu đã đọc các tin nhắn nhận được
        DB::table('chat_messages')
            ->where('sender_id', $data['with'])->where('recipient_id', $me)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => $query->map(fn ($m) => [
            'id' => $m->id,
            'mine' => $m->sender_id === $me,
            'sender' => $m->sender_name,
            'body' => $m->body,
            'created_at' => $m->created_at,
        ])]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => ['required', 'integer', 'exists:users,id', 'different:user_id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $id = DB::table('chat_messages')->insertGetId([
            'sender_id' => $request->user()->id,
            'recipient_id' => $data['to'],
            'body' => $data['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id], 'message' => 'Đã gửi tin nhắn.'], 201);
    }
}
