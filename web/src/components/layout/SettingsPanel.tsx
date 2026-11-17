import { IconLock } from '@douyinfe/semi-icons';
import { Button, Radio, SideSheet, Switch, Typography } from '@douyinfe/semi-ui';
import { useTranslation } from 'react-i18next';

import { useThemeMode } from '@/layouts/themeMode';
import type { ThemeColor, ThemeMode } from '@/lib/theme';
import { setLayoutMode, useLayoutSettings, type LayoutMode } from '@/stores/layout';
import { lockScreen, setTabsEnabled, useGlobalUi } from './globalUi';
import { LayoutPreview } from './LayoutPreview';

// 色板取值与 lib/theme COLOR_TOKENS 一一对应；调整色板时两处一起改。
const THEME_COLORS: Array<{ key: ThemeColor; hex: string }> = [
  { key: 'blue', hex: '#1664ff' },
  { key: 'violet', hex: '#6941c6' },
  { key: 'green', hex: '#099250' },
  { key: 'orange', hex: '#e8590c' },
  { key: 'red', hex: '#d92d20' },
];

const THEME_MODES: ThemeMode[] = ['light', 'dark', 'auto'];
const LAYOUT_MODES: LayoutMode[] = ['side', 'top', 'mix'];

const MODE_LABEL_KEY: Record<ThemeMode, string> = {
  light: 'settings.modeLight',
  dark: 'settings.modeDark',
  auto: 'settings.modeAuto',
};

const LAYOUT_LABEL_KEY: Record<LayoutMode, string> = {
  side: 'settings.layoutSide',
  top: 'settings.layoutTop',
  mix: 'settings.layoutMix',
};

export interface SettingsPanelProps {
  open: boolean;
  onClose: () => void;
}

/**
 * 设置面板（SideSheet 右侧抽屉）：主题模式、主题色、布局模式、
 * 多标签开关、锁屏入口。全部是设备级 UI 偏好，持久化走
 * storage，不打任何接口。
 */
export function SettingsPanel({ open, onClose }: SettingsPanelProps) {
  const { t } = useTranslation('feLayout');
  const theme = useThemeMode();
  const layout = useLayoutSettings();
  const { tabsEnabled } = useGlobalUi();

  const handleLock = () => {
    lockScreen();
    onClose();
  };

  return (
    <SideSheet
      title={t('settings.title')}
      visible={open}
      onCancel={onClose}
      width={360}
      placement="right"
    >
      <div className="flex flex-col gap-6">
        <section>
          <Typography.Title heading={6}>{t('settings.themeMode')}</Typography.Title>
          <Radio.Group
            type="button"
            value={theme.mode}
            onChange={(e) => theme.setMode(e.target.value as ThemeMode)}
            options={THEME_MODES.map((mode) => ({
              value: mode,
              label: t(MODE_LABEL_KEY[mode]),
            }))}
          />
        </section>

        <section>
          <Typography.Title heading={6}>{t('settings.themeColor')}</Typography.Title>
          <div className="mt-1 flex gap-2">
            {THEME_COLORS.map((color) => (
              <button
                key={color.key}
                type="button"
                aria-label={t(`settings.colors.${color.key}`)}
                aria-pressed={theme.color === color.key}
                onClick={() => theme.setColor(color.key)}
                className="h-7 w-7 rounded-full"
                style={{
                  cursor: 'pointer',
                  backgroundColor: color.hex,
                  border:
                    theme.color === color.key
                      ? `2px solid var(--semi-color-text-0)`
                      : '2px solid transparent',
                  outline:
                    theme.color === color.key
                      ? `2px solid ${color.hex}`
                      : '2px solid transparent',
                }}
              />
            ))}
          </div>
        </section>

        <section>
          <Typography.Title heading={6}>{t('settings.layoutMode')}</Typography.Title>
          <div className="mt-1 flex gap-2">
            {LAYOUT_MODES.map((mode) => (
              <LayoutPreview
                key={mode}
                mode={mode}
                active={layout.layoutMode === mode}
                label={t(LAYOUT_LABEL_KEY[mode])}
                onSelect={() => setLayoutMode(mode)}
              />
            ))}
          </div>
        </section>

        <section className="flex items-center justify-between">
          <Typography.Text>{t('settings.tabs')}</Typography.Text>
          <Switch
            checked={tabsEnabled}
            onChange={(checked) => setTabsEnabled(checked)}
            aria-label={t('settings.tabs')}
          />
        </section>

        <section>
          <Button icon={<IconLock />} onClick={handleLock} block>
            {t('settings.lockNow')}
          </Button>
        </section>
      </div>
    </SideSheet>
  );
}
