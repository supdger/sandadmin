import { rmSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';

import { loadEnv } from 'vite';
import { defineConfig } from 'vitest/config';
import type { Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import semiTheming from '@douyinfe/semi-vite-plugin';

// Production builds ship without the MSW service worker unless mocks are
// explicitly enabled (VITE_ENABLE_MOCKS=true), so msw stays a dev-only tool.
function stripMockWorker(): Plugin {
  let outDir = 'dist';
  let keep = false;
  return {
    name: 'sandadmin:strip-mock-worker',
    apply: 'build',
    configResolved(config) {
      outDir = config.build.outDir;
      keep = ['true', '1'].includes(String(config.env.VITE_ENABLE_MOCKS ?? '').trim());
    },
    closeBundle() {
      if (!keep) rmSync(fileURLToPath(new URL(`./${outDir}/mockServiceWorker.js`, import.meta.url)), { force: true });
    },
  };
}

export default defineConfig(({ mode }) => {
  // 只在明确设置 VITE_API_PROXY 时把 /api 转到本机后端。
  // 去掉转发头，开发登录才能按回环地址放行。
  const proxyTarget = loadEnv(mode, process.cwd(), '').VITE_API_PROXY?.trim();
  return {
  plugins: [
    stripMockWorker(),
    react(),
    // Semi theming: emit Semi styles inside a CSS cascade layer so utility
    // styles (Tailwind, layout-only) can override them predictably.
    semiTheming({ cssLayer: true }),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: proxyTarget
    ? {
        proxy: {
          '/api': {
            target: proxyTarget,
            changeOrigin: false,
            configure(proxy) {
              proxy.on('proxyReq', (proxyReq) => {
                proxyReq.removeHeader('x-forwarded-for');
                proxyReq.removeHeader('x-real-ip');
                proxyReq.removeHeader('forwarded');
              });
            },
          },
        },
      }
    : undefined,
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
  },
};
});
