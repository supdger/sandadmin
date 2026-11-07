import { fileURLToPath, URL } from 'node:url';

import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import semiTheming from '@douyinfe/semi-vite-plugin';

export default defineConfig({
  plugins: [
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
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
  },
});
