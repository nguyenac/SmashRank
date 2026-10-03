import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import { error as notifyError, success as notifySuccess } from '../lib/notification';
import type { Tournament, TournamentMatchRow } from '../types';

/**
 * Trang Tỷ số trực tiếp (Live Score):
 *  - Polling 5s để cập nhật tỷ số real-time
 *  - Nút +/- tối ưu cho trọng tài (chỉ admin): 1 chạm cộng điểm
 *  - Giải không có real-time đã được backend redirect về trang chi tiết
 */
export default function LiveScorePage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const [tournament, setTournament] = useState<Tournament | null>(null);
  const [matches, setMatches] = useState<TournamentMatchRow[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: Tournament }>(`/tournaments/${id}`).then((r) => setTournament(r.data));
  }, [id]);

  useEffect(() => {
    let timer: ReturnType<typeof setInterval>;
    const load = () => {
      api.get<{ data: TournamentMatchRow[] }>(`/tournaments/${id}/matches/live`)
        .then((r) => setMatches(r.data))
        .catch((e) => {
          setError(errorMessage(e));
          clearInterval(timer);
        });
    };
    load();
    timer = setInterval(load, 5000);
    return () => clearInterval(timer);
  }, [id]);

  const updateScore = async (match: TournamentMatchRow, side: 1 | 2, delta: 1 | -1) => {
    try {
      const score1 = Math.max(0, match.score1 + (side === 1 ? delta : 0));
      const score2 = Math.max(0, match.score2 + (side === 2 ? delta : 0));
      const done = score1 === 2 || score2 === 2;
      await api.put(`/admin/tournaments/${id}/matches/${match.id}/score`, {
        score1, score2,
        status: done ? 'completed' : 'live',
      });
      if (done) notifySuccess('Trận đấu hoàn tất!');
    } catch (err) {
      notifyError('Chỉ admin/trọng tài mới ghi được điểm', errorMessage(err));
    }
  };

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!tournament) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">🔴 Live Score — {tournament.name}</h1>
          <p className="text-xs text-neutral-400">Tự động cập nhật mỗi 5 giây</p>
        </div>
        <Link to={`/tournaments/${id}`} className="btn-ghost text-xs">← Chi tiết giải</Link>
      </div>

      <div className="grid gap-3 sm:grid-cols-2">
        {matches.map((m) => (
          <div key={m.id} className={`card ${m.status === 'live' ? 'border-rose-700' : ''}`}>
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs text-neutral-400">Vòng {m.round} · Trận {m.slot}</span>
              {m.status === 'live' && <span className="animate-pulse rounded-full bg-rose-500/20 px-2 py-0.5 text-xs font-bold text-rose-400">● LIVE</span>}
              {m.status === 'completed' && <span className="rounded-full bg-neutral-700 px-2 py-0.5 text-xs text-neutral-300">Kết thúc</span>}
            </div>

            <div className="flex items-center justify-between gap-2">
              <div className="min-w-0 flex-1">
                <p className="truncate font-semibold">{m.athlete1?.full_name ?? 'Chưa xác định'}</p>
                <p className="truncate font-semibold">{m.athlete2?.full_name ?? 'Chưa xác định'}</p>
              </div>
              <div className="text-center">
                <p className="font-mono text-3xl font-black">
                  <span className={m.score1 > m.score2 ? 'text-emerald-400' : ''}>{m.score1}</span>
                  <span className="mx-1 text-neutral-500">-</span>
                  <span className={m.score2 > m.score1 ? 'text-emerald-400' : ''}>{m.score2}</span>
                </p>
                <p className="text-[10px] text-neutral-500">games</p>
              </div>
              {user?.role === 'admin' && m.status !== 'completed' && (
                <div className="flex flex-col gap-1">
                  <div className="flex gap-1">
                    <button onClick={() => updateScore(m, 1, 1)} className="rounded bg-emerald-600 px-2 py-1 text-xs font-bold">+1</button>
                    <button onClick={() => updateScore(m, 1, -1)} className="rounded bg-neutral-700 px-2 py-1 text-xs">−</button>
                  </div>
                  <div className="flex gap-1">
                    <button onClick={() => updateScore(m, 2, 1)} className="rounded bg-emerald-600 px-2 py-1 text-xs font-bold">+1</button>
                    <button onClick={() => updateScore(m, 2, -1)} className="rounded bg-neutral-700 px-2 py-1 text-xs">−</button>
                  </div>
                </div>
              )}
            </div>
          </div>
        ))}
        {matches.length === 0 && <p className="text-center text-neutral-500">Chưa có trận nào đang thi đấu.</p>}
      </div>
    </div>
  );
}
