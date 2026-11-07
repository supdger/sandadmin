import '@douyinfe/semi-ui/react19-adapter';
// The adapter import above must stay on the first line — it patches Semi
// components for React 19 before anything else touches them.

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { App } from './app/App';
import { env } from './config/env';
import { initTheme } from './lib/theme';
import './i18n/i18n';
import './index.css';

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
