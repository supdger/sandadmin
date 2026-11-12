import { adminDelete, adminGet, adminPost, adminPut, type AdminQuery } from '@/lib/adminClient';
import type { EmptyResult, Id, Page, UserStatus } from './types';

export interface Post {
  id: Id;
  name: string;
  remark: string | null;
  sort: number;
  status: UserStatus;
  user_count: number;
  create_time: string;
}

export interface PostWrite {
  name: string;
  remark?: string | null;
  sort?: number;
  status?: UserStatus;
}

export interface PostListQuery extends AdminQuery {
  keyword?: string;
  status?: UserStatus;
  page?: number;
  page_size?: number;
}

function postPath(postId: Id): string {
  return `/posts/${encodeURIComponent(postId)}`;
}

export function listPosts(query?: PostListQuery): Promise<Page<Post>> {
  return adminGet<Page<Post>>('/posts', { query });
}

export function createPost(body: PostWrite, idempotencyKey?: string): Promise<Post> {
  return adminPost<Post>('/posts', body, { idempotencyKey });
}

export function updatePost(postId: Id, body: PostWrite): Promise<Post> {
  return adminPut<Post>(postPath(postId), body);
}

export function deletePost(postId: Id): Promise<EmptyResult> {
  return adminDelete<EmptyResult>(postPath(postId));
}
