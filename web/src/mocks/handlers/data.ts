import { http, HttpResponse, type HttpHandler } from 'msw';

import { env } from '@/config/env';
import { mockUserForToken } from './auth';
import { failOpenapi, okOpenapi } from './_shared';

const base = env.apiBaseUrl;

interface MockUser {
  username: string;
  nickname: string;
  permissions: string[];
}

function bearerToken(request: Request): string {
  return (request.headers.get('authorization') ?? '').replace(/^Bearer\s+/i, '');
}

function can(user: MockUser, permission: string): boolean {
  return user.permissions.includes('*') || user.permissions.includes(permission);
}

/** Returns the user, or an HttpResponse to send back immediately. */
function requireUser(
  request: Request,
  permission?: string,
): { user: MockUser } | { denied: ReturnType<typeof failOpenapi> } {
  const user = mockUserForToken(bearerToken(request));
  if (!user) {
    return { denied: failOpenapi(401, 'UNAUTHENTICATED', '请先登录') };
  }
  if (permission && !can(user, permission)) {
    return { denied: failOpenapi(403, 'PERMISSION_DENIED', '你没有权限做这个操作') };
  }
  return { user };
}

function isoDaysAgo(days: number, hour = 10): string {
  const date = new Date(Date.now() - days * 86_400_000);
  date.setHours(hour, 15, 0, 0);
  return date.toISOString();
}

function isoDaysAhead(days: number): string {
  return new Date(Date.now() + days * 86_400_000).toISOString();
}

function time(value: string | null | undefined): number {
  if (!value) return 0;
  const t = Date.parse(value);
  return Number.isNaN(t) ? 0 : t;
}

/* 选项组 mock */

interface DictTypeRecord {
  id: string;
  name: string;
  code: string;
  remark: string | null;
  is_system: boolean;
}

interface DictItemRecord {
  id: string;
  type_id: string;
  label: string;
  value: string;
  color: string | null;
  is_default: boolean;
  is_system: boolean;
  sort: number;
  status: 'enabled' | 'disabled';
  used: boolean | null;
  create_time: string;
}

const dictTypes: DictTypeRecord[] = [
  { id: 'dict-type-1', name: '客户来源', code: 'customer_source', remark: '客户从哪里知道我们', is_system: true },
  { id: 'dict-type-2', name: '用户状态', code: 'user_state', remark: null, is_system: true },
  { id: 'dict-type-3', name: '请假类型', code: 'leave_type', remark: '员工请假时可选的类型', is_system: false },
  { id: 'dict-type-4', name: '快递公司', code: 'express_company', remark: null, is_system: false },
  { id: 'dict-type-5', name: '备用选项组', code: 'reserved_group', remark: '预留给后续业务使用', is_system: false },
  { id: 'dict-type-6', name: '会员等级', code: 'member_level', remark: null, is_system: false },
];

function dictItem(
  id: string,
  typeId: string,
  label: string,
  sort: number,
  extra?: Partial<DictItemRecord>,
): DictItemRecord {
  return {
    id,
    type_id: typeId,
    label,
    value: `v_${id}`,
    color: null,
    is_default: false,
    is_system: false,
    sort,
    status: 'enabled',
    used: null,
    create_time: isoDaysAgo(60 + sort),
    ...extra,
  };
}

const dictItems: DictItemRecord[] = [
  dictItem('item-src-1', 'dict-type-1', '电话咨询', 1, { is_default: true, used: true }),
  dictItem('item-src-2', 'dict-type-1', '朋友介绍', 2, { used: true }),
  dictItem('item-src-3', 'dict-type-1', '门店路过', 3, { color: '#3491FA', used: true }),
  dictItem('item-src-4', 'dict-type-1', '网上搜索', 4, { used: false }),
  dictItem('item-src-5', 'dict-type-1', '展会活动', 5, { used: false, color: '#F77234' }),
  dictItem('item-src-6', 'dict-type-1', '其他', 6, { is_system: true, used: null, status: 'disabled' }),
  dictItem('item-st-1', 'dict-type-2', '正常', 1, { is_default: true, used: true }),
  dictItem('item-st-2', 'dict-type-2', '冻结', 2, { used: true, color: '#D83931' }),
  dictItem('item-st-3', 'dict-type-2', '已注销', 3, { used: false }),
  dictItem('item-lv-1', 'dict-type-3', '年假', 1, { is_default: true, used: true }),
  dictItem('item-lv-2', 'dict-type-3', '病假', 2, { used: true }),
  dictItem('item-lv-3', 'dict-type-3', '事假', 3, { used: false }),
  dictItem('item-lv-4', 'dict-type-3', '调休', 4, { used: null }),
  dictItem('item-ex-1', 'dict-type-4', '顺丰速运', 1, { is_default: true, used: true }),
  dictItem('item-ex-2', 'dict-type-4', '中通快递', 2, { used: true }),
  dictItem('item-ex-3', 'dict-type-4', '圆通速递', 3, { used: false }),
  dictItem('item-ex-4', 'dict-type-4', '韵达快递', 4, { used: false }),
  dictItem('item-ex-5', 'dict-type-4', '申通快递', 5, { used: null }),
  dictItem('item-mb-1', 'dict-type-6', '普通会员', 1, { is_default: true, used: true }),
  dictItem('item-mb-2', 'dict-type-6', '银卡会员', 2, { used: true, color: '#C7A252' }),
  dictItem('item-mb-3', 'dict-type-6', '金卡会员', 3, { used: false, color: '#C7A252' }),
  dictItem('item-mb-4', 'dict-type-6', '钻石会员', 4, { used: null, color: '#7B61FF' }),
];

let dictTypeSeq = 100;
let dictItemSeq = 100;

function dictTypeView(type: DictTypeRecord) {
  return {
    ...type,
    item_count: dictItems.filter((i) => i.type_id === type.id).length,
  };
}

function findDictType(typeId: string): DictTypeRecord | undefined {
  return dictTypes.find((t) => t.id === typeId);
}

function findDictItem(itemId: string): DictItemRecord | undefined {
  return dictItems.find((i) => i.id === itemId);
}

/** 同组内 label 唯一；返回冲突的选项名或 null。 */
function dictLabelTaken(typeId: string, label: string, exceptId?: string): string | null {
  const hit = dictItems.find(
    (i) => i.type_id === typeId && i.label === label && i.id !== exceptId,
  );
  return hit ? hit.label : null;
}

function clearGroupDefaults(typeId: string, exceptId: string): void {
  for (const item of dictItems) {
    if (item.type_id === typeId && item.is_default && item.id !== exceptId) {
      item.is_default = false;
    }
  }
}

/* 文件 mock */

interface FileRecord {
  id: string;
  original_name: string;
  kind: 'image' | 'document' | 'sheet' | 'archive' | 'other';
  media_type: string;
  size_bytes: number;
  usage_state: 'used' | 'unused' | 'unknown';
  usage_label: string | null;
  visibility: 'private' | 'public';
  purpose: 'attachment' | 'export' | 'package' | 'plugin';
  uploader_id: string | null;
  uploader_name: string | null;
  create_time: string;
}

const UPLOADERS: Array<[string, string]> = [
  ['user-001', '张伟'],
  ['user-002', '李娜'],
  ['user-003', '王芳'],
  ['user-004', '刘洋'],
  ['user-005', '陈静'],
  ['user-006', '赵磊'],
];

const USAGE_LABELS = [
  '用户头像',
  '公告《系统维护通知》',
  '流程：请假申请',
  '部门：销售部共享资料',
  '操作记录附件',
];

interface FileSeed {
  name: string;
  kind: FileRecord['kind'];
  mb: number;
  usage: 'used' | 'unused' | 'unknown';
  uploader: number;
  days: number;
}

const FILE_SEEDS: FileSeed[] = [
  { name: '合同扫描件.pdf', kind: 'document', mb: 8.4, usage: 'used', uploader: 0, days: 3 },
  { name: '门店照片_01.jpg', kind: 'image', mb: 2.1, usage: 'used', uploader: 1, days: 5 },
  { name: '销售报表.xlsx', kind: 'sheet', mb: 0.6, usage: 'used', uploader: 2, days: 8 },
  { name: '安装包.zip', kind: 'archive', mb: 96.0, usage: 'unused', uploader: 3, days: 45 },
  { name: '用户协议.docx', kind: 'document', mb: 0.3, usage: 'used', uploader: 0, days: 60 },
  { name: '产品手册.pdf', kind: 'document', mb: 12.5, usage: 'used', uploader: 4, days: 21 },
  { name: '发票_2026-10.pdf', kind: 'document', mb: 0.2, usage: 'unused', uploader: 1, days: 12 },
  { name: '活动海报.png', kind: 'image', mb: 1.8, usage: 'used', uploader: 2, days: 2 },
  { name: '排班表.xlsx', kind: 'sheet', mb: 0.1, usage: 'used', uploader: 5, days: 1 },
  { name: '供应商报价单.pdf', kind: 'document', mb: 3.7, usage: 'used', uploader: 0, days: 15 },
  { name: '培训资料.pptx', kind: 'document', mb: 24.0, usage: 'used', uploader: 4, days: 30 },
  { name: '门头照片.jpg', kind: 'image', mb: 4.4, usage: 'unknown', uploader: 3, days: 18 },
  { name: '收据照片.png', kind: 'image', mb: 0.9, usage: 'unused', uploader: 1, days: 40 },
  { name: '服务器配置文档.docx', kind: 'document', mb: 0.1, usage: 'used', uploader: 0, days: 55 },
  { name: '促销方案.pdf', kind: 'document', mb: 5.2, usage: 'used', uploader: 2, days: 9 },
  { name: '考勤表.xlsx', kind: 'sheet', mb: 0.2, usage: 'unused', uploader: 5, days: 33 },
  { name: '产品目录.pdf', kind: 'document', mb: 18.6, usage: 'used', uploader: 4, days: 25 },
  { name: '合同终版.docx', kind: 'document', mb: 0.4, usage: 'used', uploader: 0, days: 4 },
  { name: '活动照片合集.zip', kind: 'archive', mb: 210.0, usage: 'unknown', uploader: 2, days: 7 },
  { name: '会议纪要.docx', kind: 'document', mb: 0.1, usage: 'used', uploader: 3, days: 1 },
  { name: '财务报表.xlsx', kind: 'sheet', mb: 1.3, usage: 'used', uploader: 1, days: 11 },
  { name: '品牌VI规范.pdf', kind: 'document', mb: 22.8, usage: 'used', uploader: 4, days: 50 },
  { name: '设备清单.xlsx', kind: 'sheet', mb: 0.3, usage: 'unused', uploader: 0, days: 38 },
  { name: '售后记录.xlsx', kind: 'sheet', mb: 0.8, usage: 'used', uploader: 5, days: 6 },
  { name: '新员工手册.pdf', kind: 'document', mb: 9.9, usage: 'used', uploader: 4, days: 16 },
  { name: '活动预算.xlsx', kind: 'sheet', mb: 0.2, usage: 'unused', uploader: 2, days: 42 },
  { name: '门店平面图.pdf', kind: 'document', mb: 6.1, usage: 'used', uploader: 3, days: 27 },
  { name: '宣传册_封面.jpg', kind: 'image', mb: 3.3, usage: 'used', uploader: 1, days: 13 },
  { name: '宣传册_内页.jpg', kind: 'image', mb: 3.5, usage: 'used', uploader: 1, days: 13 },
  { name: '系统截图_登录页.png', kind: 'image', mb: 0.7, usage: 'used', uploader: 0, days: 58 },
  { name: '系统截图_列表页.png', kind: 'image', mb: 1.1, usage: 'unknown', uploader: 0, days: 58 },
  { name: '报价单模板.xlsx', kind: 'sheet', mb: 0.2, usage: 'used', uploader: 2, days: 20 },
  { name: '物流签收单.pdf', kind: 'document', mb: 0.4, usage: 'unused', uploader: 5, days: 47 },
  { name: '团建合影.jpg', kind: 'image', mb: 5.6, usage: 'used', uploader: 3, days: 14 },
  { name: '团建合影_全家福.jpg', kind: 'image', mb: 6.2, usage: 'used', uploader: 3, days: 14 },
  { name: '差旅报销单.docx', kind: 'document', mb: 0.1, usage: 'unused', uploader: 4, days: 51 },
  { name: '季度经营分析.pptx', kind: 'document', mb: 15.4, usage: 'used', uploader: 0, days: 10 },
  { name: '门店监控截图.png', kind: 'image', mb: 2.8, usage: 'unknown', uploader: 5, days: 5 },
  { name: '加盟合同范本.pdf', kind: 'document', mb: 1.9, usage: 'used', uploader: 0, days: 65 },
  { name: '会员名单.xlsx', kind: 'sheet', mb: 0.4, usage: 'used', uploader: 1, days: 2 },
  { name: '装修效果图.zip', kind: 'archive', mb: 88.0, usage: 'unused', uploader: 4, days: 36 },
  { name: '新品发布会照片.zip', kind: 'archive', mb: 156.0, usage: 'used', uploader: 2, days: 6 },
  { name: '员工守则.pdf', kind: 'document', mb: 0.8, usage: 'used', uploader: 4, days: 70 },
  { name: '库存盘点表.xlsx', kind: 'sheet', mb: 0.5, usage: 'unused', uploader: 5, days: 29 },
  { name: '广告投放数据.csv', kind: 'sheet', mb: 0.3, usage: 'used', uploader: 1, days: 4 },
  { name: '客户回访记录.docx', kind: 'document', mb: 0.2, usage: 'unused', uploader: 2, days: 44 },
];

const MEDIA_BY_KIND: Record<FileRecord['kind'], string> = {
  image: 'image/png',
  document: 'application/pdf',
  sheet: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  archive: 'application/zip',
  other: 'application/octet-stream',
};

function fileRecord(seed: FileSeed, index: number): FileRecord {
  const [uploaderId, uploaderName] = UPLOADERS[seed.uploader % UPLOADERS.length];
  return {
    id: `file-${String(index + 1).padStart(3, '0')}`,
    original_name: seed.name,
    kind: seed.kind,
    media_type: MEDIA_BY_KIND[seed.kind],
    size_bytes: Math.round(seed.mb * 1024 * 1024),
    usage_state: seed.usage,
    usage_label: seed.usage === 'used' ? USAGE_LABELS[index % USAGE_LABELS.length] : null,
    visibility: 'private',
    purpose: 'attachment',
    uploader_id: uploaderId,
    uploader_name: uploaderName,
    create_time: isoDaysAgo(seed.days, 8 + (index % 9)),
  };
}

const files: FileRecord[] = FILE_SEEDS.map(fileRecord);
let fileSeq = files.length;

const KIND_BY_EXT: Record<string, FileRecord['kind']> = {
  jpg: 'image', jpeg: 'image', png: 'image', gif: 'image', webp: 'image', svg: 'image',
  pdf: 'document', doc: 'document', docx: 'document', txt: 'document', md: 'document', ppt: 'document', pptx: 'document',
  xls: 'sheet', xlsx: 'sheet', csv: 'sheet',
  zip: 'archive', rar: 'archive', '7z': 'archive', tar: 'archive', gz: 'archive',
};

function kindOfName(name: string): FileRecord['kind'] {
  const ext = name.split('.').pop()?.toLowerCase() ?? '';
  return KIND_BY_EXT[ext] ?? 'other';
}

function mediaOfName(name: string, fallback: string): string {
  const ext = name.split('.').pop()?.toLowerCase() ?? '';
  const table: Record<string, string> = {
    pdf: 'application/pdf',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    zip: 'application/zip',
    png: 'image/png',
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
  };
  return table[ext] ?? fallback;
}

/** 上传登记的用途标识映射成界面能看懂的说明。 */
function usageLabelOf(usage: string | null): string | null {
  if (!usage) return null;
  if (usage.startsWith('announcement')) return '公告配图';
  if (usage.includes('avatar')) return '用户头像';
  return '业务登记';
}

/* 后台任务 mock */

interface TaskRecord {
  id: string;
  type: string;
  state: 'queued' | 'running' | 'succeeded' | 'failed' | 'cancelled';
  progress: number | null;
  stage: string | null;
  result: Record<string, unknown> | null;
  error: { code: string; message: string } | null;
  create_time: string;
  finish_time: string | null;
}

const tasks = new Map<string, TaskRecord>();
let taskSeq = 0;

function taskView(task: TaskRecord) {
  return { ...task };
}

/* 公告 mock */

interface DeptRecord {
  id: string;
  parent_id: string | null;
  name: string;
  sort: number;
}

const DEPTS: DeptRecord[] = [
  { id: 'dept-1', parent_id: null, name: '总经办', sort: 1 },
  { id: 'dept-2', parent_id: null, name: '销售部', sort: 2 },
  { id: 'dept-3', parent_id: 'dept-2', name: '华东组', sort: 3 },
  { id: 'dept-4', parent_id: 'dept-2', name: '华南组', sort: 4 },
  { id: 'dept-5', parent_id: null, name: '市场部', sort: 5 },
  { id: 'dept-6', parent_id: null, name: '财务部', sort: 6 },
  { id: 'dept-7', parent_id: null, name: '行政部', sort: 7 },
  { id: 'dept-8', parent_id: 'dept-7', name: '前台组', sort: 8 },
];

function deptName(id: string): string {
  return DEPTS.find((d) => d.id === id)?.name ?? id;
}

interface AnnouncementRecord {
  id: string;
  title: string;
  content: string;
  scope: 'all' | 'dept';
  dept_ids: string[];
  level: 'normal' | 'important';
  pinned: boolean;
  state: 'draft' | 'published' | 'withdrawn';
  image_file_ids: string[];
  published_time: string | null;
  expire_time: string | null;
  created_by_name: string;
  create_time: string;
  update_time: string;
}

function announcementView(a: AnnouncementRecord) {
  return {
    ...a,
    dept_names: a.dept_ids.map(deptName),
    expired: isExpired(a),
  };
}

const announcements: AnnouncementRecord[] = [
  {
    id: 'ann-001', title: '系统维护通知', scope: 'all', dept_ids: [], level: 'normal', pinned: false,
    state: 'published', image_file_ids: [],
    content: '<p>各位同事：</p><p>本周六（11 月 15 日）凌晨 2:00–4:00 系统升级维护，期间后台无法登录，请提前保存手头工作。</p><p>给大家带来不便敬请谅解。</p>',
    published_time: isoDaysAgo(6, 9), expire_time: null, created_by_name: '张伟',
    create_time: isoDaysAgo(7, 14), update_time: isoDaysAgo(6, 9),
  },
  {
    id: 'ann-002', title: '新版选项管理上线', scope: 'all', dept_ids: [], level: 'important', pinned: true,
    state: 'published', image_file_ids: [],
    content: '<p>大家好：</p><p>新版「选项管理」已上线，管理各个下拉框里能选的内容，改完立即生效。系统自带的选项组可以加选项，但不能删除。</p><p>有问题请联系行政部。</p>',
    published_time: isoDaysAgo(2, 10), expire_time: isoDaysAhead(30), created_by_name: '李娜',
    create_time: isoDaysAgo(3, 16), update_time: isoDaysAgo(2, 10),
  },
  {
    id: 'ann-003', title: '市场部季度培训', scope: 'dept', dept_ids: ['dept-5', 'dept-2'], level: 'normal', pinned: false,
    state: 'published', image_file_ids: [],
    content: '<p>市场部与销售部的同事请注意：</p><p>本季度培训定于下周三下午 14:00 在三楼会议室举行，请提前 10 分钟到场。</p>',
    published_time: isoDaysAgo(4, 11), expire_time: null, created_by_name: '王芳',
    create_time: isoDaysAgo(5, 9), update_time: isoDaysAgo(4, 11),
  },
  {
    id: 'ann-004', title: '国庆值班安排', scope: 'all', dept_ids: [], level: 'normal', pinned: false,
    state: 'draft', image_file_ids: [],
    content: '<p>国庆假期值班表已排好，详见附件。</p>',
    published_time: null, expire_time: null, created_by_name: '张伟',
    create_time: isoDaysAgo(1, 15), update_time: isoDaysAgo(1, 15),
  },
  {
    id: 'ann-005', title: '秋季团建报名', scope: 'all', dept_ids: [], level: 'normal', pinned: false,
    state: 'withdrawn', image_file_ids: [],
    content: '<p>秋季团建定于 10 月下旬，请有意向的同事在本周五前报名。</p>',
    published_time: isoDaysAgo(20, 9), expire_time: null, created_by_name: '陈静',
    create_time: isoDaysAgo(22, 10), update_time: isoDaysAgo(12, 18),
  },
  {
    id: 'ann-006', title: '旧版公告（已过期）', scope: 'all', dept_ids: [], level: 'normal', pinned: false,
    state: 'published', image_file_ids: [],
    content: '<p>这是一条已过期的旧公告，仅用于演示「已过期」状态。</p>',
    published_time: isoDaysAgo(80, 9), expire_time: isoDaysAgo(10, 23), created_by_name: '张伟',
    create_time: isoDaysAgo(82, 9), update_time: isoDaysAgo(10, 23),
  },
  {
    id: 'ann-007', title: '关于规范文件命名的通知', scope: 'all', dept_ids: [], level: 'normal', pinned: false,
    state: 'draft', image_file_ids: [],
    content: '<p>各部门注意：今后上传的文件请按「部门-日期-主题」格式命名。</p>',
    published_time: null, expire_time: null, created_by_name: '李娜',
    create_time: isoDaysAgo(2, 11), update_time: isoDaysAgo(2, 11),
  },
  {
    id: 'ann-008', title: '年底审计材料提交通知', scope: 'dept', dept_ids: ['dept-6'], level: 'important', pinned: false,
    state: 'published', image_file_ids: [],
    content: '<p>财务部同事请于 11 月 30 日前提交全年审计所需材料清单。</p>',
    published_time: isoDaysAgo(1, 9), expire_time: isoDaysAhead(20), created_by_name: '王芳',
    create_time: isoDaysAgo(2, 9), update_time: isoDaysAgo(1, 9),
  },
  {
    id: 'ann-009', title: '安全意识培训资料', scope: 'all', dept_ids: [], level: 'normal', pinned: false,
    state: 'published', image_file_ids: [],
    content: '<p>近期安全意识培训的课件和签到表已上传到文件管理，请各部门组织学习。</p>',
    published_time: isoDaysAgo(15, 14), expire_time: null, created_by_name: '张伟',
    create_time: isoDaysAgo(16, 10), update_time: isoDaysAgo(15, 14),
  },
];

let announcementSeq = 100;

const READ_NAME_POOL = [
  ['张伟', 'user-001'], ['李娜', 'user-002'], ['王芳', 'user-003'], ['刘洋', 'user-004'],
  ['陈静', 'user-005'], ['赵磊', 'user-006'], ['孙倩', 'user-007'], ['周凯', 'user-008'],
  ['吴敏', 'user-009'], ['郑浩', 'user-010'], ['冯雪', 'user-011'], ['褚鹏', 'user-012'],
];

// 演示账号排在名单最前，真实已读记录才能落到可见条目上。
const ROSTER_ACCOUNTS: Array<[string, string]> = [
  ['user-admin', '管理员'],
  ['user-newbie', '新同事'],
  ['user-viewer', '只读用户'],
  ['user-guest', '访客'],
];

/** announcementId -> userId -> 已读时间；收回不清除，删除随公告一起消失。 */
const announcementReads = new Map<string, Map<string, string>>();

function hashText(text: string): number {
  let hash = 0;
  for (let i = 0; i < text.length; i += 1) hash = (Math.imul(hash, 31) + text.charCodeAt(i)) >>> 0;
  return hash;
}

function isExpired(a: AnnouncementRecord): boolean {
  return a.state === 'published' && time(a.expire_time) > 0 && time(a.expire_time) < Date.now();
}

/** 公告对收件人是否可见：全员可见；指定部门的，mock 用户没有部门归属，一律不可见。 */
function visibleToUser(a: AnnouncementRecord): boolean {
  return a.scope === 'all';
}

/** 已读统计：基础名单按公告 id 取稳定伪随机，叠加真实已读记录。 */
function readStatsOf(a: AnnouncementRecord) {
  if (a.state === 'draft') {
    return { read_count: 0, unread_count: 0, items: [] };
  }
  const total = a.scope === 'all' ? 60 : a.dept_ids.length * 9 + 3;
  const seed = hashText(a.id);
  const readRatio = 0.35 + ((seed % 40) / 100);
  const baseReadCount = Math.min(total, Math.round(total * readRatio));
  const reads = announcementReads.get(a.id) ?? new Map<string, string>();
  const roster = Array.from({ length: total }, (_, i) => {
    if (i < ROSTER_ACCOUNTS.length) {
      const [userId, nickname] = ROSTER_ACCOUNTS[i];
      return { user_id: userId, username: userId, nickname };
    }
    const j = i - ROSTER_ACCOUNTS.length;
    const [nickname, userId] = READ_NAME_POOL[j % READ_NAME_POOL.length];
    const round = Math.floor(j / READ_NAME_POOL.length);
    return {
      user_id: `${userId}-${i}`,
      username: `${userId}-${i}`,
      nickname: round > 0 ? `${nickname}${round + 1}` : nickname,
    };
  });
  const items = roster.map((member, i) => {
    const storedReadTime = reads.get(member.user_id) ?? null;
    const isRead = i < baseReadCount || storedReadTime !== null;
    return {
      ...member,
      read_time: isRead ? storedReadTime ?? isoDaysAgo((seed + i) % 8, 9 + (i % 8)) : null,
    };
  });
  const readCount = items.filter((i) => i.read_time !== null).length;
  return { read_count: readCount, unread_count: total - readCount, items };
}

/* handlers */

export default [
  /* 选项组 */
  http.get(`${base}/dicts`, ({ request }) => {
    const auth = requireUser(request, 'system:dict:index');
    if ('denied' in auth) return auth.denied;
    const keyword = new URL(request.url).searchParams.get('keyword')?.trim() ?? '';
    const items = dictTypes
      .filter((t) => !keyword || t.name.includes(keyword))
      .map(dictTypeView);
    return okOpenapi({ items });
  }),

  http.post(`${base}/dicts`, async ({ request }) => {
    const auth = requireUser(request, 'system:dict:create');
    if ('denied' in auth) return auth.denied;
    const body = (await request.json()) as { name?: string; remark?: string | null };
    const name = body.name?.trim() ?? '';
    if (!name || name.length > 20) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写组名称，且不超过 20 个字', {
        name: ['请填写组名称'],
      });
    }
    if (dictTypes.some((t) => t.name === name)) {
      return failOpenapi(409, 'DICT_TYPE_TAKEN', `已经有叫“${name}”的选项组了，换个名字`);
    }
    dictTypeSeq += 1;
    const created: DictTypeRecord = {
      id: `dict-type-${dictTypeSeq}`,
      name,
      code: `custom_${dictTypeSeq}`,
      remark: body.remark?.trim() || null,
      is_system: false,
    };
    dictTypes.push(created);
    return okOpenapi(dictTypeView(created));
  }),

  http.get(`${base}/dicts/:typeId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:index');
    if ('denied' in auth) return auth.denied;
    const type = findDictType(String(params.typeId));
    if (!type) {
      return failOpenapi(404, 'DICT_TYPE_NOT_FOUND', '这个选项组不存在或已被删除');
    }
    const items = dictItems
      .filter((i) => i.type_id === type.id)
      .sort((a, b) => a.sort - b.sort)
      .map((i) => ({ ...i }));
    return okOpenapi({ ...dictTypeView(type), items });
  }),

  http.put(`${base}/dicts/:typeId`, async ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:update');
    if ('denied' in auth) return auth.denied;
    const type = findDictType(String(params.typeId));
    if (!type) {
      return failOpenapi(404, 'DICT_TYPE_NOT_FOUND', '这个选项组不存在或已被删除');
    }
    const body = (await request.json()) as { name?: string; remark?: string | null };
    const name = body.name?.trim() ?? '';
    if (!name || name.length > 20) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写组名称，且不超过 20 个字', {
        name: ['请填写组名称'],
      });
    }
    if (dictTypes.some((t) => t.id !== type.id && t.name === name)) {
      return failOpenapi(409, 'DICT_TYPE_TAKEN', `已经有叫“${name}”的选项组了，换个名字`);
    }
    type.name = name;
    type.remark = body.remark?.trim() || null;
    return okOpenapi(dictTypeView(type));
  }),

  http.delete(`${base}/dicts/:typeId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:delete');
    if ('denied' in auth) return auth.denied;
    const typeId = String(params.typeId);
    const type = findDictType(typeId);
    if (!type) {
      return failOpenapi(404, 'DICT_TYPE_NOT_FOUND', '这个选项组不存在或已被删除');
    }
    if (type.is_system) {
      return failOpenapi(409, 'DICT_TYPE_SYSTEM', '系统自带的选项组不能删除');
    }
    const index = dictTypes.indexOf(type);
    dictTypes.splice(index, 1);
    for (let i = dictItems.length - 1; i >= 0; i -= 1) {
      if (dictItems[i].type_id === typeId) dictItems.splice(i, 1);
    }
    return okOpenapi({});
  }),

  /* 选项 */
  http.post(`${base}/dicts/:typeId/items`, async ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:create');
    if ('denied' in auth) return auth.denied;
    const type = findDictType(String(params.typeId));
    if (!type) {
      return failOpenapi(404, 'DICT_TYPE_NOT_FOUND', '这个选项组不存在或已被删除');
    }
    const body = (await request.json()) as {
      label?: string;
      color?: string | null;
      is_default?: boolean;
      status?: 'enabled' | 'disabled';
    };
    const label = body.label?.trim() ?? '';
    if (!label || label.length > 20) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写选项名称，且不超过 20 个字', {
        label: ['请填写选项名称'],
      });
    }
    if (dictLabelTaken(type.id, label)) {
      return failOpenapi(409, 'DICT_ITEM_TAKEN', `这个组里已经有叫“${label}”的选项了，换个名字`);
    }
    dictItemSeq += 1;
    const maxSort = Math.max(0, ...dictItems.filter((i) => i.type_id === type.id).map((i) => i.sort));
    const created: DictItemRecord = {
      id: `dict-item-${dictItemSeq}`,
      type_id: type.id,
      label,
      value: `opt_${dictItemSeq}`,
      color: body.color ?? null,
      is_default: body.is_default === true,
      is_system: false,
      sort: maxSort + 1,
      status: body.status === 'disabled' ? 'disabled' : 'enabled',
      used: false,
      create_time: new Date().toISOString(),
    };
    if (created.is_default) clearGroupDefaults(type.id, created.id);
    dictItems.push(created);
    return okOpenapi({ ...created });
  }),

  http.put(`${base}/dicts/items/sort`, async ({ request }) => {
    const auth = requireUser(request, 'system:dict:update');
    if ('denied' in auth) return auth.denied;
    const body = (await request.json()) as { type_id?: string; item_ids?: string[] };
    const type = findDictType(body.type_id ?? '');
    if (!type) {
      return failOpenapi(404, 'DICT_TYPE_NOT_FOUND', '这个选项组不存在或已被删除');
    }
    const ids = body.item_ids ?? [];
    const groupItems = dictItems.filter((i) => i.type_id === type.id);
    const belongs = ids.every((id) => groupItems.some((i) => i.id === id));
    const complete = ids.length === groupItems.length;
    if (!belongs || !complete) {
      return failOpenapi(422, 'DICT_ITEM_SORT_INVALID', '排序的选项和这个组对不上，请刷新后重试');
    }
    ids.forEach((id, index) => {
      const item = findDictItem(id);
      if (item) item.sort = index + 1;
    });
    return okOpenapi({});
  }),

  http.put(`${base}/dicts/items/:itemId/status`, async ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:update');
    if ('denied' in auth) return auth.denied;
    const item = findDictItem(String(params.itemId));
    if (!item) {
      return failOpenapi(404, 'DICT_ITEM_NOT_FOUND', '这个选项不存在或已被删除');
    }
    const body = (await request.json()) as { status?: string };
    if (body.status !== 'enabled' && body.status !== 'disabled') {
      return failOpenapi(422, 'VALIDATION_FAILED', '状态只能是启用或停用');
    }
    item.status = body.status;
    return okOpenapi({});
  }),

  http.put(`${base}/dicts/items/:itemId`, async ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:update');
    if ('denied' in auth) return auth.denied;
    const item = findDictItem(String(params.itemId));
    if (!item) {
      return failOpenapi(404, 'DICT_ITEM_NOT_FOUND', '这个选项不存在或已被删除');
    }
    const body = (await request.json()) as {
      label?: string;
      color?: string | null;
      is_default?: boolean;
      status?: 'enabled' | 'disabled';
    };
    const label = body.label?.trim() ?? '';
    if (!label || label.length > 20) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写选项名称，且不超过 20 个字', {
        label: ['请填写选项名称'],
      });
    }
    if (dictLabelTaken(item.type_id, label, item.id)) {
      return failOpenapi(409, 'DICT_ITEM_TAKEN', `这个组里已经有叫“${label}”的选项了，换个名字`);
    }
    item.label = label;
    item.color = body.color ?? null;
    item.status = body.status === 'disabled' ? 'disabled' : 'enabled';
    item.is_default = body.is_default === true;
    if (item.is_default) clearGroupDefaults(item.type_id, item.id);
    return okOpenapi({ ...item });
  }),

  http.delete(`${base}/dicts/items/:itemId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:dict:delete');
    if ('denied' in auth) return auth.denied;
    const itemId = String(params.itemId);
    const item = findDictItem(itemId);
    if (!item) {
      return failOpenapi(404, 'DICT_ITEM_NOT_FOUND', '这个选项不存在或已被删除');
    }
    if (item.is_system) {
      return failOpenapi(409, 'DICT_ITEM_SYSTEM', '系统自带的选项不能删除');
    }
    dictItems.splice(dictItems.indexOf(item), 1);
    return okOpenapi({});
  }),

  /* 文件 */
  http.get(`${base}/files`, ({ request }) => {
    const auth = requireUser(request, 'system:file:index');
    if ('denied' in auth) return auth.denied;
    const sp = new URL(request.url).searchParams;
    const keyword = sp.get('keyword')?.trim().toLowerCase() ?? '';
    const kind = sp.get('kind') ?? '';
    const usageState = sp.get('usage_state') ?? '';
    const from = time(sp.get('create_time_from'));
    const to = time(sp.get('create_time_to'));
    const page = Math.max(1, Number(sp.get('page') ?? '1') || 1);
    const pageSize = Math.min(100, Math.max(1, Number(sp.get('page_size') ?? '20') || 20));

    const filtered = files
      .filter((f) => !keyword || f.original_name.toLowerCase().includes(keyword))
      .filter((f) => !kind || f.kind === kind)
      .filter((f) => !usageState || f.usage_state === usageState)
      .filter((f) => !from || time(f.create_time) >= from)
      .filter((f) => !to || time(f.create_time) <= to)
      .sort((a, b) => time(b.create_time) - time(a.create_time));

    const start = (page - 1) * pageSize;
    return okOpenapi({
      items: filtered.slice(start, start + pageSize).map((f) => ({ ...f })),
      total: filtered.length,
      page,
      page_size: pageSize,
    });
  }),

  http.post(`${base}/files`, async ({ request }) => {
    const auth = requireUser(request);
    if ('denied' in auth) return auth.denied;
    const form = await request.formData();
    const file = form.get('file');
    if (!(file instanceof File)) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请选择要上传的文件', { file: ['请选择要上传的文件'] });
    }
    fileSeq += 1;
    const usage = form.get('usage');
    const visibility = form.get('visibility');
    const created: FileRecord = {
      id: `file-${String(fileSeq).padStart(3, '0')}`,
      original_name: file.name,
      kind: kindOfName(file.name),
      media_type: mediaOfName(file.name, file.type || 'application/octet-stream'),
      size_bytes: file.size,
      usage_state: typeof usage === 'string' && usage ? 'used' : 'unused',
      usage_label: usageLabelOf(typeof usage === 'string' ? usage : null),
      visibility: visibility === 'public' ? 'public' : 'private',
      purpose: 'attachment',
      uploader_id: `user-${auth.user.username}`,
      uploader_name: auth.user.nickname,
      create_time: new Date().toISOString(),
    };
    files.unshift(created);
    return okOpenapi({ ...created });
  }),

  http.get(`${base}/files/:fileId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:file:index');
    if ('denied' in auth) return auth.denied;
    const file = files.find((f) => f.id === String(params.fileId));
    if (!file) {
      return failOpenapi(404, 'FILE_NOT_FOUND', '文件不存在或已被删除');
    }
    return okOpenapi({ ...file });
  }),

  http.delete(`${base}/files/:fileId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:file:delete');
    if ('denied' in auth) return auth.denied;
    const index = files.findIndex((f) => f.id === String(params.fileId));
    if (index < 0) {
      return failOpenapi(404, 'FILE_NOT_FOUND', '文件不存在或已被删除');
    }
    files.splice(index, 1);
    return okOpenapi({});
  }),

  http.get(`${base}/files/:fileId/download`, ({ request, params }) => {
    const auth = requireUser(request, 'system:file:index');
    if ('denied' in auth) return auth.denied;
    const file = files.find((f) => f.id === String(params.fileId));
    if (!file) {
      return failOpenapi(404, 'FILE_NOT_FOUND', '文件不存在或已被删除');
    }
    // mock 不校验附件用途与可见性；真实后端按 visibility 与用途登记鉴权。
    const body =
      file.kind === 'image'
        ? '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><rect width="320" height="180" fill="#e8e9eb"/><text x="160" y="95" text-anchor="middle" font-size="14" fill="#666">' +
          file.original_name +
          '</text></svg>'
        : `mock file content: ${file.original_name}`;
    return new HttpResponse(body, {
      headers: {
        'Content-Type': file.media_type,
        'Content-Disposition': `attachment; filename*=UTF-8''${encodeURIComponent(file.original_name)}`,
      },
    });
  }),

  http.post(`${base}/files/cleanup`, ({ request }) => {
    const auth = requireUser(request, 'system:file:cleanup');
    if ('denied' in auth) return auth.denied;
    const threshold = Date.now() - 30 * 86_400_000;
    let deletedCount = 0;
    let freedBytes = 0;
    for (let i = files.length - 1; i >= 0; i -= 1) {
      const file = files[i];
      if (file.usage_state === 'unused' && time(file.create_time) < threshold) {
        freedBytes += file.size_bytes;
        deletedCount += 1;
        files.splice(i, 1);
      }
    }
    taskSeq += 1;
    const taskId = `task-cleanup-${taskSeq}`;
    tasks.set(taskId, {
      id: taskId,
      type: 'cleanup.files',
      state: 'succeeded',
      progress: 100,
      stage: '已完成',
      result: { deleted_count: deletedCount, freed_bytes: freedBytes },
      error: null,
      create_time: new Date().toISOString(),
      finish_time: new Date().toISOString(),
    });
    return okOpenapi({ task_id: taskId, state: 'queued' });
  }),

  /* 后台任务查询（清理轮询用） */
  http.get(`${base}/tasks/:taskId`, ({ request, params }) => {
    const auth = requireUser(request);
    if ('denied' in auth) return auth.denied;
    const task = tasks.get(String(params.taskId));
    if (!task) {
      return failOpenapi(404, 'NOT_FOUND', '任务不存在或无权查看');
    }
    return okOpenapi(taskView(task));
  }),

  /* 部门精简清单 */
  http.get(`${base}/org/dept-options`, ({ request }) => {
    const auth = requireUser(request);
    if ('denied' in auth) return auth.denied;
    return okOpenapi({
      items: [...DEPTS]
        .sort((a, b) => a.sort - b.sort)
        .map((d) => ({ id: d.id, parent_id: d.parent_id, name: d.name, sort: d.sort })),
    });
  }),

  /* 公告 */
  http.get(`${base}/announcements`, ({ request }) => {
    const auth = requireUser(request, 'system:announcement:index');
    if ('denied' in auth) return auth.denied;
    const sp = new URL(request.url).searchParams;
    const keyword = sp.get('keyword')?.trim().toLowerCase() ?? '';
    const state = sp.get('state') ?? '';
    const page = Math.max(1, Number(sp.get('page') ?? '1') || 1);
    const pageSize = Math.min(100, Math.max(1, Number(sp.get('page_size') ?? '20') || 20));

    const filtered = announcements
      .filter((a) => !keyword || a.title.toLowerCase().includes(keyword))
      .filter((a) => !state || a.state === state)
      .sort((a, b) => {
        if (a.pinned !== b.pinned) return a.pinned ? -1 : 1;
        return time(b.create_time) - time(a.create_time);
      });

    const start = (page - 1) * pageSize;
    return okOpenapi({
      items: filtered.slice(start, start + pageSize).map(announcementView),
      total: filtered.length,
      page,
      page_size: pageSize,
    });
  }),

  http.post(`${base}/announcements`, async ({ request }) => {
    const auth = requireUser(request, 'system:announcement:create');
    if ('denied' in auth) return auth.denied;
    const body = (await request.json()) as {
      title?: string;
      content?: string;
      scope?: string;
      dept_ids?: string[];
      level?: string;
      pinned?: boolean;
      image_file_ids?: string[];
      expire_time?: string | null;
    };
    const title = body.title?.trim() ?? '';
    if (!title || title.length > 50) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写标题，且不超过 50 个字', {
        title: ['请填写标题'],
      });
    }
    if (!body.content?.trim()) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写正文', { content: ['请填写正文'] });
    }
    if (body.scope !== 'all' && body.scope !== 'dept') {
      return failOpenapi(422, 'VALIDATION_FAILED', '请选择发给谁', { scope: ['请选择发给谁'] });
    }
    const deptIds = body.scope === 'dept' ? (body.dept_ids ?? []) : [];
    if (body.scope === 'dept' && deptIds.length === 0) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请选择至少一个部门', { dept_ids: ['请选择至少一个部门'] });
    }
    if (deptIds.some((id) => !DEPTS.some((d) => d.id === id))) {
      return failOpenapi(422, 'VALIDATION_FAILED', '所选部门不存在，请刷新后重试', { dept_ids: ['部门不存在'] });
    }
    announcementSeq += 1;
    const now = new Date().toISOString();
    const created: AnnouncementRecord = {
      id: `ann-${String(announcementSeq).padStart(3, '0')}`,
      title,
      content: body.content,
      scope: body.scope,
      dept_ids: deptIds,
      level: body.level === 'important' ? 'important' : 'normal',
      pinned: body.pinned === true,
      state: 'draft',
      image_file_ids: body.image_file_ids ?? [],
      published_time: null,
      expire_time: body.expire_time ?? null,
      created_by_name: auth.user.nickname,
      create_time: now,
      update_time: now,
    };
    announcements.unshift(created);
    return okOpenapi(announcementView(created));
  }),

  http.get(`${base}/announcements/:announcementId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:announcement:index');
    if ('denied' in auth) return auth.denied;
    const ann = announcements.find((a) => a.id === String(params.announcementId));
    if (!ann) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    return okOpenapi(announcementView(ann));
  }),

  http.put(`${base}/announcements/:announcementId`, async ({ request, params }) => {
    const auth = requireUser(request, 'system:announcement:update');
    if ('denied' in auth) return auth.denied;
    const ann = announcements.find((a) => a.id === String(params.announcementId));
    if (!ann) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    const body = (await request.json()) as {
      title?: string;
      content?: string;
      scope?: string;
      dept_ids?: string[];
      level?: string;
      pinned?: boolean;
      image_file_ids?: string[];
      expire_time?: string | null;
    };
    const title = body.title?.trim() ?? '';
    if (!title || title.length > 50) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写标题，且不超过 50 个字', {
        title: ['请填写标题'],
      });
    }
    if (!body.content?.trim()) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请填写正文', { content: ['请填写正文'] });
    }
    if (body.scope !== 'all' && body.scope !== 'dept') {
      return failOpenapi(422, 'VALIDATION_FAILED', '请选择发给谁', { scope: ['请选择发给谁'] });
    }
    const deptIds = body.scope === 'dept' ? (body.dept_ids ?? []) : [];
    if (body.scope === 'dept' && deptIds.length === 0) {
      return failOpenapi(422, 'VALIDATION_FAILED', '请选择至少一个部门', { dept_ids: ['请选择至少一个部门'] });
    }
    if (deptIds.some((id) => !DEPTS.some((d) => d.id === id))) {
      return failOpenapi(422, 'VALIDATION_FAILED', '所选部门不存在，请刷新后重试', { dept_ids: ['部门不存在'] });
    }
    ann.title = title;
    ann.content = body.content;
    ann.scope = body.scope;
    ann.dept_ids = deptIds;
    ann.level = body.level === 'important' ? 'important' : 'normal';
    ann.pinned = body.pinned === true;
    ann.image_file_ids = body.image_file_ids ?? [];
    ann.expire_time = body.expire_time ?? null;
    ann.update_time = new Date().toISOString();
    return okOpenapi(announcementView(ann));
  }),

  http.delete(`${base}/announcements/:announcementId`, ({ request, params }) => {
    const auth = requireUser(request, 'system:announcement:delete');
    if ('denied' in auth) return auth.denied;
    const index = announcements.findIndex((a) => a.id === String(params.announcementId));
    if (index < 0) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    // 删除连同已读记录一起删；收回（withdrawn）才保留已读记录。
    announcementReads.delete(String(params.announcementId));
    announcements.splice(index, 1);
    return okOpenapi({});
  }),

  http.post(`${base}/announcements/:announcementId/publish`, ({ request, params }) => {
    const auth = requireUser(request, 'system:announcement:publish');
    if ('denied' in auth) return auth.denied;
    const ann = announcements.find((a) => a.id === String(params.announcementId));
    if (!ann) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    if (ann.state === 'published') {
      return failOpenapi(409, 'ANNOUNCEMENT_STATE', '这条公告已经发布了');
    }
    ann.state = 'published';
    ann.published_time = new Date().toISOString();
    ann.update_time = ann.published_time;
    return okOpenapi(announcementView(ann));
  }),

  http.post(`${base}/announcements/:announcementId/revoke`, ({ request, params }) => {
    const auth = requireUser(request, 'system:announcement:revoke');
    if ('denied' in auth) return auth.denied;
    const ann = announcements.find((a) => a.id === String(params.announcementId));
    if (!ann) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    if (ann.state !== 'published') {
      return failOpenapi(409, 'ANNOUNCEMENT_STATE', '只有已发布的公告才能收回');
    }
    ann.state = 'withdrawn';
    ann.update_time = new Date().toISOString();
    return okOpenapi(announcementView(ann));
  }),

  /* 收件人侧：重要公告弹窗候选与已读标记（与消息列表打开公告同一张已读表） */
  http.get(`${base}/me/announcements/popup`, ({ request }) => {
    const auth = requireUser(request);
    if ('denied' in auth) return auth.denied;
    const userId = `user-${auth.user.username}`;
    const items = announcements
      .filter(
        (a) =>
          a.state === 'published' &&
          !isExpired(a) &&
          a.level === 'important' &&
          visibleToUser(a) &&
          !(announcementReads.get(a.id)?.has(userId) ?? false),
      )
      .sort((a, b) => time(b.published_time) - time(a.published_time))
      .map((a) => ({
        id: a.id,
        title: a.title,
        content: a.content,
        level: 'important' as const,
        published_time: a.published_time,
        expire_time: a.expire_time,
      }));
    return okOpenapi({ items });
  }),

  http.post(`${base}/announcements/:announcementId/read`, ({ request, params }) => {
    const auth = requireUser(request);
    if ('denied' in auth) return auth.denied;
    const ann = announcements.find((a) => a.id === String(params.announcementId));
    // 不可见（未发布/已收回/已过期/不在发送范围）一律按不存在处理。
    if (!ann || ann.state !== 'published' || isExpired(ann) || !visibleToUser(ann)) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    const userId = `user-${auth.user.username}`;
    let reads = announcementReads.get(ann.id);
    if (!reads) {
      reads = new Map<string, string>();
      announcementReads.set(ann.id, reads);
    }
    // 同一人同一公告只记一次，重复调用幂等。
    if (!reads.has(userId)) {
      reads.set(userId, new Date().toISOString());
    }
    return okOpenapi({});
  }),

  http.get(`${base}/announcements/:announcementId/read-stats`, ({ request, params }) => {
    const auth = requireUser(request, 'system:announcement:index');
    if ('denied' in auth) return auth.denied;
    const ann = announcements.find((a) => a.id === String(params.announcementId));
    if (!ann) {
      return failOpenapi(404, 'ANNOUNCEMENT_NOT_FOUND', '公告不存在或已被删除');
    }
    return okOpenapi(readStatsOf(ann));
  }),
] satisfies HttpHandler[];
