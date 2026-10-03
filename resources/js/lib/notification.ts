/**
 * Notification Manager — hệ thống thông báo chuyên nghiệp thay thế alert/toast rời rạc.
 * Các mức: success | warning | error | info. Hỗ trợ tự biến mất, nút đóng, xếp chồng.
 */
import type { ReactNode } from 'react';

export type NotificationKind = 'success' | 'warning' | 'error' | 'info';

export interface NotificationItem {
  id: number;
  kind: NotificationKind;
  title: string;
  message?: string;
  timeout?: number;
}

type Listener = (items: NotificationItem[]) => void;

let items: NotificationItem[] = [];
const listeners = new Set<Listener>();
let nextId = 1;

function emit() {
  listeners.forEach((l) => l([...items]));
}

export function notify(kind: NotificationKind, title: string, message?: string, timeout = 4000): number {
  const id = nextId++;
  items = [...items, { id, kind, title, message, timeout }];
  emit();
  if (timeout > 0) {
    setTimeout(() => dismiss(id), timeout);
  }
  return id;
}

export const success = (t: string, m?: string) => notify('success', t, m);
export const warning = (t: string, m?: string) => notify('warning', t, m, 6000);
export const error = (t: string, m?: string) => notify('error', t, m, 6000);
export const info = (t: string, m?: string) => notify('info', t, m);

export function dismiss(id: number) {
  items = items.filter((i) => i.id !== id);
  emit();
}

export function subscribe(listener: Listener): () => void {
  listeners.add(listener);
  listener([...items]);
  return () => listeners.delete(listener);
}

export const STYLES: Record<NotificationKind, { icon: ReactNode; border: string; bg: string; text: string }> = {
  success: { icon: '✅', border: 'border-emerald-600', bg: 'bg-emerald-500/10', text: 'text-emerald-400' },
  warning: { icon: '⚠️', border: 'border-amber-600', bg: 'bg-amber-500/10', text: 'text-amber-400' },
  error: { icon: '❌', border: 'border-rose-600', bg: 'bg-rose-500/10', text: 'text-rose-400' },
  info: { icon: 'ℹ️', border: 'border-sky-600', bg: 'bg-sky-500/10', text: 'text-sky-400' },
};
