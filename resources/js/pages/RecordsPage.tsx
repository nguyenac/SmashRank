import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, errorMessage } from '../api';
import type { RecordItem } from '../types';

/** Trang [Danh sách Kỷ lục] — gồm cả các kỷ lục mới (tỷ lệ thắng năm, chuỗi thắng, VĐ trẻ nhất/lớn tuổi nhất...). */
export default function RecordsPage() {
  const [records, setRecords] = useState<RecordItem[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get<{ data: RecordItem[] }>('/records')
      .then((res) => setRecords(res.data))
      .catch((err) => setError(errorMessage(err)));
  }, []);

  if (error) return <p className="py-10 text-center text-rose-400">{error}</p>;

  const grouped = records.reduce<Record<string, RecordItem[]>>((acc, r) => {
    (acc[r.type] ??= []).push(r);
    return acc;
  }, {});

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-bold">Danh sách Kỷ lục</h1>
        <p className="text-sm text-neutral-400">
          Tỷ lệ thắng trong năm (≥ 50 trận) · Chuỗi trận thắng sự nghiệp (có W.O.) · Chuỗi thắng xuyên giải Super 1000→100 ·
          VĐ trẻ nhất/lớn tuổi nhất (Super 1000→300) · Nhiều danh hiệu/chung kết/trận/thắng nhất trong năm
        </p>
      </div>

      {records.length === 0 && <p className="text-center text-neutral-400">Chưa có kỷ lục nào. Chạy <code>php artisan records:rebuild</code>.</p>}

      {Object.entries(grouped).map(([type, items]) => (
        <section key={type} className="card">
          {items.map((r) => (
            <div key={r.id} className="flex flex-wrap items-center gap-4 border-b border-neutral-800 py-3 last:border-0">
              <div className="min-w-0 flex-1">
                <p className="font-semibold">{r.title}</p>
                {r.period && <p className="text-xs text-neutral-500">{r.period}</p>}
                {r.description && <p className="text-xs text-neutral-400">{r.description}</p>}
              </div>
              <p className="font-mono text-lg font-bold text-amber-400">{r.value}</p>
              {r.athlete && (
                <Link to={`/athletes/${r.athlete.id}`} className="flex items-center gap-2 text-sm hover:text-emerald-400">
                  <span>{r.athlete.full_name}</span>
                </Link>
              )}
            </div>
          ))}
        </section>
      ))}
    </div>
  );
}
