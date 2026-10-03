import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, errorMessage } from '../api';

export default function Register() {
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await api.post('/register', form);
      localStorage.setItem('smashrank_token', res.data.token);
      localStorage.setItem('smashrank_user', JSON.stringify(res.data.user));
      navigate('/2fa/setup'); // bắt buộc cấu hình 2FA ngay sau đăng ký
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-md py-10">
      <div className="card">
        <h1 className="mb-1 text-2xl font-bold">Tạo tài khoản</h1>
        <p className="mb-6 text-sm text-neutral-400">Tham gia cộng đồng SmashRank — miễn phí.</p>
        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="label">Họ và tên</label>
            <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required className="w-full" />
          </div>
          <div>
            <label className="label">Email</label>
            <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required className="w-full" />
          </div>
          <div>
            <label className="label">Mật khẩu (tối thiểu 8 ký tự)</label>
            <input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required minLength={8} className="w-full" />
          </div>
          <div>
            <label className="label">Xác nhận mật khẩu</label>
            <input type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required className="w-full" />
          </div>
          {error && <p className="text-sm text-rose-400">{error}</p>}
          <button className="btn-primary w-full" disabled={busy}>
            {busy ? 'Đang tạo…' : 'Đăng ký'}
          </button>
          <p className="text-center text-sm text-neutral-400">
            Đã có tài khoản? <Link to="/login" className="text-emerald-400 hover:underline">Đăng nhập</Link>
          </p>
        </form>
      </div>
    </div>
  );
}
