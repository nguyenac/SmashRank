<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\News;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => News::query()->orderByDesc('published_at')
                ->get(['id', 'title', 'source', 'published_at', 'created_at'])
                ->map(fn ($n) => [
                    ...$n->only(['id', 'title', 'source']),
                    'published_at' => ($n->published_at ?? $n->created_at)?->toIso8601String(),
                ]),
        ]);
    }

    public function show(News $news): JsonResponse
    {
        return response()->json(['data' => [
            ...$news->only(['id', 'title', 'body', 'source']),
            'published_at' => ($news->published_at ?? $news->created_at)?->toIso8601String(),
        ]]);
    }

    public function store(Request $request, NotificationService $notifications): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Chỉ quản trị viên đăng tin.'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:120'],
        ]);

        $news = News::create([...$data, 'published_at' => now()]);

        // Thông báo đẩy "tin tức cầu lông mới" cho mọi người dùng đăng ký PWA
        $sent = $notifications->notifyNews($news);

        return response()->json(['data' => $news, 'message' => "Đã đăng tin và gửi push tới {$sent} thiết bị."], 201);
    }

    public function comments(News $news)
    {
        return app(CommentController::class)->index(request()->merge(['type' => 'news', 'id' => $news->id]));
    }
}
