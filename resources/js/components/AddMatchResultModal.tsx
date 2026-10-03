import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { api, errorMessage } from '../api';
import { error as notifyError, success as notifySuccess } from '../lib/notification';
import type { Athlete } from '../types';

interface Props {
  onClose: () => void;
  presetAthlete1?: number;
}

/**
 * AddMatchResultModal — ghi kết quả trận đấu giữa 2 VĐV (có W.O.),
 * backend tự cập nhật thắng/thua + điểm Elo (K-factor 32).
 */
export default function AddMatchResultModal({ onClose, presetAthlete1 }: Props) {
  const [query1, setQuery1] = useState('');
  const [query2, setQuery2] = useState('');
  const [options1, setOptions1] = useState<Athlete[]>([]);
  const [options2, setOptions2] = useState<Athlete[]>([]);
  const [player1, setPlayer1] = useState<Athlete | null>(null);
  const [player2, setPlayer2] = useState<Athlete | null>(null);
  const [score1, setScore1] = useState(2);
  const [score2, setScore2] = useState(0);
  const [walkover, setWalkover] = useState(false);
  const [busy, setBusy] = useState(false);

  // Tìm VĐV theo tên (debounce)
  useEffect(() => {
    if (presetAthlete1) {
      api.get<{ data: Athlete }>(`/athletes/${presetAthlete1}`).then((r) => setPlayer1(r.data));
    }
  }, [presetAthlete1]);

  const search = useMemo(
    () => (query: string, setter: (v: Athlete[]) => void) => {
      if (query.trim().length < 2) { setter([]); return; }
      const t = setTimeout(() => {
        api.get<PaginatedAthletes>('/athletes', { params: { q: query, per_page: 5 } })
          .then((r) => setter(r.data.data))
          .catch(() => {});
      }, 300);
      return () => clearTimeout(t);
    },
    []
  );

  useEffect(() => search(query1, setOptions1), [query1, search]);
  useEffect(() => search(query2, setOptions2), [query2, search]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!player1 || !player2) {
      notifyError('Thiếu thông tin', 'Hãy chọn đủ hai vận động viên.');
      return;
    }
    setBusy(true);
    try {
      const res = await api.post<{ message: string }>('/matches', {
        athlete1_id: player1.id,
        athlete2_id: player2.id,
        score1: walkover ? (score1 >= score2 ? 1 : 0) : score1,
        score2: walkover ? (score1 >= score2 ? 0 : 1) : score2,
        walkover,
      });
      notifySuccess('Đã ghi nhận kết quả trận đấu!', res.data.message);
      onClose();
    } catch (err) {
      notifyError('Không lưu được trận đấu', errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const picker = (
    label: string,
    query: string,
    setQuery: (v: string) => void,
    options: Athlete[],
    player: Athlete | null,
    setPlayer: (a: Athlete | null) => void
  ) => (
    <div>
      <label className="label">{label}</label>
      {player ? (
        <div className="flex items-center justify-between rounded-lg border border-emerald-700 bg-emerald-500/10 px-3 py-2">
          <span className="text-sm font-semibold">{player.full_name} <span className="text-xs text-neutral-400">(Elo {player.elo_rating})</span></span>
          <button type="button" onClick={() => setPlayer(null)} className="text-xs text-rose-400 hover:underline">Đổi</button>
        </div>
      ) : (
        <>
          <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="🔍 Tìm VĐV…" className="w-full" />
          <div className="mt-1 max-h-40 space-y-1 overflow-y-auto">
            {options.map((a) => (
              <button
                key={a.id}
                type="button"
                onClick={() => { setPlayer(a); setQuery(''); }}
                className="block w-full rounded-lg border border-neutral-800 px-3 py-2 text-left text-sm hover:border-emerald-600"
              >
                {a.full_name} <span className="text-xs text-neutral-500">· Elo {a.elo_rating} · {a.country_code}</span>
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" onClick={onClose}>
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        className="absolute inset-0 bg-black/60"
      />
      <motion.div
        initial={{ y: 120, opacity: 0, scale: 0.96 }}
        animate={{ y: 0, opacity: 1, scale: 1 }}
        exit={{ y: 120, opacity: 0 }}
        transition={{ type: 'spring', damping: 26, stiffness: 300 }}
        onClick={(e) => e.stopPropagation()}
        className="card relative z-10 max-h-[90vh] w-full max-w-lg overflow-y-auto sm:m-4"
      >
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-lg font-bold">🏸 Ghi kết quả trận đấu</h2>
          <button onClick={onClose} className="text-neutral-500 hover:text-neutral-300">✕</button>
        </div>

        <form onSubmit={submit} className="space-y-4">
          {picker('Vận động viên 1', query1, setQuery1, options1, player1, setPlayer1)}
          {picker('Vận động viên 2', query2, setQuery2, options2, player2, setPlayer2)}

          <label className="flex items-center gap-2 text-sm text-neutral-300">
            <input type="checkbox" checked={walkover} onChange={(e) => setWalkover(e.target.checked)} className="accent-emerald-500" />
            Trận thắng bỏ cuộc (W.O.)
          </label>

          {!walkover && (
            <div className="flex items-center gap-4">
              <div>
                <label className="label">Game {player1?.full_name ?? 'VĐV 1'}</label>
                <input type="number" min={0} max={3} value={score1} onChange={(e) => setScore1(Number(e.target.value))} className="w-20 text-center" />
              </div>
              <span className="mt-5 font-bold text-neutral-500">—</span>
              <div>
                <label className="label">Game {player2?.full_name ?? 'VĐV 2'}</label>
                <input type="number" min={0} max={3} value={score2} onChange={(e) => setScore2(Number(e.target.value))} className="w-20 text-center" />
              </div>
              <p className="mt-5 text-xs text-neutral-500">Bo3 — thắng 2/3 game</p>
            </div>
          )}

          <button className="btn-primary w-full" disabled={busy || !player1 || !player2}>
            {busy ? 'Đang lưu…' : '💾 Ghi nhận kết quả (cập nhật Elo)'}
          </button>
        </form>
      </motion.div>
    </div>
  );
}

type PaginatedAthletes = { data: Athlete[]; meta: { total: number } };
