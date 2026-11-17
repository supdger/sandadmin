import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';

import { IconChevronRight } from '@douyinfe/semi-icons';

import { SvgIcon } from '@/components/SvgIcon';

export interface ContextMenuItem {
  type?: 'item';
  key: string;
  label: ReactNode;
  /** 图标注册表里的名字。 */
  icon?: string;
  disabled?: boolean;
  danger?: boolean;
  children?: ContextMenuEntry[];
}

export interface ContextMenuDivider {
  type: 'divider';
}

export type ContextMenuEntry = ContextMenuItem | ContextMenuDivider;

export interface ContextMenuProps {
  /** 弹出位置，取自右键事件的 clientX/clientY。 */
  x: number;
  y: number;
  entries: ContextMenuEntry[];
  onClose: () => void;
  /** 点了非禁用、非分组项时回调；组件随后自行 onClose。 */
  onSelect?: (key: string) => void;
  width?: number;
}

const MENU_PADDING = 8;

function isDivider(entry: ContextMenuEntry): entry is ContextMenuDivider {
  return entry.type === 'divider';
}

function ItemRow({
  item,
  open,
  onEnter,
  onLeave,
  onPick,
}: {
  item: ContextMenuItem;
  open: boolean;
  onEnter: () => void;
  onLeave: () => void;
  onPick: (key: string) => void;
}) {
  const hasChildren = (item.children?.length ?? 0) > 0;
  const [subOpen, setSubOpen] = useState(false);
  const expanded = open || subOpen;

  return (
    <li
      role="menuitem"
      aria-disabled={item.disabled || undefined}
      aria-haspopup={hasChildren || undefined}
      tabIndex={item.disabled ? -1 : 0}
      onMouseEnter={() => {
        onEnter();
        setSubOpen(hasChildren);
      }}
      onMouseLeave={() => {
        onLeave();
        setSubOpen(false);
      }}
      onClick={(e) => {
        e.stopPropagation();
        if (item.disabled) {
          return;
        }
        if (hasChildren) {
          setSubOpen((v) => !v);
          return;
        }
        onPick(item.key);
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter' && !item.disabled && !hasChildren) {
          onPick(item.key);
        }
      }}
      className="relative flex cursor-default items-center gap-2 rounded px-2 py-1.5 text-sm select-none"
      style={{
        color: item.danger ? 'var(--semi-color-danger)' : 'var(--semi-color-text-0)',
        opacity: item.disabled ? 0.4 : 1,
        background: expanded ? 'var(--semi-color-fill-0)' : undefined,
      }}
    >
      {item.icon ? <SvgIcon name={item.icon} size="small" /> : null}
      <span className="flex-1 truncate">{item.label}</span>
      {hasChildren ? <IconChevronRight size="small" aria-hidden /> : null}
      {hasChildren && expanded ? (
        <ul
          role="menu"
          className="absolute rounded-md border py-1 shadow-lg"
          style={{
            left: '100%',
            top: -MENU_PADDING,
            minWidth: 140,
            background: 'var(--semi-color-bg-2)',
            borderColor: 'var(--semi-color-border)',
          }}
        >
          {item.children!.map((child, index) =>
            isDivider(child) ? (
              <li
                key={`divider-${index}`}
                role="separator"
                className="my-1 border-t"
                style={{ borderColor: 'var(--semi-color-border)' }}
              />
            ) : (
              <ItemRow
                key={child.key}
                item={child}
                open={false}
                onEnter={() => {}}
                onLeave={() => {}}
                onPick={onPick}
              />
            ),
          )}
        </ul>
      ) : null}
    </li>
  );
}

/**
 * 右键菜单：按给定坐标弹出，Esc / 菜单外点击关闭。
 * 触发侧负责在 onContextMenu 里 preventDefault 并记录坐标。
 */
export function ContextMenu({ x, y, entries, onClose, onSelect, width = 168 }: ContextMenuProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [pos, setPos] = useState({ left: x, top: y });
  const [openKey, setOpenKey] = useState<string | null>(null);

  // 出屏修正：首帧先按原坐标画，量出尺寸后收进视口。
  useLayoutEffect(() => {
    const rect = ref.current?.getBoundingClientRect();
    if (!rect) {
      return;
    }
    setPos({
      left: Math.max(MENU_PADDING, Math.min(x, window.innerWidth - rect.width - MENU_PADDING)),
      top: Math.max(MENU_PADDING, Math.min(y, window.innerHeight - rect.height - MENU_PADDING)),
    });
  }, [x, y]);

  useEffect(() => {
    const handleDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        onClose();
      }
    };
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };
    document.addEventListener('mousedown', handleDown);
    document.addEventListener('keydown', handleKey);
    return () => {
      document.removeEventListener('mousedown', handleDown);
      document.removeEventListener('keydown', handleKey);
    };
  }, [onClose]);

  const handlePick = (key: string) => {
    onSelect?.(key);
    onClose();
  };

  const style: CSSProperties = {
    position: 'fixed',
    left: pos.left,
    top: pos.top,
    zIndex: 1050,
    width,
    padding: MENU_PADDING / 2,
    background: 'var(--semi-color-bg-2)',
    borderColor: 'var(--semi-color-border)',
  };

  return (
    <div
      ref={ref}
      role="menu"
      className="rounded-md border py-1 shadow-lg"
      style={style}
      onContextMenu={(e) => e.preventDefault()}
    >
      <ul>
        {entries.map((entry, index) =>
          isDivider(entry) ? (
            <li
              key={`divider-${index}`}
              role="separator"
              className="my-1 border-t"
              style={{ borderColor: 'var(--semi-color-border)' }}
            />
          ) : (
            <ItemRow
              key={entry.key}
              item={entry}
              open={openKey === entry.key}
              onEnter={() => setOpenKey(entry.key)}
              onLeave={() => setOpenKey(null)}
              onPick={handlePick}
            />
          ),
        )}
      </ul>
    </div>
  );
}
