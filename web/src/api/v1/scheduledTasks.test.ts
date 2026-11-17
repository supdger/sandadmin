import { beforeEach, describe, expect, it } from 'vitest';

import { AdminApiError } from '@/lib/adminClient';
import { clearToken, setToken } from '@/lib/authToken';
import { updateScheduledTask } from './scheduledTasks';

beforeEach(() => {
  clearToken();
  setToken('mock-admin-token');
});

describe('v1 scheduledTasks API against MSW', () => {
  it('改启停后返回更新后的任务项', async () => {
    const task = await updateScheduledTask('plugin:demo:sync', { status: 'enabled' });
    expect(task.code).toBe('plugin:demo:sync');
    expect(task.status).toBe('enabled');
  });

  it('覆盖执行时间返回新 schedule，传 null 恢复默认', async () => {
    const task = await updateScheduledTask('system:cleanup', {
      schedule: { freq: 'weekly', weekday: 1, time: '02:00' },
    });
    expect(task.schedule).toEqual({ freq: 'weekly', weekday: 1, time: '02:00' });
  });

  it('不存在的任务码返回 404', async () => {
    const err = await updateScheduledTask('no:such:task', { status: 'enabled' }).catch(
      (e: unknown) => e,
    );
    expect(err).toBeInstanceOf(AdminApiError);
    expect((err as AdminApiError).httpStatus).toBe(404);
    expect((err as AdminApiError).code).toBe('TASK_NOT_FOUND');
  });

  it('非法执行时间返回 TASK_SCHEDULE_INVALID', async () => {
    const err = await updateScheduledTask('system:cleanup', {
      schedule: { freq: 'nope' as never },
    }).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(AdminApiError);
    expect((err as AdminApiError).code).toBe('TASK_SCHEDULE_INVALID');
  });
});
