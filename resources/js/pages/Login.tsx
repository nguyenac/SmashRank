import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../AuthContext';
import { errorMessage } from '../api';

export default function Login() {
  const { login, verify2FA, sendEmailCode } = useAuth();
  const navigate = useNavigate();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [challenge, setChallenge] = useState<{ token: string; method: string } | null>(null);
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const res = await login(email, password);
      if (res.twoFactorRequired && res.challengeToken) {
        setChallenge({ token: res.challengeToken, method: res.method ?? 'totp' });
        if (res.method === 'email') await sendEmailCode(res.challengeToken);
      } else {
        navigate(res.setupRequired ? '/2fa/setup' : '/');
      }
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const submitCode = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!challenge) return;
    setBusy(true);
    setError('');
    try {
      await verify2FA(challenge.token, code);
      navigate('/');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-md py-10">
      <div className="card">
        <h1 className="mb-1 text-2xl font-bold">Đăng nhập</h1>
        <p className="mb-6 text-sm text-neutral-400">
          {challenge ? 'Nhập mã xác thực 2 lớp để tiếp tục.' : 'Chào mừng trở lại SmashRank.'}
        </p>

        {!challenge ? (
          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="label">Email</label>
              <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required className="w-full" />
            </div>
            <div>
              <label className="label">Mật khẩu</label>
              <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required className="w-full" />
            </div>
            {error && <p className="text-sm text-rose-400">{error}</p>}
            <button className="btn-primary w-full" disabled={busy}>
              {busy ? 'Đang xử lý…' : 'Đăng nhập'}
            </button>
            <div className="flex justify-between text-sm text-neutral-400">
              <Link to="/forgot-password" className="hover:text-emerald-400">Quên mật khẩu?</Link>
              <Link to="/register" className="hover:text-emerald-400">Tạo tài khoản</Link>
            </div>
          </form>
        ) : (
          <form onSubmit={submitCode} className="space-y-4">
            <p className="text-sm text-neutral-400">
              {challenge.method === 'email'
                ? 'Mã OTP 6 số đã được gửi tới email của bạn.'
                : 'Mở Google Authenticator và nhập mã 6 số hiện tại.'}
            </p>
            <input
              value={code}
              onChange={(e) => setCode(e.target.value)}
              inputMode="numeric"
              maxLength={6}
              placeholder="______"
              className="w-full text-center text-2xl tracking-[0.5em]"
              required
            />
            {error && <p className="text-sm text-rose-400">{error}</p>}
            <button className="btn-primary w-full" disabled={busy}>
              {busy ? 'Đang xác thực…' : 'Xác nhận'}
            </button>
            {challenge.method === 'email' && (
              <button
                type="button"
                className="btn-ghost w-full"
                onClick={() => sendEmailCode(challenge.token)}
              >
                Gửi lại mã OTP
              </button>
            )}
          </form>
        )}
      </div>
    </div>
  );
}
