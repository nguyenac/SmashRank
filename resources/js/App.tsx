import { Navigate, Route, Routes } from 'react-router-dom';
import type { ReactNode } from 'react';
import Layout from './components/Layout';
import { useAuth } from './AuthContext';

import Login from './pages/Login';
import Register from './pages/Register';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import TwoFactorSetup from './pages/TwoFactorSetup';
import HomePage from './pages/HomePage';
import UserDashboard from './pages/UserDashboard';
import ChatPage from './pages/ChatPage';
import BillingPage from './pages/BillingPage';
import Rankings from './pages/Rankings';
import AthleteDetail from './pages/AthleteDetail';
import Products from './pages/Products';
import Profile from './pages/Profile';
import RecordsPage from './pages/RecordsPage';
import { TournamentsPage, TournamentDetail, TournamentLiveRedirect } from './pages/TournamentsPage';
import AdminProducts from './pages/AdminProducts';
import AdminBrands from './pages/AdminBrands';
import AdminCrawl from './pages/AdminCrawl';
import AdminAnalytics from './pages/AdminAnalytics';
import AdminBackups from './pages/AdminBackups';
import AdminStatistics from './pages/AdminStatistics';
import AdminIntegrity from './pages/AdminIntegrity';
import AnalyticsHub from './pages/AnalyticsHub';
import ReportsPage from './pages/ReportsPage';
import ComparePage from './pages/ComparePage';
import TrainingPathPage from './pages/TrainingPathPage';
import MatchmakingPage from './pages/MatchmakingPage';
import LiveScorePage from './pages/LiveScorePage';
import { NewsPage, NewsDetail } from './pages/NewsPage';

function Protected({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();
  if (loading) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;
  if (!user) return <Navigate to="/login" replace />;
  if (!user.two_factor_enabled) return <Navigate to="/2fa/setup" replace />;
  return <>{children}</>;
}

function AdminOnly({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();
  if (loading) return <p className="py-10 text-center text-neutral-400">Đang tải…</p>;
  if (!user) return <Navigate to="/login" replace />;
  if (!user.two_factor_enabled) return <Navigate to="/2fa/setup" replace />;
  if (user.role !== 'admin') return <Navigate to="/" replace />;
  return <>{children}</>;
}

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />

        {/* Trang chủ + các trang công khai */}
        <Route index element={<HomePage />} />
        <Route path="/rankings" element={<Rankings />} />
        <Route path="/athletes/:id" element={<AthleteDetail />} />
        <Route path="/records" element={<RecordsPage />} />
        <Route path="/tournaments" element={<TournamentsPage />} />
        <Route path="/tournaments/:id" element={<TournamentDetail />} />
        {/* Giải không có real-time: tự động redirect về trang chi tiết */}
        {/* Giải có real-time: trang Live Score thực (polling 5s) */}
        <Route path="/tournaments/:id/live" element={<LiveScorePage />} />
        <Route path="/news" element={<NewsPage />} />
        <Route path="/news/:id" element={<NewsDetail />} />
        <Route path="/matchmaking" element={<MatchmakingPage />} />
        <Route path="/compare" element={<ComparePage />} />
        <Route path="/analytics-hub" element={<AnalyticsHub />} />
        <Route path="/training" element={<Protected><TrainingPathPage /></Protected>} />
        <Route path="/products" element={<Products />} />
        <Route path="/products/:id" element={<Products />} />

        <Route path="/2fa/setup" element={<TwoFactorSetup />} />
        <Route path="/profile" element={<Protected><Profile /></Protected>} />
        <Route path="/dashboard" element={<Protected><UserDashboard /></Protected>} />
        <Route path="/chat" element={<Protected><ChatPage /></Protected>} />
        <Route path="/billing" element={<Protected><BillingPage /></Protected>} />

        <Route path="/admin/analytics" element={<AdminOnly><AdminAnalytics /></AdminOnly>} />
        <Route path="/admin/reports" element={<AdminOnly><ReportsPage /></AdminOnly>} />
        <Route path="/admin/statistics" element={<AdminOnly><AdminStatistics /></AdminOnly>} />
        <Route path="/admin/integrity" element={<AdminOnly><AdminIntegrity /></AdminOnly>} />
        <Route path="/admin/products" element={<AdminOnly><AdminProducts /></AdminOnly>} />
        <Route path="/admin/brands" element={<AdminOnly><AdminBrands /></AdminOnly>} />
        <Route path="/admin/crawl" element={<AdminOnly><AdminCrawl /></AdminOnly>} />
        <Route path="/admin/backups" element={<AdminOnly><AdminBackups /></AdminOnly>} />

        <Route path="*" element={<p className="py-10 text-center text-neutral-400">Không tìm thấy trang.</p>} />
      </Route>
    </Routes>
  );
}
