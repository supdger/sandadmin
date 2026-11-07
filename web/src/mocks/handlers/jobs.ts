import { http, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { INSTALLED_STORAGE_KEY } from './install';
import { ok } from './_shared';

const base = env.apiBaseUrl;

const STAGES = ['准备环境', '写数据库', '装随包插件', '建管理员账号', '收尾'];
const PROGRESS_STEP = 20;
const FAIL_AT_POLL = 3;

// Each poll advances the job one step; ids containing "fail" break midway.
const polls = new Map<string, number>();

export default [
  http.get(`${base}/jobs/:id`, ({ params }) => {
    const id = String(params.id);
    const count = (polls.get(id) ?? 0) + 1;
    polls.set(id, count);

    const failing = id.includes('fail');
    if (failing && count >= FAIL_AT_POLL) {
      return ok({
        id,
        status: 'failed',
        stage: STAGES[1],
        progress: Math.min((count - 1) * PROGRESS_STEP, 100),
        error: '写入数据库时中断（模拟失败，方便演示重试）',
      });
    }

    const stageIndex = Math.min(count - 1, STAGES.length - 1);
    const progress = Math.min(count * PROGRESS_STEP, 100);
    const done = count >= STAGES.length;

    if (done && id.startsWith('install-')) {
      try {
        window.localStorage.setItem(INSTALLED_STORAGE_KEY, '1');
      } catch {
        // storage unavailable — installed flag just won't persist
      }
    }

    return ok({
      id,
      status: done ? 'success' : 'running',
      stage: STAGES[stageIndex],
      progress,
    });
  }),
] satisfies HttpHandler[];
