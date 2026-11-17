import type { RouteObject } from 'react-router';

import i18n from '@/i18n/i18n';

/*
 * 插件前端收集点：每个插件一个子目录 `src/plugins/<code>/`，入口
 * `index.ts(x)` 默认导出 PluginModule。真实插件包里的
 * `plugin-web/<app>/` 内容由构建期 `scripts/collect-plugin-pages.mjs`
 * 同步到这里，本文件只负责按目录收集与注册。
 */
export interface PluginModule {
  /** 插件编码，与目录名、菜单 `plugin` 字段、路由 `/plugin/<code>` 一致。 */
  code: string;
  /** 插件自声明的子路由，相对 `/plugin/<code>` 解析。 */
  routes: RouteObject[];
  /** 整个插件入口的权限码；数组要求全部满足（与宿主 useCan 语义一致）。 */
  permission?: string | string[];
  /** 按语言分包的词条，注册进 `plugin-<code>` 命名空间，不撞宿主 key。 */
  locales?: Record<string, Record<string, unknown>>;
}

type PluginEntry = { default: PluginModule };

const entries = import.meta.glob<PluginEntry>('./*/index.{ts,tsx}', { eager: true });

const modules = new Map<string, PluginModule>();
for (const entry of Object.values(entries)) {
  const plugin = entry.default;
  if (!plugin || typeof plugin.code !== 'string' || !Array.isArray(plugin.routes)) {
    continue;
  }
  modules.set(plugin.code, plugin);
  if (plugin.locales) {
    for (const [lng, resources] of Object.entries(plugin.locales)) {
      i18n.addResourceBundle(lng, `plugin-${plugin.code}`, resources, true, true);
    }
  }
}

export function getPluginModule(code: string): PluginModule | undefined {
  return modules.get(code);
}

export function listPluginModules(): PluginModule[] {
  return [...modules.values()];
}
