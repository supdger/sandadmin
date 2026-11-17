import { Button, Card, Space, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

export default function HelloPage() {
  const { t } = useTranslation('plugin-sand-hello');
  const navigate = useNavigate();

  return (
    <Card title={t('page.title')}>
      <Space vertical align="start" spacing="medium">
        <Typography.Text>{t('page.desc')}</Typography.Text>
        <Button theme="solid" onClick={() => navigate('/plugin/sand-hello/about')}>
          {t('action.about')}
        </Button>
      </Space>
    </Card>
  );
}
