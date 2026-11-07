import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { ok } from './_shared';

const base = env.apiBaseUrl;

export const INSTALLED_STORAGE_KEY = 'mock.installed';
export const INSTALL_JOB_STORAGE_KEY = 'mock.installJobId';

let jobSeq = 0;

function isInstalled(requestUrl: string): boolean {
  const url = new URL(requestUrl, 'http://localhost');
  if (url.searchParams.get('mocks') === 'installed') {
    return true;
  }
  try {
    return typeof window !== 'undefined' && window.localStorage.getItem(INSTALLED_STORAGE_KEY) === '1';
  } catch {
    return false;
  }
}

export default [
  http.get(`${base}/install/status`, ({ request }) => {
    const installed = isInstalled(request.url);
    return ok({ installed, lockExists: installed });
  }),

  // Environment check: this bundle always ships SQLite and needs no extra setup.
  http.get(`${base}/install/environment`, () =>
    ok([
      { key: 'runtime', name: '运行环境', ok: true, detail: '正常' },
      { key: 'database', name: '数据库', ok: true, detail: 'SQLite 随包，免配置' },
      { key: 'writable', name: '目录权限', ok: true, detail: '数据目录可写' },
    ]),
  ),

  // Admin username containing "fail" produces a failing job so the error
  // path stays demoable: `install-fail-*` ids are driven to `failed`.
  http.post(`${base}/install/start`, async ({ request }) => {
    const body = (await request.json()) as { adminUsername?: string };
    jobSeq += 1;
    const failing = (body.adminUsername ?? '').toLowerCase().includes('fail');
    return ok({ jobId: `install-${failing ? 'fail-' : ''}${jobSeq}` });
  }),
] satisfies HttpHandler[];
