import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api.post('/forgot-password', { email });
      setSent(true);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-md py-10">
      <div className="card">
        <h1 className="mb-1 text-2xl font-bold">Khôi phục mật khẩu</h1>
        {sent ? (
          <p className="text-sm text-neutral-300">
            Nếu email tồn tại trong hệ thống, liên kết đặt lại mật khẩu đã được gửi. Kiểm tra hộp thư (và mục spam).
          </p>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <p className="text-sm text-neutral-400">Nhập email đăng ký, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>
            <div>
              <label className="label">Email</label>
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required className="w-full" />
            </div>
            {error && <p className="text-sm text-rose-400">{error}</p>}
            <button className="btn-primary w-full" disabled={busy}>{busy ? 'Đang gửi…' : 'Gửi liên kết'}</button>
            <p className="text-center text-sm text-neutral-400">
              <Link to="/login" className="text-emerald-400 hover:underline">Quay lại đăng nhập</Link>
            </p>
          </form>
        )}
      </div>
    </div>
  );
}
