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

interface Streak { current: number; best: number; total_active_days: number }

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
  const [streak, setStreak] = useState<Streak | null>(null);
  const [code, setCode] = useState('');
  const [blocked, setBlocked] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: Streak }>('/my/streak').then((r) => setStreak(r.data)).catch(() => {});
  }, []);

  const confirmCode = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await api.post<{ message: string }>('/matches/confirm', { code });
      notifySuccess('Đã xác nhận trận!', res.data.message);
      setCode('');
      window.location.reload();
    } catch (err) { notifyError('Xác nhận thất bại', errorMessage(err)); }
  };

  const shareMatch = (m: MyMatch) => {
    const canvas = document.createElement('canvas');
    canvas.width = 700; canvas.height = 300;
    const ctx = canvas.getContext('2d')!;
    ctx.fillStyle = '#0a0a0b'; ctx.fillRect(0, 0, 700, 300);
    ctx.fillStyle = '#10b981'; ctx.fillRect(0, 0, 700, 8);
    ctx.fillStyle = '#34d399'; ctx.font = 'bold 22px sans-serif'; ctx.fillText('SmashRank — Kết quả trận đấu', 36, 52);
    ctx.fillStyle = '#fafafa'; ctx.font = 'bold 20px sans-serif';
    const won = m.result === 'win';
    ctx.fillText(won ? '🏆 CHIẾN THẮNG' : '💪 THUA', 36, 100);
    ctx.fillStyle = '#e5e5e5'; ctx.font = '18px sans-serif';
    ctx.fillText(`${m.venue ? m.venue + ' · ' : ''}${m.date}`, 36, 132);
    ctx.fillStyle = '#fafafa'; ctx.font = 'bold 34px sans-serif';
    ctx.fillText(`${m.my_score} - ${m.opp_score}`, 36, 190);
    ctx.fillStyle = '#a3a3a3'; ctx.font = '15px sans-serif';
    ctx.fillText(`vs ${m.opponent ?? '—'}${m.walkover ? ' (W.O.)' : ''}`, 36, 222);
    ctx.fillStyle = '#34d399'; ctx.fillText(window.location.origin + '/dashboard', 36, 266);
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = `smashrank-match-${m.id}.png`;
    a.click();
    notifySuccess('Đã tạo ảnh kết quả trận!');
  };

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

      {/* Streak + Xác nhận trận bằng mã */}
      <div className="grid gap-3 sm:grid-cols-2">
        {streak && (
          <div className="card">
            <h2 className="font-semibold">🔥 Chuỗi hoạt động</h2>
            <p className="mt-1 text-3xl font-black text-amber-400">{streak.current} ngày</p>
            <p className="text-xs text-neutral-400">Kỷ lục: {streak.best} ngày · Tổng {streak.total_active_days} ngày hoạt động</p>
          </div>
        )}
        <form onSubmit={confirmCode} className="card">
          <h2 className="font-semibold">🔐 Xác nhận trận bằng mã</h2>
          <div className="mt-2 flex gap-2">
            <input value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} maxLength={6}
              placeholder="MÃ 6 KÝ TỰ" className="flex-1 text-center font-mono tracking-[0.3em]" />
            <button className="btn-primary text-xs">Xác nhận</button>
          </div>
          <p className="mt-1 text-xs text-neutral-500">Nhập mã đối thủ gửi để cộng Elo cho cả hai.</p>
        </form>
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
                <th className="p-3 text-center">Tỷ số</th><th className="p-3">Kết quả</th><th className="p-3">Chia sẻ</th>
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
                  <td className="p-3">
                    <button onClick={() => shareMatch(m)} className="text-xs text-amber-400 hover:underline" title="Tạo ảnh chia sẻ">🖼️</button>
                  </td>
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
