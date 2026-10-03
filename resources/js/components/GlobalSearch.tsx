import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { api } from '../api';
import type { SearchResult } from '../types';
import { EQUIPMENT_TYPE_VI, LEVEL_VI } from '../types';

const TYPE_CHECKS = [
  ['athletes', '🏃 VĐV'],
  ['clubs', '🏠 CLB'],
  ['equipment', '🏸 Thiết bị'],
  ['tournaments', '🏆 Giải đấu'],
] as const;

/**
 * Global Search — thanh tìm kiếm toàn cục (VĐV / CLB / thiết bị / giải đấu)
 * với các checkbox lọc nhanh theo loại kết quả.
 */
export default function GlobalSearch() {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [types, setTypes] = useState<string[]>(['athletes', 'clubs', 'equipment', 'tournaments']);
  const [result, setResult] = useState<SearchResult | null>(null);
  const boxRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  useEffect(() => {
    if (q.trim().length < 2) { setResult(null); return; }
    const t = setTimeout(() => {
      api.get<{ data: SearchResult }>('/search', { params: { q, types: types.join(',') } })
        .then((r) => { setResult(r.data); setOpen(true); })
        .catch(() => {});
    }, 300);
    return () => clearTimeout(t);
  }, [q, types]);

  const toggleType = (t: string) =>
    setTypes((prev) => prev.includes(t) ? prev.filter((x) => x !== t) : [...prev, t]);

  const total =
    (result?.athletes.length ?? 0) + (result?.clubs.length ?? 0) +
    (result?.equipment.length ?? 0) + (result?.tournaments.length ?? 0);

  return (
    <div ref={boxRef} className="relative ml-2 hidden sm:block">
      <input
        value={q}
        onFocus={() => q && setOpen(true)}
        onChange={(e) => setQ(e.target.value)}
        placeholder="🔍 Tìm toàn cục: VĐV, CLB, mã thiết bị…"
        className="w-52 py-1.5 text-xs lg:w-64"
      />
      <AnimatePresence>
        {open && q.trim().length >= 2 && (
          <motion.div
            initial={{ opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            className="card absolute right-0 z-50 mt-2 max-h-96 w-80 overflow-y-auto !p-3"
          >
            {/* Bộ lọc nhanh theo loại */}
            <div className="mb-2 flex flex-wrap gap-2 border-b border-neutral-800 pb-2">
              {TYPE_CHECKS.map(([key, label]) => (
                <label key={key} className="flex cursor-pointer items-center gap-1 text-xs text-neutral-300">
                  <input
                    type="checkbox"
                    checked={types.includes(key)}
                    onChange={() => toggleType(key)}
                    className="accent-emerald-500"
                  />
                  {label}
                </label>
              ))}
            </div>

            {total === 0 && <p className="py-3 text-center text-xs text-neutral-500">Không có kết quả.</p>}

            {!!result?.athletes.length && (
              <>
                <p className="mb-1 text-[10px] uppercase text-neutral-500">Vận động viên</p>
                {result.athletes.map((a) => (
                  <Link key={a.id} to={`/athletes/${a.id}`} onClick={() => setOpen(false)}
                    className="block rounded-lg px-2 py-1.5 text-sm hover:bg-surface-800">
                    🏃 {a.full_name} <span className="text-xs text-neutral-500">· {a.country_code} · Elo {a.elo_rating}</span>
                  </Link>
                ))}
              </>
            )}

            {!!result?.clubs.length && (
              <>
                <p className="mb-1 mt-2 text-[10px] uppercase text-neutral-500">Câu lạc bộ</p>
                {result.clubs.map((c) => (
                  <Link key={c.club} to={`/rankings`} onClick={() => setOpen(false)}
                    className="block rounded-lg px-2 py-1.5 text-sm hover:bg-surface-800">
                    🏠 {c.club} <span className="text-xs text-neutral-500">· {c.members} thành viên</span>
                  </Link>
                ))}
              </>
            )}

            {!!result?.equipment.length && (
              <>
                <p className="mb-1 mt-2 text-[10px] uppercase text-neutral-500">Thiết bị</p>
                {result.equipment.map((e) => (
                  <Link key={e.id} to={`/products`} onClick={() => setOpen(false)}
                    className="block rounded-lg px-2 py-1.5 text-sm hover:bg-surface-800">
                    🏸 [{EQUIPMENT_TYPE_VI[e.type] ?? e.type}] {e.brand} {e.name}
                    <span className="text-xs text-neutral-500"> · mã {e.model}</span>
                  </Link>
                ))}
              </>
            )}

            {!!result?.tournaments.length && (
              <>
                <p className="mb-1 mt-2 text-[10px] uppercase text-neutral-500">Giải đấu</p>
                {result.tournaments.map((t) => (
                  <Link key={t.id} to={`/tournaments/${t.id}`} onClick={() => setOpen(false)}
                    className="block rounded-lg px-2 py-1.5 text-sm hover:bg-surface-800">
                    🏆 {t.name} <span className="text-xs text-neutral-500">· {LEVEL_VI[t.level as keyof typeof LEVEL_VI] ?? t.level}</span>
                  </Link>
                ))}
              </>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
