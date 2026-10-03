import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import type { Athlete, BirthdayEntry } from '../types';
import { CATEGORY_VI } from '../types';

const FLAG: Record<string, string> = {
  VNM: '🇻🇳', DEN: '🇩🇰', THA: '🇹🇭', SGP: '🇸🇬', KOR: '🇰🇷', CHN: '🇨🇳',
  TPE: '🇹🇼', ESP: '🇪🇸', IDN: '🇮🇩', MAS: '🇲🇾', JPN: '🇯🇵', IND: '🇮🇳',
};

/** Trang chủ: hero + "Sinh nhật hôm nay" + top 3 podium + liên kết nhanh. */
export default function HomePage() {
  const [birthdays, setBirthdays] = useState<BirthdayEntry[]>([]);
  const [selectedDate, setSelectedDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [top, setTop] = useState<Athlete[]>([]);

  useEffect(() => {
    api.get<{ data: { athletes: BirthdayEntry[] } }>('/birthdays', { params: { date: selectedDate } })
      .then((res) => setBirthdays(res.data.data.athletes));
  }, [selectedDate]);

  useEffect(() => {
    api.get<{ data: Athlete[] }>('/athletes', { params: { per_page: 3, sort: 'elo' } })
      .then((res) => setTop(res.data.data));
  }, []);

  const MEDALS = ['🥇', '🥈', '🥉'];

  return (
    <div className="space-y-6">
      {/* Hero */}
      <section className="card bg-gradient-to-br from-emerald-900/40 via-surface-900 to-surface-900 !p-8">
        <h1 className="text-3xl font-black sm:text-4xl">
          <span className="text-emerald-400">Smash</span>Rank
        </h1>
        <p className="mt-2 max-w-2xl text-neutral-300">
          Bảng xếp hạng BWF World Tour & Elo phong trào · Hồ sơ vận động viên · Kỷ lục · Giải đấu · Trang thiết bị.
        </p>
        <div className="mt-4 flex flex-wrap gap-2">
          <Link to="/rankings" className="btn-primary">Xem bảng xếp hạng</Link>
          <Link to="/records" className="btn-ghost">Danh sách Kỷ lục</Link>
          <Link to="/tournaments" className="btn-ghost">Giải đấu</Link>
          <Link to="/products" className="btn-ghost">Vợt & Giày</Link>
        </div>
      </section>

      {/* Podium top 3 */}
      <section>
        <h2 className="mb-3 font-bold">Top 3 Elo phong trào</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          {top.map((a, i) => (
            <Link key={a.id} to={`/athletes/${a.id}`} className="card text-center transition hover:border-emerald-600">
              <p className="text-4xl">{MEDALS[i]}</p>
              <p className="mt-2 text-2xl">{FLAG[a.country_code] ?? '🏳️'}</p>
              <p className="mt-1 font-bold">{a.full_name}</p>
              <p className="text-xs text-neutral-400">{CATEGORY_VI[a.category]}</p>
              <p className="mt-2 font-mono text-xl font-bold text-emerald-400">{a.elo_rating}</p>
            </Link>
          ))}
        </div>
      </section>

      {/* Sinh nhật hôm nay + tra cứu theo ngày */}
      <section className="card">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
          <h2 className="font-bold">🎂 Sinh nhật hôm nay</h2>
          <div className="flex items-center gap-2">
            <input
              type="date"
              value={selectedDate}
              onChange={(e) => setSelectedDate(e.target.value)}
              className="text-xs"
            />
            <span className="text-xs text-neutral-400">Tra sinh nhật tay vợt theo ngày bất kỳ</span>
          </div>
        </div>
        {birthdays.length === 0 ? (
          <p className="text-sm text-neutral-500">Không có tay vợt nào sinh nhật vào ngày này.</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {birthdays.map((b) => (
              <Link key={b.id} to={`/athletes/${b.id}`}
                className="flex items-center gap-2 rounded-xl border border-emerald-700/50 bg-emerald-500/5 px-3 py-2 transition hover:border-emerald-500">
                <span className="text-xl">{FLAG[b.country_code] ?? '🏳️'}</span>
                <div>
                  <p className="text-sm font-semibold">{b.full_name}</p>
                  <p className="text-xs text-neutral-400">
                    {CATEGORY_VI[b.category]} · {b.turning_age} tuổi · 🎉
                  </p>
                </div>
              </Link>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
