import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '../api';
import CommentSection from '../components/CommentSection';
import type { NewsItem } from '../types';

/** Trang Tin tức cầu lông — bình luận trực tiếp dưới mỗi bài. */
export function NewsPage() {
  const [news, setNews] = useState<NewsItem[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: NewsItem[] }>('/news').then((r) => setNews(r.data)).catch((e) => setError(errorMessage(e)));
  }, []);

  return (
    <div className="space-y-3">
      <h1 className="text-2xl font-bold">📰 Tin tức cầu lông</h1>
      {error && <p className="text-sm text-rose-400">{error}</p>}
      {news.map((n) => (
        <Link key={n.id} to={`/news/${n.id}`} className="card block transition hover:border-emerald-600">
          <p className="font-bold">{n.title}</p>
          <p className="mt-1 text-xs text-neutral-500">
            {n.source ?? 'SmashRank'} · {n.published_at ? new Date(n.published_at).toLocaleDateString('vi-VN') : ''}
          </p>
        </Link>
      ))}
    </div>
  );
}

export function NewsDetail() {
  const { id } = useParams<{ id: string }>();
  const [item, setItem] = useState<NewsItem | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: NewsItem }>(`/news/${id}`).then((r) => setItem(r.data)).catch((e) => setError(errorMessage(e)));
  }, [id]);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!item) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  return (
    <div className="space-y-4">
      <div className="card">
        <h1 className="text-2xl font-bold">{item.title}</h1>
        <p className="mt-1 text-xs text-neutral-500">
          {item.source ?? 'SmashRank'} · {item.published_at ? new Date(item.published_at).toLocaleString('vi-VN') : ''}
        </p>
        <p className="mt-4 whitespace-pre-wrap leading-relaxed text-neutral-200">{item.body}</p>
      </div>
      <CommentSection type="news" targetId={item.id} />
      <Link to="/news" className="text-sm text-emerald-400 hover:underline">← Tất cả tin tức</Link>
    </div>
  );
}
