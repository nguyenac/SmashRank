import { useEffect, useRef, useState } from 'react';
import { error as notifyError, info, success as notifySuccess } from '../lib/notification';

/**
 * Match Timer — đồng hồ đếm ngược giờ thi đấu (mặc định game 21 điểm nghỉ 60s).
 * Khi hết giờ → Vibration API rung phản hồi mạnh (hữu ích ở sân đấu ồn ào)
 * kèm Notification Manager + Web Notification nếu được phép.
 */
export default function MatchTimer() {
  const [minutes, setMinutes] = useState(5);
  const [secondsLeft, setSecondsLeft] = useState(0);
  const [running, setRunning] = useState(false);
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => () => { if (intervalRef.current) clearInterval(intervalRef.current); }, []);

  const vibrate = (pattern: number | number[]) => {
    if ('vibrate' in navigator) {
      navigator.vibrate(pattern);
    }
  };

  const fireTimeUp = () => {
    // Rung phản hồi mạnh: 3 nhịp dài — dễ nhận diện giữa sân ồn ào
    vibrate([600, 200, 600, 200, 600]);
    notifySuccess('⏰ Hết giờ thi đấu!', 'Đã rung thiết bị để nhắc bạn.');
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('SmashRank — Hết giờ!', { body: 'Thời gian thi đấu/đếm ngược đã kết thúc.' });
    }
  };

  const start = () => {
    if (minutes <= 0) { notifyError('Chưa đặt thời gian'); return; }
    setSecondsLeft(minutes * 60);
    setRunning(true);
    info('▶️ Đồng hồ bắt đầu', `${minutes} phút — hết giờ sẽ rung thiết bị.`);
    intervalRef.current = setInterval(() => {
      setSecondsLeft((s) => {
        if (s <= 1) {
          clearInterval(intervalRef.current!);
          setRunning(false);
          fireTimeUp();
          return 0;
        }
        // Rung nhẹ 1 nhịp mỗi 10 giây cuối cùng
        if (s <= 10 && 'vibrate' in navigator) navigator.vibrate(80);
        return s - 1;
      });
    }, 1000);
  };

  const stop = () => {
    setRunning(false);
    if (intervalRef.current) clearInterval(intervalRef.current);
  };

  const mm = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
  const ss = String(secondsLeft % 60).padStart(2, '0');

  return (
    <div className="card">
      <h2 className="mb-3 font-semibold">⏱️ Match Timer</h2>
      <div className="flex flex-wrap items-center gap-3">
        <div>
          <label className="label">Đặt phút</label>
          <input type="number" min={1} max={60} value={minutes}
            onChange={(e) => setMinutes(Number(e.target.value))} disabled={running} className="w-20 text-center" />
        </div>
        <p className="mt-5 font-mono text-4xl font-black text-emerald-400" style={{ fontVariantNumeric: 'tabular-nums' }}>
          {mm}:{ss}
        </p>
        <div className="mt-5 flex gap-2">
          {running
            ? <button onClick={stop} className="btn-ghost">⏸ Dừng</button>
            : <button onClick={start} className="btn-primary">▶️ Bắt đầu</button>}
          <button
            onClick={() => { if ('vibrate' in navigator) { navigator.vibrate([300, 100, 300]); notifySuccess('Rung thử thành công!'); } else notifyError('Thiết bị không hỗ trợ rung'); }}
            className="btn-ghost text-xs"
            title="Kiểm tra Vibration API"
          >
            📳 Rung thử
          </button>
        </div>
      </div>
      <p className="mt-2 text-xs text-neutral-500">
        Hết giờ: rung 3 nhịp mạnh qua Vibration API + thông báo. Rung nhẹ 10 giây cuối.
      </p>
    </div>
  );
}
