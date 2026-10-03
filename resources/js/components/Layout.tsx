import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { useAuth } from '../AuthContext';
import ToastHost from './ToastHost';
import GlobalSearch from './GlobalSearch';
import { subscribePush } from '../lib/push';

const NAV = [
  { to: '/', label: 'Trang chủ', public: true },
  { to: '/rankings', label: 'Bảng xếp hạng', public: true },
  { to: '/records', label: 'Kỷ lục', public: true },
  { to: '/tournaments', label: 'Giải đấu', public: true },
  { to: '/news', label: 'Tin tức', public: true },
  { to: '/matchmaking', label: 'Ghép đôi', public: true },
  { to: '/compare', label: 'So sánh', public: true },
  { to: '/analytics-hub', label: 'Analytics Hub', public: true },
  { to: '/products', label: 'Vợt & Giày', public: true },
  { to: '/training', label: 'Lộ trình', public: false },
  { to: '/profile', label: 'Hồ sơ của tôi', public: false },
  { to: '/dashboard', label: 'Dashboard', public: false },
  { to: '/chat', label: 'Chat', public: false },
  { to: '/billing', label: 'Thanh toán', public: false },
  { to: '/admin/analytics', label: 'Analytics', public: false, admin: true },
  { to: '/admin/reports', label: 'Báo cáo', public: false, admin: true },
  { to: '/admin/statistics', label: 'Thống kê', public: false, admin: true },
  { to: '/admin/integrity', label: 'Toàn vẹn', public: false, admin: true },
  { to: '/admin/products', label: 'Quản lý sản phẩm', public: false, admin: true },
  { to: '/admin/brands', label: 'Thương hiệu', public: false, admin: true },
  { to: '/admin/crawl', label: 'Crawl & Sync', public: false, admin: true },
  { to: '/admin/backups', label: 'Sao lưu', public: false, admin: true },
];

/** Chuyển đổi Dark/Light — lưu localStorage, áp dụng class `dark` trên <html>. */
function ThemeToggle() {
  const [dark, setDark] = useState(() => (localStorage.getItem('smashrank_theme') ?? 'dark') === 'dark');

  useEffect(() => {
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('smashrank_theme', dark ? 'dark' : 'light');
  }, [dark]);

  return (
    <button
      onClick={() => setDark(!dark)}
      className="rounded-lg border border-neutral-700 px-2 py-1.5 text-sm"
      title={dark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'}
    >
      {dark ? '🌙' : '☀️'}
    </button>
  );
}

export default function Layout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  return (
    <div className="min-h-screen">
      <ToastHost />
      <header className="sticky top-0 z-40 border-b border-neutral-800 bg-surface-900/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
          <NavLink to="/" className="text-lg font-black tracking-tight">
            <span className="text-emerald-400">Smash</span>Rank
          </NavLink>
          <nav className="hidden flex-1 gap-1 xl:flex">
            {NAV.filter((n) => !n.admin || user?.role === 'admin').map((n) => (
              <NavLink
                key={n.to}
                to={n.to}
                end={n.to === '/'}
                className={({ isActive }) =>
                  `rounded-lg px-2.5 py-2 text-sm ${isActive ? 'bg-surface-800 text-emerald-400' : 'text-neutral-300 hover:bg-surface-800'}`
                }
              >
                {n.label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-2">
            <GlobalSearch />
            {user && <button onClick={() => subscribePush(user.id).catch(() => {})} className="text-xs text-neutral-400 hover:text-emerald-400" title="Bật thông báo đẩy (PWA)">🔔</button>}
            <ThemeToggle />
            {user ? (
              <>
                <span className="hidden text-sm text-neutral-400 sm:inline">{user.name}</span>
                <button
                  className="btn-ghost"
                  onClick={async () => {
                    await logout();
                    navigate('/login');
                  }}
                >
                  Đăng xuất
                </button>
              </>
            ) : (
              <>
                <NavLink to="/login" className="btn-ghost">Đăng nhập</NavLink>
                <NavLink to="/register" className="btn-primary">Đăng ký</NavLink>
              </>
            )}
          </div>
        </div>
        {/* Menu mobile */}
        <nav className="flex gap-1 overflow-x-auto px-4 pb-2 md:hidden">
          {NAV.filter((n) => !n.admin || user?.role === 'admin').map((n) => (
            <NavLink
              key={n.to}
              to={n.to}
              end={n.to === '/'}
              className={({ isActive }) =>
                `whitespace-nowrap rounded-lg px-3 py-1.5 text-xs ${isActive ? 'bg-surface-800 text-emerald-400' : 'text-neutral-300'}`
              }
            >
              {n.label}
            </NavLink>
          ))}
        </nav>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-6">
        <Outlet />
      </main>
      <footer className="border-t border-neutral-800 py-6 text-center text-xs text-neutral-500">
        SmashRank © {new Date().getFullYear()} — Bảng xếp hạng cầu lông BWF & Elo phong trào
      </footer>
    </div>
  );
}
