<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Comment;
use App\Models\News;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bình luận trực tiếp dưới hồ sơ VĐV hoặc tin tức.
 * ?type=athlete|news & ?id=<id> — GET danh sách; POST gửi (cần đăng nhập).
 */
class CommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$type, $id] = $this->resolveTarget($request);

        $comments = Comment::query()
            ->with('user:id,name')
            ->where('commentable_type', $type)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')
            ->where('is_hidden', false)
            ->with('replies.user:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $comments->map(fn (Comment $c) => $this->format($c))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => ['nullable', 'exists:comments,id'],
        ]);

        [$type, $id] = $this->resolveTarget($request);

        // Chống spam: tối đa 1 bình luận / 15 giây / user
        $key = "comment:{$request->user()->id}";
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['message' => 'Bạn đang gửi bình luận quá nhanh, thử lại sau ít giây.'], 429);
        }
        RateLimiter::hit($key, 15);

        // Bộ lọc từ ngữ thô tục cơ bản
        $body = $data['body'];
        foreach (['địt', 'dm', 'clmm', 'vcl'] as $bad) {
            $body = str_ireplace($bad, '***', $body);
        }

        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'commentable_type' => $type,
            'commentable_id' => $id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $body,
        ]);

        // XP: +5 điểm cho đóng góp nội dung; trả về badge mới mở khóa (nếu có)
        $newBadges = app(\App\Services\XpService::class)->award($request->user(), 'comment');

        return response()->json([
            'data' => $this->format($comment->load('user:id,name')),
            'new_badges' => $newBadges,
            'message' => 'Đã gửi bình luận (+'.\App\Services\XpService::AWARDS['comment'].' XP).',
        ], 201);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Không có quyền xóa bình luận này.'], 403);
        }
        $comment->update(['is_hidden' => true]); // ẩn mềm (kiểm duyệt)

        return response()->json(['message' => 'Đã xóa bình luận.']);
    }

    private function resolveTarget(Request $request): array
    {
        $type = $request->query('type', 'athlete') === 'news' ? 'news' : 'athlete';
        $id = (int) ($request->query('id') ?? 0);

        if ($type === 'athlete' && ! Athlete::find($id)) {
            abort(404, 'VĐV không tồn tại');
        }
        if ($type === 'news' && ! News::find($id)) {
            abort(404, 'Tin tức không tồn tại');
        }

        return [$type, $id];
    }

    private function format(Comment $c): array
    {
        return [
            'id' => $c->id,
            'user' => ['id' => $c->user?->id, 'name' => $c->user?->name ?? 'Thành viên'],
            'body' => $c->body,
            'parent_id' => $c->parent_id,
            'created_at' => $c->created_at->toIso8601String(),
            'replies' => $c->replies->filter(fn ($r) => ! $r->is_hidden)->map(fn ($r) => $this->format($r)),
        ];
    }
}
