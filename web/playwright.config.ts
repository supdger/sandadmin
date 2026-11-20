import { defineConfig } from '@playwright/test';

// 菜单页冒烟（S-15）：开发服务器 + msw mock，无后端依赖。
// 端口固定落在任务约定的 19100–19199 段。
const PORT = 19100;

export default defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  retries: 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: `http://localhost:${PORT}`,
  },
  webServer: {
    command: `pnpm dev --port ${PORT} --strictPort`,
    url: `http://localhost:${PORT}`,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    env: {
      // dev 默认已开 mock，这里显式钉住，防止本地 .env 关掉。
      VITE_ENABLE_MOCKS: 'true',
    },
  },
});
