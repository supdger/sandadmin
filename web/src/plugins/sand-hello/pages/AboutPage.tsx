import { Card, Space, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

export default function AboutPage() {
  const { t } = useTranslation('plugin-sand-hello');

  return (
    <Card title={t('about.title')}>
      <Space vertical align="start" spacing="medium">
        <Typography.Text>{t('about.desc')}</Typography.Text>
        <Link to="/plugin/sand-hello">{t('action.back')}</Link>
      </Space>
    </Card>
  );
}
