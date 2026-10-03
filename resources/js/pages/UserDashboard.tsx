import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import { error as notifyError } from '../lib/notification';

interface MyMatch {
  id: number; date: string; venue?: string | null; opponent?: string | null;
  my_score: number; opp_score: number; result: 'win' | 'loss'; walkover: boolean;
}
interface DashData {
  data: MyMatch[];
  summary: { athlete?: { id: number; full_name: string; elo_rating: number; world_rank?: number | null }; wins: number; losses: number; win_rate: number };
}

/**
 * User Dashboard (Athlete Dashboard cá nhân):
 *  - Tóm tắt: thứ hạng, Elo, W/L gần nhất, trang bị
 *  - Pie tỷ lệ thắng/thua (Recharts) + bảng trận gần đây
 *  - Kiểm tra quyền: chỉ chủ tài khoản (có hồ sơ) hoặc admin xem chi tiết
 *  - Xuất lịch sử thi đấu JSON / CSV
 */
export default function UserDashboard() {
  const { user } = useAuth();
  const [data, setData] = useState<DashData | null>(null);
  const [blocked, setBlocked] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<DashData | { message: string; code: string }>('/my/matches')
      .then((r) => setData('summary' in r.data ? r.data as DashData : null))
      .catch((err) => {
        if (err.response?.data?.code === 'PROFILE_REQUIRED') {
          setBlocked(err.response.data.message);
        } else {
          setError(errorMessage(err));
        }
      });
  }, []);

  const exportFile = async (format: 'json' | 'csv') => {
    try {
      const res = await api.get('/my/matches/export', { params: { format }, responseType: 'blob' });
      const url = URL.createObjectURL(res.data);
      const a = document.createElement('a');
      a.href = url;
      a.download = `my-match-history.${format}`;
      a.click();
      URL.revokeObjectURL(url);
      notifySuccess(`Đã xuất ${format.toUpperCase()}!`);
    } catch (err) { notifyError('Lỗi xuất dữ liệu', errorMessage(err)); }
  };

  if (blocked) {
    return (
      <div className="card mx-auto max-w-lg text-center">
        <p className="text-2xl">🔒</p>
        <p className="mt-2 font-semibold">Chưa đủ quyền xem lịch sử chi tiết</p>
        <p className="mt-1 text-sm text-neutral-400">{blocked}</p>
        <Link to="/profile" className="btn-primary mt-4 inline-block">Hoàn thiện hồ sơ</Link>
      </div>
    );
  }
  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!data) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  const { summary } = data;
  const pieData = [
    { name: 'Thắng', value: summary.wins, color: '#34d399' },
    { name: 'Thua', value: summary.losses, color: '#fb7185' },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">🏠 Dashboard của tôi</h1>
        <div className="flex gap-2">
          <button onClick={() => exportFile('json')} className="btn-ghost text-xs">🗂️ Xuất JSON</button>
          <button onClick={() => exportFile('csv')} className="btn-ghost text-xs">📄 Xuất CSV</button>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div className="card text-center"><p className="text-xs text-neutral-400">Elo hiện tại</p><p className="text-2xl font-bold text-emerald-400">{summary.athlete?.elo_rating ?? '—'}</p></div>
        <div className="card text-center"><p className="text-xs text-neutral-400">Thứ hạng TG</p><p className="text-2xl font-bold">{summary.athlete?.world_rank ? `#${summary.athlete.world_rank}` : '—'}</p></div>
        <div className="card text-center"><p className="text-xs text-neutral-400">Thắng</p><p className="text-2xl font-bold text-emerald-400">{summary.wins}</p></div>
        <div className="card text-center"><p className="text-xs text-neutral-400">Thua</p><p className="text-2xl font-bold text-rose-400">{summary.losses}</p></div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="card">
          <h2 className="mb-3 font-semibold">Tỷ lệ thắng/thua ({summary.win_rate}%)</h2>
          {summary.wins + summary.losses > 0 ? (
            <div style={{ width: '100%', height: 220 }}>
              <ResponsiveContainer>
                <PieChart>
                  <Pie data={pieData} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius="50%" outerRadius="75%">
                    {pieData.map((entry, i) => <Cell key={i} fill={entry.color} />)}
                  </Pie>
                  <Tooltip contentStyle={{ background: '#1d1d20', border: '1px solid #2a2a2f' }} />
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          ) : <p className="text-sm text-neutral-500">Chưa có trận nào được ghi nhận.</p>}
        </div>

        <div className="card lg:col-span-2 overflow-x-auto !p-0">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
                <th className="p-3">Ngày</th><th className="p-3">Địa điểm</th><th className="p-3">Đối thủ</th>
                <th className="p-3 text-center">Tỷ số</th><th className="p-3">Kết quả</th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((m) => (
                <tr key={m.id} className="border-b border-neutral-800/60">
                  <td className="p-3">{m.date}</td>
                  <td className="p-3 text-neutral-400">{m.venue ?? '—'}</td>
                  <td className="p-3 font-semibold">{m.opponent ?? '—'}</td>
                  <td className="p-3 text-center font-mono">{m.walkover ? 'W.O.' : `${m.my_score}-${m.opp_score}`}</td>
                  <td className="p-3">{m.result === 'win' ? <span className="text-emerald-400">Thắng</span> : <span className="text-rose-400">Thua</span>}</td>
                </tr>
              ))}
              {data.data.length === 0 && <tr><td colSpan={5} className="p-6 text-center text-neutral-500">Chưa có trận đấu nào.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      <p className="text-xs text-neutral-500">
        🔒 Lịch sử chi tiết chỉ hiển thị cho <b>chủ tài khoản</b> hoặc <b>quản trị viên</b>.
        {user?.role === 'admin' && ' (Bạn đang xem với quyền admin)'}
      </p>
    </div>
  );
}
