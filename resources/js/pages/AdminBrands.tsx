import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import type { Brand } from '../types';

/** Quản lý danh mục thương hiệu: thêm/sửa/xóa — sản phẩm (vợt/giày) thuộc về một thương hiệu. */
export default function AdminBrands() {
  const [brands, setBrands] = useState<Brand[]>([]);
  const [form, setForm] = useState({ id: 0, name: '', country: '', description: '' });
  const [editing, setEditing] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const load = () => api.get<{ data: Brand[] }>('/brands').then((r) => setBrands(r.data));
  useEffect(() => { load().catch((e) => setError(errorMessage(e))); }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('');
    setError('');
    try {
      const payload = { name: form.name, country: form.country || null, description: form.description || null };
      if (editing) await api.put(`/admin/brands/${form.id}`, payload);
      else await api.post('/admin/brands', payload);
      setMessage(editing ? 'Đã cập nhật thương hiệu.' : 'Đã thêm thương hiệu.');
      setForm({ id: 0, name: '', country: '', description: '' });
      setEditing(false);
      load();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  const remove = async (brand: Brand) => {
    if (!window.confirm(`Xóa thương hiệu "${brand.name}"?`)) return;
    try {
      await api.delete(`/admin/brands/${brand.id}`);
      setMessage('Đã xóa thương hiệu.');
      load();
    } catch (err) {
      setError(errorMessage(err)); // 422 khi còn sản phẩm
    }
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Quản lý thương hiệu</h1>
      {message && <p className="text-sm text-emerald-400">{message}</p>}
      {error && <p className="text-sm text-rose-400">{error}</p>}

      <form onSubmit={submit} className="card grid gap-3 sm:grid-cols-2">
        <h2 className="font-semibold sm:col-span-2">{editing ? '✏️ Sửa thương hiệu' : '➕ Thêm thương hiệu'}</h2>
        <div>
          <label className="label">Tên thương hiệu *</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required className="w-full" />
        </div>
        <div>
          <label className="label">Quốc gia</label>
          <input value={form.country} onChange={(e) => setForm({ ...form, country: e.target.value })} className="w-full" />
        </div>
        <div className="sm:col-span-2">
          <label className="label">Mô tả</label>
          <input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="w-full" />
        </div>
        <div className="flex gap-2 sm:col-span-2">
          <button className="btn-primary">{editing ? 'Cập nhật' : 'Thêm mới'}</button>
          {editing && <button type="button" className="btn-ghost" onClick={() => { setEditing(false); setForm({ id: 0, name: '', country: '', description: '' }); }}>Hủy</button>}
        </div>
      </form>

      <div className="card overflow-x-auto !p-0">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
              <th className="p-3">Thương hiệu</th><th className="p-3">Quốc gia</th>
              <th className="p-3 text-right">Vợt</th><th className="p-3 text-right">Giày</th><th className="p-3"></th>
            </tr>
          </thead>
          <tbody>
            {brands.map((b) => (
              <tr key={b.id} className="border-b border-neutral-800/60">
                <td className="p-3 font-semibold">{b.name}</td>
                <td className="p-3 text-neutral-400">{b.country ?? '—'}</td>
                <td className="p-3 text-right font-mono">{b.rackets_count ?? 0}</td>
                <td className="p-3 text-right font-mono">{b.shoes_count ?? 0}</td>
                <td className="p-3 text-right">
                  <button
                    onClick={() => { setEditing(true); setForm({ id: b.id, name: b.name, country: b.country ?? '', description: b.description ?? '' }); }}
                    className="mr-2 text-xs text-emerald-400 hover:underline"
                  >
                    Sửa
                  </button>
                  <button onClick={() => remove(b)} className="text-xs text-rose-400 hover:underline">Xóa</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
