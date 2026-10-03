import { useEffect, useState } from 'react';
import { api } from '../api';
import { error as notifyError, success as notifySuccess } from '../lib/notification';
import MatchTimer from '../components/MatchTimer';
import type { Athlete, CourtSuggestion } from '../types';

/**
 * Matchmaking:
 *  - Đơn: tìm đối thủ có Elo gần nhất
 *  - Team Match 2v2: ghép đội và tính hệ số sức mạnh tổng hợp của đội
 *    (trung bình Elo của 2 thành viên + bonus cân bằng 5% nếu chênh lệch ≤ 100)
 */
export default function MatchmakingPage() {
  const [mode, setMode] = useState<'singles' | 'team'>('singles');
  const [athletes, setAthletes] = useState<Athlete[]>([]);
  const [myElo, setMyElo] = useState(1200);
  const [team1, setTeam1] = useState<Athlete | null>(null);
  const [team2, setTeam2] = useState<Athlete | null>(null);
  const [opponent1, setOpponent1] = useState<Athlete | null>(null);
  const [opponent2, setOpponent2] = useState<Athlete | null>(null);

  useEffect(() => {
    api.get<{ data: Athlete[] }>('/athletes', { params: { per_page: 100, sort: 'elo' } })
      .then((r) => setAthletes(r.data));
  }, []);

  const strength = (a: Athlete | null, b: Athlete | null): number => {
    if (!a || !b) return 0;
    const avg = (a.elo_rating + b.elo_rating) / 2;
    const balanceBonus = Math.abs(a.elo_rating - b.elo_rating) <= 100 ? avg * 0.05 : 0;
    return Math.round(avg + balanceBonus);
  };

  const singlesMatches = athletes
    .filter((a) => Math.abs(a.elo_rating - myElo) <= 150)
    .slice(0, 8);

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">🤝 Matchmaking — Ghép cặp thi đấu</h1>

      {/* Smart Court Scheduler + Match Timer (thi đấu giao lưu) */}
      <SmartCourtScheduler />
      <MatchTimer />

      <div className="flex gap-2">
        {([['singles', '🎾 Đơn (1v1)'], ['team', '👥 Đồng đội (2v2)']] as const).map(([v, l]) => (
          <button
            key={v}
            onClick={() => setMode(v)}
            className={`rounded-lg px-4 py-2 text-sm font-semibold ${mode === v ? 'bg-emerald-500 text-neutral-950' : 'border border-neutral-700 text-neutral-300'}`}
          >
            {l}
          </button>
        ))}
      </div>

      {mode === 'singles' ? (
        <div className="card">
          <h2 className="mb-1 font-semibold">Đối thủ tương đương (Elo chênh ≤ 150)</h2>
          <div className="mb-4 flex items-center gap-2">
            <label className="text-sm text-neutral-400">Elo của bạn:</label>
            <input type="number" value={myElo} onChange={(e) => setMyElo(Number(e.target.value))} className="w-24 text-center" />
          </div>
          <div className="grid gap-2 sm:grid-cols-2">
            {singlesMatches.map((a) => (
              <div key={a.id} className="flex items-center justify-between rounded-lg border border-neutral-800 px-3 py-2">
                <span className="text-sm font-semibold">{a.full_name}</span>
                <span className="font-mono text-sm text-emerald-400">Elo {a.elo_rating}</span>
              </div>
            ))}
            {singlesMatches.length === 0 && <p className="text-sm text-neutral-500">Không có đối thủ trong khoảng Elo này.</p>}
          </div>
        </div>
      ) : (
        <div className="card space-y-4">
          <h2 className="font-semibold">Team Match 2v2 — Hệ số sức mạnh tổng hợp</h2>
          <PlayerPicker label="Đội 1 — VĐV 1" athletes={athletes} value={team1} onChange={setTeam1} />
          <PlayerPicker label="Đội 1 — VĐV 2" athletes={athletes} value={team2} onChange={setTeam2} />
          <PlayerPicker label="Đội 2 — VĐV 1" athletes={athletes} value={opponent1} onChange={setOpponent1} />
          <PlayerPicker label="Đội 2 — VĐV 2" athletes={athletes} value={opponent2} onChange={setOpponent2} />

          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-xl border border-emerald-800 bg-emerald-500/5 p-4 text-center">
              <p className="text-xs uppercase text-neutral-400">Sức mạnh Đội 1</p>
              <p className="text-3xl font-black text-emerald-400">{strength(team1, team2)}</p>
              <p className="text-xs text-neutral-500">{team1?.full_name ?? '?'} + {team2?.full_name ?? '?'}</p>
            </div>
            <div className="rounded-xl border border-amber-800 bg-amber-500/5 p-4 text-center">
              <p className="text-xs uppercase text-neutral-400">Sức mạnh Đội 2</p>
              <p className="text-3xl font-black text-amber-400">{strength(opponent1, opponent2)}</p>
              <p className="text-xs text-neutral-500">{opponent1?.full_name ?? '?'} + {opponent2?.full_name ?? '?'}</p>
            </div>
          </div>

          {strength(team1, team2) > 0 && strength(opponent1, opponent2) > 0 && (
            <button
              className="btn-primary w-full"
              onClick={() => {
                const diff = Math.abs(strength(team1, team2) - strength(opponent1, opponent2));
                if (diff <= 100) notifySuccess('Cặp đấu cân bằng!', `Chênh lệch sức mạnh chỉ ${diff} điểm — trận đấu sẽ rất hấp dẫn.`);
                else notifyError('Cặp đấu lệch sức mạnh', `Chênh lệch ${diff} điểm — nên ghép lại cho cân bằng.`);
              }}
            >
              ⚖️ Kiểm tra độ cân bằng cặp đấu
            </button>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Smart Court Scheduler — nút "Suggest Slot" phân tích dữ liệu đặt sân 8 tuần
 * để gợi ý khung giờ trống tối ưu cho trận giao lưu mới.
 */
function SmartCourtScheduler() {
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [result, setResult] = useState<CourtSuggestion | null>(null);
  const [busy, setBusy] = useState(false);

  const suggest = async () => {
    setBusy(true);
    try {
      const res = await api.get<{ data: CourtSuggestion }>('/courts/suggest', { params: { date } });
      setResult(res.data);
    } catch (err) {
      notifyError('Lỗi gợi ý khung giờ', String(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="card">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 className="font-semibold">🏟️ Smart Court Scheduler</h2>
        <div className="flex items-center gap-2">
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="text-xs" />
          <button onClick={suggest} disabled={busy} className="btn-primary text-xs">
            {busy ? 'Đang phân tích…' : '💡 Suggest Slot'}
          </button>
        </div>
      </div>
      {result && (
        <>
          <p className="mb-2 text-xs text-neutral-400">{result.weekday} — {result.note}</p>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {result.suggestions.map((s) => (
              <div key={s.court.id} className="rounded-xl border border-neutral-800 p-3">
                <p className="text-sm font-semibold">{s.court.name}</p>
                {s.best_slots.map((slot) => (
                  <p key={slot.hour} className="mt-1 text-xs">
                    <span className="font-mono text-emerald-400">{slot.hour}</span>
                    {slot.prime_time && <span className="ml-1 text-amber-400">giờ vàng</span>}
                    <span className="text-neutral-500"> · đông độ {slot.busy_score}</span>
                  </p>
                ))}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

function PlayerPicker({
  label, athletes, value, onChange,
}: { label: string; athletes: Athlete[]; value: Athlete | null; onChange: (a: Athlete | null) => void }) {
  return (
    <div>
      <label className="label">{label}</label>
      <select value={value?.id ?? ''} onChange={(e) => onChange(athletes.find((a) => a.id === Number(e.target.value)) ?? null)} className="w-full">
        <option value="">— Chọn VĐV —</option>
        {athletes.map((a) => <option key={a.id} value={a.id}>{a.full_name} (Elo {a.elo_rating})</option>)}
      </select>
    </div>
  );
}
