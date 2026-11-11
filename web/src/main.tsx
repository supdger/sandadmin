import '@douyinfe/semi-ui/react19-adapter';
// The adapter import above must stay on the first line — it patches Semi
// components for React 19 before anything else touches them.

// index.css declares the cascade-layer order; layers are ordered by first
// appearance, so it must load before any module pulling in Semi styles —
// otherwise `@layer semi` lands below Tailwind's `base` layer and preflight
// resets (border: 0, padding: 0, transparent backgrounds) beat Semi styles.
import './index.css';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { App } from './app/App';
import { env } from './config/env';
import { initTheme } from './lib/theme';
import './i18n/i18n';

async function bootstrap() {
  if (env.enableMocks) {
    const { worker } = await import('./mocks/browser');
    await worker.start({ onUnhandledRequest: 'bypass' });
  }

  initTheme();
  document.title = env.appName;

  createRoot(document.getElementById('root')!).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}

void bootstrap();
