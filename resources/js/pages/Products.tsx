import { useEffect, useState } from 'react';
import { api, errorMessage } from '../api';
import type { EquipmentItem } from '../types';
import { EQUIPMENT_TYPE_VI } from '../types';

export default function Products() {
  const [items, setItems] = useState<EquipmentItem[]>([]);
  const [brands, setBrands] = useState<{ id: number; name: string }[]>([]);
  const [type, setType] = useState('');
  const [brand, setBrand] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [q, setQ] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: EquipmentItem[] }>('/products', {
      params: { type, q, brand, ...(maxPrice ? { max_price: maxPrice } : {}) },
    })
      .then((res) => setItems(res.data))
      .catch((err) => setError(errorMessage(err)));
  }, [type, q, brand, maxPrice]);

  useEffect(() => {
    api.get<{ data: { id: number; name: string }[] }>('/brands').then((r) => setBrands(r.data)).catch(() => {});
  }, []);

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Vợt & Giày cầu lông</h1>
      <div className="card flex flex-wrap gap-3">
        <input placeholder="🔍 Tìm sản phẩm…" value={q} onChange={(e) => setQ(e.target.value)} className="min-w-48 flex-1" />
        <select value={type} onChange={(e) => setType(e.target.value)}>
          <option value="">Tất cả loại</option>
          {Object.entries(EQUIPMENT_TYPE_VI).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select value={brand} onChange={(e) => setBrand(e.target.value)}>
          <option value="">Tất cả thương hiệu</option>
          {brands.map((b) => <option key={b.id} value={b.name}>{b.name}</option>)}
        </select>
        <select value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)}>
          <option value="">Mọi mức giá</option>
          <option value="1000000">≤ 1 triệu ₫</option>
          <option value="2000000">≤ 2 triệu ₫</option>
          <option value="5000000">≤ 5 triệu ₫</option>
        </select>
      </div>

      {error && <p className="text-sm text-rose-400">{error}</p>}

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => (
          <div key={item.id} className="card">
            {item.image_url && (
              <img src={item.image_url} alt={item.name} className="mb-3 h-40 w-full rounded-lg object-cover" />
            )}
            <span className={`text-xs font-semibold ${item.type === 'racket' ? 'text-emerald-400' : 'text-amber-400'}`}>
              {item.type === 'racket' ? '🏸 Vợt' : '👟 Giày'}
            </span>
            <h3 className="mt-1 font-bold">{item.name}</h3>
            <p className="text-sm text-neutral-400">{item.brand} · {item.model}</p>
            {item.description && <p className="mt-2 line-clamp-2 text-sm text-neutral-400">{item.description}</p>}
            {item.price && <p className="mt-2 font-mono font-semibold text-amber-400">{Number(item.price).toLocaleString('vi-VN')} ₫</p>}
            {item.specifications && Object.keys(item.specifications).length > 0 && (
              <div className="mt-3 flex flex-wrap gap-1">
                {Object.entries(item.specifications).slice(0, 4).map(([k, v]) => (
                  <span key={k} className="rounded-full bg-surface-800 px-2 py-0.5 text-[10px] text-neutral-300">
                    {k}: {String(v)}
                  </span>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
      {items.length === 0 && !error && <p className="text-center text-neutral-400">Không có sản phẩm nào.</p>}
    </div>
  );
}
