import { useMemo, useState } from 'react';

import { Button, Card, Empty, Modal, Select, Tag, Toast, Typography } from '@douyinfe/semi-ui';
import { IconClose } from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  getShortcuts,
  resetShortcuts,
  saveShortcuts,
  type ShortcutItem,
} from '@/api/v1/dashboard';
import { getMyMenus, type MyMenuNode } from '@/api/v1/me';
import { adminErrorMessage } from '@/lib/adminError';
import { listOrEmpty } from './listOrEmpty';

const SHORTCUTS_QUERY_KEY = ['dashboard', 'shortcuts'] as const;
const MENUS_QUERY_KEY = ['me', 'menus'] as const;
const MAX_SHORTCUTS = 8;

interface LeafMenu {
  key: string;
  title: string;
  path: string;
}

function flattenLeaves(items: MyMenuNode[], out: LeafMenu[] = []): LeafMenu[] {
  for (const item of items) {
    if (item.children && item.children.length > 0) {
      flattenLeaves(item.children, out);
    } else if (item.type === 'page' && item.path) {
      out.push({ key: item.key, title: item.title, path: item.path });
    }
  }
  return out;
}

/**
 * Workbench 快捷入口 strip. Auto mode renders the server-picked list; the
 * 「调整」 modal switches to manual mode — drag to reorder, × to remove,
 * search to add — persisted via PUT/DELETE /dashboard/shortcuts.
 */
export function ShortcutBar() {
  const { t } = useTranslation('workbench');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [draft, setDraft] = useState<ShortcutItem[]>([]);
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  const [overIndex, setOverIndex] = useState<number | null>(null);

  const shortcuts = useQuery({ queryKey: SHORTCUTS_QUERY_KEY, queryFn: getShortcuts });
  const menus = useQuery({ queryKey: MENUS_QUERY_KEY, queryFn: getMyMenus, staleTime: 60_000 });

  const leafMenus = useMemo(
    () => flattenLeaves(Array.isArray(menus.data?.items) ? menus.data.items : []),
    [menus.data],
  );

  const save = useMutation({
    mutationFn: (menuKeys: string[]) => saveShortcuts(menuKeys),
    onSuccess: (data) => {
      queryClient.setQueryData(SHORTCUTS_QUERY_KEY, data);
      Toast.success(t('shortcuts.saved'));
      setModalOpen(false);
    },
    onError: (e) => Toast.error(adminErrorMessage(e, t('shortcuts.saveFailed'))),
  });

  const restore = useMutation({
    mutationFn: resetShortcuts,
    onSuccess: (data) => {
      queryClient.setQueryData(SHORTCUTS_QUERY_KEY, data);
      Toast.success(t('shortcuts.restored'));
      setModalOpen(false);
    },
    onError: (e) => Toast.error(adminErrorMessage(e, t('shortcuts.saveFailed'))),
  });

  const openModal = () => {
    setDraft(listOrEmpty(shortcuts.data?.items));
    setDragIndex(null);
    setOverIndex(null);
    setModalOpen(true);
  };

  const moveItem = (from: number, to: number) => {
    setDraft((prev) => {
      const next = [...prev];
      const [item] = next.splice(from, 1);
      next.splice(to, 0, item);
      return next;
    });
  };

  const addFromMenu = (menuKey: string) => {
    const leaf = leafMenus.find((m) => m.key === menuKey);
    if (!leaf || draft.some((s) => s.menu_key === menuKey)) {
      return;
    }
    if (draft.length >= MAX_SHORTCUTS) {
      Toast.warning(t('shortcuts.maxReached'));
      return;
    }
    setDraft((prev) => [
      ...prev,
      { menu_key: menuKey, title: leaf.title, icon: null, link: leaf.path },
    ]);
  };

  const availableOptions = leafMenus
    .filter((m) => !draft.some((s) => s.menu_key === m.key))
    .map((m) => ({ value: m.key, label: m.title }));

  const items = listOrEmpty(shortcuts.data?.items);

  return (
    <Card
      title={t('shortcuts.title')}
      headerExtraContent={
        <Button size="small" theme="borderless" onClick={openModal}>
          {t('shortcuts.adjust')}
        </Button>
      }
    >
      {shortcuts.isPending ? (
        <Typography.Text type="tertiary" size="small">
          {t('common:loading')}
        </Typography.Text>
      ) : items.length === 0 ? (
        <Empty title={t('shortcuts.empty')} description={undefined} />
      ) : (
        <div className="flex flex-wrap gap-2">
          {items.map((item) => (
            <Button
              key={item.menu_key}
              size="small"
              theme="light"
              onClick={() => navigate(item.link)}
            >
              {item.title}
            </Button>
          ))}
        </div>
      )}

      <Modal
        visible={modalOpen}
        title={t('shortcuts.modalTitle')}
        width={480}
        footer={
          <div className="flex items-center justify-between">
            <Button
              theme="borderless"
              disabled={restore.isPending}
              onClick={() => restore.mutate()}
            >
              {t('shortcuts.restore')}
            </Button>
            <div className="flex gap-2">
              <Button onClick={() => setModalOpen(false)}>{t('common:actions.cancel')}</Button>
              <Button
                type="primary"
                theme="solid"
                loading={save.isPending}
                onClick={() => save.mutate(draft.map((s) => s.menu_key))}
              >
                {t('shortcuts.save')}
              </Button>
            </div>
          </div>
        }
        onCancel={() => setModalOpen(false)}
      >
        {draft.length === 0 ? (
          <Empty title={t('shortcuts.empty')} description={undefined} />
        ) : (
          <div className="flex flex-col gap-1">
            {draft.map((item, index) => (
              <div
                key={item.menu_key}
                draggable
                onDragStart={() => setDragIndex(index)}
                onDragOver={(e) => {
                  e.preventDefault();
                  setOverIndex(index);
                }}
                onDrop={() => {
                  if (dragIndex !== null && dragIndex !== index) {
                    moveItem(dragIndex, index);
                  }
                  setDragIndex(null);
                  setOverIndex(null);
                }}
                onDragEnd={() => {
                  setDragIndex(null);
                  setOverIndex(null);
                }}
                className="flex items-center gap-2 rounded px-2 py-1"
                style={{
                  cursor: 'grab',
                  backgroundColor:
                    overIndex === index && dragIndex !== index
                      ? 'var(--semi-color-fill-0)'
                      : undefined,
                }}
              >
                <span className="text-[var(--semi-color-text-3)]">⠿</span>
                <Typography.Text ellipsis={{ showTooltip: true }}>{item.title}</Typography.Text>
                {shortcuts.data?.mode === 'auto' ? <Tag size="small">{t('shortcuts.autoTag')}</Tag> : null}
                <Button
                  size="small"
                  theme="borderless"
                  icon={<IconClose />}
                  aria-label={`${t('shortcuts.remove')} ${item.title}`}
                  onClick={() => setDraft((prev) => prev.filter((s) => s.menu_key !== item.menu_key))}
                />
              </div>
            ))}
          </div>
        )}
        <div className="mt-4 flex items-center gap-2">
          <Select
            placeholder={t('shortcuts.addPlaceholder')}
            optionList={availableOptions}
            showClear
            filter
            style={{ flex: 1 }}
            onChange={(value) => addFromMenu(String(value))}
          />
        </div>
      </Modal>
    </Card>
  );
}
