import { Button, Card, Tag, Typography } from '@douyinfe/semi-ui';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { getOnboarding } from '@/api/v1/dashboard';

const ONBOARDING_QUERY_KEY = ['dashboard', 'onboarding'] as const;

const STEP_LINKS: Record<string, string> = {
  add_user: '/permission/user',
  set_roles: '/permission/role',
  connect_ai: '/plugin/sand-ai/provider',
};

/**
 * 「开始使用前」 checklist card — hidden once every step is done; each step
 * links to the page where it gets finished.
 */
export function OnboardingCard() {
  const { t } = useTranslation('workbench');
  const onboarding = useQuery({ queryKey: ONBOARDING_QUERY_KEY, queryFn: getOnboarding });

  if (onboarding.isPending || onboarding.isError || !onboarding.data) {
    return null;
  }
  const state = onboarding.data;
  if (state.done || state.steps.length === 0) {
    return null;
  }

  return (
    <Card title={t('onboarding.title')}>
      <div className="flex flex-col gap-2">
        {state.steps.map((step) => {
          const done = step.done;
          const link = STEP_LINKS[step.key];
          return (
            <div key={step.key} className="flex items-center gap-2">
              <Tag size="small" color={done ? 'green' : 'grey'}>
                {done ? '✓' : '○'}
              </Tag>
              <Typography.Text delete={done} type={done ? 'tertiary' : undefined}>
                {t(`onboarding.steps.${step.key}.title`, { defaultValue: step.key })}
              </Typography.Text>
              {!done && link ? (
                <Link to={link} className="ml-auto">
                  <Button size="small" theme="borderless">
                    {t(`onboarding.steps.${step.key}.action`, { defaultValue: '去处理' })}
                  </Button>
                </Link>
              ) : null}
            </div>
          );
        })}
      </div>
    </Card>
  );
}
