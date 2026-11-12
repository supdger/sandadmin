import { Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { useMe } from '@/hooks/useMe';
import { useSiteSettings } from '@/hooks/useSiteSettings';
import { zonedNow } from '@/lib/siteSettings';

type GreetingKey = 'morning' | 'noon' | 'afternoon' | 'evening';

function greetingKey(hour: number): GreetingKey {
  if (hour < 6) {
    return 'morning';
  }
  if (hour < 12) {
    return 'morning';
  }
  if (hour < 14) {
    return 'noon';
  }
  if (hour < 18) {
    return 'afternoon';
  }
  return 'evening';
}

const WEEKDAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'] as const;

/** Time-aware greeting + today's date + one-line subtitle. */
export function GreetingHeader() {
  const { t } = useTranslation('workbench');
  const me = useMe();
  const site = useSiteSettings();
  // 系统设置里改了时区马上生效，问候语与「今天」都按那个时区算。
  const now = zonedNow(site.timezone);
  const name = me.data?.nickname || t('greeting.fallbackName');
  const weekday = WEEKDAY_KEYS[now.getDay()];

  return (
    <div>
      <Typography.Title heading={3} style={{ margin: 0 }}>
        {t(`greeting.${greetingKey(now.getHours())}`, { name })}
      </Typography.Title>
      <Typography.Text type="secondary">
        {t('greeting.subtitle')} ·{' '}
        {t('greeting.date', {
          month: now.getMonth() + 1,
          day: now.getDate(),
          weekday: t(`greeting.weekday.${weekday}`),
        })}
      </Typography.Text>
    </div>
  );
}
