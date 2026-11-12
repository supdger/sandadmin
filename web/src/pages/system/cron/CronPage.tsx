import { useMemo, useState } from 'react';

import {
  Banner,
  Button,
  Input,
  InputNumber,
  Modal,
  RadioGroup,
  Select,
  SideSheet,
  Switch,
  Table,
  Tag,
  TimePicker,
  Toast,
  Typography,
} from '@douyinfe/semi-ui';
import { IconSearch } from '@douyinfe/semi-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  listScheduleRuns,
  listScheduledTasks,
  runScheduledTask,
  updateScheduledTask,
  type ScheduledTask,
  type TaskRun,
  type TaskSchedule,
} from '@/api/v1/schedules';
import { Empty } from '@/components/Empty';
import { ErrorState } from '@/components/ErrorState';
import { PageHeader } from '@/components/PageHeader';
import { AdminCopyButton } from '@/components/AdminErrorNotice';
import { useCan } from '@/hooks/useCan';
import { useUrlQuery } from '@/hooks/useUrlQuery';
import { adminErrorMessage } from '@/lib/adminError';
import { AdminApiError } from '@/lib/adminClient';
import { confirmDanger } from '@/lib/confirmAction';
import { formatDateTime } from '@/lib/format';
import { nextRunTimes, normalizeTime, scheduleText } from './scheduleText';

const URL_FIELDS = { keyword: {}, status: {}, failed_only: { kind: 'boolean' as const } };

interface EditDraft {
  freq: TaskSchedule['freq'];
  interval: number;
  time: string;
  weekday: number;
  monthday: number;
}

function draftFrom(schedule: TaskSchedule | null): EditDraft {
  return {
    freq: schedule?.freq ?? 'daily',
    interval: schedule?.interval ?? 10,
    time: normalizeTime(schedule?.time) ?? '03:00',
    weekday: schedule?.weekday ?? 1,
    monthday: schedule?.monthday ?? 1,
  };
}

function draftToSchedule(d: EditDraft): TaskSchedule {
  switch (d.freq) {
    case 'interval':
      return { freq: 'interval', interval: d.interval };
    case 'hourly':
      return { freq: 'hourly', time: `00:${String(d.monthday % 60).padStart(2, '0')}` };
    case 'daily':
      return { freq: 'daily', time: d.time };
    case 'weekly':
      return { freq: 'weekly', weekday: d.weekday, time: d.time };
    case 'monthly':
      return { freq: 'monthly', monthday: d.monthday, time: d.time };
  }
}

/** hourly 的分钟数单独存——monthday 被复用当分钟槽位不合适，独立字段更直观。 */
interface EditState {
  task: ScheduledTask;
  draft: EditDraft;
  minute: number;
}

export function CronPage() {
  const { t } = useTranslation('cron');
  const queryClient = useQueryClient();
  const canIndex = useCan('system:cron:index');
  const canUpdate = useCan('system:cron:update');
  const canRun = useCan('system:cron:run');

  const { values, setValues } = useUrlQuery(URL_FIELDS);
  const keyword = (values.keyword as string | undefined) ?? '';
  const status = (values.status as string | undefined) ?? '';
  const failedOnly = values.failed_only === true;

  const query = useQuery({
    queryKey: ['scheduledTasks', keyword, status, failedOnly],
    queryFn: () =>
      listScheduledTasks({
        keyword: keyword || undefined,
        status: status === '' ? undefined : (status as 'enabled' | 'disabled'),
        failed_only: failedOnly || undefined,
      }),
    enabled: canIndex,
  });

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: ['scheduledTasks'] });

  // ---------- 立即执行 ----------
  const [runningCode, setRunningCode] = useState<string | null>(null);
  const runMutation = useMutation({
    mutationFn: (task: ScheduledTask) => runScheduledTask(task.code),
    onSuccess: async (_accepted, task) => {
      try {
        const runs = await listScheduleRuns(task.code);
        const latest = runs.items[0];
        if (latest?.status === 'failed') {
          Toast.error(t('run.failed', { name: task.name, reason: latest.message ?? '' }));
        } else {
          Toast.success(t('run.done', { name: task.name }));
        }
      } catch {
        Toast.success(t('run.done', { name: task.name }));
      }
      void invalidate();
    },
    onError: (error, task) => {
      Toast.error(adminErrorMessage(error, t('run.failed', { name: task.name, reason: '' })));
    },
    onSettled: () => setRunningCode(null),
  });

  // ---------- 启停 ----------
  const toggleMutation = useMutation({
    mutationFn: ({ task, next }: { task: ScheduledTask; next: 'enabled' | 'disabled' }) =>
      updateScheduledTask(task.code, { status: next }),
    onSuccess: (_data, { task, next }) => {
      Toast.success(next === 'enabled' ? t('toggle.enabled', { name: task.name }) : t('toggle.paused', { name: task.name }));
      void invalidate();
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('loadFailed')));
    },
  });

  const askToggle = (task: ScheduledTask, checked: boolean) => {
    if (!checked && task.is_critical) {
      confirmDanger({
        title: t('toggle.pauseConfirmTitle', { name: task.name }),
        content: t('toggle.pauseConfirmContent'),
        okText: t('toggle.pause'),
        cancelText: t('toggle.think'),
        onOk: () => toggleMutation.mutate({ task, next: 'disabled' }),
      });
      return;
    }
    toggleMutation.mutate({ task, next: checked ? 'enabled' : 'disabled' });
  };

  // ---------- 改时间 ----------
  const [editState, setEditState] = useState<EditState | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const validateDraft = (s: EditState): Record<string, string> => {
    const errs: Record<string, string> = {};
    if (s.draft.freq === 'interval' && (s.draft.interval < 5 || s.draft.interval > 60 || !Number.isInteger(s.draft.interval))) {
      errs.interval = t('edit.intervalRule');
    }
    if (s.draft.freq === 'monthly' && (s.draft.monthday < 1 || s.draft.monthday > 31 || !Number.isInteger(s.draft.monthday))) {
      errs.monthday = t('edit.monthdayRule');
    }
    if ((s.draft.freq === 'daily' || s.draft.freq === 'weekly' || s.draft.freq === 'monthly') && !normalizeTime(s.draft.time)) {
      errs.time = t('edit.intervalRule');
    }
    return errs;
  };

  const saveMutation = useMutation({
    mutationFn: ({ task, schedule }: { task: ScheduledTask; schedule: TaskSchedule }) =>
      updateScheduledTask(task.code, { schedule }),
    onSuccess: (updated, { task }) => {
      Toast.success(
        t('edit.saved', { name: task.name, time: formatDateTime(updated.next_run_time) || '' }),
      );
      setEditState(null);
      void invalidate();
    },
    onError: (error) => {
      Toast.error(adminErrorMessage(error, t('loadFailed')));
    },
  });

  const previewRuns = useMemo(() => {
    if (!editState) return [];
    const schedule = draftToSchedule({
      ...editState.draft,
      // hourly 分钟存独立字段。
      ...(editState.draft.freq === 'hourly'
        ? {}
        : {}),
    });
    const fixed =
      editState.draft.freq === 'hourly'
        ? { freq: 'hourly' as const, time: `00:${String(editState.minute).padStart(2, '0')}` }
        : schedule;
    return nextRunTimes(fixed);
  }, [editState]);

  // ---------- 执行记录 ----------
  const [runsTask, setRunsTask] = useState<ScheduledTask | null>(null);
  const runsQuery = useQuery({
    queryKey: ['scheduleRuns', runsTask?.code],
    queryFn: () => listScheduleRuns(runsTask!.code),
    enabled: runsTask !== null,
  });

  const failedCount = (query.data?.items ?? []).filter((x) => x.last_run_succeeded === false).length;

  const columns = useMemo(
    () => [
      {
        title: t('columns.task'),
        dataIndex: 'name',
        render: (_: unknown, row: ScheduledTask) => (
          <div className="flex flex-col">
            <span>{row.name}</span>
            <Typography.Text type="tertiary" size="small">
              {[
                row.description,
                row.plugin_code
                  ? `${t('source.plugin')} · ${row.source_label ?? ''}`
                  : row.source_label ?? t('source.system'),
              ]
                .filter(Boolean)
                .join(' · ')}
            </Typography.Text>
          </div>
        ),
      },
      {
        title: t('columns.schedule'),
        dataIndex: 'schedule',
        // 清单锁定写「时间由系统定」；否则渲染生效时间结构（覆盖或清单默认）。
        render: (_: unknown, row: ScheduledTask) =>
          row.schedule_locked ? t('schedule.bySystem') : scheduleText(row.schedule),
      },
      {
        title: t('columns.last'),
        dataIndex: 'last_run_time',
        render: (_: unknown, row: ScheduledTask) =>
          row.last_run_time ? (
            <div className="flex flex-col">
              <span>{formatDateTime(row.last_run_time)}</span>
              <Typography.Text type={row.last_run_succeeded === false ? 'danger' : 'tertiary'} size="small">
                {row.last_run_succeeded === false ? t('lastResult.failed') : t('lastResult.ok')}
              </Typography.Text>
            </div>
          ) : (
            t('lastResult.never')
          ),
      },
      {
        title: t('columns.next'),
        dataIndex: 'next_run_time',
        // 停用的任务没有下一次：写「已暂停」。
        render: (_: unknown, row: ScheduledTask) =>
          row.status === 'disabled'
            ? t('nextRun.paused')
            : formatDateTime(row.next_run_time) || '',
      },
      {
        title: t('columns.enabled'),
        dataIndex: 'status',
        render: (_: unknown, row: ScheduledTask) => (
          <Switch
            checked={row.status === 'enabled'}
            disabled={!canUpdate || toggleMutation.isPending}
            onChange={(checked) => askToggle(row, checked)}
            aria-label={`${t('columns.enabled')}-${row.name}`}
          />
        ),
      },
      {
        title: t('columns.actions'),
        dataIndex: 'code',
        render: (_: unknown, row: ScheduledTask) => (
          <div className="flex gap-2">
            {canRun ? (
              <Button
                size="small"
                theme="borderless"
                loading={runningCode === row.code}
                disabled={runningCode !== null}
                onClick={() => {
                  setRunningCode(row.code);
                  runMutation.mutate(row);
                }}
              >
                {runningCode === row.code ? t('actions.running') : t('actions.runNow')}
              </Button>
            ) : null}
            {canUpdate ? (
              // schedule_locked=清单锁定：不渲染「改时间」。
              row.schedule_locked ? null : (
                <Button
                  size="small"
                  theme="borderless"
                  onClick={() => {
                    const draft = draftFrom(row.schedule);
                    setEditState({
                      task: row,
                      draft,
                      minute: row.schedule?.time ? Number(normalizeTime(row.schedule.time)?.slice(3) ?? 0) : 0,
                    });
                    setFieldErrors({});
                  }}
                >
                  {t('actions.editTime')}
                </Button>
              )
            ) : null}
            <Button size="small" theme="borderless" onClick={() => setRunsTask(row)}>
              {t('actions.runs')}
            </Button>
          </div>
        ),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t, canRun, canUpdate, runningCode, toggleMutation.isPending],
  );

  if (!canIndex) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <Empty title={t('noPermission')} />
      </div>
    );
  }

  if (query.isError && !query.data) {
    return (
      <div className="p-6">
        <PageHeader title={t('title')} subtitle={t('subtitle')} />
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </div>
    );
  }

  const edit = editState;
  const monthEndHint = edit?.draft.freq === 'monthly' && edit.draft.monthday >= 29;

  return (
    <div className="p-6 flex flex-col gap-4">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />

      {failedCount > 0 && !failedOnly ? (
        <Banner
          type="warning"
          description={
            <span className="flex items-center gap-3">
              {t('failedBanner', { count: failedCount })}
              <Button size="small" onClick={() => setValues({ failed_only: true, status: undefined })}>
                {t('failedOnly')}
              </Button>
            </span>
          }
          closeIcon={null}
        />
      ) : null}
      {failedOnly ? (
        <Button style={{ alignSelf: 'flex-start' }} onClick={() => setValues({ failed_only: undefined })}>
          {t('showAll')}
        </Button>
      ) : null}

      <div className="flex flex-wrap items-center gap-3">
        <Input
          prefix={<IconSearch />}
          placeholder={t('filters.keywordPlaceholder')}
          value={keyword}
          onChange={(v) => setValues({ keyword: v || undefined })}
          style={{ width: 220 }}
          showClear
        />
        <Select
          placeholder={t('filters.status')}
          value={failedOnly ? 'failed' : status}
          optionList={[
            { value: '', label: t('filters.statusAll') },
            { value: 'enabled', label: t('filters.enabled') },
            { value: 'disabled', label: t('filters.disabled') },
            { value: 'failed', label: t('filters.failedOnly') },
          ]}
          onChange={(v) => {
            const next = v as string;
            if (next === 'failed') {
              setValues({ failed_only: true, status: undefined });
            } else {
              setValues({ status: next || undefined, failed_only: undefined });
            }
          }}
          style={{ width: 160 }}
        />
      </div>

      <Table<ScheduledTask>
        columns={columns}
        dataSource={query.data?.items ?? []}
        rowKey="code"
        loading={query.isLoading}
        pagination={false}
        empty={<Empty title={t('runsDrawer.empty')} />}
        scroll={{ x: 960 }}
      />

      {edit ? (
        <Modal
          visible
          title={t('edit.title', { name: edit.task.name })}
          okText={t('edit.save')}
          cancelText={t('edit.cancel')}
          confirmLoading={saveMutation.isPending}
          onCancel={() => setEditState(null)}
          onOk={() => {
            const errs = validateDraft(edit);
            setFieldErrors(errs);
            if (Object.keys(errs).length > 0) return;
            const schedule =
              edit.draft.freq === 'hourly'
                ? { freq: 'hourly' as const, time: `00:${String(edit.minute).padStart(2, '0')}` }
                : draftToSchedule(edit.draft);
            saveMutation.mutate({ task: edit.task, schedule });
          }}
        >
          <div className="flex flex-col gap-3">
            <div>
              <div className="mb-1">{t('edit.freqLabel')}</div>
              <RadioGroup
                value={edit.draft.freq}
                onChange={(e) =>
                  setEditState({ ...edit, draft: { ...edit.draft, freq: e.target.value as EditDraft['freq'] } })
                }
                options={[
                  { value: 'interval', label: t('edit.freq.interval') },
                  { value: 'hourly', label: t('edit.freq.hourly') },
                  { value: 'daily', label: t('edit.freq.daily') },
                  { value: 'weekly', label: t('edit.freq.weekly') },
                  { value: 'monthly', label: t('edit.freq.monthly') },
                ]}
              />
            </div>

            {edit.draft.freq === 'interval' ? (
              <div>
                <div className="mb-1">{t('edit.intervalLabel')}</div>
                <InputNumber
                  value={edit.draft.interval}
                  min={1}
                  max={999}
                  onChange={(v) =>
                    setEditState({ ...edit, draft: { ...edit.draft, interval: Number(v) || 0 } })
                  }
                />
                {fieldErrors.interval ? (
                  <Typography.Text type="danger" size="small">{fieldErrors.interval}</Typography.Text>
                ) : null}
              </div>
            ) : null}

            {edit.draft.freq === 'hourly' ? (
              <div>
                <div className="mb-1">{t('edit.minuteLabel')}</div>
                <InputNumber
                  value={edit.minute}
                  min={0}
                  max={59}
                  onChange={(v) => setEditState({ ...edit, minute: Number(v) || 0 })}
                />
              </div>
            ) : null}

            {edit.draft.freq === 'weekly' ? (
              <div>
                <div className="mb-1">{t('edit.weekdayLabel')}</div>
                <Select
                  value={edit.draft.weekday}
                  optionList={(t('schedule.weekdays', { returnObjects: true }) as string[]).map(
                    (w, i) => ({ value: i + 1, label: w }),
                  )}
                  onChange={(v) => setEditState({ ...edit, draft: { ...edit.draft, weekday: Number(v) } })}
                  style={{ width: 160 }}
                />
              </div>
            ) : null}

            {edit.draft.freq === 'monthly' ? (
              <div>
                <div className="mb-1">{t('edit.monthdayLabel')}</div>
                <InputNumber
                  value={edit.draft.monthday}
                  min={1}
                  max={31}
                  onChange={(v) =>
                    setEditState({ ...edit, draft: { ...edit.draft, monthday: Number(v) || 0 } })
                  }
                />
                {fieldErrors.monthday ? (
                  <Typography.Text type="danger" size="small">{fieldErrors.monthday}</Typography.Text>
                ) : null}
                {monthEndHint ? (
                  <Typography.Text type="secondary" size="small" style={{ display: 'block' }}>
                    {t('edit.monthEndHint')}
                  </Typography.Text>
                ) : null}
              </div>
            ) : null}

            {edit.draft.freq === 'daily' || edit.draft.freq === 'weekly' || edit.draft.freq === 'monthly' ? (
              <div>
                <div className="mb-1">{t('edit.timeLabel')}</div>
                <TimePicker
                  value={edit.draft.time}
                  format="HH:mm"
                  onChange={(v) => {
                    const text = typeof v === 'string' ? v : v instanceof Date ? `${String(v.getHours()).padStart(2, '0')}:${String(v.getMinutes()).padStart(2, '0')}` : '';
                    setEditState({ ...edit, draft: { ...edit.draft, time: text } });
                  }}
                />
                {fieldErrors.time ? (
                  <Typography.Text type="danger" size="small">{fieldErrors.time}</Typography.Text>
                ) : null}
              </div>
            ) : null}

            <div>
              <div className="mb-1">{t('edit.previewTitle')}</div>
              <ul style={{ margin: 0, paddingLeft: 18 }}>
                {previewRuns.map((d, i) => (
                  <li key={i}>{formatDateTime(d.toISOString())}</li>
                ))}
              </ul>
            </div>
          </div>
        </Modal>
      ) : null}

      <SideSheet
        visible={runsTask !== null}
        onCancel={() => setRunsTask(null)}
        title={runsTask ? t('runsDrawer.title', { name: runsTask.name }) : ''}
        footer={
          <div className="flex justify-end w-full">
            <Button onClick={() => setRunsTask(null)}>{t('runsDrawer.close')}</Button>
          </div>
        }
      >
        {runsQuery.isLoading ? null : (runsQuery.data?.items ?? []).length === 0 ? (
          <Empty title={t('runsDrawer.empty')} />
        ) : (
          <div className="flex flex-col gap-3">
            {(runsQuery.data?.items ?? []).map((run: TaskRun) => (
              <div key={run.id} className="border-b border-solid pb-3" style={{ borderColor: 'var(--semi-color-border)' }}>
                <div className="flex justify-between">
                  <Typography.Text strong>{formatDateTime(run.start_time)}</Typography.Text>
                  {run.status === 'succeeded' ? (
                    <Tag color="green" size="small">{t('runsDrawer.stateOk')}</Tag>
                  ) : run.status === 'failed' ? (
                    <Tag color="red" size="small">{t('runsDrawer.stateFailed')}</Tag>
                  ) : (
                    <Tag color="blue" size="small">{t('runsDrawer.stateRunning')}</Tag>
                  )}
                </div>
                <Typography.Text type="tertiary" size="small">
                  {run.trigger === 'manual' ? t('runsDrawer.manual') : t('runsDrawer.auto')}
                  {run.duration_ms != null
                    ? ` · ${t('runsDrawer.durationSeconds', {
                        count: Math.max(0, Math.round(run.duration_ms / 1000)),
                      })}`
                    : ''}
                </Typography.Text>
                {run.status === 'failed' ? (
                  // 系统内部失败（有 error_code）→ 复制问题信息；否则回显后端人话。
                  run.error_code ? (
                    <div className="flex items-center gap-2 mt-1">
                      <Typography.Text type="danger" size="small">
                        {run.message ?? t('runsDrawer.systemFail')}
                      </Typography.Text>
                      <AdminCopyButton
                        error={
                          new AdminApiError({
                            code: run.error_code,
                            message: run.error_code,
                            httpStatus: 500,
                            method: 'POST',
                            url: `/scheduled-tasks/${runsTask?.code ?? ''}/run`,
                            requestId: run.request_id ?? undefined,
                            system: true,
                          })
                        }
                      />
                    </div>
                  ) : run.message ? (
                    <Typography.Text type="danger" size="small" style={{ display: 'block' }}>
                      {run.message}
                    </Typography.Text>
                  ) : null
                ) : null}
              </div>
            ))}
          </div>
        )}
      </SideSheet>
    </div>
  );
}
