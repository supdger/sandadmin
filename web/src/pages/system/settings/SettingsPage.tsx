import { useMemo, useRef, useState } from 'react';

import {
  Banner,
  Button,
  Input,
  InputNumber,
  Select,
  Switch,
  Tabs,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IllustrationNoAccess } from '@douyinfe/semi-illustrations';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';

import {
  getSettingGroups,
  getSettings,
  updateSettings,
  uploadSettingLogo,
  type SettingItem,
} from '@/api/v1/settings';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { useCan } from '@/hooks/useCan';
import { useFileImageUrl } from '@/hooks/useFileImageUrl';
import { adminErrorMessage } from '@/lib/adminError';
import { confirmAction } from '@/lib/confirmAction';
import { applySiteSettings, settingsItemsToPatch } from '@/lib/siteSettings';

/** 密钥类：password 型或 has_value=true 且 value 为 null——只说明「已设置」，不给输入框。 */
function isSecretItem(item: SettingItem): boolean {
  return (item.type === 'password' || item.type === 'text') && item.has_value && item.value === null;
}

/** image 项的浅/深底色预览：value 是 file_id（或直出地址），统一走 useFileImageUrl。 */
function SettingLogoPreview({ fileRef }: { fileRef: string | null }) {
  const url = useFileImageUrl(fileRef);
  return (
    <div
      style={{
        width: 120,
        height: 48,
        border: '1px solid var(--semi-color-border)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      {url ? <img src={url} alt="" style={{ maxHeight: 40, maxWidth: 110 }} /> : null}
    </div>
  );
}

export function SettingsPage() {
  const { t } = useTranslation('settings');
  const { t: tc } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const canIndex = useCan('system:config:index');
  const canUpdate = useCan('system:config:update');

  const groupsQuery = useQuery({
    queryKey: ['settings', 'groups'],
    queryFn: getSettingGroups,
    enabled: canIndex,
  });

  const [activeGroup, setActiveGroup] = useState<string | null>(null);
  const group = activeGroup ?? groupsQuery.data?.items[0]?.key ?? null;

  const itemsQuery = useQuery({
    queryKey: ['settings', 'items', group],
    queryFn: () => getSettings(group!),
    enabled: canIndex && group !== null,
  });

  // 文本/数字等手动保存的草稿；开关/下拉立即生效不进草稿。
  const [drafts, setDrafts] = useState<Record<string, string | number | null>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const groupTitle = groupsQuery.data?.items.find((g) => g.key === group)?.title ?? '';

  const dirtyKeys = useMemo(
    () =>
      Object.keys(drafts).filter((k) => {
        const item = itemsQuery.data?.items.find((i) => i.key === k);
        return item && drafts[k] !== (item.value as string | number | null);
      }),
    [drafts, itemsQuery.data],
  );


  const saveMutation = useMutation({
    mutationFn: (values: Record<string, string | number | boolean | null>) =>
      updateSettings(group!, values),
    onSuccess: (data, values) => {
      queryClient.setQueryData(['settings', 'items', group], data);
      setDrafts((d) => {
        const next = { ...d };
        for (const k of Object.keys(values)) delete next[k];
        return next;
      });
      Toast.success(t('form.saved', { group: groupTitle }));
      // 系统名/标志/时区/水印保存后本会话立即生效；刷新后由种子查询恢复。
      if (group === 'basic' || group === 'watermark') {
        applySiteSettings(settingsItemsToPatch(data.items));
      }
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('loadFailed')));
    },
  });

  const trySwitchGroup = (next: string) => {
    if (next === group) return;
    const doSwitch = () => {
      setDrafts({});
      setErrors({});
      setActiveGroup(next);
    };
    if (dirtyKeys.length > 0) {
      confirmAction({
        title: t('form.discardTitle'),
        okText: t('form.discardOk'),
        cancelText: t('form.discardCancel'),
        onOk: doSwitch,
      });
      return;
    }
    doSwitch();
  };

  const immediateSave = (item: SettingItem, value: string | number | boolean | null) => {
    // 登录保持时长改短时先提示后果。
    if (
      item.key === 'session_days' &&
      typeof value === 'number' &&
      typeof item.value === 'number' &&
      value < item.value
    ) {
      confirmAction({
        title: t('form.sessionShrinkTitle', { days: value }),
        content: t('form.sessionShrinkContent'),
        okText: t('form.sessionShrinkOk', { days: value }),
        cancelText: t('form.think'),
        onOk: () => saveMutation.mutate({ [item.key]: value }),
      });
      return;
    }
    saveMutation.mutate({ [item.key]: value });
  };

  const saveDrafts = () => {
    const errs: Record<string, string> = {};
    for (const k of dirtyKeys) {
      const item = itemsQuery.data?.items.find((i) => i.key === k);
      if (!item) continue;
      if (item.type === 'text' && (drafts[k] === null || String(drafts[k] ?? '').trim() === '')) {
        errs[k] = t('form.required');
      }
    }
    setErrors(errs);
    if (Object.keys(errs).length > 0) {
      // 聚焦第一个出错字段。
      const first = Object.keys(errs)[0];
      document.querySelector<HTMLElement>(`[data-setting-key="${first}"]`)?.focus();
      return;
    }
    const values: Record<string, string | number | null> = {};
    for (const k of dirtyKeys) values[k] = drafts[k];
    saveMutation.mutate(values);
  };

  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const uploadLogo = async (item: SettingItem, file: File) => {
    try {
      const res = await uploadSettingLogo(file);
      // 契约：回 FileObject，把 file_id 写进设置项保存。
      saveMutation.mutate({ [item.key]: res.id });
    } catch (error) {
      Toast.error(adminErrorMessage(error, t('loadFailed')));
    }
  };

  if (!canIndex) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <Empty image={<IllustrationNoAccess />} title={t('noPermission')} description="">
          <Button type="primary" onClick={() => navigate('/')}>
            {tc('actions.backHome')}
          </Button>
        </Empty>
      </div>
    );
  }

  if (groupsQuery.isError && !groupsQuery.data) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <ErrorState error={groupsQuery.error} onRetry={() => void groupsQuery.refetch()} />
      </div>
    );
  }

  const items = itemsQuery.data?.items ?? [];

  const renderItem = (item: SettingItem) => {
    const readonly = !canUpdate;
    if (isSecretItem(item)) {
      return (
        <div key={item.key} className="flex flex-col gap-1 py-2">
          <Typography.Text strong>{item.label}</Typography.Text>
          <Typography.Text type="tertiary" size="small">{t('form.secretSet')}</Typography.Text>
        </div>
      );
    }
    const help = (
      <Typography.Text type="tertiary" size="small" style={{ display: 'block' }}>
        {[
          item.remark,
          // 有默认值的 select 随时告诉人清空会落到哪。
          item.type === 'select' && item.default_value != null
            ? t('form.clearedToDefault', {
                value:
                  item.options?.find((o) => o.value === item.default_value)?.label ??
                  String(item.default_value),
              })
            : null,
        ]
          .filter(Boolean)
          .join('；')}
      </Typography.Text>
    );
    const error = errors[item.key];
    return (
      <div key={item.key} className="flex flex-col gap-1 py-2">
        <Typography.Text strong>{item.label}</Typography.Text>
        {item.type === 'text' ? (
          <Input
            data-setting-key={item.key}
            value={String(drafts[item.key] ?? item.value ?? '')}
            disabled={readonly}
            onChange={(v) => setDrafts((d) => ({ ...d, [item.key]: v }))}
            style={{ maxWidth: 420 }}
          />
        ) : item.type === 'password' ? (
          // 未设置过的敏感项可以在这里第一次填写。
          <Input
            data-setting-key={item.key}
            type="password"
            value={String(drafts[item.key] ?? item.value ?? '')}
            disabled={readonly}
            onChange={(v) => setDrafts((d) => ({ ...d, [item.key]: v }))}
            style={{ maxWidth: 420 }}
          />
        ) : item.type === 'number' ? (
          <InputNumber
            data-setting-key={item.key}
            value={Number(drafts[item.key] ?? item.value ?? 0)}
            disabled={readonly}
            onChange={(v) => setDrafts((d) => ({ ...d, [item.key]: Number(v) }))}
          />
        ) : item.type === 'boolean' ? (
          <Switch
            checked={Boolean(item.value)}
            disabled={readonly || saveMutation.isPending}
            onChange={(v) => immediateSave(item, v)}
            aria-label={item.label}
          />
        ) : item.type === 'select' ? (
          <Select
            value={(drafts[item.key] ?? item.value) as string | number | undefined}
            disabled={readonly || saveMutation.isPending}
            optionList={item.options ?? []}
            showClear={item.default_value != null}
            onChange={(v) => {
              const next = (v as string | number | undefined) ?? null;
              if (next === null) {
                // 可清空且有默认值：清空 = 恢复默认，立即生效。
                immediateSave(item, item.default_value ?? null);
                return;
              }
              immediateSave(item, next);
            }}
            style={{ width: 280 }}
          />
        ) : item.type === 'image' ? (
          <div className="flex items-center gap-4">
            <div className="flex gap-3">
              <div className="flex flex-col items-center gap-1">
                <div style={{ background: '#fff' }}>
                  <SettingLogoPreview fileRef={item.value == null ? null : String(item.value)} />
                </div>
                <Typography.Text type="tertiary" size="small">{t('form.logoLight')}</Typography.Text>
              </div>
              <div className="flex flex-col items-center gap-1">
                <div style={{ background: '#16161a' }}>
                  <SettingLogoPreview fileRef={item.value == null ? null : String(item.value)} />
                </div>
                <Typography.Text type="tertiary" size="small">{t('form.logoDark')}</Typography.Text>
              </div>
            </div>
            {readonly ? null : (
              <>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  style={{ display: 'none' }}
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) void uploadLogo(item, file);
                    e.target.value = '';
                  }}
                />
                <Button size="small" onClick={() => fileInputRef.current?.click()}>
                  {t('form.logoUpload')}
                </Button>
              </>
            )}
          </div>
        ) : null}
        {help}
        {error ? (
          <Typography.Text type="danger" size="small">{error}</Typography.Text>
        ) : null}
      </div>
    );
  };

  return (
    <div className="p-6 flex flex-col gap-4">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />
      {!canUpdate ? <Banner type="info" description={t('readonlyBanner')} closeIcon={null} /> : null}

      <div className="flex gap-6">
        <Tabs
          type="line"
          tabPosition="left"
          activeKey={group ?? undefined}
          onChange={trySwitchGroup}
          style={{ flexShrink: 0 }}
        >
          {(groupsQuery.data?.items ?? []).map((g) => (
            <Tabs.TabPane
              key={g.key}
              itemKey={g.key}
              tab={
                <span className="flex flex-col">
                  <span>{g.title}</span>
                  {g.key === 'login' ? (
                    <Typography.Text type="tertiary" size="small">
                      {t('groups.loginHint')}
                    </Typography.Text>
                  ) : null}
                </span>
              }
            />
          ))}
        </Tabs>

        <div className="flex-1 min-w-0">
          {itemsQuery.isError && !itemsQuery.data ? (
            <ErrorState error={itemsQuery.error} onRetry={() => void itemsQuery.refetch()} />
          ) : items.length === 0 && !itemsQuery.isLoading ? (
            <Empty />
          ) : (
            <>
              {items.map(renderItem)}
              {canUpdate && dirtyKeys.length > 0 ? (
                <div className="mt-2 flex justify-end">
                  <Button
                    theme="solid"
                    type="primary"
                    onClick={saveDrafts}
                    loading={saveMutation.isPending}
                  >
                    {t('form.save')}
                  </Button>
                </div>
              ) : null}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
