import { useEffect, useMemo, useRef, useState } from 'react';

import {
  Button,
  Collapse,
  DatePicker,
  Input,
  Modal,
  Radio,
  SideSheet,
  Switch,
  Toast,
  TreeSelect,
  Typography,
} from '@douyinfe/semi-ui';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  createAnnouncement,
  publishAnnouncement,
  updateAnnouncement,
  type Announcement,
  type AnnouncementWrite,
} from '@/api/announcements';
import { getDeptOptions, type DeptOption } from '@/api/org';
import { apiErrorMessage } from '@/lib/apiClient';
import { useMediaQuery } from './useMediaQuery';
import { toDateKey } from './format';
import { RichTextEditor } from './RichTextEditor';

export interface AnnouncementEditorProps {
  visible: boolean;
  /** null 表示新建；否则编辑该公告。 */
  announcement: Announcement | null;
  canPublish: boolean;
  onClose: () => void;
}

interface DeptNode {
  value: string;
  label: string;
  key: string;
  children?: DeptNode[];
}

/** 把 /org/dept-options 的平铺清单按 parent_id 组装成树。 */
function buildDeptTree(items: DeptOption[]): DeptNode[] {
  const byParent = new Map<string | null, DeptOption[]>();
  for (const item of items) {
    const list = byParent.get(item.parent_id) ?? [];
    list.push(item);
    byParent.set(item.parent_id, list);
  }
  const build = (parentId: string | null): DeptNode[] =>
    (byParent.get(parentId) ?? [])
      .slice()
      .sort((a, b) => a.sort - b.sort)
      .map((d) => {
        const children = build(d.id);
        return { value: d.id, label: d.name, key: d.id, children: children.length ? children : undefined };
      });
  return build(null);
}

interface FormState {
  title: string;
  content: string;
  scope: 'all' | 'dept';
  deptIds: string[];
  level: 'normal' | 'important';
  pinned: boolean;
  expireMode: 'forever' | 'date';
  expireDate: string | null;
}

function formOf(a: Announcement | null): FormState {
  return {
    title: a?.title ?? '',
    content: a?.content ?? '',
    scope: a?.scope ?? 'all',
    deptIds: a?.dept_ids ?? [],
    level: a?.level ?? 'normal',
    pinned: a?.pinned ?? false,
    expireMode: a?.expire_time ? 'date' : 'forever',
    expireDate: a?.expire_time ? a.expire_time.slice(0, 10) : null,
  };
}

function isBlankHtml(html: string): boolean {
  return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length === 0;
}

function toWriteOf(form: FormState): AnnouncementWrite {
  return {
    title: form.title.trim(),
    content: form.content,
    scope: form.scope,
    dept_ids: form.scope === 'dept' ? form.deptIds : [],
    level: form.level,
    pinned: form.pinned,
    expire_time:
      form.expireMode === 'date' && form.expireDate
        ? new Date(`${form.expireDate}T23:59:59`).toISOString()
        : null,
  };
}

/** 公告编辑抽屉：标题/正文/发给常驻，重要程度/置顶/有效期收进「更多设置」。 */
export function AnnouncementEditor({ visible, announcement, canPublish, onClose }: AnnouncementEditorProps) {
  const { t } = useTranslation('announcements');
  const queryClient = useQueryClient();
  const editing = announcement !== null;
  const huge = useMediaQuery('(min-width: 1920px)');
  const large = useMediaQuery('(min-width: 1440px)');
  const drawerWidth = huge ? 900 : large ? 800 : 720;

  const [form, setForm] = useState<FormState>(() => formOf(announcement));
  const [initial, setInitial] = useState<FormState>(() => formOf(announcement));
  const [errors, setErrors] = useState<{ title?: string; content?: string; dept?: string }>({});
  const [savedAt, setSavedAt] = useState<string | null>(null);
  const [confirmClose, setConfirmClose] = useState(false);
  const formRef = useRef(form);
  const dirtyRef = useRef(false);
  // 只在打开/切换公告时重置表单；列表刷新导致的对象变化不能冲掉正在写的内容。
  const loadedKey = useRef('');

  const depts = useQuery({
    queryKey: ['org', 'dept-options'],
    queryFn: getDeptOptions,
    staleTime: 5 * 60_000,
  });
  const deptTree = useMemo(() => buildDeptTree(depts.data?.items ?? []), [depts.data]);

  // 每次打开或切换公告时重置表单。
  useEffect(() => {
    const key = visible ? (announcement?.id ?? 'new') : '';
    if (!key || key === loadedKey.current) return;
    loadedKey.current = key;
    const next = formOf(announcement);
    setForm(next);
    setInitial(next);
    setErrors({});
    setSavedAt(null);
    dirtyRef.current = false;
  }, [visible, announcement]);

  const dirty = useMemo(
    () => JSON.stringify(form) !== JSON.stringify(initial),
    [form, initial],
  );

  // 自动保存间隔要读最新表单，不能在渲染期写 ref。
  useEffect(() => {
    formRef.current = form;
    dirtyRef.current = dirty;
  }, [form, dirty]);

  const toWrite = () => toWriteOf(form);

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ['announcements'] });

  const saveDraft = useMutation({
    mutationFn: (body: AnnouncementWrite) =>
      editing ? updateAnnouncement(announcement.id, body) : createAnnouncement(body),
    onSuccess: async () => {
      // 已发布公告的「保存」是更新不是存草稿，提示口径不同。
      Toast.success(
        editing && announcement.state === 'published'
          ? t('editor.updated')
          : t(editing ? 'editor.saved' : 'editor.savedDraft'),
      );
      await invalidate();
      onClose();
    },
    onError: (error) => Toast.error(apiErrorMessage(error, t('editor.saveFailed'))),
  });

  // 草稿自动保存：只更新不关抽屉，成功后把当前表单记为新基线。
  const autoSave = useMutation({
    mutationFn: (body: AnnouncementWrite) =>
      announcement ? updateAnnouncement(announcement.id, body) : Promise.reject(new Error('no announcement')),
    onSuccess: () => {
      setInitial(formRef.current);
      setSavedAt(new Date().toTimeString().slice(0, 5));
      void invalidate();
    },
    onError: () => {
      // 自动保存失败不打扰编辑；下次间隔会重试。
    },
  });

  const doPublish = useMutation({
    mutationFn: async (body: AnnouncementWrite) => {
      const saved = editing
        ? await updateAnnouncement(announcement.id, body)
        : await createAnnouncement(body);
      return publishAnnouncement(saved.id);
    },
    onSuccess: async () => {
      Toast.success(t('confirm.published'));
      await invalidate();
      onClose();
    },
    onError: (error) => Toast.error(apiErrorMessage(error, t('confirm.publishFailed'))),
  });

  const validate = (): boolean => {
    const next: typeof errors = {};
    if (!form.title.trim()) next.title = t('editor.titleRequired');
    if (isBlankHtml(form.content)) next.content = t('editor.contentRequired');
    if (form.scope === 'dept' && form.deptIds.length === 0) next.dept = t('editor.deptRequired');
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const requestClose = () => {
    if (dirty) {
      setConfirmClose(true);
      return;
    }
    onClose();
  };

  // 草稿每 30 秒自动保存一次；只对已有草稿自动写，避免新公告被悄悄建出来。
  useEffect(() => {
    if (!visible || !editing || announcement.state !== 'draft' || !dirty) return;
    const timer = setInterval(() => {
      if (!dirtyRef.current || autoSave.isPending) return;
      autoSave.mutate(toWriteOf(formRef.current));
    }, 30_000);
    return () => clearInterval(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, editing, announcement?.state, dirty]);

  const publish = () => {
    if (!validate() || !canPublish) return;
    Modal.confirm({
      title: t('editor.publishConfirmTitle'),
      content:
        form.scope === 'dept'
          ? t('editor.publishDeptContent')
          : t('editor.publishConfirmContent'),
      okText: t('table.publish'),
      cancelText: t('editor.think'),
      onOk: () => doPublish.mutateAsync(toWrite()).catch(() => undefined),
    });
  };

  const patch = (partial: Partial<FormState>) => setForm((prev) => ({ ...prev, ...partial }));

  const footer = (
    <div className="flex items-center justify-between gap-2">
      <Typography.Text type="secondary" size="small">
        {savedAt ? t('editor.autoSaved', { time: savedAt }) : ''}
      </Typography.Text>
      <div className="flex gap-2">
        <Button onClick={() => saveDraft.mutate(toWrite())} loading={saveDraft.isPending} disabled={!dirty && editing}>
          {editing && announcement.state === 'published' ? t('editor.saveChanges') : t('editor.saveDraft')}
        </Button>
        {canPublish && (!editing || announcement.state !== 'published') ? (
          <Button theme="solid" onClick={publish} loading={doPublish.isPending}>
            {editing && announcement.state === 'withdrawn' ? t('table.republish') : t('editor.publish')}
          </Button>
        ) : null}
      </div>
    </div>
  );

  return (
    <>
      <SideSheet
        visible={visible}
        width={drawerWidth}
        title={t(editing ? 'editor.editTitle' : 'editor.addTitle')}
        onCancel={requestClose}
        footer={footer}
        closable
      >
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-1">
            <Typography.Text strong>
              {t('editor.titleLabel')}
              <Typography.Text type="danger"> *</Typography.Text>
            </Typography.Text>
            <Input
              value={form.title}
              maxLength={50}
              showClear
              placeholder={t('editor.titlePlaceholder')}
              onChange={(v) => patch({ title: v })}
            />
            {errors.title ? <Typography.Text type="danger" size="small">{errors.title}</Typography.Text> : null}
          </div>

          <div className="flex flex-col gap-1">
            <Typography.Text strong>
              {t('editor.contentLabel')}
              <Typography.Text type="danger"> *</Typography.Text>
            </Typography.Text>
            {/* 正文每次打开按公告重挂载，defaultHtml 才能生效 */}
            <RichTextEditor
              key={announcement?.id ?? 'new'}
              defaultHtml={form.content || undefined}
              placeholder={t('editor.contentPlaceholder')}
              onChange={(html) => patch({ content: html })}
            />
            {errors.content ? <Typography.Text type="danger" size="small">{errors.content}</Typography.Text> : null}
          </div>

          <div className="flex flex-col gap-1">
            <Typography.Text strong>
              {t('editor.scopeLabel')}
              <Typography.Text type="danger"> *</Typography.Text>
            </Typography.Text>
            <Radio.Group
              type="button"
              value={form.scope}
              onChange={(e) => patch({ scope: e.target.value as 'all' | 'dept' })}
            >
              <Radio value="all">{t('editor.scopeAll')}</Radio>
              <Radio value="dept">{t('editor.scopeDept')}</Radio>
            </Radio.Group>
            {form.scope === 'dept' ? (
              <div className="mt-1 flex flex-col gap-1">
                <TreeSelect
                  multiple
                  checkRelation="unRelated"
                  treeData={deptTree}
                  value={form.deptIds}
                  showClear
                  placeholder={t('editor.deptPlaceholder')}
                  onChange={(value) => patch({ deptIds: (Array.isArray(value) ? value : [value]).map(String) })}
                  style={{ width: '100%' }}
                />
                {errors.dept ? <Typography.Text type="danger" size="small">{errors.dept}</Typography.Text> : null}
              </div>
            ) : null}
          </div>

          <Collapse keepDOM={false}>
            <Collapse.Panel header={t('editor.moreSettings')} itemKey="more">
              <div className="flex flex-col gap-4 pt-2">
                <div className="flex flex-col gap-1">
                  <Typography.Text strong>{t('editor.level')}</Typography.Text>
                  <Radio.Group
                    type="button"
                    value={form.level}
                    onChange={(e) => patch({ level: e.target.value as 'normal' | 'important' })}
                  >
                    <Radio value="normal">{t('editor.levelNormal')}</Radio>
                    <Radio value="important">{t('editor.levelImportant')}</Radio>
                  </Radio.Group>
                  <Typography.Text type="tertiary" size="small">{t('editor.levelHint')}</Typography.Text>
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <Typography.Text strong>{t('editor.pinned')}</Typography.Text>
                    <div><Typography.Text type="tertiary" size="small">{t('editor.pinnedHint')}</Typography.Text></div>
                  </div>
                  <Switch checked={form.pinned} onChange={(v) => patch({ pinned: v })} />
                </div>

                <div className="flex flex-col gap-1">
                  <Typography.Text strong>{t('editor.expire')}</Typography.Text>
                  <Radio.Group
                    type="button"
                    value={form.expireMode}
                    onChange={(e) => patch({ expireMode: e.target.value as 'forever' | 'date' })}
                  >
                    <Radio value="forever">{t('editor.expireForever')}</Radio>
                    <Radio value="date">{t('editor.expireDate')}</Radio>
                  </Radio.Group>
                  {form.expireMode === 'date' ? (
                    <DatePicker
                      type="date"
                      value={form.expireDate ? new Date(`${form.expireDate}T00:00:00`) : undefined}
                      showClear
                      placeholder={t('editor.expireDatePlaceholder')}
                      onChange={(_d, dateString) =>
                        patch({ expireDate: toDateKey(Array.isArray(dateString) ? dateString[0] : dateString) })
                      }
                      style={{ width: 220 }}
                    />
                  ) : null}
                  <Typography.Text type="tertiary" size="small">{t('editor.expireHint')}</Typography.Text>
                </div>
              </div>
            </Collapse.Panel>
          </Collapse>
        </div>
      </SideSheet>

      <Modal
        visible={confirmClose}
        title={t('editor.closeConfirmTitle')}
        onCancel={() => setConfirmClose(false)}
        footer={
          <div className="flex justify-end gap-2">
            <Button theme="solid" type="danger" onClick={() => { setConfirmClose(false); onClose(); }}>
              {t('editor.discard')}
            </Button>
            <Button onClick={() => setConfirmClose(false)}>{t('editor.keepEditing')}</Button>
          </div>
        }
      >
        <Typography.Paragraph>{t('editor.closeConfirmContent')}</Typography.Paragraph>
      </Modal>
    </>
  );
}
