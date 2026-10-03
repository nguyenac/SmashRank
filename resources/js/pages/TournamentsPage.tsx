import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, errorMessage } from '../api';
import type { Tournament } from '../types';
import { LEVEL_VI } from '../types';

/** Danh sách giải đấu + tìm kiếm theo tên/hiệp hội/cấp độ. */
export function TournamentsPage() {
  const [q, setQ] = useState('');
  const [level, setLevel] = useState('');
  const [association, setAssociation] = useState('');
  const [tournaments, setTournaments] = useState<Tournament[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: Tournament[] }>('/tournaments', { params: { q, level, association, per_page: 50 } })
      .then((res) => setTournaments(res.data))
      .catch((err) => setError(errorMessage(err)));
  }, [q, level, association]);

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Giải đấu</h1>
      <div className="card grid gap-3 sm:grid-cols-3">
        <input placeholder="🔍 Tên giải / hiệp hội…" value={q} onChange={(e) => setQ(e.target.value)} />
        <select value={level} onChange={(e) => setLevel(e.target.value)}>
          <option value="">Mọi cấp độ</option>
          {Object.entries(LEVEL_VI).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <input placeholder="Hiệp hội tay vợt…" value={association} onChange={(e) => setAssociation(e.target.value)} />
      </div>
      {error && <p className="text-sm text-rose-400">{error}</p>}

      <div className="grid gap-3 sm:grid-cols-2">
        {tournaments.map((t) => (
          <Link key={t.id} to={`/tournaments/${t.id}`} className="card transition hover:border-emerald-600">
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="font-bold">{t.name}</p>
                <p className="text-xs text-neutral-400">
                  {LEVEL_VI[t.level]} · {t.host_country ?? '—'}
                  {t.is_asian_games && ' · Asian Games'}
                  {t.is_team_event && ' · Đồng đội'}
                </p>
              </div>
              {/* Chỉ hiển thị nút Live Score màu đỏ khi giải có tỷ số real-time */}
              {t.has_live_scores && <span className="rounded-full bg-rose-500/20 px-3 py-1 text-xs font-bold text-rose-400">● LIVE</span>}
            </div>
            <p className="mt-2 text-xs text-neutral-500">
              {t.start_date ? `${t.start_date} → ${t.end_date ?? '?'}` : 'Chưa có lịch'}
            </p>
          </Link>
        ))}
      </div>
    </div>
  );
}

/**
 * Trang chi tiết giải đấu.
 * Nút "Live Score" chỉ hiện khi has_live_scores = true. Nếu người dùng vào
 * trực tiếp trang /tournaments/:id/live của giải KHÔNG có real-time,
 * hệ thống TỰ ĐỘNG CHUYỂN HƯỚNG về trang chi tiết giải đấu.
 */
export function TournamentDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [t, setT] = useState<Tournament | null>(null);
  const [association, setAssociation] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: Tournament }>(`/tournaments/${id}`, { params: { association } })
      .then((res) => setT(res.data.data))
      .catch((err) => setError(errorMessage(err)));
  }, [id, association]);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!t) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  return (
    <div className="space-y-4">
      <div className="card flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t.name}</h1>
          <p className="text-sm text-neutral-400">
            {LEVEL_VI[t.level]} · {t.association ?? '—'} · {t.start_date ?? '?'} → {t.end_date ?? '?'}
          </p>
          {t.start_date && <CountdownWidget startDate={t.start_date} />}
        </div>
        <div className="flex flex-wrap gap-2">
          {t.start_date && (
            <>
              <a
                className="btn-ghost text-xs"
                href={`https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(t.name)}&dates=${t.start_date.replace(/-/g, '')}/${(t.end_date ?? t.start_date).replace(/-/g, '')}&details=${encodeURIComponent('Giải đấu trên SmashRank')}`}
                target="_blank" rel="noreferrer"
              >
                📅 Thêm vào Google Calendar
              </a>
              <TournamentChecklist tournamentName={t.name} startDate={t.start_date} />
            </>
          )}
        </div>
        {t.has_live_scores ? (
          <button onClick={() => navigate(`/tournaments/${t.id}/live`)} className="btn-primary !bg-rose-500 !text-white hover:!bg-rose-400">
            🔴 Live Score
          </button>
        ) : (
          <span className="text-xs text-neutral-500">Giải không có tỷ số trực tiếp</span>
        )}
      </div>

      <div className="card">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-semibold">Kết quả / VĐV tham gia</h2>
          <div className="flex items-center gap-2">
            <input
              placeholder="Tìm theo hiệp hội tay vợt…"
              value={association}
              onChange={(e) => setAssociation(e.target.value)}
              className="text-xs"
            />
          </div>
        </div>
        {(t.winners ?? []).length === 0
          ? <p className="text-sm text-neutral-500">Chưa có kết quả.</p>
          : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
                  <th className="p-2">VĐV</th><th className="p-2">Nội dung</th><th className="p-2">Kết quả</th><th className="p-2">Ngày</th>
                </tr>
              </thead>
              <tbody>
                {(t.winners ?? []).map((w, i) => (
                  <tr key={i} className="border-b border-neutral-800/60">
                    <td className="p-2"><Link to={`/athletes/${w.athlete_id}`} className="font-semibold hover:text-emerald-400">{w.athlete_name}</Link></td>
                    <td className="p-2">{w.category}</td>
                    <td className="p-2">
                      <span className={`rounded-full px-2 py-0.5 text-xs ${w.placement === 'champion' ? 'bg-amber-500/20 text-amber-400' : 'bg-neutral-700 text-neutral-300'}`}>
                        {w.placement === 'champion' ? '🏆 Vô địch' : '🥈 Á quân'}
                      </span>
                    </td>
                    <td className="p-2 text-neutral-400">{w.achieved_at ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
      </div>
    </div>
  );
}

/** Widget đếm ngược đến ngày khai mạc giải. */
function CountdownWidget({ startDate }: { startDate: string }) {
  const [remaining, setRemaining] = useState('');

  useEffect(() => {
    const tick = () => {
      const diff = new Date(startDate + 'T08:00:00').getTime() - Date.now();
      if (diff <= 0) { setRemaining('Đang diễn ra!'); return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      setRemaining(d > 0 ? `${d} ngày ${h} giờ` : `${h} giờ ${Math.floor((diff % 3600000) / 60000)} phút`);
    };
    tick();
    const timer = setInterval(tick, 60000);
    return () => clearInterval(timer);
  }, [startDate]);

  return <p className="mt-1 text-sm font-semibold text-amber-400">⏳ Còn {remaining} đến khai mạc</p>;
}

/**
 * Checklist "Chế độ thi đấu": vật dụng cần mang (vợt, giày, nước, khăn...)
 * lưu localStorage; gửi Web Notification nhắc 1 giờ trước giờ thi đấu khi app mở.
 */
function TournamentChecklist({ tournamentName, startDate }: { tournamentName: string; startDate: string }) {
  const ITEMS = ['Vợt', 'Giày', 'Nước uống', 'Khăn', 'Quấn cán dự phòng', 'Đồng phục'];
  const [open, setOpen] = useState(false);
  const [checked, setChecked] = useState<string[]>(() => {
    try { return JSON.parse(localStorage.getItem(`checklist_${tournamentName}`) ?? '[]'); } catch { return []; }
  });

  const toggle = (item: string) => {
    const next = checked.includes(item) ? checked.filter((c) => c !== item) : [...checked, item];
    setChecked(next);
    localStorage.setItem(`checklist_${tournamentName}`, JSON.stringify(next));
  };

  const remind = async () => {
    if (!('Notification' in window)) { alert('Trình duyệt không hỗ trợ thông báo.'); return; }
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') return;
    // Nhắc khi còn 1 giờ (nếu app/tab còn mở)
    const diff = new Date(startDate + 'T08:00:00').getTime() - Date.now() - 3600000;
    if (diff > 0) {
      setTimeout(() => new Notification('SmashRank — Sắp thi đấu!', {
        body: `Giải "${tournamentName}" bắt đầu sau 1 giờ. Checklist: ${ITEMS.filter((i) => !checked.includes(i)).join(', ') || 'đủ đồ!'}`,
      }), diff);
      alert('Đã bật nhắc nhở 1 giờ trước giờ thi đấu (cần tab mở).');
    } else alert('Giải đã gần diễn ra — kiểm tra checklist ngay!');
  };

  return (
    <>
      <button onClick={() => setOpen(!open)} className="btn-ghost text-xs">✅ Checklist thi đấu ({checked.length}/{ITEMS.length})</button>
      <button onClick={remind} className="btn-ghost text-xs" title="Nhắc 1 giờ trước giờ thi đấu">⏰ Nhắc trước 1h</button>
      {open && (
        <div className="card mt-2 w-full sm:w-auto">
          {ITEMS.map((item) => (
            <label key={item} className="flex cursor-pointer items-center gap-2 py-1 text-sm">
              <input type="checkbox" checked={checked.includes(item)} onChange={() => toggle(item)} className="accent-emerald-500" />
              {item}
            </label>
          ))}
        </div>
      )}
    </>
  );
}

/** Trang Live Score: giải không có real-time → tự động redirect về chi tiết giải đấu. */
export function TournamentLiveRedirect() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  useEffect(() => {
    api.get<{ data: Tournament }>(`/tournaments/${id}`)
      .then((res) => {
        if (!res.data.data.has_live_scores) {
          navigate(`/tournaments/${id}`, { replace: true }); // ← tự động chuyển hướng
        }
      })
      .catch(() => navigate('/tournaments', { replace: true }));
  }, [id, navigate]);

  return <p className="py-10 text-center text-neutral-400">Giải này không có tỷ số trực tiếp. Đang chuyển về trang chi tiết giải đấu…</p>;
}
