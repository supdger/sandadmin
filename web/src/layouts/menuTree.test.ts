import type { NavItems } from '@douyinfe/semi-ui/lib/es/navigation';
import { describe, expect, it } from 'vitest';

import type { MyMenuNode } from '@/api/v1/me';
// 插件词条在注册表初始化时写入 plugin-<code> 命名空间，这里靠副作用引入。
import '@/plugins/registry';

import { resolveMenuTitle, toNavItems } from './menuTree';

const pluginPage: MyMenuNode = {
  key: 'plugin:sand-hello:home',
  type: 'page',
  title: '插件首页',
  icon: null,
  path: '/plugin/sand-hello',
  url: null,
  title_key: 'menu.home',
  plugin: 'sand-hello',
};

describe('resolveMenuTitle', () => {
  it('translates plugin titles through the plugin-<code> namespace', () => {
    expect(resolveMenuTitle(pluginPage)).toBe('你好插件');
  });

  it('falls back to title when plugin or title_key is missing', () => {
    expect(resolveMenuTitle({ ...pluginPage, plugin: null })).toBe('插件首页');
    expect(resolveMenuTitle({ ...pluginPage, title_key: null })).toBe('插件首页');
  });

  it('falls back to title when the plugin namespace is not registered', () => {
    expect(resolveMenuTitle({ ...pluginPage, plugin: 'ghost' })).toBe('插件首页');
  });
});

describe('toNavItems', () => {
  it('translates plugin titles and keeps group nesting', () => {
    const group: MyMenuNode = {
      key: 'plugin:sand-hello',
      type: 'group',
      title: '你好插件',
      icon: 'star',
      path: null,
      url: null,
      children: [pluginPage],
    };
    const items = toNavItems([group]) as Array<{ text?: unknown; items?: NavItems }>;
    expect(items[0]?.text).toBe('你好插件');
    expect((items[0]?.items?.[0] as { text?: unknown })?.text).toBe('你好插件');
  });

  it('keeps the backend title for nodes without plugin metadata', () => {
    const plain: MyMenuNode = {
      key: 'home',
      type: 'page',
      title: '工作台',
      icon: 'home',
      path: '/',
      url: null,
    };
    const items = toNavItems([plain]) as Array<{ text?: unknown }>;
    expect(items[0]?.text).toBe('工作台');
  });
});
