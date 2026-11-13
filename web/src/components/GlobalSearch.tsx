import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import { Input, Modal, Tag, Typography } from '@douyinfe/semi-ui';
import { IconSearch } from '@douyinfe/semi-icons';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router';

import type { MyMenuNode } from '@/api/v1/me';
import { searchAdmin } from '@/api/v1/search';
import { useCan } from '@/hooks/useCan';
import { isAdminSystemError } from '@/lib/adminClient';
import { flattenMenuPages, matchMenuPages, type MenuSearchHit } from '@/lib/menuSearch';
import { AdminCopyButton } from './AdminErrorNotice';

const RECENT_KEY = 'sandadmin:recent-pages';
const RECENT_MAX = 5;

interface RecentEntry {
  path: string;
  title: string;
}

function readRecent(): RecentEntry[] {
  try {
    const raw = localStorage.getItem(RECENT_KEY);
    const list = raw ? (JSON.parse(raw) as RecentEntry[]) : [];
    return Array.isArray(list) ? list.slice(0, RECENT_MAX) : [];
  } catch {
    return [];
  }
}

/** 记录一次页面访问（最近访问源）；工作台不记。 */
function recordRecentPage(path: string, title: string): void {
  if (!path || path === '/' || !title) return;
  const list = readRecent().filter((e) => e.path !== path);
  list.unshift({ path, title });
  try {
    localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, RECENT_MAX)));
  } catch {
    // 存储不可用时静默。
  }
}

interface FlatHit {
  key: string;
  group: 'menu' | 'user' | 'recent';
  title: string;
  subtitle: string | null;
  link: string;
}

export function GlobalSearch({ menus }: { menus: MyMenuNode[] }) {
  const { t } = useTranslation('search');
  const navigate = useNavigate();
  const location = useLocation();
  const canUser = useCan('system:user:index');
  const isMac =
    typeof navigator !== 'undefined' &&
    /mac/i.test((navigator as Navigator & { userAgentData?: { platform?: string } }).userAgentData?.platform ?? navigator.platform ?? '');

  const [open, setOpen] = useState(false);
  const [keyword, setKeyword] = useState('');
  const [active, setActive] = useState(0);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const triggerRef = useRef<HTMLDivElement | null>(null);

  const openPanel = useCallback(() => {
    setKeyword('');
    setActive(0);
    setOpen(true);
  }, []);

  // 记录最近访问（排除工作台）。
  useEffect(() => {
    const hit = flattenMenuPages(menus).find((m) => m.path === location.pathname);
    if (hit) recordRecentPage(hit.path, hit.title);
  }, [location.pathname, menus]);

  // ⌘K / Ctrl K 打开；Esc 由 Modal 处理。
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openPanel();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [openPanel]);

  useEffect(() => {
    if (open) {
      setTimeout(() => inputRef.current?.focus(), 0);
    }
  }, [open]);

  const pages = useMemo(() => flattenMenuPages(menus), [menus]);
  const menuHits = useMemo(() => matchMenuPages(pages, keyword), [pages, keyword]);

  const userQuery = useQuery({
    queryKey: ['search', 'users', keyword],
    queryFn: () => searchAdmin(keyword, ['user']),
    enabled: open && canUser && keyword.trim().length > 0,
    retry: false,
  });

  const userItems = useMemo(
    () => userQuery.data?.groups.find((g) => g.type === 'user')?.items ?? [],
    [userQuery.data],
  );
  const userFailed = userQuery.isError;

  const recent = useMemo(() => (keyword ? [] : readRecent()), [keyword]);

  const flatHits = useMemo<FlatHit[]>(() => {
    if (!keyword.trim()) {
      return recent.map((r) => ({ key: `r-${r.path}`, group: 'recent', title: r.title, subtitle: null, link: r.path }));
    }
    const out: FlatHit[] = menuHits.map((h: MenuSearchHit) => ({
      key: `m-${h.key}`,
      group: 'menu',
      title: h.title,
      // 与 /search 返回的菜单项副标题同格式（「组织与用户 › 用户管理」）
      subtitle: h.groupTitle ? `${h.groupTitle} › ${h.title}` : null,
      link: h.path,
    }));
    for (const item of userItems) {
      out.push({ key: `u-${item.id}`, group: 'user', title: item.title, subtitle: item.subtitle, link: item.link });
    }
    return out;
  }, [keyword, recent, menuHits, userItems]);

  const go = useCallback(
    (hit: FlatHit) => {
      setOpen(false);
      navigate(hit.link);
    },
    [navigate],
  );

  const onPanelKey = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActive((a) => Math.min(a + 1, flatHits.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive((a) => Math.max(a - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const hit = flatHits[active];
      if (hit) go(hit);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      setOpen(false);
      triggerRef.current?.querySelector('input')?.focus();
    }
  };

  const placeholder = canUser ? t('panelPlaceholder') : t('panelPlaceholderMenuOnly');

  const renderRows = (group: FlatHit['group'], label: string) => {
    const rows = flatHits
      .map((h, i) => ({ h, i }))
      .filter(({ h }) => h.group === group);
    if (rows.length === 0) return null;
    return (
      <div key={group}>
        <Typography.Text type="tertiary" size="small" style={{ display: 'block', padding: '4px 8px' }}>
          {label}
        </Typography.Text>
        {rows.map(({ h, i }) => (
          <div
            key={h.key}
            role="option"
            aria-selected={i === active}
            style={{
              padding: '6px 8px',
              borderRadius: 4,
              cursor: 'pointer',
              background: i === active ? 'var(--semi-color-fill-0)' : undefined,
            }}
            onMouseEnter={() => setActive(i)}
            onClick={() => go(h)}
          >
            <span>{h.title}</span>
            {h.subtitle ? (
              <Typography.Text type="tertiary" size="small"> · {h.subtitle}</Typography.Text>
            ) : null}
          </div>
        ))}
      </div>
    );
  };

  return (
    <>
      <div ref={triggerRef} onClick={openPanel} style={{ cursor: 'pointer' }}>
        <Input
          prefix={<IconSearch />}
          placeholder={t('triggerPlaceholder')}
          readonly
          style={{ width: 200 }}
          suffix={<Tag size="small" type="ghost">{isMac ? t('shortcutMac') : t('shortcutOther')}</Tag>}
          aria-label={t('triggerPlaceholder')}
        />
      </div>
      <Modal
        visible={open}
        onCancel={() => {
          setOpen(false);
          triggerRef.current?.querySelector('input')?.focus();
        }}
        footer={null}
        closable={false}
        style={{ maxWidth: '92vw' }}
        width={560}
      >
        <div onKeyDown={onPanelKey}>
          <Input
            ref={inputRef}
            prefix={<IconSearch />}
            placeholder={placeholder}
            value={keyword}
            onChange={(v) => {
              setKeyword(v);
              setActive(0);
            }}
            showClear
            aria-label={placeholder}
          />
          <div style={{ marginTop: 8, maxHeight: '50vh', overflow: 'auto' }}>
            {!keyword.trim() ? (
              <>
                {renderRows('recent', t('recent.title'))}
                {flatHits.length === 0 ? (
                  <Typography.Text type="tertiary" size="small" style={{ display: 'block', padding: '8px' }}>
                    {t('recent.empty')}
                  </Typography.Text>
                ) : null}
              </>
            ) : (
              <>
                {renderRows('menu', t('groups.menu'))}
                {canUser && !userFailed ? renderRows('user', t('groups.user')) : null}
                {canUser && userFailed ? (
                  <div style={{ padding: '4px 8px' }}>
                    <Typography.Text type="tertiary" size="small" style={{ display: 'block' }}>
                      {t('groups.user')}
                    </Typography.Text>
                    <div className="flex items-center gap-2">
                      <Typography.Text type="danger" size="small">
                        {t('userGroupError')}
                      </Typography.Text>
                      {isAdminSystemError(userQuery.error) ? (
                        <AdminCopyButton error={userQuery.error} />
                      ) : null}
                    </div>
                  </div>
                ) : null}
                {menuHits.length === 0 && userItems.length === 0 && !userQuery.isLoading ? (
                  <Typography.Text type="tertiary" size="small" style={{ display: 'block', padding: '8px' }}>
                    {t('empty.noResult')}
                  </Typography.Text>
                ) : null}
              </>
            )}
          </div>
          <Typography.Text type="tertiary" size="small" style={{ display: 'block', marginTop: 8 }}>
            {t('hint')}
          </Typography.Text>
        </div>
      </Modal>
    </>
  );
}
