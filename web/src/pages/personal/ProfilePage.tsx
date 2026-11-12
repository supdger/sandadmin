import { useState } from 'react';

import { Tabs } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { PageHeader } from '@/components/PageHeader';
import { confirmAction } from '@/lib/confirmAction';
import { ProfileBasicTab } from './ProfileBasicTab';
import { ProfileSecurityTab } from './ProfileSecurityTab';

// 登录设备接口还没接上，页签先不放出来，避免点进去永远报错。
const TAB_KEYS = ['basic', 'security'] as const;
type TabKey = (typeof TAB_KEYS)[number];

function isTabKey(value: string | null): value is TabKey {
  return TAB_KEYS.includes(value as TabKey);
}

/**
 * Personal settings: left tab rail (basic / security), active tab
 * mirrored into ?tab=. Unsaved basic-profile edits trigger a discard confirm
 * before switching away.
 */
export function ProfilePage() {
  const { t } = useTranslation('profile');
  const [searchParams, setSearchParams] = useSearchParams();
  const tabParam = searchParams.get('tab');
  const activeTab: TabKey = isTabKey(tabParam) ? tabParam : 'basic';
  const [dirty, setDirty] = useState(false);

  const handleTabChange = (key: string) => {
    if (key === activeTab || !isTabKey(key)) {
      return;
    }
    if (dirty && activeTab === 'basic') {
      confirmAction({
        title: t('basic.unsavedTitle'),
        okText: t('basic.unsavedOk'),
        cancelText: t('basic.unsavedCancel'),
        onOk: () => {
          setDirty(false);
          setSearchParams(
            (prev) => {
              const next = new URLSearchParams(prev);
              next.set('tab', key);
              return next;
            },
            { replace: true },
          );
        },
      });
      return;
    }
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        next.set('tab', key);
        return next;
      },
      { replace: true },
    );
  };

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      <Tabs
        tabPosition="left"
        activeKey={activeTab}
        onChange={handleTabChange}
        tabBarStyle={{ minWidth: 160 }}
      >
        <Tabs.TabPane itemKey="basic" tab={t('tabs.basic')}>
          <ProfileBasicTab onDirtyChange={setDirty} />
        </Tabs.TabPane>
        <Tabs.TabPane itemKey="security" tab={t('tabs.security')}>
          <ProfileSecurityTab />
        </Tabs.TabPane>
      </Tabs>
    </div>
  );
}
