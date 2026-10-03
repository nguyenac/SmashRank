import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import type { CrawlLogItem } from '../types';

/**
 * Khu vực Crawl & đồng bộ dữ liệu (Admin):
 *  - VĐV: badmintonranks.com + đồng bộ bwfbadminton.com/rankings
 *  - Thương hiệu/sản phẩm: shopvnb.com + đồng bộ badmintoncn.com/cbo_eq/ (dịch EN→ZH)
 */
export default function AdminCrawl() {
  const [logs, setLogs] = useState<CrawlLogItem[]>([]);
  const [busy, setBusy] = useState<string>('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const loadLogs = () => api.get<{ data: CrawlLogItem[] }>('/admin/crawl/logs')
    .then((r) => setLogs(r.data));

  useEffect(() => { loadLogs(); }, []);

  const run = async (target: 'athletes' | 'products') => {
    setBusy(target);
    setMessage('');
    setError('');
    try {
      const res = await api.post<{ message: string }>(`/admin/crawl/${target}`);
      setMessage(res.data.message);
      await loadLogs();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy('');
    }
  };

  const SOURCE_LABEL: Record<string, string> = {
    badmintonranks: 'badmintonranks.com',
    bwfbadminton: 'bwfbadminton.com/rankings',
    shopvnb: 'shopvnb.com',
    badmintoncn: 'badmintoncn.com/cbo_eq/',
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Crawl & Đồng bộ dữ liệu</h1>

      {message && <p className="text-sm text-emerald-400">{message}</p>}
      {error && <p className="text-sm text-rose-400">{error}</p>}

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="card">
          <h2 className="font-semibold">Vận động viên</h2>
          <p className="mt-1 text-xs text-neutral-400">
            Lấy VĐV từ <b>badmintonranks.com</b> → đồng bộ thứ hạng với <b>bwfbadminton.com/rankings</b>
          </p>
          <button onClick={() => run('athletes')} className="btn-primary mt-3" disabled={busy !== ''}>
            {busy === 'athletes' ? 'Đang crawl…' : '⬇️ Crawl VĐV + Sync BWF'}
          </button>
        </div>
        <div className="card">
          <h2 className="font-semibold">Thương hiệu & Sản phẩm</h2>
          <p className="mt-1 text-xs text-neutral-400">
            Lấy từ <b>shopvnb.com</b> → đồng bộ <b>badmintoncn.com/cbo_eq/</b> (dịch EN→ZH)
          </p>
          <button onClick={() => run('products')} className="btn-primary mt-3" disabled={busy !== ''}>
            {busy === 'products' ? 'Đang crawl…' : '⬇️ Crawl Sản phẩm + Sync CN'}
          </button>
        </div>
      </div>

      <p className="text-xs text-neutral-500">
        ⚠️ Kiểm tra robots.txt & điều khoản của từng nguồn trước khi bật lịch tự động; crawler đặt User-Agent
        <code> SmashRankBot</code> và giới hạn tần suất. Chi tiết: <code>docs/CRAWLER.md</code>.
      </p>

      <div className="card overflow-x-auto !p-0">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
              <th className="p-3">Loại</th><th className="p-3">Nguồn</th><th className="p-3 text-right">Tìm thấy</th>
              <th className="p-3 text-right">Cập nhật</th><th className="p-3">Trạng thái</th><th className="p-3">Thời điểm</th>
            </tr>
          </thead>
          <tbody>
            {logs.map((l) => (
              <tr key={l.id} className="border-b border-neutral-800/60">
                <td className="p-3">{l.target === 'athletes' ? 'VĐV' : 'Sản phẩm'}</td>
                <td className="p-3 text-neutral-400">{SOURCE_LABEL[l.source] ?? l.source}</td>
                <td className="p-3 text-right font-mono">{l.items_found}</td>
                <td className="p-3 text-right font-mono text-emerald-400">{l.items_upserted}</td>
                <td className="p-3">
                  <span className={`rounded-full px-2 py-0.5 text-xs ${l.status === 'success' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'}`}>
                    {l.status}
                  </span>
                </td>
                <td className="p-3 text-xs text-neutral-400">{new Date(l.created_at).toLocaleString('vi-VN')}</td>
              </tr>
            ))}
            {logs.length === 0 && <tr><td colSpan={6} className="p-6 text-center text-neutral-500">Chưa có lần crawl nào.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
