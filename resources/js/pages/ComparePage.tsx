import { useEffect, useState } from 'react';
import {
  PolarAngleAxis, PolarGrid, PolarRadiusAxis, Radar, RadarChart, ResponsiveContainer, Legend,
} from 'recharts';
import { api } from '../api';
import type { Athlete } from '../types';

/**
 * So sánh vận động viên:
 *  - Đơn: Radar overlay 2 VĐV + số liệu cạnh nhau
 *  - Đội 2v2: chọn 2 cặp → tổng power rating của từng đội → dự đoán kết quả
 */
export default function ComparePage() {
  const [athletes, setAthletes] = useState<Athlete[]>([]);
  const [a1, setA1] = useState<Athlete | null>(null);
  const [a2, setA2] = useState<Athlete | null>(null);
  const [a3, setA3] = useState<Athlete | null>(null);
  const [mode, setMode] = useState<'singles' | 'team'>('singles');
  const [t1a, setT1a] = useState<Athlete | null>(null);
  const [t1b, setT1b] = useState<Athlete | null>(null);
  const [t2a, setT2a] = useState<Athlete | null>(null);
  const [t2b, setT2b] = useState<Athlete | null>(null);

  useEffect(() => {
    api.get<{ data: Athlete[] }>('/athletes', { params: { per_page: 60 } }).then((r) => setAthletes(r.data));
  }, []);

  const power = (a: Athlete | null, b: Athlete | null) => {
    if (!a || !b) return 0;
    const avg = (a.elo_rating + b.elo_rating) / 2;
    const balance = Math.abs(a.elo_rating - b.elo_rating) <= 100 ? avg * 0.05 : 0;
    return Math.round(avg + balance);
  };

  const LABELS: Record<string, string> = {
    skill_power: 'Sức mạnh', skill_agility: 'Tốc độ', skill_defense: 'Phòng thủ',
    skill_technique: 'Kỹ thuật', skill_stamina: 'Thể lực', skill_mentality: 'Tâm lý',
  };

  const radarData = a1 && a2
    ? Object.keys(LABELS).map((key) => ({
        metric: LABELS[key],
        [a1.full_name]: a1[key as keyof Athlete] as number,
        [a2.full_name]: a2[key as keyof Athlete] as number,
        ...(a3 ? { [a3.full_name]: a3[key as keyof Athlete] as number } : {}),
      }))
    : [];

  // Xuất SVG radar → PNG (không cần thư viện ngoài)
  const exportChartAsPng = (containerId: string, filename: string) => {
    const svg = document.querySelector(`#${containerId} svg`);
    if (!svg) return;
    const xml = new XMLSerializer().serializeToString(svg);
    const img = new Image();
    img.onload = () => {
      const canvas = document.createElement('canvas');
      canvas.width = 800; canvas.height = 560;
      const ctx = canvas.getContext('2d')!;
      ctx.fillStyle = '#0a0a0b';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      const a = document.createElement('a');
      a.href = canvas.toDataURL('image/png');
      a.download = filename;
      a.click();
    };
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(xml)));
  };

  const predict = () => {
    const p1 = power(t1a, t1b), p2 = power(t2a, t2b);
    const diff = p1 - p2;
    const prob1 = Math.round(100 / (1 + 10 ** (-diff / 400)));
    return { p1, p2, prob1 };
  };

  const pred = (t1a && t1b && t2a && t2b) ? predict() : null;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">⚔️ So sánh vận động viên</h1>

      <div className="flex gap-2">
        {([['singles', 'Đơn — 1v1'], ['team', 'Đội — 2v2']] as const).map(([v, l]) => (
          <button key={v} onClick={() => setMode(v)}
            className={`rounded-lg px-4 py-2 text-sm font-semibold ${mode === v ? 'bg-emerald-500 text-neutral-950' : 'border border-neutral-700 text-neutral-300'}`}>
            {l}
          </button>
        ))}
      </div>

      {mode === 'singles' ? (
        <>
          <div className="card grid gap-3 sm:grid-cols-3">
            <Picker label="VĐV 1" athletes={athletes} value={a1} onChange={setA1} />
            <Picker label="VĐV 2" athletes={athletes} value={a2} onChange={setA2} />
            <Picker label="VĐV 3 (tùy chọn)" athletes={athletes} value={a3} onChange={setA3} />
          </div>
          {a1 && a2 && (
            <>
              <div className="card" id="compare-radar">
                <h2 className="mb-3 font-semibold">Radar so sánh chỉ số kỹ năng (tấn công / phòng thủ / tốc độ…)</h2>
                <div style={{ width: '100%', height: 320 }}>
                  <ResponsiveContainer>
                    <RadarChart data={radarData} outerRadius="70%">
                      <PolarGrid stroke="#2a2a2f" />
                      <PolarAngleAxis dataKey="metric" tick={{ fill: '#d4d4d4', fontSize: 11 }} />
                      <PolarRadiusAxis domain={[0, 100]} tick={{ fill: '#737373', fontSize: 10 }} />
                      <Radar name={a1.full_name} dataKey={a1.full_name} stroke="#34d399" fill="#34d399" fillOpacity={0.25} />
                      <Radar name={a2.full_name} dataKey={a2.full_name} stroke="#fbbf24" fill="#fbbf24" fillOpacity={0.2} />
                      {a3 && <Radar name={a3.full_name} dataKey={a3.full_name} stroke="#fb7185" fill="#fb7185" fillOpacity={0.15} />}
                      <Legend />
                    </RadarChart>
                  </ResponsiveContainer>
                </div>
                <button onClick={() => exportChartAsPng('compare-radar', 'smashrank-compare.png')} className="btn-ghost mt-2 text-xs">📸 Xuất biểu đồ PNG</button>
              </div>
              <div className="grid grid-cols-2 gap-3">
                {[[a1], [a2]].flat().map((a) => (
                  <div key={a!.id} className="card text-center">
                    <p className="font-bold">{a!.full_name}</p>
                    <p className="font-mono text-2xl text-emerald-400">{a!.elo_rating}</p>
                    <p className="text-xs text-neutral-400">Elo {a!.win_rate ?? 0}% thắng</p>
                  </div>
                ))}
              </div>
            </>
          )}
        </>
      ) : (
        <>
          <div className="card grid gap-3 sm:grid-cols-2">
            <Picker label="Đội 1 — VĐV A" athletes={athletes} value={t1a} onChange={setT1a} />
            <Picker label="Đội 1 — VĐV B" athletes={athletes} value={t1b} onChange={setT1b} />
            <Picker label="Đội 2 — VĐV A" athletes={athletes} value={t2a} onChange={setT2a} />
            <Picker label="Đội 2 — VĐV B" athletes={athletes} value={t2b} onChange={setT2b} />
          </div>
          {pred && (
            <div className="grid grid-cols-3 gap-3">
              <div className="card text-center">
                <p className="text-xs uppercase text-neutral-400">Sức mạnh Đội 1</p>
                <p className="text-3xl font-black text-emerald-400">{pred.p1}</p>
              </div>
              <div className="card flex flex-col items-center justify-center text-center">
                <p className="text-xs uppercase text-neutral-400">Dự đoán</p>
                <p className="mt-1 text-lg font-bold">{pred.prob1}% — {100 - pred.prob1}%</p>
                <p className="text-xs text-neutral-400">Đội 1 thắng {pred.prob1}% (mô hình Elo)</p>
              </div>
              <div className="card text-center">
                <p className="text-xs uppercase text-neutral-400">Sức mạnh Đội 2</p>
                <p className="text-3xl font-black text-amber-400">{pred.p2}</p>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function Picker({ label, athletes, value, onChange }: { label: string; athletes: Athlete[]; value: Athlete | null; onChange: (a: Athlete | null) => void }) {
  return (
    <div>
      <label className="label">{label}</label>
      <select value={value?.id ?? ''} onChange={(e) => onChange(athletes.find((a) => a.id === Number(e.target.value)) ?? null)} className="w-full">
        <option value="">— Chọn —</option>
        {athletes.map((a) => <option key={a.id} value={a.id}>{a.full_name} (Elo {a.elo_rating})</option>)}
      </select>
    </div>
  );
}
