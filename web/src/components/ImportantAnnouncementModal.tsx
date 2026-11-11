import { useState } from 'react';

import { Button, Modal, Toast, Typography } from '@douyinfe/semi-ui';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { markAnnouncementRead, type AnnouncementPopupItem } from '@/api/notifications';
import { apiErrorMessage } from '@/lib/apiClient';

export interface ImportantAnnouncementModalProps {
  /** Queue of important announcements; shown one at a time, in order. */
  items: AnnouncementPopupItem[];
  /** Called after the last item is acknowledged. */
  onCloseAll: () => void;
}

const BLOCKED_TAGS = new Set(['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base']);

/**
 * Announcement bodies are rich text already whitelist-cleaned server-side;
 * strip active content again before rendering so a compromised payload can
 * never execute script in the admin shell.
 */
function sanitizeAnnouncementHtml(html: string): string {
  if (typeof DOMParser === 'undefined') {
    return '';
  }
  const doc = new DOMParser().parseFromString(html, 'text/html');
  doc.querySelectorAll('*').forEach((el) => {
    const tag = el.tagName.toLowerCase();
    if (BLOCKED_TAGS.has(tag)) {
      el.remove();
      return;
    }
    for (const attr of Array.from(el.attributes)) {
      const name = attr.name.toLowerCase();
      const value = attr.value.trim().toLowerCase();
      if (
        name.startsWith('on') ||
        ((name === 'href' || name === 'src' || name === 'xlink:href') &&
          (value.startsWith('javascript:') || value.startsWith('vbscript:')))
      ) {
        el.removeAttribute(attr.name);
      }
    }
  });
  return doc.body.innerHTML;
}

/**
 * Sequential popup for important unread announcements. Each item is marked
 * read via POST /announcements/{id}/read when the user taps 「知道了」.
 */
export function ImportantAnnouncementModal({ items, onCloseAll }: ImportantAnnouncementModalProps) {
  const { t } = useTranslation('notifications');
  const queryClient = useQueryClient();
  const [index, setIndex] = useState(0);
  const [marking, setMarking] = useState(false);

  const current = items[index];
  if (!current) {
    return null;
  }

  const sanitized = sanitizeAnnouncementHtml(current.content ?? '');

  const handleKnow = async () => {
    setMarking(true);
    try {
      await markAnnouncementRead(current.id);
      void queryClient.invalidateQueries({ queryKey: ['notifications'] });
      if (index + 1 >= items.length) {
        onCloseAll();
      } else {
        setIndex(index + 1);
      }
    } catch (e) {
      Toast.error(apiErrorMessage(e, t('announcement.readFailed')));
    } finally {
      setMarking(false);
    }
  };

  return (
    <Modal
      visible
      title={current.title}
      width={560}
      footer={
        <Button type="primary" theme="solid" loading={marking} onClick={() => void handleKnow()}>
          {t('announcement.gotIt')}
        </Button>
      }
      closable={false}
      maskClosable={false}
      closeOnEsc={false}
    >
      {sanitized ? (
        <div
          className="text-sm leading-6 [&_a]:text-[var(--semi-color-primary)] [&_img]:max-w-full [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2 [&_ul]:list-disc [&_ul]:pl-5"
          // Sanitized above: script/active content stripped, safe to inject.
          dangerouslySetInnerHTML={{ __html: sanitized }}
        />
      ) : (
        <Typography.Text type="secondary">{current.title}</Typography.Text>
      )}
      {items.length > 1 ? (
        <Typography.Text type="tertiary" size="small">
          {index + 1} / {items.length}
        </Typography.Text>
      ) : null}
    </Modal>
  );
}
