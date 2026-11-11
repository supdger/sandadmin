import type { ReactNode } from 'react';

import {
  IconApps,
  IconBarChartHStroked,
  IconBell,
  IconBook,
  IconBriefcase,
  IconCalendar,
  IconClock,
  IconEyeOpened,
  IconFile,
  IconFilter,
  IconFolder,
  IconHome,
  IconIdCard,
  IconLink,
  IconList,
  IconMail,
  IconMapPin,
  IconMenu,
  IconPhone,
  IconPriceTag,
  IconSafe,
  IconSetting,
  IconStar,
  IconUser,
  IconUserGroup,
} from '@douyinfe/semi-icons';

export interface MenuIconOption {
  /** 存进 `menu.icon` 的值；必须是 shell 能渲染的键。 */
  key: string;
  /** 选择器里展示、也用于关键词搜索的中文名。 */
  label: string;
  /** 额外搜索关键词（用户可能输入的同义词）。 */
  keywords: string;
  icon: ReactNode;
}

/**
 * 菜单编辑器自建的图标集。key 同时是侧边栏认识的图标名——
 * 需与 AppShell 里的 `MENU_ICONS` 保持同步。
 */
export const MENU_ICON_OPTIONS: MenuIconOption[] = [
  { key: 'home', label: '首页', keywords: '工作台 首页', icon: <IconHome /> },
  { key: 'users', label: '用户组', keywords: '成员 团队', icon: <IconUserGroup /> },
  { key: 'user', label: '用户', keywords: '个人 账号', icon: <IconUser /> },
  { key: 'setting', label: '设置', keywords: '配置 系统', icon: <IconSetting /> },
  { key: 'menu', label: '菜单', keywords: '导航 栏目', icon: <IconMenu /> },
  { key: 'safe', label: '安全', keywords: '权限 盾牌', icon: <IconSafe /> },
  { key: 'chart', label: '图表', keywords: '报表 数据 看板', icon: <IconBarChartHStroked /> },
  { key: 'file', label: '文件', keywords: '文档 资料', icon: <IconFile /> },
  { key: 'link', label: '链接', keywords: '外链 网址', icon: <IconLink /> },
  { key: 'star', label: '星标', keywords: '收藏 喜欢', icon: <IconStar /> },
  { key: 'calendar', label: '日历', keywords: '日期 日程', icon: <IconCalendar /> },
  { key: 'clock', label: '时钟', keywords: '时间 历史', icon: <IconClock /> },
  { key: 'mail', label: '邮件', keywords: '邮箱 信箱', icon: <IconMail /> },
  { key: 'phone', label: '电话', keywords: '联系方式 客服', icon: <IconPhone /> },
  { key: 'map', label: '位置', keywords: '地图 地址', icon: <IconMapPin /> },
  { key: 'tag', label: '标签', keywords: '分类 标记', icon: <IconPriceTag /> },
  { key: 'folder', label: '文件夹', keywords: '目录 归档', icon: <IconFolder /> },
  { key: 'list', label: '列表', keywords: '清单 表格', icon: <IconList /> },
  { key: 'filter', label: '筛选', keywords: '过滤 搜索', icon: <IconFilter /> },
  { key: 'eye', label: '查看', keywords: '预览 显示', icon: <IconEyeOpened /> },
  { key: 'bell', label: '通知', keywords: '消息 提醒', icon: <IconBell /> },
  { key: 'book', label: '书本', keywords: '阅读 手册', icon: <IconBook /> },
  { key: 'building', label: '部门', keywords: '组织 机构', icon: <IconBriefcase /> },
  { key: 'idcard', label: '证件', keywords: '身份 岗位', icon: <IconIdCard /> },
];

const ICON_BY_KEY = new Map(MENU_ICON_OPTIONS.map((opt) => [opt.key, opt.icon]));

/** 把存储的图标名解析成组件；未知名字用中性兜底图标。 */
export function menuIconNode(icon: string | null | undefined): ReactNode {
  if (!icon) {
    return undefined;
  }
  return ICON_BY_KEY.get(icon) ?? <IconApps />;
}
