import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';

interface SetupData {
  secret: string;
  otpauth_uri: string;
}

/**
 * Trang cấu hình 2FA — bắt buộc cho mọi người dùng.
 *  - Phương thức TOTP: nhập secret vào Google Authenticator, xác nhận bằng mã 6 số.
 *  - Phương thức Email: nhận mã OTP qua email và xác nhận.
 */
export default function TwoFactorSetup() {
  const { user, refreshMe } = useAuth();
  const navigate = useNavigate();
  const [setup, setSetup] = useState<SetupData | null>(null);
  const [code, setCode] = useState('');
  const [method, setMethod] = useState<'totp' | 'email'>('totp');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const loadSecret = useCallback(async () => {
    try {
      const res = await api.post<SetupData>('/2fa/setup');
      setSetup(res.data);
    } catch (err) {
      setError(errorMessage(err));
    }
  }, []);

  useEffect(() => {
    // Đã bật 2FA rồi thì về trang chủ
    if (user?.two_factor_enabled) navigate('/');
    else loadSecret();
  }, [user, navigate, loadSecret]);

  const enable = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api.post('/2fa/enable', { code, method });
      await refreshMe();
      navigate('/');
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-lg py-10">
      <div className="card">
        <h1 className="mb-1 text-2xl font-bold">Cấu hình xác thực 2 lớp (2FA)</h1>
        <p className="mb-6 text-sm text-neutral-400">
          Bảo mật tài khoản là bắt buộc trên SmashRank. Chọn một trong hai phương thức bên dưới.
        </p>

        <div className="mb-6 grid grid-cols-2 gap-3">
          <button
            onClick={() => setMethod('totp')}
            className={`rounded-xl border p-4 text-left ${method === 'totp' ? 'border-emerald-500 bg-emerald-500/10' : 'border-neutral-700'}`}
          >
            <p className="font-semibold">Google Authenticator</p>
            <p className="mt-1 text-xs text-neutral-400">Mã TOTP 6 số, đổi mỗi 30 giây</p>
          </button>
          <button
            onClick={() => setMethod('email')}
            className={`rounded-xl border p-4 text-left ${method === 'email' ? 'border-emerald-500 bg-emerald-500/10' : 'border-neutral-700'}`}
          >
            <p className="font-semibold">Mã qua Email</p>
            <p className="mt-1 text-xs text-neutral-400">Mã OTP gửi tới email khi đăng nhập</p>
          </button>
        </div>

        {method === 'totp' && setup && (
          <div className="mb-6 space-y-3 text-sm">
            <p className="text-neutral-300">1. Mở Google Authenticator → dấu <b>+</b> → “Nhập mã thiết lập”.</p>
            <p className="text-neutral-300">2. Nhập khóa (secret) dưới đây:</p>
            <code className="block break-all rounded-lg bg-surface-800 p-3 font-mono text-emerald-400">{setup.secret}</code>
            <p className="break-all text-xs text-neutral-500">Hoặc dán URI vào app: {setup.otpauth_uri}</p>
          </div>
        )}

        {method === 'email' && (
          <p className="mb-6 text-sm text-neutral-400">
            Khi đăng nhập, hệ thống sẽ gửi mã OTP 6 số tới <b>{user?.email}</b> (hiệu lực 5 phút).
          </p>
        )}

        <form onSubmit={enable} className="space-y-4">
          <div>
            <label className="label">Nhập mã xác thực để kích hoạt</label>
            <input
              value={code}
              onChange={(e) => setCode(e.target.value)}
              inputMode="numeric"
              maxLength={6}
              placeholder="______"
              className="w-full text-center text-2xl tracking-[0.5em]"
              required
            />
          </div>
          {error && <p className="text-sm text-rose-400">{error}</p>}
          <button className="btn-primary w-full" disabled={busy}>
            {busy ? 'Đang kích hoạt…' : 'Bật 2FA'}
          </button>
        </form>
      </div>
    </div>
  );
}
