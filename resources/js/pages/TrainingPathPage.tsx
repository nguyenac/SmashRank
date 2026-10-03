import { useEffect, useRef, useState } from 'react';
import * as d3 from 'd3';
import {
  PolarAngleAxis, PolarGrid, PolarRadiusAxis, Radar, RadarChart, ResponsiveContainer,
} from 'recharts';
import { api, errorMessage } from '../api';
import { error as notifyError, success as notifySuccess } from '../lib/notification';
import type { DrillItem, SkillProgressPoint, TrainingGoal } from '../types';
import { DRILL_CATEGORY_VI, DRILL_DIFFICULTY_VI } from '../types';

const DAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

/**
 * Lộ trình huấn luyện nâng cao:
 *  - Focus Mode: ẩn UI phụ, biểu đồ tiến độ toàn màn hình
 *  - Skill Progression Timeline (D3.js): mốc kỹ năng, click xem bài tập đã hoàn thành
 *  - AI Smart Coach: gợi ý bài tập theo điểm yếu + checklist mục tiêu hàng ngày
 *  - Cảnh báo chấn thương: Training Load so với hồi phục (thang xanh→đỏ)
 *  - Nhóm tập luyện + Smart Goals
 */
export default function TrainingPathPage() {
  const [goals, setGoals] = useState<TrainingGoal[]>([]);
  const [smartGoals, setSmartGoals] = useState<{ id: number; title: string; baseline: number; current: number; target: number; progress: number }[]>([]);
  const [suggestion, setSuggestion] = useState<string[]>([]);
  const [progress, setProgress] = useState<SkillProgressPoint[]>([]);
  const [drills, setDrills] = useState<DrillItem[]>([]);
  const [aiDrills, setAiDrills] = useState<DrillItem[]>([]);
  const [aiNote, setAiNote] = useState('');
  const [dailyDone, setDailyDone] = useState<string[]>(() => {
    try { return JSON.parse(localStorage.getItem('daily_checklist') ?? '[]'); } catch { return []; }
  });
  const [filter, setFilter] = useState({ category: '', difficulty: '', duration_min: '' });
  const [plan, setPlan] = useState<Record<string, string[]>>(() => {
    try { return JSON.parse(localStorage.getItem('training_week_plan') ?? '{}'); } catch { return {}; }
  });
  const [form, setForm] = useState({ title: '', target: '', drill: '', frequency: '', deadline: '', metric: '', target_value: '' });
  const [focusMode, setFocusMode] = useState(false);
  const [groups, setGroups] = useState<{ id: number; name: string; coach?: string; members_count: number; leaderboard: { rank: number; name: string; xp: number; elo?: number }[] }[]>([]);
  const [error, setError] = useState('');

  const load = () => {
    api.get<{ data: TrainingGoal[]; coach_suggestion: string[] }>('/training-goals')
      .then((r) => { setGoals(r.data); setSuggestion(r.data.coach_suggestion ?? []); })
      .catch((e) => setError(errorMessage(e)));
    api.get<{ data: SkillProgressPoint[] }>('/drills/skill-progress').then((r) => setProgress(r.data)).catch(() => {});
    api.get<{ data: typeof smartGoals }>('/training-goals/smart-progress').then((r) => setSmartGoals(r.data)).catch(() => {});
    api.get<{ data: typeof groups }>('/training-groups').then((r) => setGroups(r.data)).catch(() => {});
    loadDrills();
  };

  const loadDrills = () => {
    api.get<{ data: DrillItem[] }>('/drills', { params: filter }).then((r) => setDrills(r.data)).catch(() => {});
  };

  const loadAi = () => {
    api.get<{ data: DrillItem[]; message: string }>('/coach/drill-suggestions')
      .then((r) => { setAiDrills(r.data); setAiNote(r.data.message ?? ''); })
      .catch(() => {});
  };

  useEffect(load, []);
  useEffect(loadDrills, [filter]);
  useEffect(loadAi, []);

  // ---- Training Load (cảnh báo chấn thương): bài tập trong tuần vs mục tiêu ----
  const weeklyLoad = Object.values(plan).reduce((sum, arr) => sum + arr.length, 0);
  const loadStatus = weeklyLoad === 0 ? { color: 'bg-neutral-700', text: 'Chưa có khối lượng tập', level: 0 }
    : weeklyLoad <= 5 ? { color: 'bg-emerald-500', text: 'An toàn', level: 1 }
    : weeklyLoad <= 10 ? { color: 'bg-amber-400', text: 'Cao — theo dõi hồi phục', level: 2 }
    : { color: 'bg-rose-500', text: 'Rất cao — nguy cơ chấn thương!', level: 3 };

  const savePlan = (next: Record<string, string[]>) => {
    setPlan(next);
    localStorage.setItem('training_week_plan', JSON.stringify(next));
  };

  const toggleDaily = (title: string) => {
    const next = dailyDone.includes(title) ? dailyDone.filter((d) => d !== title) : [...dailyDone, title];
    setDailyDone(next);
    localStorage.setItem('daily_checklist', JSON.stringify(next));
  };

  const onDrop = (day: string, e: React.DragEvent) => {
    e.preventDefault();
    const drillTitle = e.dataTransfer.getData('text/plain');
    if (!drillTitle) return;
    savePlan({ ...plan, [day]: [...(plan[day] ?? []), drillTitle] });
    notifySuccess(`Đã xếp "${drillTitle}" vào ${day}`);
  };

  const submitGoal = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/training-goals', {
        ...form,
        deadline: form.deadline || undefined,
        metric: form.metric || undefined,
        target_value: form.metric && form.target_value ? Number(form.target_value) : undefined,
      });
      notifySuccess('Đã thêm mục tiêu!');
      setForm({ title: '', target: '', drill: '', frequency: '', deadline: '', metric: '', target_value: '' });
      load();
    } catch (err) { notifyError('Không lưu được mục tiêu', errorMessage(err)); }
  };

  const createDrill = async (e: React.FormEvent) => {
    e.preventDefault();
    const fd = new FormData(e.target as HTMLFormElement);
    try {
      const res = await api.post<{ new_badges: { name: string; icon: string }[] }>('/drills', {
        title: fd.get('title'), detail: fd.get('detail'), category: fd.get('category'),
        difficulty: fd.get('difficulty'), duration_min: Number(fd.get('duration_min')),
      });
      notifySuccess('Đã tạo bài tập (+10 XP)!');
      res.data.new_badges?.forEach((b) => notifySuccess(`🏅 Danh hiệu mới: ${b.icon} ${b.name}`));
      (e.target as HTMLFormElement).reset();
      loadDrills();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  // Radar phát triển kỹ năng
  const latest = progress[progress.length - 1];
  const earliest = progress[0];
  const radarData = latest ? [
    { metric: 'Sức mạnh', now: latest.power, base: earliest?.power ?? latest.power },
    { metric: 'Tốc độ', now: latest.speed, base: earliest?.speed ?? latest.speed },
    { metric: 'Phòng thủ', now: latest.defense, base: earliest?.defense ?? latest.defense },
    { metric: 'Trên lưới', now: latest.net_play, base: earliest?.net_play ?? latest.net_play },
    { metric: 'Thể lực', now: latest.stamina, base: earliest?.stamina ?? latest.stamina },
  ] : [];

  return (
    <div className={focusMode ? 'fixed inset-0 z-50 overflow-y-auto bg-surface-950 p-6' : 'space-y-4'}>
      <div className={focusMode ? 'mx-auto max-w-4xl space-y-4' : 'space-y-4'}>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <h1 className="text-2xl font-bold">🎯 Lộ trình huấn luyện {focusMode && <span className="text-sm text-emerald-400">— Focus Mode</span>}</h1>
          <button onClick={() => setFocusMode(!focusMode)} className={focusMode ? 'btn-ghost text-xs' : 'btn-primary text-xs'}>
            {focusMode ? '✕ Thoát Focus Mode' : '🔍 Focus Mode'}
          </button>
        </div>

        {/* Biểu đồ tiến độ — toàn màn hình khi Focus Mode */}
        {radarData.length > 0 && (
          <div className={focusMode ? 'card !p-8' : 'card'}>
            <h2 className="mb-3 font-semibold">📈 Phát triển kỹ năng (hiện tại vs ban đầu)</h2>
            <div style={{ width: '100%', height: focusMode ? 520 : 300 }}>
              <ResponsiveContainer>
                <RadarChart data={radarData} outerRadius={focusMode ? '80%' : '70%'}>
                  <PolarGrid stroke="#2a2a2f" />
                  <PolarAngleAxis dataKey="metric" tick={{ fill: '#d4d4d4', fontSize: focusMode ? 14 : 12 }} />
                  <PolarRadiusAxis domain={[0, 100]} tick={{ fill: '#737373', fontSize: 10 }} />
                  <Radar name="Ban đầu" dataKey="base" stroke="#737373" fill="#737373" fillOpacity={0.15} />
                  <Radar name="Hiện tại" dataKey="now" stroke="#34d399" fill="#34d399" fillOpacity={0.35} />
                </RadarChart>
              </ResponsiveContainer>
            </div>
            {focusMode && <p className="mt-4 text-center text-sm text-neutral-400">Chế độ toàn màn hình — theo dõi chỉ số kỹ thuật không bị phân tâm.</p>}
          </div>
        )}

        {!focusMode && (
          <>
            {/* Cảnh báo sớm chấn thương (Training Load) */}
            <div className="card">
              <h2 className="mb-2 font-semibold">🚨 Cảnh báo sớm chấn thương</h2>
              <div className="flex items-center gap-3">
                <div className="h-3 flex-1 overflow-hidden rounded-full bg-neutral-800">
                  <div className={`h-full ${loadStatus.color}`} style={{ width: `${min(100, weeklyLoad * 8)}%` }} />
                </div>
                <span className={`rounded-full px-3 py-1 text-xs font-bold text-neutral-950 ${loadStatus.color}`}>{loadStatus.text}</span>
              </div>
              <p className="mt-2 text-xs text-neutral-500">Khối lượng tuần: {weeklyLoad} bài — so với dữ liệu hồi phục trong nhật ký dinh dưỡng & chấn thương. Thang: xanh → vàng → đỏ.</p>
            </div>

            {/* AI Smart Coach */}
            <div className="card border-amber-800/60 bg-amber-500/5">
              <h2 className="font-semibold text-amber-400">🤖 AI Smart Coach — Checklist mục tiêu hàng ngày</h2>
              <p className="mt-1 text-xs text-neutral-400">{aiNote || 'Phân tích lịch sử thi đấu & chỉ số để gợi ý bài tập cải thiện điểm yếu.'}</p>
              <div className="mt-3 grid gap-2 sm:grid-cols-2">
                {aiDrills.map((d) => (
                  <label key={d.id} className="flex cursor-pointer items-start gap-2 rounded-lg border border-neutral-800 p-2 text-sm">
                    <input type="checkbox" checked={dailyDone.includes(d.title)} onChange={() => toggleDaily(d.title)} className="mt-0.5 accent-emerald-500" />
                    <span>
                      <b>{d.title}</b> <span className="text-xs text-neutral-500">({DRILL_CATEGORY_VI[d.category]} · {d.duration_min}′)</span>
                      <br /><span className="text-xs text-neutral-400">{d.detail.slice(0, 90)}…</span>
                    </span>
                  </label>
                ))}
                {aiDrills.length === 0 && <p className="text-sm text-neutral-500">Chưa có gợi ý — cần hồ sơ VĐV.</p>}
              </div>
              <p className="mt-2 text-[10px] text-neutral-500">✅ {dailyDone.length}/{aiDrills.length} hoàn thành hôm nay · ⚠️ Dữ liệu THAM KHẢO.</p>
            </div>

            {/* Smart Goals */}
            {smartGoals.length > 0 && (
              <div className="card">
                <h2 className="mb-2 font-semibold">🎯 Mục tiêu thông minh (tự tính tiến độ)</h2>
                {smartGoals.map((g) => (
                  <div key={g.id} className="mb-2">
                    <p className="text-sm">{g.title} — {g.baseline} → <b className="text-emerald-400">{g.current}</b> / {g.target} ({g.metric === 'elo' ? 'Elo' : '% thắng'})</p>
                    <div className="h-2 rounded-full bg-neutral-800"><div className="h-full rounded-full bg-emerald-500" style={{ width: `${g.progress}%` }} /></div>
                  </div>
                ))}
              </div>
            )}

            {/* Gợi ý HLV + Nhóm tập luyện */}
            <div className="grid gap-4 lg:grid-cols-2">
              <div className="card border-emerald-800/60 bg-emerald-500/5">
                <h2 className="font-semibold text-emerald-400">🧑‍🏫 Gợi ý từ HLV</h2>
                <ul className="mt-2 list-inside list-disc space-y-1 text-sm text-neutral-200">
                  {suggestion.map((s, i) => <li key={i}>{s}</li>)}
                </ul>
                <p className="mt-2 text-[10px] text-neutral-500">⚠️ Dữ liệu chỉ mang tính THAM KHẢO.</p>
              </div>
              <div className="card">
                <h2 className="mb-2 font-semibold">👥 Nhóm tập luyện</h2>
                {groups.map((g) => (
                  <div key={g.id} className="mb-2 rounded-lg border border-neutral-800 p-2">
                    <p className="text-sm font-semibold">{g.name} <span className="text-xs text-neutral-500">· {g.members_count} thành viên · HLV: {g.coach ?? '—'}</span></p>
                    {g.leaderboard.slice(0, 3).map((m) => (
                      <p key={m.rank} className="text-xs text-neutral-400">#{m.rank} {m.name} — {m.xp} XP {m.elo ? `· Elo ${m.elo}` : ''}</p>
                    ))}
                    <button onClick={async () => { try { await api.post(`/training-groups/${g.id}/join`); notifySuccess('Đã tham gia nhóm!'); load(); } catch (e) { notifyError('Lỗi', errorMessage(e)); } }} className="mt-1 text-xs text-emerald-400 hover:underline">Tham gia</button>
                  </div>
                ))}
                <button onClick={async () => { const name = prompt('Tên nhóm:'); if (!name) return; try { await api.post('/training-groups', { name }); notifySuccess('Đã tạo nhóm!'); load(); } catch (e) { notifyError('Lỗi', errorMessage(e)); } }} className="btn-ghost text-xs">➕ Tạo nhóm</button>
              </div>
            </div>

            <SkillTimeline progress={progress} doneGoals={goals.filter((g) => g.status === 'done').map((g) => g.title)} />

            <div className="grid gap-4 lg:grid-cols-2">
              {/* Thư viện bài tập */}
              <div className="card">
                <h2 className="mb-3 font-semibold">📚 Thư viện bài tập</h2>
                <div className="mb-3 grid grid-cols-3 gap-2">
                  <select value={filter.category} onChange={(e) => setFilter({ ...filter, category: e.target.value })} className="text-xs">
                    <option value="">Mọi kỹ thuật</option>
                    {Object.entries(DRILL_CATEGORY_VI).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                  <select value={filter.difficulty} onChange={(e) => setFilter({ ...filter, difficulty: e.target.value })} className="text-xs">
                    <option value="">Mọi độ khó</option>
                    {Object.entries(DRILL_DIFFICULTY_VI).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                  </select>
                  <select value={filter.duration_min} onChange={(e) => setFilter({ ...filter, duration_min: e.target.value })} className="text-xs">
                    <option value="">Mọi thời lượng</option><option value="10">≤ 10′</option><option value="20">≤ 20′</option><option value="30">≤ 30′</option>
                  </select>
                </div>
                <div className="max-h-72 space-y-2 overflow-y-auto">
                  {drills.map((d) => (
                    <div key={d.id} draggable onDragStart={(e) => e.dataTransfer.setData('text/plain', d.title)}
                      className="cursor-grab rounded-lg border border-neutral-800 p-3 transition hover:border-emerald-600 active:cursor-grabbing">
                      <p className="text-sm font-semibold">{d.title}</p>
                      <p className="mt-1 line-clamp-2 text-xs text-neutral-400">{d.detail}</p>
                      <p className="mt-1 text-[10px] text-neutral-500">{DRILL_CATEGORY_VI[d.category]} · {DRILL_DIFFICULTY_VI[d.difficulty]} · {d.duration_min}′{d.is_reference && ' · tham khảo'}</p>
                    </div>
                  ))}
                </div>
                <form onSubmit={createDrill} className="mt-4 space-y-2 border-t border-neutral-800 pt-3">
                  <p className="text-xs font-semibold text-neutral-300">➕ Tạo bài tập (+10 XP)</p>
                  <input name="title" placeholder="Tên bài tập" required className="w-full text-xs" />
                  <textarea name="detail" placeholder="Bộ pháp di chuyển, kỹ thuật động tác, tư duy đánh cầu…" required rows={2} className="w-full text-xs" />
                  <div className="grid grid-cols-3 gap-2">
                    <select name="category" className="text-xs"><option value="attack">Tấn công</option><option value="defense">Phòng thủ</option><option value="footwork">Bộ pháp</option><option value="technique">Kỹ thuật</option><option value="stamina">Thể lực</option></select>
                    <select name="difficulty" className="text-xs"><option value="easy">Dễ</option><option value="medium">TB</option><option value="hard">Khó</option></select>
                    <input name="duration_min" type="number" min={5} max={180} placeholder="Phút" required className="text-xs" />
                  </div>
                  <button className="btn-primary text-xs">💾 Lưu bài tập</button>
                </form>
              </div>

              {/* Lịch tuần kéo thả + mục tiêu */}
              <div className="card">
                <h2 className="mb-3 font-semibold">🗓️ Lịch trình cá nhân <span className="text-xs font-normal text-neutral-500">(kéo bài tập vào ô)</span></h2>
                <div className="grid grid-cols-7 gap-1">
                  {DAYS.map((day) => (
                    <div key={day} onDragOver={(e) => e.preventDefault()} onDrop={(e) => onDrop(day, e)}
                      className="min-h-36 rounded-lg border border-dashed border-neutral-700 p-1.5 transition hover:border-emerald-600">
                      <p className="mb-1 text-center text-[10px] font-bold text-neutral-400">{day}</p>
                      {(plan[day] ?? []).map((title, i) => (
                        <p key={i} className="mb-1 break-words rounded bg-emerald-500/10 px-1 py-0.5 text-[10px] text-emerald-300">{title}</p>
                      ))}
                      {(plan[day] ?? []).length > 0 && (
                        <button onClick={() => savePlan({ ...plan, [day]: [] })} className="text-[9px] text-neutral-600 hover:text-rose-400">xóa</button>
                      )}
                    </div>
                  ))}
                </div>

                <form onSubmit={submitGoal} className="mt-4 space-y-2 border-t border-neutral-800 pt-3">
                  <p className="text-xs font-semibold text-neutral-300">🎯 Mục tiêu / Smart Goal</p>
                  <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="VD: Tăng 500 điểm Elo" required className="w-full text-xs" />
                  <div className="grid grid-cols-3 gap-2">
                    <select value={form.metric} onChange={(e) => setForm({ ...form, metric: e.target.value })} className="text-xs">
                      <option value="">Thường</option><option value="elo">Smart: Elo</option><option value="win_rate">Smart: % thắng</option>
                    </select>
                    <input value={form.target_value} onChange={(e) => setForm({ ...form, target_value: e.target.value })} placeholder="Đích (VD: 1500)" className="text-xs" />
                    <input type="date" value={form.deadline} onChange={(e) => setForm({ ...form, deadline: e.target.value })} className="text-xs" />
                  </div>
                  <button className="btn-primary text-xs">💾 Lưu mục tiêu</button>
                </form>
                <div className="mt-3 space-y-2">
                  {goals.slice(0, 4).map((g) => (
                    <div key={g.id} className="flex items-center gap-2">
                      <span className="w-40 truncate text-xs">{g.status === 'done' ? '✅ ' : ''}{g.title}</span>
                      <div className="h-1.5 flex-1 rounded-full bg-neutral-800"><div className="h-full rounded-full bg-emerald-500" style={{ width: `${g.progress}%` }} /></div>
                      <span className="w-8 text-right font-mono text-[10px]">{g.progress}%</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </>
        )}
      </div>
      {error && <p className="text-sm text-rose-400">{error}</p>}
    </div>
  );
}

/** Skill Progression Timeline (D3.js): mốc kỹ năng — click xem chi tiết giai đoạn. */
function SkillTimeline({ progress, doneGoals }: { progress: SkillProgressPoint[]; doneGoals: string[] }) {
  const ref = useRef<SVGSVGElement>(null);

  useEffect(() => {
    if (!ref.current || progress.length < 2) return;
    const svg = d3.select(ref.current);
    svg.selectAll('*').remove();
    const width = 640, height = 140, margin = 30;
    const x = d3.scalePoint<SkillProgressPoint>()
      .domain(progress).range([margin, width - margin]);

    const g = svg.append('g').attr('transform', `translate(0,${height / 2})`);
    g.append('line').attr('x1', margin).attr('x2', width - margin).attr('stroke', '#2a2a2f').attr('stroke-width', 2);

    progress.forEach((p, i) => {
      const avg = Math.round((p.power + p.speed + p.defense + p.net_play + p.stamina) / 5);
      const node = g.append('g').attr('transform', `translate(${x(p)},0)`)
        .style('cursor', 'pointer')
        .on('click', () => {
          alert(`Giai đoạn ${p.date}\nSức mạnh ${p.power} · Tốc độ ${p.speed} · Phòng thủ ${p.defense} · Lưới ${p.net_play} · Thể lực ${p.stamina}\nBài tập đã hoàn thành: ${doneGoals.length} mục tiêu`);
        });
      node.append('circle').attr('r', 8).attr('fill', i === progress.length - 1 ? '#34d399' : '#fbbf24');
      node.append('text').attr('y', 24).attr('text-anchor', 'middle').attr('fill', '#a3a3a3').attr('font-size', 9).text(p.date.slice(5));
      node.append('text').attr('y', -16).attr('text-anchor', 'middle').attr('fill', '#34d399').attr('font-size', 10).text(avg);
    });
  }, [progress, doneGoals]);

  if (progress.length < 2) return null;

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">🕰️ Skill Progression Timeline <span className="text-xs font-normal text-neutral-500">(D3.js — click mốc để xem chi tiết)</span></h2>
      <svg ref={ref} viewBox="0 0 640 140" className="w-full" />
    </div>
  );
}

function min(a: number, b: number) { return Math.min(a, b); }
