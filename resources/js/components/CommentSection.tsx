import { useCallback, useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import { error as notifyError, success as notifySuccess } from '../lib/notification';
import type { CommentItem } from '../types';

/**
 * Bình luận trực tiếp dưới hồ sơ VĐV / tin tức — hỗ trợ phân tầng (trả lời).
 * Chống spam: backend giới hạn 15s/lần.
 */
export default function CommentSection({ type, targetId }: { type: 'athlete' | 'news'; targetId: number }) {
  const { user } = useAuth();
  const [comments, setComments] = useState<CommentItem[]>([]);
  const [body, setBody] = useState('');
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.get<{ data: CommentItem[] }>('/comments', { params: { type, id: targetId } })
      .then((res) => setComments(res.data))
      .catch(() => {});
  }, [type, targetId]);

  useEffect(load, [load]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user) {
      notifyError('Chưa đăng nhập', 'Đăng nhập để tham gia thảo luận.');
      return;
    }
    setBusy(true);
    try {
      await api.post('/comments', { body, parent_id: replyTo ?? undefined }, { params: { type, id: targetId } });
      setBody('');
      setReplyTo(null);
      notifySuccess('Đã gửi bình luận!');
      load();
    } catch (err) {
      notifyError('Không gửi được bình luận', errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const renderComment = (c: CommentItem, depth = 0) => (
    <div key={c.id} className={`border-b border-neutral-800/60 py-3 ${depth > 0 ? 'ml-6 border-l-2 border-l-neutral-800 pl-4' : ''}`}>
      <p className="text-sm">
        <span className="font-semibold text-emerald-400">{c.user.name}</span>{' '}
        <span className="text-xs text-neutral-500">{new Date(c.created_at).toLocaleString('vi-VN')}</span>
      </p>
      <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-200">{c.body}</p>
      <button
        onClick={() => setReplyTo(replyTo === c.id ? null : c.id)}
        className="mt-1 text-xs text-neutral-500 hover:text-emerald-400"
      >
        ↩ Trả lời
      </button>
      {c.replies?.map((r) => renderComment(r, depth + 1))}
    </div>
  );

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">💬 Thảo luận ({comments.length})</h2>

      <form onSubmit={submit} className="mb-4 space-y-2">
        {replyTo && (
          <p className="text-xs text-neutral-400">
            Đang trả lời bình luận #{replyTo} —{' '}
            <button type="button" onClick={() => setReplyTo(null)} className="text-emerald-400 hover:underline">hủy</button>
          </p>
        )}
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={2}
          maxLength={1000}
          placeholder={user ? 'Chia sẻ quan điểm về phong độ tay vợt…' : 'Đăng nhập để bình luận…'}
          className="w-full"
        />
        <button className="btn-primary text-xs" disabled={busy || !body.trim()}>
          {busy ? 'Đang gửi…' : 'Gửi bình luận'}
        </button>
      </form>

      {comments.length === 0
        ? <p className="text-sm text-neutral-500">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
        : comments.map((c) => renderComment(c))}
    </div>
  );
}
