import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { api, errorMessage } from '../api';

interface Demographics {
  age_distribution: { label: string; total: number }[];
  nationality_distribution: { country: string; country_code: string; total: number }[];
  skill_distribution: Record<string, number>;
  grassroots_distribution: Record<string, number>;
}

const COLORS = ['#34d399', '#fbbf24', '#fb7185', '#60a5fa', '#a78bfa', '#f97316', '#22d3ee', '#e879f9', '#4ade80', '#facc15', '#f472b6', '#38bdf8', '#a3e635', '#fb923c', '#94a3b8'];

const SKILL_VI: Record<string, string> = { pro: 'Chuyên nghiệp', advanced: 'Nâng cao', intermediate: 'Trung bình khá', beginner: 'Mới bắt đầu' };

/** 'Analytics Hub' — phân bố độ tuổi, quốc tịch, trình độ kỹ thuật (Recharts). */
export default function AnalyticsHub() {
  const [data, setData] = useState<Demographics | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: Demographics }>('/demographics')
      .then((r) => setData(r.data))
      .catch((e) => setError(errorMessage(e)));
  }, []);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!data) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  const tooltipStyle = { background: '#1d1d20', border: '1px solid #2a2a2f', borderRadius: 8 };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">🌐 Analytics Hub</h1>
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="card">
          <h2 className="mb-3 font-semibold">Phân bố độ tuổi</h2>
          <div style={{ width: '100%', height: 280 }}>
            <ResponsiveContainer>
              <BarChart data={data.age_distribution}>
                <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
                <XAxis dataKey="label" tick={{ fill: '#a3a3a3', fontSize: 11 }} />
                <YAxis allowDecimals={false} tick={{ fill: '#a3a3a3', fontSize: 11 }} />
                <Tooltip contentStyle={tooltipStyle} />
                <Bar dataKey="total" name="Số VĐV" fill="#60a5fa" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="card">
          <h2 className="mb-3 font-semibold">Trình độ kỹ thuật (BWF)</h2>
          <div style={{ width: '100%', height: 280 }}>
            <ResponsiveContainer>
              <PieChart>
                <Pie data={Object.entries(data.skill_distribution).map(([k, v]) => ({ name: SKILL_VI[k] ?? k, value: v }))}
                  dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius="75%" innerRadius="45%" paddingAngle={2}>
                  {Object.keys(data.skill_distribution).map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Tooltip contentStyle={tooltipStyle} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="card lg:col-span-2">
          <h2 className="mb-3 font-semibold">Phân bố quốc tịch (Top 15)</h2>
          <div style={{ width: '100%', height: 340 }}>
            <ResponsiveContainer>
              <BarChart data={data.nationality_distribution} layout="vertical">
                <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
                <XAxis type="number" allowDecimals={false} tick={{ fill: '#a3a3a3', fontSize: 11 }} />
                <YAxis type="category" dataKey="country" width={110} tick={{ fill: '#a3a3a3', fontSize: 11 }} />
                <Tooltip contentStyle={tooltipStyle} />
                <Bar dataKey="total" name="Số VĐV" radius={[0, 4, 4, 0]}>
                  {data.nationality_distribution.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="card lg:col-span-2">
          <h2 className="mb-3 font-semibold">Hạng phong trào Việt Vũ</h2>
          <div className="flex flex-wrap gap-2">
            {Object.entries(data.grassroots_distribution).map(([rank, total]) => (
              <span key={rank} className="rounded-full border border-emerald-800 bg-emerald-500/10 px-4 py-2 text-sm">
                {rank}: <b className="text-emerald-400">{total}</b>
              </span>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
