import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { api } from './api';
import type { User } from './types';

interface AuthState {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<{ twoFactorRequired: boolean; challengeToken?: string; method?: string; setupRequired?: boolean }>;
  verify2FA: (challengeToken: string, code: string) => Promise<void>;
  sendEmailCode: (challengeToken: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('smashrank_token');
    if (!token) {
      setLoading(false);
      return;
    }
    api.get<{ user: User }>('/me')
      .then((res) => {
        setUser(res.data.user);
        localStorage.setItem('smashrank_user', JSON.stringify(res.data.user));
      })
      .catch(() => {
        localStorage.removeItem('smashrank_token');
        localStorage.removeItem('smashrank_user');
      })
      .finally(() => setLoading(false));
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.post('/login', { email, password });
    if (res.data.two_factor_required) {
      return { twoFactorRequired: true, challengeToken: res.data.challenge_token, method: res.data.two_factor_method };
    }
    localStorage.setItem('smashrank_token', res.data.token);
    localStorage.setItem('smashrank_user', JSON.stringify(res.data.user));
    setUser(res.data.user);
    return { twoFactorRequired: false, setupRequired: !!res.data.two_factor_setup_required };
  }, []);

  const verify2FA = useCallback(async (challengeToken: string, code: string) => {
    const res = await api.post('/2fa/verify', { challenge_token: challengeToken, code });
    localStorage.setItem('smashrank_token', res.data.token);
    localStorage.setItem('smashrank_user', JSON.stringify(res.data.user));
    setUser(res.data.user);
  }, []);

  const sendEmailCode = useCallback(async (challengeToken: string) => {
    await api.post('/2fa/email-code', { challenge_token: challengeToken });
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/logout');
    } catch { /* bỏ qua */ }
    localStorage.removeItem('smashrank_token');
    localStorage.removeItem('smashrank_user');
    setUser(null);
  }, []);

  const refreshMe = useCallback(async () => {
    const res = await api.get<{ user: User }>('/me');
    setUser(res.data.user);
    localStorage.setItem('smashrank_user', JSON.stringify(res.data.user));
  }, []);

  const value = useMemo(
    () => ({ user, loading, login, verify2FA, sendEmailCode, logout, refreshMe }),
    [user, loading, login, verify2FA, sendEmailCode, logout, refreshMe],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth phải được dùng bên trong AuthProvider');
  return ctx;
}
