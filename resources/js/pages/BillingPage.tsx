import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import { useAuth } from '../AuthContext';
import { error as notifyError, success as notifySuccess } from '../lib/notification';

interface Payment {
  id: number; user?: string; package_name: string; amount: string;
  method: string; status: string; reference?: string | null; created_at: string; paid_at?: string | null;
}

const PACKAGES = [
  { name: 'Gói tháng — 12 buổi', amount: 1200000 },
  { name: 'Gói 10 buổi linh hoạt', amount: 1500000 },
  { name: 'Gói cá nhân 1-1 (4 buổi)', amount: 2000000 },
];

const METHOD_VI: Record<string, string> = { bank: 'Chuyển khoản', vnpay: 'VNPay', momo: 'MoMo', cash: 'Tiền mặt' };
const STATUS_VI: Record<string, string> = { pending: 'Chờ xác nhận', paid: 'Đã thanh toán', cancelled: 'Đã hủy' };

/** Thanh toán: hóa đơn gói tập (VNPay/MoMo stub — admin xác nhận). */
export default function BillingPage() {
  const { user } = useAuth();
  const [payments, setPayments] = useState<Payment[]>([]);
  const [form, setForm] = useState({ package_name: PACKAGES[0].name, amount: PACKAGES[0].amount, method: 'bank' });
  const [error, setError] = useState('');

  const load = () => api.get<{ data: Payment[] }>('/payments').then((r) => setPayments(r.data)).catch((e) => setError(errorMessage(e)));
  useEffect(() => { load(); }, []);

  const create = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await api.post<{ message: string; gateway_url: string | null }>('/payments', form);
      if (res.data.gateway_url) { window.location.href = res.data.gateway_url; return; }
      notifySuccess(res.data.message);
      load();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  const confirm = async (p: Payment, status: 'paid' | 'cancelled') => {
    try {
      await api.put(`/payments/${p.id}/confirm`, { status });
      notifySuccess('Đã cập nhật hóa đơn.');
      load();
    } catch (err) { notifyError('Lỗi', errorMessage(err)); }
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">💳 Thanh toán gói tập</h1>
      {error && <p className="text-sm text-rose-400">{error}</p>}

      <form onSubmit={create} className="card grid gap-3 sm:grid-cols-4">
        <h2 className="font-semibold sm:col-span-4">➕ Tạo hóa đơn mới</h2>
        <div className="sm:col-span-2">
          <label className="label">Gói tập</label>
          <select value={form.package_name} onChange={(e) => {
            const pkg = PACKAGES.find((p) => p.name === e.target.value);
            setForm({ ...form, package_name: e.target.value, amount: pkg?.amount ?? form.amount });
          }} className="w-full">
            {PACKAGES.map((p) => <option key={p.name} value={p.name}>{p.name}</option>)}
          </select>
        </div>
        <div>
          <label className="label">Số tiền (₫)</label>
          <input type="number" min={10000} value={form.amount} onChange={(e) => setForm({ ...form, amount: Number(e.target.value) })} className="w-full" />
        </div>
        <div>
          <label className="label">Phương thức</label>
          <select value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })} className="w-full">
            {Object.entries(METHOD_VI).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
        </div>
        <div className="sm:col-span-4">
          <button className="btn-primary">🧾 Tạo hóa đơn</button>
          <span className="ml-3 text-xs text-neutral-500">VNPay/MoMo: cấu hình key trong .env để tự chuyển cổng thanh toán.</span>
        </div>
      </form>

      <div className="card overflow-x-auto !p-0">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
              <th className="p-3">Gói</th><th className="p-3">Số tiền</th><th className="p-3">Phương thức</th>
              <th className="p-3">Trạng thái</th><th className="p-3">Ngày tạo</th>
              {user?.role === 'admin' && <><th className="p-3">Học viên</th><th className="p-3"></th></>}
            </tr>
          </thead>
          <tbody>
            {payments.map((p) => (
              <tr key={p.id} className="border-b border-neutral-800/60">
                <td className="p-3 font-semibold">{p.package_name}</td>
                <td className="p-3 font-mono">{Number(p.amount).toLocaleString('vi-VN')} ₫</td>
                <td className="p-3">{METHOD_VI[p.method] ?? p.method}</td>
                <td className="p-3">
                  <span className={`rounded-full px-2 py-0.5 text-xs ${p.status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : p.status === 'pending' ? 'bg-amber-500/20 text-amber-400' : 'bg-neutral-700 text-neutral-300'}`}>
                    {STATUS_VI[p.status] ?? p.status}
                  </span>
                </td>
                <td className="p-3 text-xs text-neutral-400">{new Date(p.created_at).toLocaleDateString('vi-VN')}</td>
                {user?.role === 'admin' && (
                  <>
                    <td className="p-3">{p.user ?? '—'}</td>
                    <td className="p-3 text-right">
                      {p.status === 'pending' && (
                        <>
                          <button onClick={() => confirm(p, 'paid')} className="mr-2 text-xs text-emerald-400 hover:underline">Xác nhận</button>
                          <button onClick={() => confirm(p, 'cancelled')} className="text-xs text-rose-400 hover:underline">Hủy</button>
                        </>
                      )}
                    </td>
                  </>
                )}
              </tr>
            ))}
            {payments.length === 0 && <tr><td colSpan={7} className="p-6 text-center text-neutral-500">Chưa có hóa đơn nào.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
