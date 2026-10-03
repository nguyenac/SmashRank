import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api';
import CountryPieChart from '../components/CountryPieChart';
import EquipmentBrandBarChart from '../components/EquipmentBrandBarChart';
import type { AnalyticsSummary, QuickStats } from '../types';

const SKILL_VI: Record<string, string> = {
  pro: 'Chuyên nghiệp',
  advanced: 'Nâng cao',
  intermediate: 'Trung bình khá',
  beginner: 'Mới bắt đầu',
};

export default function AdminAnalytics() {
  const [data, setData] = useState<AnalyticsSummary | null>(null);
  const [quick, setQuick] = useState<QuickStats | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: AnalyticsSummary }>('/admin/analytics/summary')
      .then((res) => setData(res.data))
      .catch((err) => setError(errorMessage(err)));
    api.get<{ data: QuickStats }>('/admin/analytics/quick')
      .then((res) => setQuick(res.data))
      .catch(() => {});
  }, []);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!data) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  const stats = [
    ['VĐV mới trong tuần', data.new_this_week, 'text-emerald-400'],
    ['VĐV mới trong tháng', data.new_this_month, 'text-amber-400'],
    ['Tổng số VĐV', data.total_athletes, 'text-sky-400'],
    ['Tài khoản người dùng', data.total_users, 'text-rose-400'],
  ] as const;

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Analytics Dashboard</h1>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {stats.map(([label, value, color]) => (
          <div key={label} className="card text-center">
            <p className="text-xs uppercase tracking-wide text-neutral-400">{label}</p>
            <p className={`mt-1 text-3xl font-black ${color}`}>{value}</p>
          </div>
        ))}
      </div>

      {/* ---------- Xuất dữ liệu: CSV / JSON / PDF ---------- */}
      <div className="card flex flex-wrap items-center gap-2">
        <h2 className="mr-2 font-semibold">⬇️ Xuất dữ liệu</h2>
        <a href="/api/admin/export/athletes.csv" className="btn-ghost text-xs">📄 CSV danh sách VĐV</a>
        <a href="/api/admin/export/all.json" className="btn-ghost text-xs">🗂️ JSON toàn bộ dữ liệu</a>
        <span className="text-xs text-neutral-500">PDF hồ sơ từng VĐV: nút “PDF” ở bảng bên dưới</span>
      </div>

      {/* ---------- Dashboard nhanh: Pie phân nhóm Việt Vũ + Pie kỹ năng + Bar thương hiệu ---------- */}
      {quick && (
        <div className="grid gap-4 lg:grid-cols-3">
          <div className="card">
            <h2 className="mb-3 font-semibold">Phân nhóm trình độ phong trào</h2>
            <CountryPieChart
              data={Object.entries(quick.grassroots_distribution).map(([rank, total]) => ({
                country: rank,
                total,
              }))}
            />
          </div>
          <div className="card">
            <h2 className="mb-3 font-semibold">Cấp độ kỹ năng (BWF)</h2>
            <CountryPieChart
              data={Object.entries(quick.skill_distribution).map(([level, total]) => ({
                country: SKILL_VI[level] ?? level,
                total,
              }))}
            />
          </div>
          <div className="card">
            <h2 className="mb-3 font-semibold">Thiết bị theo thương hiệu</h2>
            <EquipmentBrandBarChart data={quick.equipment_by_brand} />
          </div>
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="card">
          <h2 className="mb-3 font-semibold">Phân bổ VĐV theo quốc gia</h2>
          <CountryPieChart data={data.country_distribution} />
        </div>

        <div className="card">
          <h2 className="mb-3 font-semibold">🚀 VĐV tăng trưởng Elo mạnh nhất</h2>
          <div className="space-y-2">
            {data.top_elo_growers.map((a, i) => (
              <div key={a.id} className="flex items-center gap-3 rounded-lg border border-neutral-800 p-3 transition hover:border-emerald-600">
                <span className="w-6 text-center font-bold text-neutral-500">{i + 1}</span>
                <Link to={`/athletes/${a.id}`} className="min-w-0 flex-1">
                  <p className="truncate font-semibold">{a.full_name}</p>
                  <p className="text-xs text-neutral-400">{a.elo_prev} → {a.elo_now}</p>
                </Link>
                <span className="font-mono font-bold text-emerald-400">+{a.elo_growth}</span>
                <a href={`/api/admin/export/athlete/${a.id}.pdf`} className="text-xs text-amber-400 hover:underline" title="Xuất PDF hồ sơ">PDF</a>
              </div>
            ))}
            {data.top_elo_growers.length === 0 && <p className="text-sm text-neutral-500">Chưa có dữ liệu tăng trưởng.</p>}
          </div>
        </div>
      </div>

      <div className="card">
        <h2 className="mb-3 font-semibold">Phân bố trình độ</h2>
        <div className="flex flex-wrap gap-3">
          {Object.entries(data.skill_levels).map(([level, count]) => (
            <div key={level} className="rounded-xl border border-neutral-800 px-4 py-3">
              <p className="text-xs uppercase text-neutral-400">{SKILL_VI[level] ?? level}</p>
              <p className="text-xl font-bold">{count}</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
