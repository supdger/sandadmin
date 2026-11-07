import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { ok } from './_shared';

const base = env.apiBaseUrl;

function isInstalled(requestUrl: string): boolean {
  const url = new URL(requestUrl, 'http://localhost');
  if (url.searchParams.get('mocks') === 'installed') {
    return true;
  }
  try {
    return typeof window !== 'undefined' && window.localStorage.getItem('mock.installed') === '1';
  } catch {
    return false;
  }
}

export default [
  http.get(`${base}/install/status`, ({ request }) => {
    const installed = isInstalled(request.url);
    return ok({ installed, lockExists: installed });
  }),

  http.post(`${base}/install/start`, () => ok({ jobId: 'mock-install-job' })),
] satisfies HttpHandler[];
