import axios from 'axios';

export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('smashrank_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('smashrank_token');
      localStorage.removeItem('smashrank_user');
      if (!window.location.pathname.startsWith('/login')) window.location.href = '/login';
    }
    return Promise.reject(err);
  },
);

export function errorMessage(err: unknown): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as Record<string, unknown> | undefined;
    if (data?.message) return String(data.message);
    if (data?.errors) {
      const first = Object.values(data.errors as Record<string, string[]>)[0];
      if (first?.length) return first[0];
    }
  }
  return 'Đã xảy ra lỗi, vui lòng thử lại.';
}
