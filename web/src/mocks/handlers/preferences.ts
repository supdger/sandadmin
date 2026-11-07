import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { ok } from './_shared';

const base = env.apiBaseUrl;

let stored: Record<string, unknown> = { theme: 'auto' };

export default [
  http.get(`${base}/me/preferences`, () => ok({ ...stored })),

  http.put(`${base}/me/preferences`, async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;
    stored = { ...stored, ...body };
    return ok(null);
  }),
] satisfies HttpHandler[];
