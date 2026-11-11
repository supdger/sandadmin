import { useMemo, useState } from 'react';

import { Checkbox, Input, Tag, Tooltip, Typography } from '@douyinfe/semi-ui';
import {
  IconChevronDown,
  IconChevronRight,
  IconSearch,
} from '@douyinfe/semi-icons';
import { useTranslation } from 'react-i18next';

import type {
  PermissionAction,
  PermissionGroup,
  PermissionMenu,
} from '@/api/v1/permissions';
import {
  groupCheckState,
  ownableCodes,
} from '@/pages/rbac/lib/permissionMatrix';

export interface PermissionMatrixProps {
  groups: PermissionGroup[];
  /** 当前已勾选的权限码。 */
  selected: ReadonlySet<string>;
  /** 当前登录人可分配的码；'*' 表示不受限。 */
  granted: string[] | '*';
  /** 只读角色渲染时所有勾选框禁用。 */
  disabled?: boolean;
  onToggleCode: (action: PermissionAction, siblings: PermissionAction[]) => void;
  onToggleGroup: (codes: string[], check: boolean) => void;
}

interface FilteredMenu {
  menu: PermissionMenu;
  /** 命中关键词的动作码；空数组表示显示全部动作。 */
  matchedActions: PermissionAction[];
}

function menuMatches(menu: PermissionMenu, keyword: string): FilteredMenu | null {
  if (!keyword) {
    return { menu, matchedActions: menu.actions };
  }
  const actionHits = menu.actions.filter((a) =>
    [a.label, a.description ?? '', a.code].some((s) =>
      s.toLowerCase().includes(keyword),
    ),
  );
  const selfHit = [menu.title, menu.description ?? ''].some((s) =>
    s.toLowerCase().includes(keyword),
  );
  if (!selfHit && actionHits.length === 0) {
    return null;
  }
  // 菜单级命中保留全部动作；动作级命中收窄显示。
  return {
    menu,
    matchedActions: selfHit ? menu.actions : actionHits,
  };
}

/**
 * 权限分组渲染成可折叠区块；每个菜单行内联展示动作勾选框。
 * 权限码不露出——只显示名称/描述。
 */
export function PermissionMatrix({
  groups,
  selected,
  granted,
  disabled = false,
  onToggleCode,
  onToggleGroup,
}: PermissionMatrixProps) {
  const { t } = useTranslation('rbac');
  const [keyword, setKeyword] = useState('');
  const [collapsed, setCollapsed] = useState<Set<string>>(new Set());

  const filtered = useMemo(() => {
    const kw = keyword.trim().toLowerCase();
    return groups
      .map((group) => {
        const groupHit =
          !kw ||
          group.group_title.toLowerCase().includes(kw) ||
          group.group_key.toLowerCase().includes(kw);
        const menus = group.menus
          .map((menu) => menuMatches(menu, groupHit ? '' : kw))
          .filter((m): m is FilteredMenu => m !== null)
          .map((m) => (groupHit && kw ? { ...m, matchedActions: m.menu.actions } : m));
        if (menus.length === 0) {
          return null;
        }
        return { group, menus };
      })
      .filter((g): g is { group: PermissionGroup; menus: FilteredMenu[] } => g !== null);
  }, [groups, keyword]);

  const toggleCollapse = (key: string) => {
    setCollapsed((prev) => {
      const next = new Set(prev);
      if (next.has(key)) {
        next.delete(key);
      } else {
        next.add(key);
      }
      return next;
    });
  };

  const isOwnable = (code: string) =>
    granted === '*' || granted.includes(code);

  return (
    <div className="flex flex-col gap-3">
      <Input
        prefix={<IconSearch />}
        placeholder={t('permission.searchPlaceholder')}
        value={keyword}
        onChange={setKeyword}
        showClear
        style={{ maxWidth: 280 }}
      />
      {filtered.length === 0 ? (
        <Typography.Text type="secondary">
          {t('permission.noResult')}
        </Typography.Text>
      ) : (
        filtered.map(({ group, menus }) => {
          const isCollapsed = collapsed.has(group.group_key);
          // 分组勾选框只覆盖当前用户可授权的码。
          const ownable = menus.flatMap((m) =>
            ownableCodes(m.matchedActions, granted),
          );
          const uniqueOwnable = Array.from(new Set(ownable));
          const state = groupCheckState(uniqueOwnable, selected);
          return (
            <div
              key={group.group_key}
              className="rounded-lg border border-[var(--semi-color-border)]"
            >
              <button
                type="button"
                className="flex w-full items-center gap-2 px-3 py-2 text-left"
                onClick={() => toggleCollapse(group.group_key)}
              >
                {isCollapsed ? <IconChevronRight /> : <IconChevronDown />}
                <Typography.Text strong>{group.group_title}</Typography.Text>
                <span
                  className="ml-auto"
                  onClick={(e) => e.stopPropagation()}
                  role="presentation"
                >
                  <Checkbox
                    checked={state === 'checked'}
                    indeterminate={state === 'indeterminate'}
                    disabled={disabled || uniqueOwnable.length === 0}
                    onChange={(e) =>
                      onToggleGroup(uniqueOwnable, Boolean(e.target.checked))
                    }
                  >
                    {t('permission.selectAllGroup')}
                  </Checkbox>
                </span>
              </button>
              {!isCollapsed ? (
                <div className="flex flex-col gap-2 px-3 pb-3">
                  {menus.map(({ menu, matchedActions }) => (
                    <div
                      key={menu.menu_key}
                      className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-md bg-[var(--semi-color-fill-0)] px-3 py-2"
                    >
                      <div className="min-w-40">
                        <Typography.Text strong size="small">
                          {menu.title}
                        </Typography.Text>
                        {menu.description ? (
                          <div>
                            <Typography.Text type="secondary" size="small">
                              {menu.description}
                            </Typography.Text>
                          </div>
                        ) : null}
                      </div>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
                        {matchedActions.map((action) => {
                          const ownable = isOwnable(action.code);
                          const box = (
                            <Checkbox
                              key={action.code}
                              checked={selected.has(action.code)}
                              disabled={disabled || !ownable}
                              onChange={() =>
                                onToggleCode(action, menu.actions)
                              }
                            >
                              {action.label}
                            </Checkbox>
                          );
                          if (disabled) {
                            return box;
                          }
                          if (!ownable) {
                            return (
                              <Tooltip
                                key={action.code}
                                content={t('permission.notOwnedTooltip')}
                              >
                                <span>{box}</span>
                              </Tooltip>
                            );
                          }
                          return box;
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          );
        })
      )}
      <Tag size="small" color="blue" className="self-start">
        {t('permission.selectedCount', { count: selected.size })}
      </Tag>
    </div>
  );
}
