import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: true,
    // Docker（macOS）ではホストのファイル変更の通知がコンテナに届かず、変更が反映されないため、
    // コンテナ内ではポーリングで変更を検知する（compose.yaml で VITE_USE_POLLING=true を設定）
    watch: {
      usePolling: process.env.VITE_USE_POLLING === 'true',
    },
    // /api をバックエンドへ転送し、同一オリジンで呼び出す（CORS不要。方式設計書 3.4）
    proxy: {
      '/api': {
        target: process.env.API_PROXY_TARGET ?? 'http://localhost:8000',
      },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    restoreMocks: true,
  },
})
