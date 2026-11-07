import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { ok } from './_shared';

const base = env.apiBaseUrl;

const STAGES = ['准备环境', '执行迁移', '写入初始数据', '完成'];

// Progress advances on every poll until the job succeeds.
const polls = new Map<string, number>();

export default [
  http.get(`${base}/jobs/:id`, ({ params }) => {
    const id = String(params.id);
    const count = (polls.get(id) ?? 0) + 1;
    polls.set(id, count);

    const progress = Math.min(count * 25, 100);
    const stage = STAGES[Math.min(count - 1, STAGES.length - 1)];
    return ok({
      id,
      status: progress >= 100 ? 'success' : 'running',
      stage,
      progress,
    });
  }),
] satisfies HttpHandler[];
