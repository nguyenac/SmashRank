import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import type { EquipmentItem } from '../types';

interface FormState {
  id?: number;
  name: string;
  type: 'racket' | 'shoes';
  brand: string;
  model: string;
  description: string;
  price: string;
  specifications_json: string;
}

const EMPTY: FormState = {
  name: '', type: 'racket', brand: '', model: '', description: '', price: '', specifications_json: '',
};

export default function AdminProducts() {
  const [items, setItems] = useState<EquipmentItem[]>([]);
  const [form, setForm] = useState<FormState>(EMPTY);
  const [editing, setEditing] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const load = () => {
    api.get<{ data: EquipmentItem[] }>('/products', { params: { per_page: 100 } })
      .then((res) => setItems(res.data))
      .catch((err) => setError(errorMessage(err)));
  };

  useEffect(load, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage('');
    setError('');
    try {
      const payload: Record<string, unknown> = {
        name: form.name, type: form.type, brand: form.brand, model: form.model,
        description: form.description || null,
        price: form.price ? Number(form.price) : null,
        specifications: form.specifications_json ? JSON.parse(form.specifications_json) : null,
      };
      if (editing && form.id) await api.put(`/admin/products/${form.id}`, payload);
      else await api.post('/admin/products', payload);
      setMessage(editing ? 'Đã cập nhật sản phẩm.' : 'Đã thêm sản phẩm.');
      setForm(EMPTY);
      setEditing(false);
      load();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  const edit = (item: EquipmentItem) => {
    setEditing(true);
    setForm({
      id: item.id,
      name: item.name,
      type: item.type,
      brand: item.brand,
      model: item.model,
      description: item.description ?? '',
      price: item.price ? String(item.price) : '',
      specifications_json: item.specifications ? JSON.stringify(item.specifications, null, 2) : '',
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const remove = async (item: EquipmentItem) => {
    if (!window.confirm(`Xóa sản phẩm "${item.name}"?`)) return;
    try {
      await api.delete(`/admin/products/${item.id}`);
      setMessage('Đã xóa sản phẩm.');
      load();
    } catch (err) {
      setError(errorMessage(err));
    }
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Quản lý sản phẩm (Vợt & Giày)</h1>
      {message && <p className="text-sm text-emerald-400">{message}</p>}
      {error && <p className="text-sm text-rose-400">{error}</p>}

      <form onSubmit={submit} className="card grid gap-3 sm:grid-cols-2">
        <h2 className="font-semibold sm:col-span-2">{editing ? '✏️ Sửa sản phẩm' : '➕ Thêm sản phẩm mới'}</h2>
        <div>
          <label className="label">Tên sản phẩm *</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required className="w-full" />
        </div>
        <div>
          <label className="label">Loại sản phẩm *</label>
          <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value as 'racket' | 'shoes' })} className="w-full">
            <option value="racket">Vợt</option>
            <option value="shoes">Giày</option>
          </select>
        </div>
        <div>
          <label className="label">Thương hiệu *</label>
          <input value={form.brand} onChange={(e) => setForm({ ...form, brand: e.target.value })} required className="w-full" />
        </div>
        <div>
          <label className="label">Model *</label>
          <input value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value })} required className="w-full" />
        </div>
        <div className="sm:col-span-2">
          <label className="label">Mô tả</label>
          <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={2} className="w-full" />
        </div>
        <div>
          <label className="label">Giá (₫, tùy chọn)</label>
          <input type="number" min={0} value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} className="w-full" />
        </div>
        <div>
          <label className="label">Thông số (JSON, tùy chọn)</label>
          <input value={form.specifications_json} onChange={(e) => setForm({ ...form, specifications_json: e.target.value })}
            placeholder='{"weight":"4U","max_tension":28}' className="w-full font-mono text-xs" />
        </div>
        <div className="flex gap-2 sm:col-span-2">
          <button className="btn-primary">{editing ? 'Cập nhật' : 'Thêm mới'}</button>
          {editing && (
            <button type="button" className="btn-ghost" onClick={() => { setEditing(false); setForm(EMPTY); }}>Hủy</button>
          )}
        </div>
      </form>

      <div className="card overflow-x-auto !p-0">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-neutral-800 text-left text-xs uppercase text-neutral-400">
              <th className="p-3">Tên</th><th className="p-3">Loại</th><th className="p-3">Thương hiệu</th>
              <th className="p-3">Model</th><th className="p-3 text-right">Giá</th><th className="p-3"></th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id} className="border-b border-neutral-800/60">
                <td className="p-3 font-semibold">{item.name}</td>
                <td className="p-3">{item.type === 'racket' ? '🏸 Vợt' : '👟 Giày'}</td>
                <td className="p-3">{item.brand}</td>
                <td className="p-3 text-neutral-400">{item.model}</td>
                <td className="p-3 text-right font-mono">{item.price ? `${Number(item.price).toLocaleString('vi-VN')} ₫` : '—'}</td>
                <td className="p-3 text-right">
                  <button onClick={() => edit(item)} className="mr-2 text-xs text-emerald-400 hover:underline">Sửa</button>
                  <button onClick={() => remove(item)} className="text-xs text-rose-400 hover:underline">Xóa</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
