import type { ReactNode } from 'react';

import { SvgIcon } from '@/components/SvgIcon';

export interface MenuIconOption {
  /** 存进 `menu.icon` 的值；必须是图标注册表里的键（lib/icons）。 */
  key: string;
  /** 选择器里展示、也用于关键词搜索的中文名。 */
  label: string;
  /** 额外搜索关键词（用户可能输入的同义词）。 */
  keywords: string;
  icon: ReactNode;
}

/**
 * 菜单编辑器可选项清单。key 同时是侧边栏认识的图标名，
 * 组件由 `components/SvgIcon` 查注册表渲染，两份映射表
 * 在这里合成一份——新增图标只改 lib/icons 的注册。
 */
export const MENU_ICON_OPTIONS: MenuIconOption[] = [
  { key: 'home', label: '首页', keywords: '工作台 首页', icon: <SvgIcon name="home" /> },
  { key: 'users', label: '用户组', keywords: '成员 团队', icon: <SvgIcon name="users" /> },
  { key: 'user', label: '用户', keywords: '个人 账号', icon: <SvgIcon name="user" /> },
  { key: 'setting', label: '设置', keywords: '配置 系统', icon: <SvgIcon name="setting" /> },
  { key: 'menu', label: '菜单', keywords: '导航 栏目', icon: <SvgIcon name="menu" /> },
  { key: 'safe', label: '安全', keywords: '权限 盾牌', icon: <SvgIcon name="safe" /> },
  { key: 'chart', label: '图表', keywords: '报表 数据 看板', icon: <SvgIcon name="chart" /> },
  { key: 'file', label: '文件', keywords: '文档 资料', icon: <SvgIcon name="file" /> },
  { key: 'link', label: '链接', keywords: '外链 网址', icon: <SvgIcon name="link" /> },
  { key: 'star', label: '星标', keywords: '收藏 喜欢', icon: <SvgIcon name="star" /> },
  { key: 'calendar', label: '日历', keywords: '日期 日程', icon: <SvgIcon name="calendar" /> },
  { key: 'clock', label: '时钟', keywords: '时间 历史', icon: <SvgIcon name="clock" /> },
  { key: 'mail', label: '邮件', keywords: '邮箱 信箱', icon: <SvgIcon name="mail" /> },
  { key: 'phone', label: '电话', keywords: '联系方式 客服', icon: <SvgIcon name="phone" /> },
  { key: 'map', label: '位置', keywords: '地图 地址', icon: <SvgIcon name="map" /> },
  { key: 'tag', label: '标签', keywords: '分类 标记', icon: <SvgIcon name="tag" /> },
  { key: 'folder', label: '文件夹', keywords: '目录 归档', icon: <SvgIcon name="folder" /> },
  { key: 'list', label: '列表', keywords: '清单 表格', icon: <SvgIcon name="list" /> },
  { key: 'filter', label: '筛选', keywords: '过滤 搜索', icon: <SvgIcon name="filter" /> },
  { key: 'eye', label: '查看', keywords: '预览 显示', icon: <SvgIcon name="eye" /> },
  { key: 'bell', label: '通知', keywords: '消息 提醒', icon: <SvgIcon name="bell" /> },
  { key: 'book', label: '书本', keywords: '阅读 手册', icon: <SvgIcon name="book" /> },
  { key: 'building', label: '部门', keywords: '组织 机构', icon: <SvgIcon name="building" /> },
  { key: 'idcard', label: '证件', keywords: '身份 岗位', icon: <SvgIcon name="idcard" /> },
];

/** 把存储的图标名解析成组件；未知名字用注册表兜底图标。 */
export function menuIconNode(icon: string | null | undefined): ReactNode {
  if (!icon) {
    return undefined;
  }
  return <SvgIcon name={icon} />;
}
