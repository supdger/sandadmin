import { HttpResponse, type HttpHandler } from 'msw';
import { http } from 'msw';

import type { Task } from '@/api/v1/tasks';
import { failV1, okV1, queryPage, queryParam, requireUser, v1Base } from './_v1';
import { authzVersion, mockTasks, pollTask } from './_v1data';

const TASK_CSV_HEADER = 'username,nickname,phone,email,dept_name,status\n';

function findTask(id: string) {
  return mockTasks.find((t) => t.id === id);
}

export default [
  http.get(`${v1Base}/tasks`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    const type = queryParam(url, 'type');
    const state = queryParam(url, 'state');
    const filtered = mockTasks.filter((t) => {
      if (type && t.type !== type) return false;
      if (state && t.state !== state) return false;
      return true;
    });
    const { page, page_size } = queryPage(url);
    const start = (page - 1) * page_size;
    return okV1({
      items: filtered.slice(start, start + page_size),
      total: filtered.length,
      page,
      page_size,
    });
  }),

  http.get(`${v1Base}/tasks/:taskId`, ({ request, params }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const task = findTask(String(params.taskId));
    if (!task) {
      return failV1(404, 'TASK_RUN_NOT_FOUND', '要查看的任务不存在或已被删除');
    }
    // 每次轮询前进 queued → running → succeeded，
    // 让 UI 不用定时器就能看到生命周期。
    const publicTask: Task = { ...pollTask(task) };
    delete (publicTask as { pollsUntilDone?: number }).pollsUntilDone;
    return okV1(publicTask);
  }),

  http.get(`${v1Base}/tasks/:taskId/download`, ({ request, params }) => {
    const auth = requireUser(request);
    if ('response' in auth) return auth.response;
    const task = findTask(String(params.taskId));
    if (!task) {
      return failV1(404, 'TASK_RUN_NOT_FOUND', '要下载的任务不存在或已被删除');
    }
    if (task.state !== 'succeeded') {
      return failV1(409, 'TASK_RUN_NOT_DOWNLOADABLE', '任务还没有完成，暂时不能下载');
    }
    const csv =
      TASK_CSV_HEADER +
      'admin,管理员,13800000000,admin@example.com,沙盒网络,enabled\n' +
      'user002,新同事,13800000001,newbie@example.com,前端组,enabled\n';
    return new HttpResponse(csv, {
      headers: {
        'content-type': 'text/csv; charset=utf-8',
        'content-disposition': `attachment; filename="${task.id}.csv"`,
        'x-authz-version': String(authzVersion()),
      },
    });
  }),
] satisfies HttpHandler[];
