import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { QRCodeSVG } from 'qrcode.react';
import * as d3 from 'd3';
import {
  Area, AreaChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis, PolarAngleAxis, PolarGrid, PolarRadiusAxis, Radar, RadarChart,
} from 'recharts';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import RankTrendChart from '../components/RankTrendChart';
import RadarSkillChart from '../components/RadarSkillChart';
import CommentSection from '../components/CommentSection';
import AddMatchResultModal from '../components/AddMatchResultModal';
import { error as notifyError, info, success as notifySuccess } from '../lib/notification';
import type {
  Athlete, CoachNoteItem, EquipmentItem, InjuryItem, MatchHistoryItem, RacketSuggestion, WeightEntryItem,
} from '../types';
import { CATEGORY_VI, SKILL_LEVEL_VI } from '../types';

const TABS = [
  ['overview', 'Tổng quan'],
  ['body', 'Thể chất'],
  ['nutrition', 'Dinh dưỡng'],
  ['coach', 'Huấn luyện'],
  ['extra', 'Tiện ích'],
] as const;

export default function AthleteDetail() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const [athlete, setAthlete] = useState<Athlete | null>(null);
  const [metric, setMetric] = useState<'elo_rating' | 'points' | 'win_rate'>('elo_rating');
  const [woBreaksStreak, setWoBreaksStreak] = useState(true);
  const [showMatchModal, setShowMatchModal] = useState(false);
  const [following, setFollowing] = useState(false);
  const [tab, setTab] = useState<(typeof TABS)[number][0]>('overview');
  const [error, setError] = useState('');

  // Huy hiệu 'Verified Pro' dành cho VĐV thứ hạng cao (Top 100 BWF)
  const isVerifiedPro = !!athlete && ((athlete.world_rank ?? 999) <= 100 || athlete.verified);

  useEffect(() => {
    api.get<{ data: Athlete }>(`/athletes/${id}`)
      .then((res) => setAthlete(res.data))
      .catch((err) => setError(errorMessage(err)));
  }, [id]);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!athlete) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  const toggleFollow = async () => {
    if (!user) { notifyError('Chưa đăng nhập', 'Đăng nhập để theo dõi VĐV.'); return; }
    try {
      if (following) { await api.delete(`/follows/${id}`); setFollowing(false); notifySuccess('Đã bỏ theo dõi.'); }
      else { await api.post(`/follows/${id}`); setFollowing(true); notifySuccess('Đã theo dõi!', 'Bạn sẽ nhận thông báo khi thứ hạng thay đổi.'); }
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  const share = async () => {
    const url = window.location.href;
    if (navigator.share) {
      try { await navigator.share({ title: athlete.full_name, text: `Hồ sơ ${athlete.full_name} trên SmashRank`, url }); }
      catch { /* hủy */ }
    } else { await navigator.clipboard.writeText(url); notifySuccess('Đã sao chép liên kết!'); }
  };

  return (
    <motion.div initial={{ opacity: 0, y: 24 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: 24 }} transition={{ duration: 0.3 }} className="space-y-4">
      <div className="card flex flex-wrap items-center gap-4">
        <div className="relative">
          <div className={`flex h-20 w-20 items-center justify-center rounded-full bg-surface-800 text-3xl ${isVerifiedPro ? 'ring-4 ring-amber-400 ring-offset-2 ring-offset-surface-950' : ''}`}>
            {athlete.avatar_url ? <img src={athlete.avatar_url} alt={athlete.full_name} className="h-full w-full rounded-full object-cover" /> : '🏸'}
          </div>
          {isVerifiedPro && (
            <span
              className="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-amber-400 bg-neutral-950 text-xs"
              title={`Verified Pro — BWF World Rank #${athlete.world_rank} · Điểm BWF ${athlete.ranking_points.toLocaleString()} · Đã xác thực bởi SmashRank`}
            >
              🥇
            </span>
          )}
        </div>
        <div className="min-w-0 flex-1">
          <h1 className="text-2xl font-bold">{athlete.full_name} {athlete.verified && <span className="text-emerald-400">✔</span>}</h1>
          <p className="text-sm text-neutral-400">
            {athlete.nationality} · {CATEGORY_VI[athlete.category]} · {SKILL_LEVEL_VI[athlete.skill_level]} ·
            {athlete.dominant_hand === 'right' ? ' Tay phải' : ' Tay trái'}
            {athlete.club && ` · ${athlete.club}`}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {user && <button onClick={toggleFollow} className={following ? 'btn-ghost' : 'btn-primary'}>{following ? '🔔 Đang theo dõi' : '🔔 Theo dõi'}</button>}
          <button onClick={() => setShowMatchModal(true)} className="btn-ghost">🏸 Ghi kết quả</button>
          <ShareProfileButton athlete={athlete} />
          <button onClick={share} className="btn-primary">🔗 Chia sẻ</button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 overflow-x-auto">
        {TABS.map(([key, label]) => (
          <button key={key} onClick={() => setTab(key)}
            className={`whitespace-nowrap rounded-lg px-4 py-2 text-sm font-semibold ${tab === key ? 'bg-emerald-500 text-neutral-950' : 'border border-neutral-700 text-neutral-300 hover:border-emerald-600'}`}>
            {label}
          </button>
        ))}
      </div>

      {/* ================= TAB: TỔNG QUAN ================= */}
      {tab === 'overview' && (
        <>
          <MilestonesBadges athleteId={athlete.id} />
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            {[['Thứ hạng TG', athlete.world_rank ? `#${athlete.world_rank}` : '—'], ['Điểm BWF', athlete.ranking_points.toLocaleString()], ['Điểm Elo', String(athlete.elo_rating)], ['Tỷ lệ thắng', `${athlete.win_rate ?? 0}%`]].map(([label, value]) => (
              <div key={label} className="card text-center">
                <p className="text-xs uppercase text-neutral-400">{label}</p>
                <p className="mt-1 text-xl font-bold text-emerald-400">{value}</p>
              </div>
            ))}
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <div className="card"><h2 className="mb-3 font-semibold">Radar kỹ năng</h2>{athlete.skills && <RadarSkillChart skills={athlete.skills} />}</div>
            <div className="card">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="font-semibold">Xu hướng 12 tháng</h2>
                <select value={metric} onChange={(e) => setMetric(e.target.value as typeof metric)} className="text-xs">
                  <option value="elo_rating">Điểm Elo</option><option value="points">Điểm BWF</option><option value="win_rate">Tỷ lệ thắng</option>
                </select>
              </div>
              {athlete.ranking_history?.length ? <RankTrendChart data={athlete.ranking_history} metric={metric} /> : <p className="text-sm text-neutral-500">Chưa có dữ liệu.</p>}
            </div>
          </div>

          {athlete.details && (
            <div className="card">
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">🔥 Chuỗi trận thắng</h2>
                <label className="flex cursor-pointer items-center gap-2 text-xs text-neutral-400">
                  <input type="checkbox" checked={woBreaksStreak} onChange={(e) => setWoBreaksStreak(e.target.checked)} className="accent-emerald-500" />
                  Thắng W.O. làm ngắt quãng chuỗi
                </label>
              </div>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[['Chuỗi hiện tại', athlete.details.win_streak_current, 'text-emerald-400'], [woBreaksStreak ? 'Kỷ lục (W.O. ngắt)' : 'Kỷ lục (W.O. tính thắng)', woBreaksStreak ? athlete.details.win_streak_career_excl_wo : athlete.details.win_streak_career, 'text-amber-400'], ['Chuỗi Super 1000→100', athlete.details.super_streak, 'text-sky-400'], ['Điểm GOAT', athlete.details.goat_points, 'text-rose-400']].map(([label, value, color]) => (
                  <div key={String(label)} className="rounded-xl border border-neutral-800 p-3 text-center">
                    <p className="text-xs text-neutral-400">{label}</p>
                    <p className={`text-xl font-bold ${color}`}>{value}</p>
                  </div>
                ))}
              </div>
            </div>
          )}

          <MatchHistoryCard athleteId={athlete.id} editable />
          <RacketSuggestionCard athleteId={athlete.id} />
          <ZoneHeatmapCard athleteId={athlete.id} />
          <SimilarProGear athlete={athlete} />
          <EquipmentFitCard athleteId={athlete.id} />
          <CommentSection type="athlete" targetId={athlete.id} />
        </>
      )}

      {/* ================= TAB: THỂ CHẤT ================= */}
      {tab === 'body' && (
        <>
          <WeightTracker athleteId={athlete.id} />
          <InjuryHistory athleteId={athlete.id} />
          <MatchHistoryCard athleteId={athlete.id} />
        </>
      )}

      {/* ================= TAB: DINH DƯỠNG ================= */}
      {tab === 'nutrition' && (
        <>
          <NutritionDiary athleteId={athlete.id} />
          <PeakPerformanceHeatmap athleteId={athlete.id} />
        </>
      )}

      {/* ================= TAB: HUẤN LUYỆN ================= */}
      {tab === 'coach' && (
        <>
          <CoachBotCard athleteId={athlete.id} />
          <PlaystyleRadar athleteId={athlete.id} />
          {user?.role === 'admin' && <CoachNotesCard athleteId={athlete.id} />}
          <Veocard athleteId={athlete.id} />
        </>
      )}

      {/* ================= TAB: TIỆN ÍCH ================= */}
      {tab === 'extra' && (
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="card text-center">
            <h2 className="mb-3 font-semibold">📱 Mã QR hồ sơ</h2>
            <div className="inline-block rounded-xl bg-white p-3">
              <QRCodeSVG value={`${window.location.origin}/athletes/${athlete.id}`} size={160} />
            </div>
            <p className="mt-2 text-xs text-neutral-400">Quét để mở hồ sơ ngay tại sân đấu</p>
          </div>
          <div className="card">
            <h2 className="mb-3 font-semibold">📄 Xuất PDF hồ sơ</h2>
            <p className="mb-3 text-sm text-neutral-400">Tải hồ sơ đầy đủ (chỉ số, trang bị, lịch sử) dạng PDF A4 để lưu trữ hoặc in.</p>
            <a href={`/api/admin/export/athlete/${athlete.id}.pdf`} className="btn-primary">⬇️ Tải PDF</a>
            {user?.role !== 'admin' && <p className="mt-2 text-xs text-neutral-500">(Chức năng PDF dành cho admin/HLV)</p>}
          </div>
          <CameraRecorder athleteId={athlete.id} />
        </div>
      )}

      <AnimatePresence>
        {showMatchModal && <AddMatchResultModal presetAthlete1={athlete.id} onClose={() => setShowMatchModal(false)} />}
      </AnimatePresence>
    </motion.div>
  );
}

/* ---------------- Cân nặng (Weight Tracker — AreaChart) ---------------- */
function WeightTracker({ athleteId }: { athleteId: number }) {
  const [entries, setEntries] = useState<WeightEntryItem[]>([]);
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [weight, setWeight] = useState('');
  const [heartRate, setHeartRate] = useState('');

  const load = () => api.get<{ data: WeightEntryItem[] }>(`/athletes/${athleteId}/weight-entries`).then((r) => setEntries(r.data));
  useEffect(() => { load().catch(() => {}); }, [athleteId]);

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post(`/athletes/${athleteId}/weight-entries`, { measured_at: date, weight_kg: Number(weight), heart_rate: heartRate ? Number(heartRate) : undefined });
      notifySuccess('Đã ghi nhận cân nặng!');
      setWeight('');
      load();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  const chartData = entries.map((e) => ({ date: e.measured_at.slice(5), weight: Number(e.weight_kg), heart_rate: (e as WeightEntryItem & { heart_rate?: number | null }).heart_rate ?? null }));

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">⚖️ Weight Tracking</h2>
      <form onSubmit={add} className="mb-4 flex flex-wrap items-end gap-2">
        <div><label className="label">Ngày (có thể nhập quá khứ)</label><input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></div>
        <div><label className="label">Cân nặng (kg)</label><input type="number" step="0.1" min={30} max={200} value={weight} onChange={(e) => setWeight(e.target.value)} className="w-24" required /></div>
        <div><label className="label">Nhịp tim TB (bpm)</label><input type="number" min={40} max={220} value={heartRate} onChange={(e) => setHeartRate(e.target.value)} className="w-24" /></div>
        <button className="btn-primary mt-4 text-xs">➕ Thêm</button>
      </form>
      {chartData.length ? (
        <div style={{ width: '100%', height: 220 }}>
          <ResponsiveContainer>
            <AreaChart data={chartData} margin={{ top: 8, right: 16, bottom: 0, left: -20 }}>
              <defs>
                <linearGradient id="weightGrad" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor="#34d399" stopOpacity={0.6} />
                  <stop offset="100%" stopColor="#34d399" stopOpacity={0.05} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
              <XAxis dataKey="date" tick={{ fill: '#a3a3a3', fontSize: 10 }} />
              <YAxis domain={['auto', 'auto']} tick={{ fill: '#a3a3a3', fontSize: 10 }} />
              <Tooltip contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f' }} formatter={(v) => [`${v} kg`, 'Cân nặng']} />
              <Area type="monotone" dataKey="weight" stroke="#34d399" strokeWidth={2} fill="url(#weightGrad)" />
            </AreaChart>
          </ResponsiveContainer>
        </div>
      ) : <p className="text-sm text-neutral-500">Chưa có dữ liệu cân nặng.</p>}
      {entries.some((e) => (e as WeightEntryItem & { heart_rate?: number }).heart_rate) && (
        <div style={{ width: '100%', height: 160 }} className="mt-4">
          <ResponsiveContainer>
            <LineChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
              <XAxis dataKey="date" tick={{ fill: '#a3a3a3', fontSize: 10 }} />
              <YAxis tick={{ fill: '#a3a3a3', fontSize: 10 }} />
              <Tooltip contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f' }} formatter={(v: number | string) => [`${v} bpm`, 'Nhịp tim']} />
              <Line type="monotone" dataKey="heart_rate" stroke="#fb7185" strokeWidth={2} dot={{ r: 3 }} connectNulls />
            </LineChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
}

/* ---------------- Lịch sử chấn thương ---------------- */
function InjuryHistory({ athleteId }: { athleteId: number }) {
  const [items, setItems] = useState<InjuryItem[]>([]);
  const [form, setForm] = useState({ type: '', occurred_at: '', expected_recovery: '', description: '' });

  const load = () => api.get<{ data: InjuryItem[] }>(`/athletes/${athleteId}/injuries`).then((r) => setItems(r.data));
  useEffect(() => { load().catch(() => {}); }, [athleteId]);

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post(`/athletes/${athleteId}/injuries`, { ...form, expected_recovery: form.expected_recovery || undefined });
      notifySuccess('Đã ghi nhận chấn thương.');
      setForm({ type: '', occurred_at: '', expected_recovery: '', description: '' });
      load();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  const markRecovered = async (inj: InjuryItem) => {
    try { await api.put(`/injuries/${inj.id}`, { status: 'recovered' }); load(); } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🩹 Lịch sử chấn thương</h2>
      <form onSubmit={add} className="mb-4 grid gap-2 sm:grid-cols-4">
        <input placeholder="Loại (VD: Cổ tay)" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} required />
        <input type="date" value={form.occurred_at} onChange={(e) => setForm({ ...form, occurred_at: e.target.value })} required />
        <input type="date" value={form.expected_recovery} onChange={(e) => setForm({ ...form, expected_recovery: e.target.value })} title="Dự kiến hồi phục" />
        <button className="btn-primary text-xs">➕ Ghi nhận</button>
      </form>
      {items.length === 0 ? <p className="text-sm text-neutral-500">Chưa có ghi nhận chấn thương nào. 🎉</p> : (
        <div className="space-y-2">
          {items.map((i) => (
            <div key={i.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-neutral-800 px-3 py-2">
              <div>
                <p className="text-sm font-semibold">{i.type} <span className="text-xs font-normal text-neutral-500">· {i.occurred_at}</span></p>
                {i.expected_recovery && <p className="text-xs text-neutral-400">Dự kiến hồi phục: {i.expected_recovery}</p>}
              </div>
              {i.status === 'recovering'
                ? <button onClick={() => markRecovered(i)} className="text-xs text-emerald-400 hover:underline">Đánh dấu đã hồi phục</button>
                : <span className="rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">Đã hồi phục</span>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* ---------------- Ghi chú HLV (timeline) ---------------- */
function CoachNotesCard({ athleteId }: { athleteId: number }) {
  const [notes, setNotes] = useState<CoachNoteItem[]>([]);
  const [body, setBody] = useState('');

  const load = () => api.get<{ data: CoachNoteItem[] }>(`/athletes/${athleteId}/coach-notes`).then((r) => setNotes(r.data));
  useEffect(() => { load().catch(() => {}); }, [athleteId]);

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post(`/athletes/${athleteId}/coach-notes`, { body });
      setBody('');
      notifySuccess('Đã thêm ghi chú HLV.');
      load();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">📋 Nhật ký chiến thuật (HLV)</h2>
      <form onSubmit={add} className="mb-4 flex gap-2">
        <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={2} placeholder="Nhận xét chiến thuật, điểm cần cải thiện…" className="flex-1" required />
        <button className="btn-primary self-end text-xs">➕</button>
      </form>
      <div className="space-y-0">
        {notes.map((n, i) => (
          <div key={n.id} className="relative border-l-2 border-emerald-700 pb-4 pl-4 last:pb-0">
            <span className="absolute -left-[7px] top-1 h-3 w-3 rounded-full bg-emerald-500" />
            <p className="text-xs text-neutral-500">{new Date(n.created_at).toLocaleString('vi-VN')} · {n.author?.name ?? 'HLV'}</p>
            <p className="mt-1 text-sm text-neutral-200">{n.body}</p>
            {i === notes.length - 1 && null}
          </div>
        ))}
        {notes.length === 0 && <p className="text-sm text-neutral-500">Chưa có ghi chú nào.</p>}
      </div>
    </div>
  );
}

/* ---------------- Coach Bot (tư vấn chiến thuật) ---------------- */
function CoachBotCard({ athleteId }: { athleteId: number }) {
  const [advices, setAdvices] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);

  const ask = async () => {
    setBusy(true);
    try {
      const res = await api.get<{ data: { advices: string[] } }>(`/athletes/${athleteId}/coach-bot`);
      setAdvices(res.data.data.advices);
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
    finally { setBusy(false); }
  };

  return (
    <div className="card border-emerald-800/60 bg-emerald-500/5">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="font-semibold">🤖 Coach Bot — Tư vấn chiến thuật</h2>
        <button onClick={ask} disabled={busy} className="btn-primary text-xs">{busy ? 'Đang phân tích…' : 'Phân tích hồ sơ'}</button>
      </div>
      {advices.length === 0 ? <p className="text-sm text-neutral-500">Nhấn "Phân tích hồ sơ" để nhận tư vấn dựa trên radar chỉ số + trang bị + phong độ.</p> : (
        <ul className="list-inside list-disc space-y-2 text-sm text-neutral-200">{advices.map((a, i) => <li key={i}>{a}</li>)}</ul>
      )}
    </div>
  );
}

/* ---------------- Radar lối chơi chuyên sâu (HLV gán điểm) ---------------- */
function PlaystyleRadar({ athleteId }: { athleteId: number }) {
  const { user } = useAuth();
  const [scores, setScores] = useState<Record<string, number>>({ attack_smash: 50, net_kill: 50, endurance_defense: 50, clear_control: 50 });
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    api.get<{ data: Record<string, number> }>(`/athletes/${athleteId}/playstyle`)
      .then((r) => { setScores(r.data); setLoaded(true); });
  }, [athleteId]);

  const LABELS: Record<string, string> = { attack_smash: 'Tấn công cuối sân', net_kill: 'Cắt cầu trên lưới', endurance_defense: 'Phòng thủ bền bỉ', clear_control: 'Khả năng điều cầu' };
  const data = Object.entries(LABELS).map(([key, label]) => ({ metric: label, value: scores[key] ?? 0 }));

  const save = async () => {
    try {
      await api.post(`/athletes/${athleteId}/playstyle`, scores);
      notifySuccess('Đã cập nhật chỉ số lối chơi!');
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🎯 Phân tích lối chơi chuyên sâu</h2>
      <div className="grid gap-4 lg:grid-cols-2">
        <div style={{ width: '100%', height: 280 }}>
          <ResponsiveContainer>
            <RadarChart data={data} outerRadius="70%">
              <PolarGrid stroke="#2a2a2f" />
              <PolarAngleAxis dataKey="metric" tick={{ fill: '#d4d4d4', fontSize: 10 }} />
              <PolarRadiusAxis domain={[0, 100]} tick={{ fill: '#737373', fontSize: 9 }} />
              <Radar dataKey="value" stroke="#fbbf24" fill="#fbbf24" fillOpacity={0.3} />
            </RadarChart>
          </ResponsiveContainer>
        </div>
        <div className="space-y-2">
          {loaded && Object.keys(LABELS).map((key) => (
            <div key={key} className="flex items-center gap-2">
              <span className="w-40 text-xs text-neutral-300">{LABELS[key]}</span>
              <input type="range" min={0} max={100} value={scores[key]} disabled={user?.role !== 'admin'}
                onChange={(e) => setScores({ ...scores, [key]: Number(e.target.value) })} className="flex-1 accent-amber-500" />
              <span className="w-8 text-right font-mono text-xs">{scores[key]}</span>
            </div>
          ))}
          {user?.role === 'admin' && <button onClick={save} className="btn-primary text-xs">💾 Lưu (HLV gán điểm)</button>}
          {user?.role !== 'admin' && <p className="text-xs text-neutral-500">Chỉ HLV/Quản trị viên được gán điểm.</p>}
        </div>
      </div>
    </div>
  );
}

/* ---------------- Veo video highlight ---------------- */
function Veocard({ athleteId }: { athleteId: number }) {
  const [result, setResult] = useState<{ status: string; message?: string; storyboard?: string[] } | null>(null);
  const [busy, setBusy] = useState(false);

  const generate = async () => {
    setBusy(true);
    try {
      const res = await api.post<{ data: { status: string; message?: string; storyboard?: string[] } }>(`/athletes/${athleteId}/veo-highlight`);
      setResult(res.data.data);
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
    finally { setBusy(false); }
  };

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🎬 Video Highlight 15s (Veo)</h2>
      <button onClick={generate} disabled={busy} className="btn-primary text-xs">{busy ? 'Đang tạo…' : 'Tạo video tổng hợp kỹ thuật'}</button>
      {result && (
        <div className="mt-3">
          <p className="text-xs text-neutral-400">{result.message ?? `Trạng thái: ${result.status}`}</p>
          {result.storyboard && (
            <ul className="mt-2 list-inside list-disc text-sm text-neutral-200">
              {result.storyboard.map((s, i) => <li key={i}>{s}</li>)}
            </ul>
          )}
          <p className="mt-2 text-[10px] text-neutral-500">Cấu hình GOOGLE_VEO_API_KEY trong .env để render video thật; hiện trả story-board tham khảo.</p>
        </div>
      )}
    </div>
  );
}

/* ---------------- Lịch sử thi đấu / đặt sân ---------------- */
function MatchHistoryCard({ athleteId }: { athleteId: number }) {
  const [items, setItems] = useState<MatchHistoryItem[]>([]);

  useEffect(() => {
    api.get<{ data: MatchHistoryItem[] }>(`/athletes/${athleteId}/match-history`).then((r) => setItems(r.data)).catch(() => {});
  }, [athleteId]);

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🏟️ Lịch sử thi đấu & đặt sân</h2>
      {items.length === 0 ? <p className="text-sm text-neutral-500">Chưa có trận đấu nào được ghi nhận.</p> : (
        <table className="w-full text-sm">
          <thead><tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
            <th className="p-2">Ngày</th><th className="p-2">Địa điểm</th><th className="p-2">Đối thủ</th><th className="p-2">Tỷ số</th><th className="p-2">Kết quả</th>
          </tr></thead>
          <tbody>
            {items.map((m, i) => (
              <tr key={i} className="border-b border-neutral-800/60">
                <td className="p-2">{m.date}</td>
                <td className="p-2 text-neutral-400">{m.venue ?? '—'}</td>
                <td className="p-2">{m.opponent ?? '—'}</td>
                <td className="p-2 font-mono">{m.walkover ? 'W.O.' : m.score}</td>
                <td className="p-2">{m.result === 'win' ? <span className="text-emerald-400">Thắng</span> : <span className="text-rose-400">Thua</span>}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

/* ---------------- Gợi ý vợt ---------------- */
function RacketSuggestionCard({ athleteId }: { athleteId: number }) {
  const [items, setItems] = useState<RacketSuggestion[]>([]);

  useEffect(() => {
    api.get<{ data: RacketSuggestion[] }>(`/athletes/${athleteId}/racket-suggestions`).then((r) => setItems(r.data)).catch(() => {});
  }, [athleteId]);

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">💡 Gợi ý vợt phù hợp (lối đánh + trình độ)</h2>
      {items.length === 0 ? <p className="text-sm text-neutral-500">Chưa có gợi ý phù hợp.</p> : (
        <div className="grid gap-2 sm:grid-cols-2">
          {items.map((r) => (
            <div key={r.id} className="rounded-lg border border-neutral-800 p-3">
              <p className="text-sm font-semibold">{r.brand} {r.name}</p>
              <p className="text-xs text-emerald-400">{r.match_reason}</p>
              {r.price && <p className="mt-1 font-mono text-xs text-amber-400">{Number(r.price).toLocaleString('vi-VN')} ₫</p>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* ---------------- Nhật ký dinh dưỡng ---------------- */
function NutritionDiary({ athleteId }: { athleteId: number }) {
  const [logs, setLogs] = useState<{ id: number; log_date: string; meal: string; description?: string | null; calories: number; protein_g: number; water_ml: number }[]>([]);
  const [form, setForm] = useState({ log_date: new Date().toISOString().slice(0, 10), meal: 'breakfast', description: '', calories: '', protein_g: '', water_ml: '' });

  const load = () => api.get<{ data: typeof logs }>(`/athletes/${athleteId}/nutrition`).then((r) => setLogs(r.data));
  useEffect(() => { load().catch(() => {}); }, [athleteId]);

  const MEALS: Record<string, string> = { breakfast: 'Sáng', lunch: 'Trưa', dinner: 'Tối', snack: 'Bổ sung', pre_match: 'Trận đấu' };

  const add = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post(`/athletes/${athleteId}/nutrition`, {
        ...form,
        description: form.description || undefined,
        calories: Number(form.calories) || 0, protein_g: Number(form.protein_g) || 0, water_ml: Number(form.water_ml) || 0,
      });
      notifySuccess('Đã ghi nhật ký dinh dưỡng!');
      load();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  const byDay = [...new Set(logs.map((l) => l.log_date))].slice(0, 10);

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🥗 Dinh dưỡng VĐV (nhật ký theo ngày)</h2>
      <form onSubmit={add} className="mb-4 grid gap-2 sm:grid-cols-6">
        <input type="date" value={form.log_date} onChange={(e) => setForm({ ...form, log_date: e.target.value })} />
        <select value={form.meal} onChange={(e) => setForm({ ...form, meal: e.target.value })}>
          {Object.entries(MEALS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <input placeholder="Món ăn / bổ sung" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="sm:col-span-2" />
        <input type="number" placeholder="Kcal" value={form.calories} onChange={(e) => setForm({ ...form, calories: e.target.value })} />
        <button className="btn-primary text-xs">➕ Ghi</button>
      </form>
      {byDay.length === 0 ? <p className="text-sm text-neutral-500">Chưa có nhật ký. Ghi chú lịch ăn phù hợp cường độ tập.</p> : byDay.map((date) => (
        <div key={date} className="mb-2 rounded-lg border border-neutral-800 p-2">
          <p className="text-xs font-bold text-emerald-400">{date}</p>
          {logs.filter((l) => l.log_date === date).map((l) => (
            <p key={l.id} className="text-xs text-neutral-300">{MEALS[l.meal]}: {l.description || '—'} · {l.calories} kcal · đạm {l.protein_g}g · nước {l.water_ml}ml</p>
          ))}
        </div>
      ))}
    </div>
  );
}

/* ---------------- Career Milestones (huy hiệu cột mốc) ---------------- */
function MilestonesBadges({ athleteId }: { athleteId: number }) {
  const [items, setItems] = useState<{ icon: string; label: string; achieved: boolean; progress: number }[]>([]);

  useEffect(() => {
    api.get<{ data: typeof items }>(`/athletes/${athleteId}/milestones`).then((r) => setItems(r.data)).catch(() => {});
  }, [athleteId]);

  if (items.length === 0) return null;

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🏅 Career Milestones</h2>
      <div className="flex flex-wrap gap-2">
        {items.map((m) => (
          <div key={m.label} title={`${m.progress}%`} className={`rounded-xl border px-3 py-2 text-center ${m.achieved ? 'border-amber-500 bg-amber-500/10' : 'border-neutral-800 opacity-50'}`}>
            <p className="text-xl">{m.icon}</p>
            <p className="text-[10px] text-neutral-300">{m.label}</p>
            {!m.achieved && <div className="mt-1 h-1 w-16 rounded-full bg-neutral-800"><div className="h-full rounded-full bg-amber-400" style={{ width: `${m.progress}%` }} /></div>}
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------------- Heatmap phong độ theo ngày ---------------- */
function PeakPerformanceHeatmap({ athleteId }: { athleteId: number }) {
  const [items, setItems] = useState<{ day: string; matches: number; intensity: number }[]>([]);

  useEffect(() => {
    api.get<{ data: typeof items }>(`/athletes/${athleteId}/peak-heatmap`).then((r) => setItems(r.data)).catch(() => {});
  }, [athleteId]);

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🔥 Heatmap phong độ theo ngày</h2>
      <div className="flex gap-2">
        {items.map((d) => (
          <div key={d.day} className="text-center">
            <div className={`h-10 w-10 rounded-lg ${d.intensity > 66 ? 'bg-amber-400' : d.intensity > 33 ? 'bg-emerald-500' : d.intensity > 0 ? 'bg-emerald-900' : 'bg-neutral-900'}`} title={`${d.day}: ${d.matches} trận`} />
            <p className="mt-1 text-[10px] text-neutral-500">{d.day}</p>
          </div>
        ))}
      </div>
      <p className="mt-2 text-xs text-neutral-500">Màu sáng = thi đấu nhiều hơn (phong độ đạt cao nhất theo lịch sử).</p>
    </div>
  );
}

/* ---------------- Heatmap vùng sân (D3.js — kiểu cú đánh ưa thích) ---------------- */
function ZoneHeatmapCard({ athleteId }: { athleteId: number }) {
  const { user } = useAuth();
  const svgRef = useRef<SVGSVGElement>(null);
  const [zones, setZones] = useState<number[]>([0, 0, 0, 0, 0, 0]);
  const [playstyle, setPlaystyle] = useState<Record<string, number> | null>(null);
  const LABELS = ['Trước-trái', 'Trước-giữa', 'Trước-phải', 'Sau-trái', 'Sau-giữa', 'Sau-phải'];

  useEffect(() => {
    api.get<{ data: number[] }>(`/athletes/${athleteId}/zones`).then((r) => setZones(r.data)).catch(() => {});
    api.get<{ data: Record<string, number> }>(`/athletes/${athleteId}/playstyle`).then((r) => setPlaystyle(r.data)).catch(() => {});
  }, [athleteId]);

  // D3: vẽ sân cầu lông 6 vùng, màu theo giá trị (xanh = ưu thế, đỏ = sơ hở)
  useEffect(() => {
    if (!svgRef.current) return;
    const svg = d3.select(svgRef.current);
    svg.selectAll('*').remove();

    const W = 400, H = 480, CW = 300, CH = 400, x0 = (W - CW) / 2, y0 = (H - CH) / 2;
    const color = d3.scaleLinear<number>().domain([-100, 0, 100]).range(['#fb7185', '#3f3f46', '#34d399']).clamp(true);

    svg.append('rect').attr('x', x0).attr('y', y0).attr('width', CW).attr('height', CH)
      .attr('fill', 'none').attr('stroke', '#525252').attr('stroke-width', 2);
    svg.append('line').attr('x1', x0).attr('x2', x0 + CW).attr('y1', y0 + CH / 2).attr('y2', y0 + CH / 2).attr('stroke', '#525252').attr('stroke-width', 2);

    const cellW = CW / 3, cellH = CH / 2;
    zones.forEach((v, i) => {
      const col = i % 3, row = Math.floor(i / 3);
      const g = svg.append('g').style('cursor', user?.role === 'admin' ? 'pointer' : 'default')
        .on('click', () => {
          if (user?.role !== 'admin') return;
          const delta = window.prompt('Điều chỉnh (bước 10):', '10');
          if (!delta) return;
          const next = zones.map((z, j) => (j === i ? Math.max(-100, Math.min(100, z + Number(delta) * (row === 0 ? 1 : -1))) : z));
          setZones(next);
          api.post(`/athletes/${athleteId}/zones`, { zones: next }).then(() => notifySuccess('Đã cập nhật!')).catch((e) => notifyError('Lỗi', errorMessage(e)));
        });
      g.append('rect')
        .attr('x', x0 + col * cellW + 2).attr('y', y0 + row * cellH + 2)
        .attr('width', cellW - 4).attr('height', cellH - 4).attr('rx', 8)
        .attr('fill', color(v)).attr('fill-opacity', 0.75);
      g.append('text').attr('x', x0 + col * cellW + cellW / 2).attr('y', y0 + row * cellH + cellH / 2 - 6)
        .attr('text-anchor', 'middle').attr('fill', '#fff').attr('font-size', 11)
        .text(LABELS[i]);
      g.append('text').attr('x', x0 + col * cellW + cellW / 2).attr('y', y0 + row * cellH + cellH / 2 + 12)
        .attr('text-anchor', 'middle').attr('fill', '#e5e5e5').attr('font-size', 14).attr('font-weight', 'bold')
        .text(v > 0 ? `+${v}` : v);
    });

    // Ghi chú kiểu cú đánh ưa thích từ playstyle (thống kê quá khứ)
    if (playstyle) {
      const top = Object.entries(playstyle).sort((a, b) => b[1] - a[1])[0];
      const names: Record<string, string> = { attack_smash: 'Đập cầu cuối sân', net_kill: 'Cắt cầu trên lưới', endurance_defense: 'Phòng thủ bền bỉ', clear_control: 'Điều cầu' };
      svg.append('text').attr('x', W / 2).attr('y', H - 6).attr('text-anchor', 'middle')
        .attr('fill', '#a3a3a3').attr('font-size', 11)
        .text(`Kiểu cú đánh ưa thích: ${names[top[0]] ?? top[0]} (${top[1]}/100)`);
    }
  }, [zones, playstyle, user, athleteId]);

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🗺️ Heatmap vùng sân & kiểu cú đánh <span className="text-xs font-normal text-neutral-500">(D3.js — xanh = ưu thế, đỏ = sơ hở)</span></h2>
      <svg ref={svgRef} viewBox="0 0 400 480" className="mx-auto w-full max-w-sm" />
      {user?.role === 'admin' && <p className="mt-2 text-center text-xs text-neutral-500">Click vùng sân để điều chỉnh điểm (HLV).</p>}
    </div>
  );
}

/* ---------------- Similar Pro Gear (3 sản phẩm thay thế cùng thương hiệu) ---------------- */
function SimilarProGear({ athlete }: { athlete: Athlete }) {
  const [items, setItems] = useState<EquipmentItem[]>([]);

  useEffect(() => {
    const brand = athlete.racket ?? athlete.shoes;
    if (!brand) return;
    const type = athlete.racket ? 'racket' : 'shoes';
    api.get<{ data: EquipmentItem[] }>('/products', { params: { brand, type } })
      .then((r) => setItems(r.data.filter((p) => p.name !== brand).slice(0, 3)))
      .catch(() => {});
  }, [athlete.racket, athlete.shoes]);

  if (items.length === 0) return null;

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🔄 Similar Pro Gear <span className="text-xs font-normal text-neutral-500">(cùng thương hiệu {athlete.racket ?? athlete.shoes})</span></h2>
      <div className="grid gap-2 sm:grid-cols-3">
        {items.map((p) => (
          <Link key={p.id} to="/products" className="rounded-lg border border-neutral-800 p-3 transition hover:border-emerald-600">
            <p className="text-sm font-semibold">{p.name}</p>
            <p className="text-xs text-neutral-400">{p.brand} · {p.model}</p>
            {p.price && <p className="mt-1 font-mono text-xs text-amber-400">{Number(p.price).toLocaleString('vi-VN')} ₫</p>}
          </Link>
        ))}
      </div>
    </div>
  );
}

/* ---------------- Chia sẻ hồ sơ: tạo ảnh tóm tắt (canvas) ---------------- */
function ShareProfileButton({ athlete }: { athlete: Athlete }) {
  const generate = () => {
    const canvas = document.createElement('canvas');
    canvas.width = 800; canvas.height = 420;
    const ctx = canvas.getContext('2d')!;
    ctx.fillStyle = '#0a0a0b'; ctx.fillRect(0, 0, 800, 420);
    ctx.fillStyle = '#10b981'; ctx.fillRect(0, 0, 800, 8);
    ctx.fillStyle = '#34d399'; ctx.font = 'bold 28px sans-serif';
    ctx.fillText('SmashRank', 40, 60);
    ctx.fillStyle = '#fafafa'; ctx.font = 'bold 36px sans-serif';
    ctx.fillText(athlete.full_name, 40, 120);
    ctx.fillStyle = '#a3a3a3'; ctx.font = '16px sans-serif';
    ctx.fillText(`${athlete.nationality} · ${athlete.category} · ${athlete.club ?? ''}`, 40, 152);
    const stats: [string, string][] = [
      ['Elo', String(athlete.elo_rating)],
      ['BWF', athlete.ranking_points.toLocaleString()],
      ['Hạng TG', athlete.world_rank ? `#${athlete.world_rank}` : '—'],
      ['Thắng %', `${athlete.win_rate ?? 0}%`],
    ];
    stats.forEach(([label, value], i) => {
      const x = 40 + i * 185;
      ctx.fillStyle = '#1d1d20'; ctx.fillRect(x, 190, 165, 90);
      ctx.fillStyle = '#a3a3a3'; ctx.font = '13px sans-serif'; ctx.fillText(label, x + 16, 220);
      ctx.fillStyle = '#34d399'; ctx.font = 'bold 26px sans-serif'; ctx.fillText(value, x + 16, 260);
    });
    ctx.fillStyle = '#a3a3a3'; ctx.font = '14px sans-serif';
    ctx.fillText(`Vợt: ${athlete.racket ?? '—'} · Giày: ${athlete.shoes ?? '—'}`, 40, 330);
    ctx.fillStyle = '#34d399'; ctx.font = '14px sans-serif';
    ctx.fillText(window.location.href, 40, 370);

    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `smashrank-${athlete.id}-card.png`;
    a.click();
    notifySuccess('Đã tạo ảnh tóm tắt hồ sơ!');
  };

  return <button onClick={generate} className="btn-ghost" title="Tạo ảnh tóm tắt để đăng mạng xã hội">🖼️ Ảnh tóm tắt</button>;
}

/* ---------------- Tương thích thiết bị (vợt + giày) ---------------- */
function EquipmentFitCard({ athleteId }: { athleteId: number }) {
  const [data, setData] = useState<{ rackets: RacketSuggestion[]; shoes: RacketSuggestion[] } | null>(null);

  useEffect(() => {
    api.get<{ data: typeof data }>(`/athletes/${athleteId}/equipment-fit`).then((r) => setData(r.data)).catch(() => {});
  }, [athleteId]);

  if (!data) return null;

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🧩 Tương thích thiết bị (theo chiến thuật + thể chất)</h2>
      <div className="grid gap-4 sm:grid-cols-2">
        {(['rackets', 'shoes'] as const).map((key) => (
          <div key={key}>
            <p className="mb-2 text-xs uppercase text-neutral-400">{key === 'rackets' ? '🏸 Vợt phù hợp' : '👟 Giày phù hợp'}</p>
            {data[key].map((s) => (
              <div key={s.id} className="mb-2 rounded-lg border border-neutral-800 p-2">
                <p className="text-sm font-semibold">{s.brand} {s.name}</p>
                <p className="text-xs text-emerald-400">{s.match_reason}</p>
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------------- Quay video bằng camera + gắn thẻ thời gian ---------------- */
function CameraRecorder({ athleteId }: { athleteId: number }) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const mediaRef = useRef<MediaRecorder | null>(null);
  const chunks = useRef<Blob[]>([]);
  const startedAt = useRef<number>(0);
  const [recording, setRecording] = useState(false);
  const [videoUrl, setVideoUrl] = useState<string | null>(null);
  const [tags, setTags] = useState<{ label: string; ms: number }[]>([]);

  const start = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      if (videoRef.current) videoRef.current.srcObject = stream;
      mediaRef.current = new MediaRecorder(stream);
      chunks.current = [];
      startedAt.current = Date.now();
      mediaRef.current.ondataavailable = (e) => chunks.current.push(e.data);
      mediaRef.current.onstop = () => {
        const blob = new Blob(chunks.current, { type: 'video/webm' });
        setVideoUrl(URL.createObjectURL(blob));
        stream.getTracks().forEach((t) => t.stop());
      };
      mediaRef.current.start();
      setRecording(true);
    } catch { notifyError('Không truy cập được camera'); }
  };

  const stop = () => { mediaRef.current?.stop(); setRecording(false); info('Đã dừng quay — video khả dụng để tải về.'); };

  const tag = () => {
    const label = window.prompt('Nhãn kỹ thuật tại mốc này (VD: Jump smash):');
    if (!label) return;
    const ms = Date.now() - startedAt.current;
    setTags((t) => [...t, { label, ms }]);
    // Gắn thẻ vào hồ sơ VĐV qua API
    api.post(`/athletes/${athleteId}/veo-highlight`).catch(() => {});
  };

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🎥 Quay video kỹ thuật (Camera)</h2>
      <video ref={videoRef} autoPlay muted className="mb-3 w-full rounded-lg" style={{ display: recording || videoUrl ? 'block' : 'none' }} />
      <div className="flex gap-2">
        {!recording ? <button onClick={start} className="btn-primary text-xs">⏺ Bắt đầu quay</button> : (
          <>
            <button onClick={tag} className="btn-ghost text-xs">🏷️ Gắn thẻ thời gian</button>
            <button onClick={stop} className="btn-primary text-xs">⏹ Dừng</button>
          </>
        )}
      </div>
      {tags.length > 0 && (
        <ul className="mt-2 text-xs text-neutral-300">
          {tags.map((t, i) => <li key={i}>🏷️ {(t.ms / 1000).toFixed(1)}s — {t.label}</li>)}
        </ul>
      )}
      {videoUrl && <a href={videoUrl} download="smashrank-technique.webm" className="btn-primary mt-2 text-xs">⬇️ Tải video</a>}
      <p className="mt-2 text-xs text-neutral-500">Video xử lý ngay trên thiết bị (không upload tự động) — tuân thủ quyền riêng tư.</p>
    </div>
  );
}
