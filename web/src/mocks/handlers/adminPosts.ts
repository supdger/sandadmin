import type { HttpHandler } from 'msw';
import { http } from 'msw';

import type { Post, PostWrite } from '@/api/v1/posts';
import { failV1, okV1, queryPage, queryParam, requirePermission, v1Base } from './_v1';
import { mockPosts, mockUsers } from './_v1data';

const POST_PERMS = {
  index: 'system:post:index',
  create: 'system:post:create',
  update: 'system:post:update',
  remove: 'system:post:delete',
} as const;

function findPost(id: string): Post | undefined {
  return mockPosts.find((p) => p.id === id);
}

function postNameTaken(name: string, exceptId?: string): boolean {
  return mockPosts.some((p) => p.name === name && p.id !== exceptId);
}

export default [
  http.get(`${v1Base}/posts`, ({ request }) => {
    const auth = requirePermission(request, POST_PERMS.index);
    if ('response' in auth) return auth.response;
    const url = new URL(request.url);
    if (queryParam(url, 'keyword') === '__error__') {
      return failV1(500, 'INTERNAL_ERROR', '岗位列表加载失败，请稍后再试');
    }
    const keyword = queryParam(url, 'keyword');
    const status = queryParam(url, 'status');
    const filtered = mockPosts.filter((p) => {
      if (keyword && !p.name.includes(keyword)) return false;
      if (status && p.status !== status) return false;
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

  http.post(`${v1Base}/posts`, async ({ request }) => {
    const auth = requirePermission(request, POST_PERMS.create);
    if ('response' in auth) return auth.response;
    const body = (await request.json()) as PostWrite;
    if (!body.name?.trim()) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        name: ['岗位名称不能为空'],
      });
    }
    if (postNameTaken(body.name.trim())) {
      return failV1(409, 'POST_NAME_TAKEN', '岗位名称已存在，请换一个', {
        name: ['岗位名称已存在，请换一个'],
      });
    }
    const post: Post = {
      id: `post-${Date.now().toString(36)}`,
      name: body.name.trim(),
      remark: body.remark ?? null,
      sort: body.sort ?? mockPosts.length + 1,
      status: body.status ?? 'enabled',
      user_count: 0,
      create_time: new Date().toISOString(),
    };
    mockPosts.push(post);
    return okV1(post, { status: 201 });
  }),

  http.put(`${v1Base}/posts/:postId`, async ({ request, params }) => {
    const auth = requirePermission(request, POST_PERMS.update);
    if ('response' in auth) return auth.response;
    const post = findPost(String(params.postId));
    if (!post) {
      return failV1(404, 'POST_NOT_FOUND', '要操作的岗位不存在或已被删除');
    }
    const body = (await request.json()) as PostWrite;
    if (!body.name?.trim()) {
      return failV1(400, 'VALIDATION_FAILED', '提交的内容有误，请检查后重试', {
        name: ['岗位名称不能为空'],
      });
    }
    if (postNameTaken(body.name.trim(), post.id)) {
      return failV1(409, 'POST_NAME_TAKEN', '岗位名称已存在，请换一个', {
        name: ['岗位名称已存在，请换一个'],
      });
    }
    Object.assign(post, {
      name: body.name.trim(),
      remark: body.remark ?? post.remark,
      sort: body.sort ?? post.sort,
      status: body.status ?? post.status,
    });
    return okV1(post);
  }),

  http.delete(`${v1Base}/posts/:postId`, ({ request, params }) => {
    const auth = requirePermission(request, POST_PERMS.remove);
    if ('response' in auth) return auth.response;
    const post = findPost(String(params.postId));
    if (!post) {
      return failV1(404, 'POST_NOT_FOUND', '要操作的岗位不存在或已被删除');
    }
    // 契约没有定义「岗位在用」错误码——成员仍引用该岗位时删除也成功，
    // 他们的 post_id 被清成未设置。
    for (const user of mockUsers) {
      if (user.post_id === post.id) {
        user.post_id = null;
      }
    }
    mockPosts.splice(mockPosts.indexOf(post), 1);
    return okV1({});
  }),
] satisfies HttpHandler[];
