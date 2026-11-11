import { adminGet, adminPost, type AdminQuery } from '@/lib/adminClient';
import { markAnnouncementRead } from './announcements';
import type { Id, Page } from './types';

/**
 * GET /notifications item. `kind=notification` is a direct message
 * (`id` = notification id); `kind=announcement` is a published announcement
 * shown in the inbox (`announcement_id` carries the announcement id, `link`
 * is always null — clicking opens the announcement body instead).
 */
export interface NotificationItem {
  id: Id;
  kind: 'notification' | 'announcement';
  source_code: string | null;
  text: string;
  title: string | null;
  announcement_id: Id | null;
  link: string | null;
  read_time: string | null;
  create_time: string;
}

export type PageNotification = Page<NotificationItem>;

/** GET /me/announcements/popup item — important unread announcement popup. */
export interface AnnouncementPopupItem {
  id: Id;
  title: string;
  /** 服务端白名单清洗后的富文本；渲染前仍须过 sanitizeAnnouncementHtml。 */
  content: string;
  level: 'important';
  published_time: string;
  expire_time: string | null;
}

export interface AnnouncementPopupList {
  items: AnnouncementPopupItem[];
}

export interface NotificationQuery extends AdminQuery {
  unread_only?: boolean;
  page?: number;
  page_size?: number;
}

export function getNotifications(query: NotificationQuery = {}): Promise<PageNotification> {
  return adminGet<PageNotification>('/notifications', { query });
}

export function getUnreadCount(): Promise<{ count: number }> {
  return adminGet<{ count: number }>('/notifications/unread-count');
}

export function markNotificationRead(notificationId: Id): Promise<unknown> {
  return adminPost(`/notifications/${encodeURIComponent(notificationId)}/read`);
}

export function markAllNotificationsRead(): Promise<unknown> {
  return adminPost('/notifications/read-all');
}

export function getAnnouncementPopup(): Promise<AnnouncementPopupList> {
  return adminGet<AnnouncementPopupList>('/me/announcements/popup');
}

export { markAnnouncementRead } from './announcements';

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
