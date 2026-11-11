import { apiGet, apiPost } from '@/lib/apiClient';

/**
 * GET /notifications item. `kind=notification` is a direct message
 * (`id` = notification id); `kind=announcement` is a published announcement
 * shown in the inbox (`announcement_id` carries the announcement id, `link`
 * is always null — clicking opens the announcement body instead).
 */
export interface NotificationItem {
  id: string;
  kind: 'notification' | 'announcement';
  source_code: string | null;
  text: string;
  title: string | null;
  announcement_id: string | null;
  link: string | null;
  read_time: string | null;
  create_time: string;
}

export interface PageNotification {
  items: NotificationItem[];
  total: number;
  page: number;
  page_size: number;
}

/** GET /me/announcements/popup item — important unread announcement popup. */
export interface AnnouncementPopupItem {
  id: string;
  title: string;
  content: string;
  level: 'important';
  published_time: string;
  expire_time: string | null;
}

export interface AnnouncementPopupList {
  items: AnnouncementPopupItem[];
}

export interface NotificationQuery {
  unreadOnly?: boolean;
  page?: number;
  pageSize?: number;
}

function buildQuery(params: NotificationQuery): string {
  const search = new URLSearchParams();
  if (params.unreadOnly) {
    search.set('unread_only', 'true');
  }
  if (params.page !== undefined) {
    search.set('page', String(params.page));
  }
  if (params.pageSize !== undefined) {
    search.set('page_size', String(params.pageSize));
  }
  const query = search.toString();
  return query ? `?${query}` : '';
}

export function getNotifications(params: NotificationQuery = {}): Promise<PageNotification> {
  return apiGet<PageNotification>(`/notifications${buildQuery(params)}`);
}

export function getUnreadCount(): Promise<{ count: number }> {
  return apiGet<{ count: number }>('/notifications/unread-count');
}

export function markNotificationRead(notificationId: string): Promise<unknown> {
  return apiPost(`/notifications/${encodeURIComponent(notificationId)}/read`);
}

export function markAllNotificationsRead(): Promise<unknown> {
  return apiPost('/notifications/read-all');
}

export function getAnnouncementPopup(): Promise<AnnouncementPopupList> {
  return apiGet<AnnouncementPopupList>('/me/announcements/popup');
}

export function markAnnouncementRead(announcementId: string): Promise<unknown> {
  return apiPost(`/announcements/${encodeURIComponent(announcementId)}/read`);
}

/**
 * Inbox items are a merged view: kind=notification reads via
 * /notifications/{id}/read, kind=announcement via /announcements/{id}/read.
 */
export function markInboxItemRead(item: NotificationItem): Promise<unknown> {
  if (item.kind === 'announcement') {
    return markAnnouncementRead(item.announcement_id ?? item.id);
  }
  return markNotificationRead(item.id);
}
