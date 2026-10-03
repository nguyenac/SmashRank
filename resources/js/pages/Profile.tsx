import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import RankTrendChart from '../components/RankTrendChart';
import type { Athlete, EquipmentItem } from '../types';

interface ProfileData {
  user: { id: number; name: string; email: string };
  athlete: Athlete | null;
}

const ASSESSMENTS = [
  // 8 tiêu chí chuẩn Việt Vũ
  ['serve', 'Phát cầu'],
  ['smash', 'Đập cầu'],
  ['net_play', 'Cầu gần lưới'],
  ['backhand', 'Trái tay'],
  ['defense', 'Phòng thủ'],
  ['footwork', 'Bước di chuyển'],
  ['endurance', 'Sức bền'],
  ['mentality', 'Tâm lý thi đấu'],
] as const;

export default function Profile() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [data, setData] = useState<ProfileData | null>(null);
  const [items, setItems] = useState<EquipmentItem[]>([]);
  const [form, setForm] = useState<Record<string, string | number | null>>({});
  const [scores, setScores] = useState<Record<string, number>>({});
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<ProfileData>('/profile').then((res) => {
      setData(res.data);
      setForm({
        name: res.data.user.name,
        nationality: res.data.athlete?.nationality ?? '',
        club: res.data.athlete?.club ?? '',
        birth_year: res.data.athlete?.birth_year ?? '',
        dominant_hand: res.data.athlete?.dominant_hand ?? 'right',
        skill_level: res.data.athlete?.skill_level ?? 'beginner',
        racket_id: res.data.athlete?.racket_id ?? '',
        shoes_id: res.data.athlete?.shoes_id ?? '',
      } as Record<string, string | number | null>);
    }).catch((err) => setError(errorMessage(err)));

    api.get<{ data: EquipmentItem[] }>('/products').then((res) => setItems(res.data));
  }, []);

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('');
    setError('');
    try {
      const payload = { ...form, assessment: Object.keys(scores).length ? scores : undefined };
      await api.put('/profile', payload);
      setMessage('Đã lưu hồ sơ thành công!');
      const res = await api.get<ProfileData>('/profile');
      setData(res.data);
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  if (error && !data) return <p className="py-10 text-center text-rose-400">{error}</p>;
  if (!data) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;

  return (
    <div className="space-y-4">
      <div className="card flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">Hồ sơ của tôi</h1>
          <p className="text-sm text-neutral-400">{data.user.email} · Vai trò: {user?.role === 'admin' ? 'Quản trị viên' : 'Thành viên'}</p>
        </div>
        <button className="btn-ghost" onClick={() => navigate('/2fa/setup')}>🔐 Cấu hình 2FA</button>
      </div>

      <form onSubmit={save} className="grid gap-4 lg:grid-cols-2">
        <div className="card space-y-3">
          <h2 className="font-semibold">Thông tin cá nhân</h2>
          <div>
            <label className="label">Họ và tên</label>
            <input value={String(form.name ?? '')} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Quốc tịch</label>
              <input value={String(form.nationality ?? '')} onChange={(e) => setForm({ ...form, nationality: e.target.value })} className="w-full" />
            </div>
            <div>
              <label className="label">Năm sinh</label>
              <input type="number" value={String(form.birth_year ?? '')} onChange={(e) => setForm({ ...form, birth_year: e.target.value })} className="w-full" />
            </div>
          </div>
          <div>
            <label className="label">Câu lạc bộ</label>
            <input value={String(form.club ?? '')} onChange={(e) => setForm({ ...form, club: e.target.value })} className="w-full" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label">Tay thuận</label>
              <select value={String(form.dominant_hand)} onChange={(e) => setForm({ ...form, dominant_hand: e.target.value })} className="w-full">
                <option value="right">Tay phải</option>
                <option value="left">Tay trái</option>
              </select>
            </div>
            <div>
              <label className="label">Trình độ</label>
              <select value={String(form.skill_level)} onChange={(e) => setForm({ ...form, skill_level: e.target.value })} className="w-full">
                <option value="beginner">Mới bắt đầu</option>
                <option value="intermediate">Trung bình khá</option>
                <option value="advanced">Nâng cao</option>
                <option value="pro">Chuyên nghiệp</option>
              </select>
            </div>
          </div>
        </div>

        <div className="card space-y-3">
          <h2 className="font-semibold">Trang bị thi đấu</h2>
          <div>
            <label className="label">Vợt đang dùng</label>
            <select value={String(form.racket_id ?? '')} onChange={(e) => setForm({ ...form, racket_id: e.target.value })} className="w-full">
              <option value="">— Chưa chọn —</option>
              {items.filter((i) => i.type === 'racket').map((i) => <option key={i.id} value={i.id}>{i.brand} {i.name}</option>)}
            </select>
          </div>
          <div>
            <label className="label">Giày đang dùng</label>
            <select value={String(form.shoes_id ?? '')} onChange={(e) => setForm({ ...form, shoes_id: e.target.value })} className="w-full">
              <option value="">— Chưa chọn —</option>
              {items.filter((i) => i.type === 'shoes').map((i) => <option key={i.id} value={i.id}>{i.brand} {i.name}</option>)}
            </select>
          </div>
          <h2 className="pt-2 font-semibold">Tự đánh giá Việt Vũ</h2>
          {ASSESSMENTS.map(([key, label]) => (
            <div key={key} className="flex items-center gap-3">
              <span className="w-32 text-sm text-neutral-300">{label}</span>
              <input
                type="range" min={0} max={100} value={scores[key] ?? 50}
                onChange={(e) => setScores({ ...scores, [key]: Number(e.target.value) })}
                className="flex-1 accent-emerald-500"
              />
              <span className="w-10 text-right font-mono text-sm">{scores[key] ?? 50}</span>
            </div>
          ))}
        </div>

        <div className="lg:col-span-2">
          {message && <p className="mb-3 text-sm text-emerald-400">{message}</p>}
          {error && <p className="mb-3 text-sm text-rose-400">{error}</p>}
          <button className="btn-primary">💾 Lưu hồ sơ</button>
        </div>
      </form>

      {data.athlete && (
        <div className="card">
          <h2 className="mb-3 font-semibold">Xu hướng điểm của bạn</h2>
          {data.athlete.ranking_history?.length
            ? <RankTrendChart data={data.athlete.ranking_history} metric="elo_rating" height={240} />
            : <p className="text-sm text-neutral-500">Tham gia thi đấu để có dữ liệu Elo.</p>}
        </div>
      )}
    </div>
  );
}
