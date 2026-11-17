import { useGlobalUi, closeSettings } from './globalUi';
import { ScreenLock } from './ScreenLock';
import { SettingsPanel } from './SettingsPanel';

/**
 * 全局件统一挂载点：设置面板、锁屏罩层这类与路由无关的全局
 * UI 都挂这里；往后再加全局搜索、命令面板也放这里。
 */
export function GlobalMount() {
  const { settingsOpen, screenLocked } = useGlobalUi();
  return (
    <>
      <SettingsPanel open={settingsOpen} onClose={closeSettings} />
      {screenLocked ? <ScreenLock /> : null}
    </>
  );
}
