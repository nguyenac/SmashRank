import { useEffect, useRef, useState } from 'react';
import { api, errorMessage } from '../api';
import { error as notifyError } from '../lib/notification';

interface Thread { user: { id: number; name: string }; last_message?: string | null; unread: number }
interface Msg { id: number; mine: boolean; sender: string; body: string; created_at: string }

/**
 * Chat học viên ↔ HLV — polling 3s (nâng cấp WebSocket/Reverb sau, schema giữ nguyên).
 */
export default function ChatPage() {
  const [threads, setThreads] = useState<Thread[]>([]);
  const [active, setActive] = useState<number | null>(null);
  const [messages, setMessages] = useState<Msg[]>([]);
  const [body, setBody] = useState('');
  const bottomRef = useRef<HTMLDivElement>(null);
  const lastIdRef = useRef(0);

  useEffect(() => {
    api.get<{ data: Thread[] }>('/chat/threads').then((r) => setThreads(r.data)).catch((e) => notifyError('Lỗi', errorMessage(e)));
  }, []);

  // Polling tin nhắn hội thoại đang mở
  useEffect(() => {
    if (!active) return;
    lastIdRef.current = 0;
    setMessages([]);
    const load = () => {
      api.get<{ data: Msg[] }>('/chat/messages', { params: { with: active, after: lastIdRef.current } })
        .then((r) => {
          if (r.data.length) {
            lastIdRef.current = r.data[r.data.length - 1].id;
            setMessages((prev) => [...prev, ...r.data]);
          }
        })
        .catch(() => {});
    };
    load();
    const timer = setInterval(load, 3000);
    return () => clearInterval(timer);
  }, [active]);

  useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);

  const send = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!active || !body.trim()) return;
    try {
      await api.post('/chat/send', { to: active, body });
      setBody('');
      const r = await api.get<{ data: Msg[] }>('/chat/messages', { params: { with: active, after: lastIdRef.current } });
      if (r.data.length) { lastIdRef.current = r.data[r.data.length - 1].id; setMessages((p) => [...p, ...r.data]); }
    } catch (err) { notifyError('Không gửi được', errorMessage(err)); }
  };

  return (
    <div className="grid gap-4 md:grid-cols-[260px_1fr]">
      {/* Danh sách hội thoại */}
      <div className="card max-h-[70vh] overflow-y-auto !p-2">
        <h2 className="px-2 pb-2 pt-1 text-xs uppercase text-neutral-400">Hội thoại</h2>
        {threads.map((t) => (
          <button key={t.user.id} onClick={() => setActive(t.user.id)}
            className={`block w-full rounded-lg px-3 py-2 text-left text-sm transition ${active === t.user.id ? 'bg-surface-800 text-emerald-400' : 'hover:bg-surface-800/60'}`}>
            <p className="flex items-center justify-between font-semibold">
              {t.user.name}
              {t.unread > 0 && <span className="rounded-full bg-rose-500 px-2 text-[10px] text-white">{t.unread}</span>}
            </p>
            {t.last_message && <p className="truncate text-xs text-neutral-500">{t.last_message}</p>}
          </button>
        ))}
        {threads.length === 0 && <p className="px-2 text-sm text-neutral-500">Chưa có hội thoại.</p>}
      </div>

      {/* Khung chat */}
      <div className="card flex max-h-[70vh] flex-col">
        {active ? (
          <>
            <div className="flex-1 space-y-2 overflow-y-auto pb-2">
              {messages.map((m) => (
                <div key={m.id} className={`flex ${m.mine ? 'justify-end' : 'justify-start'}`}>
                  <div className={`max-w-[70%] rounded-2xl px-3 py-2 text-sm ${m.mine ? 'bg-emerald-600 text-neutral-950' : 'bg-surface-800'}`}>
                    <p>{m.body}</p>
                    <p className="mt-0.5 text-[10px] opacity-60">{new Date(m.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}</p>
                  </div>
                </div>
              ))}
              <div ref={bottomRef} />
            </div>
            <form onSubmit={send} className="flex gap-2 border-t border-neutral-800 pt-3">
              <input value={body} onChange={(e) => setBody(e.target.value)} placeholder="Nhập tin nhắn…" className="flex-1" />
              <button className="btn-primary">Gửi</button>
            </form>
          </>
        ) : <p className="m-auto text-neutral-500">Chọn một hội thoại để bắt đầu chat với HLV.</p>}
      </div>
    </div>
  );
}
