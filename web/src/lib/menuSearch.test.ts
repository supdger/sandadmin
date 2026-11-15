import { describe, expect, it } from 'vitest';

import type { MyMenuNode } from '@/api/v1/me';
import { flattenMenuPages, matchMenuPages, pinyinInitials } from './menuSearch';

const MENUS: MyMenuNode[] = [
  { key: 'home', type: 'page', title: '工作台', icon: null, path: '/', children: [], url: null },
  {
    key: 'org',
    type: 'group',
    title: '组织与用户',
    icon: null,
    path: null, url: null,
    children: [
      { key: 'system:user', type: 'page', title: '用户管理', icon: null, path: '/system/user', children: [], url: null },
      { key: 'system:dept', type: 'page', title: '部门管理', icon: null, path: '/system/dept', children: [], url: null },
    ],
  },
];

describe('menuSearch', () => {
  it('拉平菜单页并保留分组名', () => {
    const hits = flattenMenuPages(MENUS);
    expect(hits).toHaveLength(3);
    expect(hits.find((h) => h.title === '用户管理')?.groupTitle).toBe('组织与用户');
  });

  it('按汉字与拼音首字母匹配', () => {
    const hits = flattenMenuPages(MENUS);
    expect(matchMenuPages(hits, '用户').map((h) => h.title)).toContain('用户管理');
    expect(matchMenuPages(hits, 'yhgl').map((h) => h.title)).toContain('用户管理');
    expect(matchMenuPages(hits, 'bmgl').map((h) => h.title)).toContain('部门管理');
  });

  it('拼音首字母覆盖菜单常见字', () => {
    expect(pinyinInitials('用户管理')).toBe('yhgl');
  });
});
