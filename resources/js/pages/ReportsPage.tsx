import { useEffect, useState } from 'react';
import {
  Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { api, errorMessage } from '../api';
import type { ReportData } from '../types';

/**
 * Tab 'Báo cáo' (Admin) — Recharts:
 *  - Sản phẩm bán chạy (Bar, theo số lượng + doanh thu)
 *  - Lượt đăng ký thành viên theo tuần/tháng (Line)
 *  - Tần suất sử dụng sân theo tuần/tháng (Bar)
 */
export default function ReportsPage() {
  const [data, setData] = useState<ReportData | null>(null);
  const [granularity, setGranularity] = useState<'weekly' | 'monthly'>('weekly');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: ReportData }>('/admin/reports')
      .then((r) => setData(r.data))
      .catch((e) => setError(errorMessage(e)));
  }, []);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!data) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  const tooltipStyle = { background: '#1d1d20', border: '1px solid #2a2a2f', borderRadius: 8 };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">📊 Báo cáo thống kê</h1>
        <div className="flex gap-2">
          {([['weekly', 'Theo tuần'], ['monthly', 'Theo tháng']] as const).map(([v, l]) => (
            <button
              key={v}
              onClick={() => setGranularity(v)}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${granularity === v ? 'bg-emerald-500 text-neutral-950' : 'border border-neutral-700 text-neutral-300'}`}
            >
              {l}
            </button>
          ))}
        </div>
      </div>

      {/* Sản phẩm bán chạy */}
      <div className="card">
        <h2 className="mb-3 font-semibold">🏆 Sản phẩm bán chạy (Top 10)</h2>
        <div style={{ width: '100%', height: 320 }}>
          <ResponsiveContainer>
            <BarChart data={data.top_products} margin={{ top: 8, right: 16, bottom: 40, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
              <XAxis dataKey="name" tick={{ fill: '#a3a3a3', fontSize: 9 }} angle={-30} textAnchor="end" interval={0} />
              <YAxis tick={{ fill: '#a3a3a3', fontSize: 11 }} allowDecimals={false} />
              <Tooltip contentStyle={tooltipStyle} />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="sold" name="Số lượng bán" fill="#34d399" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Đăng ký thành viên */}
        <div className="card">
          <h2 className="mb-3 font-semibold">👥 Lượt đăng ký thành viên</h2>
          <div style={{ width: '100%', height: 280 }}>
            <ResponsiveContainer>
              <LineChart data={data.member_registrations[granularity]} margin={{ top: 8, right: 16, bottom: 8, left: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
                <XAxis dataKey="label" tick={{ fill: '#a3a3a3', fontSize: 10 }} />
                <YAxis tick={{ fill: '#a3a3a3', fontSize: 11 }} allowDecimals={false} />
                <Tooltip contentStyle={tooltipStyle} />
                <Line type="monotone" dataKey="total" name="Đăng ký mới" stroke="#60a5fa" strokeWidth={2} dot={{ r: 3 }} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Tần suất sử dụng sân */}
        <div className="card">
          <h2 className="mb-3 font-semibold">🏸 Tần suất sử dụng sân (giờ)</h2>
          <div style={{ width: '100%', height: 280 }}>
            <ResponsiveContainer>
              <BarChart data={data.court_usage[granularity]} margin={{ top: 8, right: 16, bottom: 8, left: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#2a2a2f" />
                <XAxis dataKey="label" tick={{ fill: '#a3a3a3', fontSize: 10 }} />
                <YAxis tick={{ fill: '#a3a3a3', fontSize: 11 }} />
                <Tooltip contentStyle={tooltipStyle} />
                <Bar dataKey="total" name="Giờ sử dụng" fill="#fbbf24" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>
      </div>
    </div>
  );
}
