import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import { error as notifyError, success as notifySuccess } from '../lib/notification';

interface DupeGroup {
  key: string;
  count: number;
  items: Record<string, string | number | null>[];
}

/** Toàn vẹn dữ liệu: hiển thị VĐV/sản phẩm trùng lặp + hướng dẫn xử lý. */
export default function AdminIntegrity() {
  const [data, setData] = useState<{ athletes: DupeGroup[]; equipment: DupeGroup[]; summary: { duplicate_athlete_groups: number; duplicate_equipment_groups: number; hint: string } } | null>(null);
  const [error, setError] = useState('');

  const load = () => api.get<{ data: typeof data }>('/admin/duplicates').then((r) => setData(r.data)).catch((e) => setError(errorMessage(e)));
  useEffect(() => { load(); }, []);

  const merge = async (items: { id: number }[]) => {
    if (items.length < 2) return;
    const keep = items[0].id;
    for (const dup of items.slice(1)) {
      if (!window.confirm(`Gộp hồ sơ #${dup.id} vào #${keep}?`)) continue;
      try {
        await api.post('/admin/athletes-merge', null, { params: { keep, duplicate: dup.id } });
        notifySuccess(`Đã gộp #${dup.id} vào #${keep}.`);
      } catch (e) {
        notifyError('Không gộp được', errorMessage(e));
      }
    }
    load();
  };

  const removeItem = async (type: 'products', id: number) => {
    if (!window.confirm('Xóa sản phẩm trùng #' + id + '?')) return;
    try { await api.delete(`/admin/products/${id}`); notifySuccess('Đã xóa.'); load(); }
    catch (e) { notifyError('Lỗi', errorMessage(e)); }
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">🧬 Toàn vẹn dữ liệu — Trùng lặp</h1>
      {error && <p className="text-sm text-rose-400">{error}</p>}
      {data && (
        <>
          <p className="text-sm text-neutral-400">
            Nhóm VĐV trùng: <b className="text-amber-400">{data.summary.duplicate_athlete_groups}</b> ·
            Nhóm sản phẩm trùng: <b className="text-amber-400">{data.summary.duplicate_equipment_groups}</b> — {data.summary.hint}
          </p>

          <div className="card">
            <h2 className="mb-2 font-semibold">🏃 VĐV trùng (theo họ tên đã chuẩn hóa)</h2>
            {data.athletes.length === 0 ? <p className="text-sm text-emerald-400">Không phát hiện trùng lặp. ✅</p> : data.athletes.map((g) => (
              <div key={g.key} className="mb-3 rounded-lg border border-amber-800/50 bg-amber-500/5 p-3">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-semibold">"{g.key}" × {g.count}</p>
                  <button onClick={() => merge(g.items as { id: number }[])} className="text-xs text-emerald-400 hover:underline">Gộp nhóm này</button>
                </div>
                {g.items.map((it) => (
                  <p key={String(it.id)} className="text-xs text-neutral-400">
                    #{it.id} {it.full_name} ({it.country_code}) · Elo {it.elo_rating}{it.source ? ` · nguồn ${it.source}` : ''}
                  </p>
                ))}
              </div>
            ))}
          </div>

          <div className="card">
            <h2 className="mb-2 font-semibold">🏸 Sản phẩm trùng (cùng thương hiệu + model)</h2>
            {data.equipment.length === 0 ? <p className="text-sm text-emerald-400">Không phát hiện trùng lặp. ✅</p> : data.equipment.map((g) => (
              <div key={g.key} className="mb-3 rounded-lg border border-amber-800/50 bg-amber-500/5 p-3">
                <p className="text-sm font-semibold">"{g.key}" × {g.count}</p>
                {g.items.map((it) => (
                  <div key={String(it.id)} className="flex items-center justify-between text-xs text-neutral-400">
                    <span>#{it.id} {it.name} ({it.type}){it.source ? ` · ${it.source}` : ''}</span>
                    <button onClick={() => removeItem('products', Number(it.id))} className="text-rose-400 hover:underline">Xóa</button>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
