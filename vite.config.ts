import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/main.tsx'],
      refresh: true,
    }),
    react(),
  ],
  server: {
    port: 5173,
    // Chế độ dev: Vite (5173) proxy API + ảnh sang Laravel (8000)
    proxy: {
      '/api': 'http://localhost:8000',
      '/storage': 'http://localhost:8000',
    },
  },
});
