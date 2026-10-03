import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import type { BackupRecord, BackupSettings } from '../types';

export default function AdminBackups() {
  const [settings, setSettings] = useState<BackupSettings | null>(null);
  const [records, setRecords] = useState<BackupRecord[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = () => {
    api.get<{ data: BackupSettings }>('/admin/backups/settings').then((res) => setSettings(res.data));
    api.get<{ data: BackupRecord[] }>('/admin/backups').then((res) => setRecords(res.data));
  };

  useEffect(load, []);

  const saveSettings = async () => {
    if (!settings) return;
    setMessage('');
    setError('');
    try {
      await api.put('/admin/backups/settings', {
        frequency: settings.frequency,
        enabled: settings.enabled,
        drive_folder_id: settings.drive_folder_id ?? '',
      });
      setMessage('Đã lưu cấu hình sao lưu.');
      load();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  const runNow = async () => {
    setBusy(true);
    setMessage('');
    setError('');
    try {
      const res = await api.post<{ message: string }>('/admin/backups/run');
      setMessage(res.data.message);
      load();
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const restore = async (record: BackupRecord) => {
    if (!window.confirm(
      `PHỤC HỒI sẽ GHI ĐÈ toàn bộ CSDL hiện tại bằng bản sao lưu "${record.filename}".\nBạn chắc chắn chứ?`,
    )) return;
    setBusy(true);
    setError('');
    try {
      const res = await api.post<{ message: string }>(`/admin/backups/${record.id}/restore`, { confirmation: 'yes' });
      setMessage(res.data.message);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const statusBadge = (status: string) => ({
    uploaded: 'bg-emerald-500/20 text-emerald-400',
    local_only: 'bg-amber-500/20 text-amber-400',
    failed: 'bg-rose-500/20 text-rose-400',
    pending: 'bg-neutral-700 text-neutral-300',
  }[status] ?? 'bg-neutral-700 text-neutral-300');

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Sao lưu dữ liệu → Google Drive</h1>

      {message && <p className="text-sm text-emerald-400">{message}</p>}
      {error && <p className="text-sm text-rose-400">{error}</p>}

      <div className="card grid gap-3 sm:grid-cols-3">
        <h2 className="font-semibold sm:col-span-3">Lịch sao lưu tự động</h2>
        {settings ? (
          <>
            <div>
              <label className="label">Tần suất</label>
              <select
                value={settings.frequency}
                onChange={(e) => setSettings({ ...settings, frequency: e.target.value as BackupSettings['frequency'] })}
                className="w-full"
              >
                <option value="daily">Hàng ngày</option>
                <option value="weekly">Hàng tuần</option>
                <option value="monthly">Hàng tháng</option>
              </select>
            </div>
            <div>
              <label className="label">Trạng thái</label>
              <select
                value={settings.enabled ? '1' : '0'}
                onChange={(e) => setSettings({ ...settings, enabled: e.target.value === '1' })}
                className="w-full"
              >
                <option value="1">Đang bật</option>
                <option value="0">Tạm tắt</option>
              </select>
            </div>
            <div>
              <label className="label">Drive Folder ID (tùy chọn)</label>
              <input
                value={settings.drive_folder_id ?? ''}
                onChange={(e) => setSettings({ ...settings, drive_folder_id: e.target.value })}
                className="w-full"
              />
            </div>
            <div className="flex items-center gap-3 sm:col-span-3">
              <button onClick={saveSettings} className="btn-primary">💾 Lưu cấu hình</button>
              <button onClick={runNow} className="btn-ghost" disabled={busy}>
                {busy ? 'Đang sao lưu…' : '▶️ Sao lưu ngay'}
              </button>
              <span className={`rounded-full px-3 py-1 text-xs ${settings.drive_configured ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}`}>
                {settings.drive_configured ? 'Google Drive đã kết nối' : 'Google Drive chưa cấu hình (.env)'}
              </span>
              {settings.last_run_at && (
                <span className="text-xs text-neutral-400">Lần chạy gần nhất: {new Date(settings.last_run_at).toLocaleString('vi-VN')}</span>
              )}
            </div>
          </>
        ) : <p className="text-sm text-neutral-400">Đang tải cấu hình…</p>}
      </div>

      <div className="card overflow-x-auto !p-0">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
              <th className="p-3">File</th><th className="p-3">Kích thước</th><th className="p-3">Trạng thái</th>
              <th className="p-3">Loại</th><th className="p-3">Thời điểm</th><th className="p-3"></th>
            </tr>
          </thead>
          <tbody>
            {records.map((r) => (
              <tr key={r.id} className="border-b border-neutral-800/60">
                <td className="p-3 font-mono text-xs">{r.filename}</td>
                <td className="p-3 font-mono">{r.size_kb.toLocaleString()} KB</td>
                <td className="p-3"><span className={`rounded-full px-2 py-0.5 text-xs ${statusBadge(r.status)}`}>{r.status}</span></td>
                <td className="p-3 text-neutral-400">{r.type === 'manual' ? 'Thủ công' : 'Tự động'}</td>
                <td className="p-3 text-neutral-400">{new Date(r.created_at).toLocaleString('vi-VN')}</td>
                <td className="p-3 text-right">
                  {r.drive_file_id && (
                    <button onClick={() => restore(r)} disabled={busy} className="text-xs text-amber-400 hover:underline">
                      Phục hồi
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {records.length === 0 && (
              <tr><td colSpan={6} className="p-6 text-center text-neutral-500">Chưa có bản sao lưu nào.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-neutral-500">
        Lịch tự động chạy lúc 02:00 mỗi ngày qua Laravel Scheduler (cron <code>php artisan schedule:run</code>);
        lệnh <code>backup:run</code> tự kiểm tra tần suất daily/weekly/monthly đã cấu hình.
        Hướng dẫn cấp quyền Google Drive: xem <code>docs/BACKUP_GOOGLE_DRIVE.md</code>.
      </p>
    </div>
  );
}
