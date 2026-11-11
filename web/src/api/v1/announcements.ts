import { adminDelete, adminGet, adminPost, adminPut, type AdminQuery } from '@/lib/adminClient';
import type { Id, Page } from './types';

export type AnnouncementState = 'draft' | 'published' | 'withdrawn';

/** 列表筛选态：expired 是派生筛选（expire_time 已过即算），不进 Announcement.state。 */
export type AnnouncementStateFilter = AnnouncementState | 'expired';

export interface Announcement {
  id: Id;
  title: string;
  /** 后端白名单清洗后的正文内容，渲染前仍须过 sanitizeAnnouncementHtml。 */
  content: string;
  scope: 'all' | 'dept';
  dept_ids: Id[];
  dept_names: string[];
  read_count: number;
  recipient_count: number;
  level: 'normal' | 'important';
  pinned: boolean;
  state: AnnouncementState;
  /** 只读派生态：有效期已过为 true，不落库。 */
  expired: boolean;
  image_file_ids: Id[];
  published_time: string | null;
  expire_time: string | null;
  created_by_name: string;
  create_time: string;
  update_time: string;
}

export interface AnnouncementWrite {
  title: string;
  content: string;
  scope: 'all' | 'dept';
  dept_ids?: Id[];
  level?: 'normal' | 'important';
  pinned?: boolean;
  image_file_ids?: Id[];
  expire_time?: string | null;
}

export type PageAnnouncement = Page<Announcement>;

export interface AnnouncementListQuery extends AdminQuery {
  keyword?: string;
  state?: AnnouncementStateFilter;
  page?: number;
  page_size?: number;
}

export interface AnnouncementReadStats {
  read_count: number;
  unread_count: number;
  items: Array<{
    user_id: Id;
    username: string;
    nickname: string;
    read_time: string | null;
  }>;
}

export function listAnnouncements(query: AnnouncementListQuery): Promise<PageAnnouncement> {
  return adminGet<PageAnnouncement>('/announcements', { query });
}

export function getAnnouncement(id: Id): Promise<Announcement> {
  return adminGet<Announcement>(`/announcements/${encodeURIComponent(id)}`);
}

export function createAnnouncement(body: AnnouncementWrite): Promise<Announcement> {
  return adminPost<Announcement>('/announcements', body);
}

export function updateAnnouncement(id: Id, body: AnnouncementWrite): Promise<Announcement> {
  return adminPut<Announcement>(`/announcements/${encodeURIComponent(id)}`, body);
}

export function deleteAnnouncement(id: Id): Promise<unknown> {
  return adminDelete(`/announcements/${encodeURIComponent(id)}`);
}

export function publishAnnouncement(id: Id): Promise<Announcement> {
  return adminPost<Announcement>(`/announcements/${encodeURIComponent(id)}/publish`);
}

export function revokeAnnouncement(id: Id): Promise<Announcement> {
  return adminPost<Announcement>(`/announcements/${encodeURIComponent(id)}/revoke`);
}

export function markAnnouncementRead(id: Id): Promise<unknown> {
  return adminPost(`/announcements/${encodeURIComponent(id)}/read`);
}

export function getAnnouncementReadStats(id: Id): Promise<AnnouncementReadStats> {
  return adminGet<AnnouncementReadStats>(`/announcements/${encodeURIComponent(id)}/read-stats`);
}
