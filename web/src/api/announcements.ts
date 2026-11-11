import { apiDelete, apiGet, apiPost, apiPut } from '@/lib/apiClient';

export interface Announcement {
  id: string;
  title: string;
  /** 后端白名单清洗后的正文内容，渲染时不得当作可信标记直接注入。 */
  content: string;
  scope: 'all' | 'dept';
  dept_ids: string[];
  dept_names: string[];
  level: 'normal' | 'important';
  pinned: boolean;
  state: 'draft' | 'published' | 'withdrawn';
  /** 只读派生态：有效期已过为 true，不落库。 */
  expired: boolean;
  image_file_ids: string[];
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
  dept_ids?: string[];
  level?: 'normal' | 'important';
  pinned?: boolean;
  image_file_ids?: string[];
  expire_time?: string | null;
}

export interface PageAnnouncement {
  items: Announcement[];
  total: number;
  page: number;
  page_size: number;
}

export interface AnnouncementListParams {
  keyword?: string;
  state?: string;
  page?: number;
  page_size?: number;
}

export interface AnnouncementReadStats {
  read_count: number;
  unread_count: number;
  items: Array<{
    user_id: string;
    username: string;
    nickname: string;
    read_time: string | null;
  }>;
}

export function listAnnouncements(params: AnnouncementListParams): Promise<PageAnnouncement> {
  const sp = new URLSearchParams();
  if (params.keyword?.trim()) sp.set('keyword', params.keyword.trim());
  if (params.state) sp.set('state', params.state);
  if (params.page) sp.set('page', String(params.page));
  if (params.page_size) sp.set('page_size', String(params.page_size));
  const query = sp.toString();
  return apiGet<PageAnnouncement>(`/announcements${query ? `?${query}` : ''}`);
}

export function getAnnouncement(id: string): Promise<Announcement> {
  return apiGet<Announcement>(`/announcements/${encodeURIComponent(id)}`);
}

export function createAnnouncement(body: AnnouncementWrite): Promise<Announcement> {
  return apiPost<Announcement>('/announcements', body);
}

export function updateAnnouncement(id: string, body: AnnouncementWrite): Promise<Announcement> {
  return apiPut<Announcement>(`/announcements/${encodeURIComponent(id)}`, body);
}

export function deleteAnnouncement(id: string): Promise<unknown> {
  return apiDelete(`/announcements/${encodeURIComponent(id)}`);
}

export function publishAnnouncement(id: string): Promise<Announcement> {
  return apiPost<Announcement>(`/announcements/${encodeURIComponent(id)}/publish`);
}

export function revokeAnnouncement(id: string): Promise<Announcement> {
  return apiPost<Announcement>(`/announcements/${encodeURIComponent(id)}/revoke`);
}

export function getAnnouncementReadStats(id: string): Promise<AnnouncementReadStats> {
  return apiGet<AnnouncementReadStats>(`/announcements/${encodeURIComponent(id)}/read-stats`);
}
