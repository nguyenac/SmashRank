import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { AuthProvider } from './AuthContext';
import './index.css';

// Theme: dark (mặc định) / light — lưu localStorage
const theme = localStorage.getItem('smashrank_theme') ?? 'dark';
document.documentElement.classList.toggle('dark', theme === 'dark');

// PWA: đăng ký service worker (offline cache + push notifications)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => { /* bỏ qua lỗi SW */ });
  });
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </React.StrictMode>,
);
