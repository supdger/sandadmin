import { apiDelete, apiGet, apiPost, apiPut } from '@/lib/apiClient';

export type ChartRange = '7d' | '30d' | '90d';

/** Sub-line under a summary number; `tone` drives its color. */
export interface CardSub {
  text?: string;
  tone?: 'up' | 'down' | 'warning' | 'plain';
}

/** GET /dashboard/summary card — `error` marks a single failed card. */
export interface DashboardCard {
  key: string;
  value: number | null;
  unit: string | null;
  sub: CardSub | null;
  link: string | null;
  error: { code: string; request_id?: string } | null;
}

export interface DashboardCardList {
  cards: DashboardCard[];
}

export interface OnboardingStep {
  key: string;
  done: boolean;
}

export interface OnboardingState {
  done: boolean;
  steps: OnboardingStep[];
}

/** GET /dashboard/todos item — one thing waiting on the current user. */
export interface TodoItem {
  source_key: string;
  source_label: string;
  title: string;
  create_time: string;
  overdue: boolean;
  link: string;
}

export interface TodoList {
  items: TodoItem[];
  total: number;
}

export interface PageTodo {
  items: TodoItem[];
  total: number;
  page: number;
  page_size: number;
}

export interface DashboardChart {
  key: string;
  unit: string | null;
  points: Array<{ day: string; value: number }>;
}

export interface ChartList {
  charts: DashboardChart[];
}

export interface ActivityItem {
  actor_name: string;
  action_text: string;
  target_label: string | null;
  create_time: string;
}

export interface ActivityList {
  items: ActivityItem[];
}

/** GET /dashboard/notices item — at most the first one is shown as a banner. */
export interface DashboardNotice {
  notice_key: string;
  level: 'warning' | 'info';
  text_key: string;
  params: Record<string, unknown> | null;
  action: { label_key: string; link: string } | null;
}

export interface NoticeList {
  items: DashboardNotice[];
}

export interface ShortcutItem {
  menu_key: string;
  title: string;
  icon: string | null;
  link: string;
}

export interface ShortcutList {
  mode: 'auto' | 'manual';
  items: ShortcutItem[];
}

export function getOnboarding(): Promise<OnboardingState> {
  return apiGet<OnboardingState>('/dashboard/onboarding');
}

export function getSummaryCards(): Promise<DashboardCardList> {
  return apiGet<DashboardCardList>('/dashboard/summary');
}

export function getWorkbenchTodos(limit = 5): Promise<TodoList> {
  return apiGet<TodoList>(`/dashboard/todos?limit=${limit}`);
}

export function getAllTodos(page: number, pageSize: number): Promise<PageTodo> {
  return apiGet<PageTodo>(`/dashboard/todos/all?page=${page}&page_size=${pageSize}`);
}

export function getDashboardCharts(range: ChartRange): Promise<ChartList> {
  return apiGet<ChartList>(`/dashboard/charts?range=${range}`);
}

export function getRecentActivities(limit = 10): Promise<ActivityList> {
  return apiGet<ActivityList>(`/dashboard/activities?limit=${limit}`);
}

export function getWorkbenchNotices(): Promise<NoticeList> {
  return apiGet<NoticeList>('/dashboard/notices');
}

export function dismissNotice(noticeKey: string): Promise<unknown> {
  return apiPost(`/dashboard/notices/${encodeURIComponent(noticeKey)}/dismiss`);
}

export function getShortcuts(): Promise<ShortcutList> {
  return apiGet<ShortcutList>('/dashboard/shortcuts');
}

export function saveShortcuts(menuKeys: string[]): Promise<ShortcutList> {
  return apiPut<ShortcutList>('/dashboard/shortcuts', { menu_keys: menuKeys });
}

export function resetShortcuts(): Promise<ShortcutList> {
  return apiDelete<ShortcutList>('/dashboard/shortcuts');
}

export function reportVisits(menuKeys: string[]): Promise<unknown> {
  return apiPost('/dashboard/visits', { menu_keys: menuKeys });
}
