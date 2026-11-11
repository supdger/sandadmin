import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import { env } from '@/config/env';

/*
 * Locale resources are aggregated from `locales/<locale>/<namespace>.ts`;
 * the file name becomes the i18next namespace. New locales or namespaces
 * only need a new file — no changes here.
 */
type ResourceModule = { default: Record<string, unknown> };

const zhCNModules = import.meta.glob<ResourceModule>('./locales/zh-CN/*.ts', {
  eager: true,
});

const zhCNResources: Record<string, Record<string, unknown>> = {};
for (const [path, mod] of Object.entries(zhCNModules)) {
  const ns = path.replace(/^.*\//, '').replace(/\.ts$/, '');
  zhCNResources[ns] = mod.default;
}

void i18n.use(initReactI18next).init({
  lng: env.defaultLocale,
  fallbackLng: 'zh-CN',
  defaultNS: 'common',
  resources: {
    'zh-CN': zhCNResources,
  },
  interpolation: {
    escapeValue: false,
  },
});

export default i18n;
