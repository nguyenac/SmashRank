import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api';
import type { Athlete, LeaderboardRow, Paginated } from '../types';
import { CATEGORY_VI, SKILL_LEVEL_VI } from '../types';

const CATEGORIES = ['MS', 'WS', 'MD', 'WD', 'XD'] as const;

const FLAG: Record<string, string> = {
  VNM: '🇻🇳', DEN: '🇩🇰', THA: '🇹🇭', SGP: '🇸🇬', KOR: '🇰🇷', CHN: '🇨🇳',
  TPE: '🇹🇼', ESP: '🇪🇸', IDN: '🇮🇩', MAS: '🇲🇾', JPN: '🇯🇵', IND: '🇮🇳',
};

const FAVORITES_KEY = 'smashrank_favorites';

function loadFavorites(): number[] {
  try {
    return JSON.parse(localStorage.getItem(FAVORITES_KEY) ?? '[]');
  } catch {
    return [];
  }
}

export default function Rankings() {
  // Tab leaderboard: tổng (theo Elo) | tuần | tháng
  const [period, setPeriod] = useState<'all' | 'weekly' | 'monthly'>('all');
  const [board, setBoard] = useState<LeaderboardRow[]>([]);

  // Bộ lọc nâng cao (chế độ bảng xếp hạng tổng)
  const [q, setQ] = useState('');
  const [category, setCategory] = useState('');
  const [skill, setSkill] = useState('');
  const [nationality, setNationality] = useState('');
  const [hand, setHand] = useState('');
  const [ageGroup, setAgeGroup] = useState('');
  const [onlyFavorites, setOnlyFavorites] = useState(false);
  const [favorites, setFavorites] = useState<number[]>(loadFavorites);
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<Paginated<Athlete> | null>(null);
  const [zen, setZen] = useState(() => localStorage.getItem('zen_mode') === '1');
  const [error, setError] = useState('');

  useEffect(() => {
    if (period !== 'all') {
      api.get<{ data: LeaderboardRow[] }>('/leaderboards', { params: { period } })
        .then((res) => setBoard(res.data))
        .catch((err) => setError(errorMessage(err)));
      return;
    }
    api.get<Paginated<Athlete>>('/athletes', {
      params: { q, category, skill_level: skill, nationality, dominant_hand: hand, age_group: ageGroup, page, per_page: 20 },
    })
      .then((res) => setResult(res.data))
      .catch((err) => setError(errorMessage(err)));
  }, [period, q, category, skill, nationality, hand, ageGroup, page]);

  const toggleFavorite = (id: number) => {
    const next = favorites.includes(id) ? favorites.filter((f) => f !== id) : [...favorites, id];
    setFavorites(next);
    localStorage.setItem(FAVORITES_KEY, JSON.stringify(next));
  };

  const toggleZen = () => {
    const next = !zen;
    setZen(next);
    localStorage.setItem('zen_mode', next ? '1' : '0');
  };

  const medal = (i: number) => (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i + 1}`);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">
          {period === 'all' ? 'Bảng xếp hạng tổng' : period === 'weekly' ? 'Bảng xếp hạng tuần' : 'Bảng xếp hạng tháng'}
          {zen && <span className="ml-2 text-xs font-normal text-emerald-400">— Zen Mode</span>}
        </h1>
        <button onClick={toggleZen} className="btn-ghost text-xs">{zen ? 'Thoát Zen Mode' : '🌿 Zen Mode'}</button>
      </div>

      {/* Tabs Tổng / Tuần / Tháng */}
      <div className="flex gap-2">
        {([['all', 'Tổng'], ['weekly', 'Tuần'], ['monthly', 'Tháng']] as const).map(([value, label]) => (
          <button
            key={value}
            onClick={() => { setPeriod(value); setPage(1); }}
            className={`rounded-lg px-4 py-2 text-sm font-semibold ${period === value ? 'bg-emerald-500 text-neutral-950' : 'border border-neutral-700 text-neutral-300 hover:border-emerald-600'}`}
          >
            {label}
          </button>
        ))}
      </div>

      {error && <p className="text-sm text-rose-400">{error}</p>}

      {/* ---------- Weekly / Monthly Leaderboard ---------- */}
      {period !== 'all' && (
        <div className="card overflow-x-auto !p-0">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
                <th className="p-3">Hạng</th><th className="p-3">Vận động viên</th>
                <th className="p-3 text-right">Điểm kỳ này</th><th className="p-3 text-right">Elo hiện tại</th>
                <th className="p-3 text-right">↑↓ So với trước</th>
              </tr>
            </thead>
            <tbody>
              {board.map((row) => (
                <tr key={row.athlete?.id ?? row.rank} className="border-b border-neutral-800/60 hover:bg-surface-800/60">
                  <td className="p-3 font-bold">{medal(row.rank - 1)}</td>
                  <td className="p-3">
                    {row.athlete && (
                      <Link to={`/athletes/${row.athlete.id}`} className="font-semibold hover:text-emerald-400">
                        {FLAG[row.athlete.country_code] ?? '🏳️'} {row.athlete.full_name}
                      </Link>
                    )}
                  </td>
                  <td className="p-3 text-right font-mono font-bold text-amber-400">
                    {row.period_points !== null && row.period_points !== undefined && (row.period_points > 0 ? `+${row.period_points}` : row.period_points)}
                  </td>
                  <td className="p-3 text-right font-mono text-emerald-400">{row.athlete?.elo_rating}</td>
                  <td className="p-3 text-right font-mono">
                    {row.rank_change > 0 && <span className="text-emerald-400">▲ {row.rank_change}</span>}
                    {row.rank_change < 0 && <span className="text-rose-400">▼ {Math.abs(row.rank_change)}</span>}
                    {row.rank_change === 0 && <span className="text-neutral-500">—</span>}
                  </td>
                </tr>
              ))}
              {board.length === 0 && (
                <tr><td colSpan={5} className="p-6 text-center text-neutral-500">Chưa có trận đấu nào trong kỳ này.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* ---------- Bảng xếp hạng tổng ---------- */}
      {period === 'all' && (
        <>
          <div className="card grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <input placeholder="🔍 Tìm kiếm VĐV, CLB…" value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} />
            <select value={category} onChange={(e) => { setCategory(e.target.value); setPage(1); }}>
              <option value="">Tất cả nội dung</option>
              {CATEGORIES.map((c) => <option key={c} value={c}>{c} — {CATEGORY_VI[c]}</option>)}
            </select>
            <select value={skill} onChange={(e) => { setSkill(e.target.value); setPage(1); }}>
              <option value="">Mọi trình độ</option>
              {Object.entries(SKILL_LEVEL_VI).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
            <input placeholder="Quốc tịch (VD: Việt Nam)" value={nationality} onChange={(e) => { setNationality(e.target.value); setPage(1); }} />
            <select value={hand} onChange={(e) => { setHand(e.target.value); setPage(1); }}>
              <option value="">Tay thuận — tất cả</option>
              <option value="right">Tay phải</option>
              <option value="left">Tay trái</option>
            </select>
            <select value={ageGroup} onChange={(e) => { setAgeGroup(e.target.value); setPage(1); }}>
              <option value="">Nhóm tuổi — tất cả</option>
              <option value="U18">U18 (tài năng trẻ)</option>
              <option value="U21">U21</option>
              <option value="Open">Open</option>
            </select>
            <label className="flex items-center gap-2 self-center text-sm text-neutral-300">
              <input type="checkbox" checked={onlyFavorites} onChange={(e) => setOnlyFavorites(e.target.checked)} className="accent-rose-500" />
              ❤️ Chỉ VĐV yêu thích ({favorites.length})
            </label>
            <p className="self-center text-xs text-neutral-400">{result ? `Tổng ${result.meta.total} VĐV` : 'Đang tải…'}</p>
          </div>

          {zen ? (
            <div className="grid gap-3 sm:grid-cols-2">
              {result?.data
                .filter((a) => !onlyFavorites || favorites.includes(a.id))
                .map((a) => (
                <Link key={a.id} to={`/athletes/${a.id}`} className="card flex items-center gap-3 !p-3 transition hover:border-emerald-600">
                  <span className="text-2xl">{FLAG[a.country_code] ?? '🏳️'}</span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold">{a.full_name}</p>
                    <p className="text-xs text-neutral-400">{a.racket ?? '—'}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-mono font-bold text-emerald-400">{a.elo_rating}</p>
                    <p className="text-[10px] text-neutral-500">Elo</p>
                  </div>
                  <button onClick={(e) => { e.preventDefault(); toggleFavorite(a.id); }} className="text-lg">
                    {favorites.includes(a.id) ? '❤️' : '🤍'}
                  </button>
                </Link>
              ))}
            </div>
          ) : (
            <div className="card overflow-x-auto !p-0">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
                    <th className="p-3">#</th><th className="p-3">Vận động viên</th><th className="p-3">Quốc gia</th>
                    <th className="p-3">Nội dung</th><th className="p-3">Trình độ</th>
                    <th className="p-3 text-right">BWF điểm</th><th className="p-3 text-right">Elo</th>
                    <th className="p-3 text-right">Thắng %</th><th className="p-3">❤️</th>
                  </tr>
                </thead>
                <tbody>
                  {result?.data
                    .filter((a) => !onlyFavorites || favorites.includes(a.id))
                    .map((a) => (
                    <tr key={a.id} className="border-b border-neutral-800/60 hover:bg-surface-800/60">
                      <td className="p-3 font-bold">{medal(result.data.indexOf(a))}</td>
                      <td className="p-3">
                        <Link to={`/athletes/${a.id}`} className="font-semibold hover:text-emerald-400">{a.full_name}</Link>
                        {a.verified && <span className="ml-1 text-xs text-emerald-400">✔</span>}
                        {a.racket && <p className="text-xs text-neutral-500">🏸 {a.racket}</p>}
                      </td>
                      <td className="p-3">{FLAG[a.country_code] ?? '🏳️'} {a.nationality}</td>
                      <td className="p-3">{a.category}</td>
                      <td className="p-3">{SKILL_LEVEL_VI[a.skill_level]}</td>
                      <td className="p-3 text-right font-mono">{a.ranking_points.toLocaleString()}</td>
                      <td className="p-3 text-right font-mono font-bold text-emerald-400">{a.elo_rating}</td>
                      <td className="p-3 text-right font-mono">{a.win_rate ?? 0}%</td>
                      <td className="p-3">
                        <button onClick={() => toggleFavorite(a.id)} className="text-lg" title="Yêu thích">
                          {favorites.includes(a.id) ? '❤️' : '🤍'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {result && result.meta.last_page > 1 && (
            <div className="flex items-center justify-center gap-3">
              <button className="btn-ghost" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Trước</button>
              <span className="text-sm text-neutral-400">Trang {result.meta.current_page}/{result.meta.last_page}</span>
              <button className="btn-ghost" disabled={page >= result.meta.last_page} onClick={() => setPage(page + 1)}>Sau →</button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
