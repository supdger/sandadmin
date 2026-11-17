import type { HttpHandler } from 'msw';
import { http } from 'msw';

import { failV1, okV1, requireUser, v1Base } from './_v1';

/*
 * 表格/表单族组件的测试桩：一个支持分页/筛选/排序的通用列表
 * 加一个带 fields 校验错误的写端点。只服务 Vitest/开发 mock，
 * 不对应真实契约端点。
 */

interface TestRow {
  id: string;
  name: string;
  status: 'enabled' | 'disabled';
  create_time: string;
}

const TEST_ROWS: TestRow[] = Array.from({ length: 57 }, (_, index) => ({
  id: `row-${String(index + 1).padStart(3, '0')}`,
  name: `测试行${index + 1}`,
  status: index % 3 === 0 ? 'disabled' : 'enabled',
  create_time: `2026-01-${String((index % 28) + 1).padStart(2, '0')}T08:00:00+08:00`,
}));

export default [
  http.get(`${v1Base}/test-rows`, ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) {
      return auth.response;
    }
    const sp = new URL(request.url).searchParams;
    const keyword = (sp.get('keyword') ?? '').trim();
    const status = sp.get('status') ?? '';
    const page = Math.max(1, Number(sp.get('page')) || 1);
    const pageSize = Math.min(100, Math.max(1, Number(sp.get('page_size')) || 20));
    const sortField = sp.get('sort_field') ?? '';
    const sortOrder = sp.get('sort_order') ?? 'asc';

    const filtered = TEST_ROWS.filter(
      (row) =>
        (!keyword || row.name.includes(keyword)) && (!status || row.status === status),
    );
    if (sortField === 'name' || sortField === 'create_time' || sortField === 'status') {
      filtered.sort((a, b) => {
        const order = a[sortField] < b[sortField] ? -1 : a[sortField] > b[sortField] ? 1 : 0;
        return sortOrder === 'desc' ? -order : order;
      });
    }
    const start = (page - 1) * pageSize;
    return okV1({
      items: filtered.slice(start, start + pageSize),
      total: filtered.length,
      page,
      page_size: pageSize,
    });
  }),

  http.post(`${v1Base}/test-rows`, async ({ request }) => {
    const auth = requireUser(request);
    if ('response' in auth) {
      return auth.response;
    }
    const body = (await request.json()) as { name?: string };
    const name = body.name?.trim() ?? '';
    if (!name) {
      return failV1(422, 'VALIDATION_FAILED', '请检查表单内容', {
        name: ['名称不能为空'],
      });
    }
    const created: TestRow = {
      id: `row-${String(TEST_ROWS.length + 1).padStart(3, '0')}`,
      name,
      status: 'enabled',
      create_time: new Date().toISOString(),
    };
    TEST_ROWS.push(created);
    return okV1(created);
  }),
] satisfies HttpHandler[];
