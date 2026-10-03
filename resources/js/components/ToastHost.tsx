import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';
import { dismiss, STYLES, subscribe } from '../lib/notification';
import type { NotificationItem } from '../lib/notification';

/** Container hiển thị thông báo (Notification Manager) — góc phải trên, có animation. */
export default function ToastHost() {
  const [items, setItems] = useState<NotificationItem[]>([]);

  useEffect(() => subscribe(setItems), []);

  return (
    <div className="pointer-events-none fixed right-4 top-20 z-50 flex w-80 flex-col gap-2">
      <AnimatePresence>
        {items.map((item) => {
          const s = STYLES[item.kind];
          return (
            <motion.div
              key={item.id}
              initial={{ opacity: 0, x: 60 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: 60 }}
              className={`pointer-events-auto rounded-xl border ${s.border} ${s.bg} p-3 shadow-lg backdrop-blur`}
            >
              <div className="flex items-start gap-2">
                <span>{s.icon}</span>
                <div className="min-w-0 flex-1">
                  <p className={`text-sm font-semibold ${s.text}`}>{item.title}</p>
                  {item.message && <p className="mt-0.5 text-xs text-neutral-300">{item.message}</p>}
                </div>
                <button onClick={() => dismiss(item.id)} className="text-neutral-500 hover:text-neutral-300">✕</button>
              </div>
            </motion.div>
          );
        })}
      </AnimatePresence>
    </div>
  );
}
