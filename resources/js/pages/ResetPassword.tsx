import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, errorMessage } from '../api';

export default function ResetPassword() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const email = params.get('email') ?? '';
  const token = params.get('token') ?? '';
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api.post('/reset-password', { email, token, password, password_confirmation: confirmation });
      alert('Đã đặt lại mật khẩu. Vui lòng đăng nhập lại.');
      navigate('/login');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  if (!email || !token) {
    return (
      <div className="mx-auto max-w-md py-10 text-center">
        <p className="text-neutral-400">Liên kết không hợp lệ.</p>
        <Link to="/forgot-password" className="mt-3 inline-block text-emerald-400 hover:underline">Yêu cầu liên kết mới</Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-md py-10">
      <div className="card">
        <h1 className="mb-6 text-2xl font-bold">Đặt lại mật khẩu</h1>
        <form onSubmit={submit} className="space-y-4">
          <div>
            <label className="label">Mật khẩu mới</label>
            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} className="w-full" />
          </div>
          <div>
            <label className="label">Xác nhận mật khẩu</label>
            <input type="password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} required className="w-full" />
          </div>
          {error && <p className="text-sm text-rose-400">{error}</p>}
          <button className="btn-primary w-full" disabled={busy}>{busy ? 'Đang lưu…' : 'Đặt lại mật khẩu'}</button>
        </form>
      </div>
    </div>
  );
}
