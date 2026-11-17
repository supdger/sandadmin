import { IconClose } from '@douyinfe/semi-icons';
import { Dropdown } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import { PATH_HOME } from '@/routes/paths';
import {
  activateTab,
  closeAllTabs,
  closeOtherTabs,
  closeTab,
  useTabs,
  type WorkTab,
} from '@/stores/tabs';

/**
 * 多标签栏。数据全走 stores/tabs（登记由 PageContent 的
 * useTabRouteSync 完成）：点标签跳路由，× 关闭，右键出
 * 关闭/关闭其他/关闭全部。关掉最后一个标签回首页。
 */
export function WorkTabs() {
  const { t } = useTranslation('feLayout');
  const navigate = useNavigate();
  const { tabs, activeKey } = useTabs();

  const handleSelect = (tab: WorkTab) => {
    activateTab(tab.key);
    navigate(tab.path);
  };

  const handleClose = (key: string) => {
    const wasActive = key === activeKey;
    const next = closeTab(key);
    if (wasActive) {
      navigate(next?.path ?? PATH_HOME);
    }
  };

  const handleCloseAll = () => {
    closeAllTabs();
    navigate(PATH_HOME);
  };

  const contextMenu = (tab: WorkTab) => (
    <Dropdown.Menu>
      <Dropdown.Item onClick={() => handleClose(tab.key)}>{t('tabs.close')}</Dropdown.Item>
      <Dropdown.Item onClick={() => closeOtherTabs(tab.key)}>
        {t('tabs.closeOthers')}
      </Dropdown.Item>
      <Dropdown.Item onClick={handleCloseAll}>{t('tabs.closeAll')}</Dropdown.Item>
    </Dropdown.Menu>
  );

  return (
    <div
      role="tablist"
      className="flex h-10 items-center gap-1 overflow-x-auto px-3"
      style={{
        backgroundColor: 'var(--semi-color-bg-1)',
        borderBottom: '1px solid var(--semi-color-border)',
      }}
    >
      {tabs.map((tab) => {
        const active = tab.key === activeKey;
        return (
          <Dropdown key={tab.key} trigger="contextMenu" render={contextMenu(tab)}>
            <div
              role="tab"
              aria-selected={active}
              onClick={() => handleSelect(tab)}
              className="flex h-7 shrink-0 cursor-pointer items-center gap-1 rounded px-2"
              style={{
                border: '1px solid var(--semi-color-border)',
                backgroundColor: active
                  ? 'var(--semi-color-primary-light-default)'
                  : 'var(--semi-color-bg-2)',
                color: active ? 'var(--semi-color-primary)' : 'var(--semi-color-text-1)',
              }}
            >
              <span className="text-xs" style={{ color: 'inherit' }}>
                {tab.title}
              </span>
              <span
                role="button"
                aria-label={`${t('tabs.close')} ${tab.title}`}
                onClick={(e) => {
                  e.stopPropagation();
                  handleClose(tab.key);
                }}
                className="inline-flex"
                style={{ cursor: 'pointer' }}
              >
                <IconClose size="small" />
              </span>
            </div>
          </Dropdown>
        );
      })}
    </div>
  );
}
