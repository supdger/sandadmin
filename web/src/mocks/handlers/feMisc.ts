import type { HttpHandler } from 'msw';
import { http } from 'msw';

import { failV1, okV1, requireUser, v1Base } from './_v1';

/*
 * 通用件包的 v1 mock：目前只补 PUT /scheduled-tasks/{code}
 * （定时任务覆盖写）。文件上传族走 data.ts 既有 /files 桩。
 */

const SCHEDULE_FREQS = new Set(['interval', 'hourly', 'daily', 'weekly', 'monthly']);

interface ScheduleState {
  code: string;
  schedule: Record<string, unknown> | null;
  status: 'enabled' | 'disabled';
}

const SEEDS: ScheduleState[] = [
  { code: 'system:cleanup', schedule: { freq: 'daily', time: '03:30' }, status: 'enabled' },
  { code: 'plugin:demo:sync', schedule: null, status: 'disabled' },
];

const state = new Map<string, ScheduleState>(SEEDS.map((s) => [s.code, { ...s }]));

function itemOf(s: ScheduleState) {
  return {
    code: s.code,
    name: s.code,
    description: null,
    source_label: s.code.startsWith('plugin:') ? s.code.split(':')[1] : '宿主',
    is_critical: false,
    plugin_code: s.code.startsWith('plugin:') ? s.code.split(':')[1] : null,
    schedule: s.schedule,
    status: s.status,
    schedule_locked: false,
    last_run_time: null,
    last_run_succeeded: null,
    next_run_time: null,
  };
}

export default [
  http.put(`${v1Base}/scheduled-tasks/:code`, async ({ request, params }) => {
    const auth = requireUser(request);
    if ('response' in auth) {
      return auth.response;
    }
    const code = String(params.code);
    const found = state.get(code);
    if (!found) {
      return failV1(404, 'TASK_NOT_FOUND', '要找的内容不存在或已被删除');
    }
    const body = (await request.json().catch(() => ({}))) as Record<string, unknown>;
    if (body.status !== undefined && body.status !== 'enabled' && body.status !== 'disabled') {
      return failV1(422, 'VALIDATION_FAILED', '请求内容有误，请按提示修改', {
        status: ['状态不正确'],
      });
    }
    if (body.schedule !== undefined && body.schedule !== null) {
      const schedule = body.schedule as Record<string, unknown>;
      if (typeof schedule.freq !== 'string' || !SCHEDULE_FREQS.has(schedule.freq)) {
        return failV1(422, 'TASK_SCHEDULE_INVALID', '执行时间填写不正确', {
          schedule: ['执行时间填写不正确'],
        });
      }
      found.schedule = schedule;
    } else if (body.schedule === null) {
      found.schedule = null;
    }
    if (typeof body.status === 'string') {
      found.status = body.status as ScheduleState['status'];
    }
    return okV1(itemOf(found));
  }),
] satisfies HttpHandler[];
