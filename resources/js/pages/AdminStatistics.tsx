import { useEffect, useState } from 'react';
import {
  CartesianGrid, Legend, ResponsiveContainer, Scatter, ScatterChart, Tooltip, XAxis, YAxis, ZAxis,
} from 'recharts';
import { api, errorMessage } from '../api';

interface StatsData {
  scatter: { athlete: string; racket: string; weight?: string | null; max_tension?: number | null; win_rate: number }[];
  by_weight: { weight: string; avg_win_rate: number; count: number }[];
  activity_heatmap: number[][];
  equipment_types: Record<string, number>;
}

const DAYS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

/**
 * Platform Statistics (Admin):
 *  - Scatter: loại vợt (độ căng tối đa) ↔ hiệu suất thi đấu (tỷ lệ thắng)
 *  - Heatmap: mật độ hoạt động theo ngày trong tuần x giờ (6h-22h)
 */
export default function AdminStatistics() {
  const [data, setData] = useState<StatsData | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: StatsData }>('/admin/statistics')
      .then((r) => setData(r.data))
      .catch((e) => setError(errorMessage(e)));
  }, []);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!data) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  const maxDensity = Math.max(1, ...data.activity_heatmap.flat());

  const heatColor = (v: number) => {
    const ratio = v / maxDensity;
    if (ratio === 0) return 'bg-neutral-900';
    if (ratio < 0.25) return 'bg-emerald-900';
    if (ratio < 0.5) return 'bg-emerald-700';
    if (ratio < 0.75) return 'bg-emerald-500';
    return 'bg-amber-400';
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">🔬 Platform Statistics</h1>

      <div className="card">
        <h2 className="mb-3 font-semibold">Loại vợt ↔ Hiệu suất thi đấu</h2>
        <div style={{ width: '100%', height: 320 }}>
          <ResponsiveContainer>
            <ScatterChart margin={{ top: 8, right: 16, bottom: 24, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
              <XAxis dataKey="max_tension" name="Căng tối đa (lbs)" tick={{ fill: '#a3a3a3', fontSize: 11 }}
                label={{ value: 'Độ căng tối đa (lbs)', position: 'insideBottom', offset: -16, fill: '#737373', fontSize: 11 }} />
              <YAxis dataKey="win_rate" name="Tỷ lệ thắng (%)" tick={{ fill: '#a3a3a3', fontSize: 11 }} />
              <ZAxis range={[60, 60]} />
              <Tooltip contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f' }}
                formatter={(v: number | string, name: string) => [name === 'win_rate' ? `${v}%` : v, name]} />
              <Scatter data={data.scatter} fill="#34d399" />
            </ScatterChart>
          </ResponsiveContainer>
        </div>
        <p className="mt-2 text-xs text-neutral-500">Mỗi điểm = 1 VĐV (độ căng vợt vs tỷ lệ thắng). Nhìn khoa học hơn: bảng trung bình theo cỡ vợt:</p>
        <div className="mt-2 flex flex-wrap gap-2">
          {data.by_weight.map((w) => (
            <span key={w.weight} className="rounded-full border border-neutral-700 px-3 py-1 text-xs">
              Vợt {w.weight}: TB thắng <b className="text-emerald-400">{w.avg_win_rate}%</b> ({w.count} VĐV)
            </span>
          ))}
        </div>
      </div>

      <div className="card">
        <h2 className="mb-3 font-semibold">Heatmap hoạt động trong tuần (theo giờ)</h2>
        <div className="overflow-x-auto">
          <table className="text-xs">
            <thead>
              <tr>
                <th className="p-1"></th>
                {Array.from({ length: 17 }, (_, i) => <th key={i} className="p-1 text-neutral-500">{i + 6}h</th>)}
              </tr>
            </thead>
            <tbody>
              {data.activity_heatmap.map((row, d) => (
                <tr key={d}>
                  <td className="p-1 pr-2 font-semibold text-neutral-400">{DAYS[d]}</td>
                  {row.map((v, h) => (
                    <td key={h} className="p-0.5">
                      <div className={`h-6 w-8 rounded ${heatColor(v)}`} title={`${DAYS[d]} ${h + 6}h: ${v} giờ`} />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <p className="mt-2 text-xs text-neutral-500">Màu càng sáng = mật độ thi đấu/đặt sân càng cao. Xanh đậm → vàng là giờ cao điểm.</p>
      </div>

      <div className="card">
        <h2 className="mb-3 font-semibold">Tổng quan danh mục thiết bị</h2>
        <div className="flex flex-wrap gap-2">
          {Object.entries(data.equipment_types).map(([type, total]) => (
            <span key={type} className="rounded-full border border-neutral-700 px-3 py-1 text-xs">{type}: <b>{total}</b></span>
          ))}
        </div>
      </div>
    </div>
  );
}
